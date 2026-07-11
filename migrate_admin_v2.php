<?php
// migrate_admin_v2.php
// এই স্ক্রিপ্টটি একবার ব্রাউজারে রান করলেই হবে (php migrate_admin_v2.php অথবা লোকালহোস্টে ওপেন করলেই)।
// এটি safe/idempotent — বারবার রান করলেও সমস্যা হবে না, শুধু প্রয়োজনীয় জিনিসগুলো
// একবারই যোগ হবে।
//
// এটা যা করে:
//  1) academic_records টেবিলে "status" কলাম যোগ করে ('active' | 'completed') — এই কলামটাই
//     ছাত্রের course archive/complete করার ভিত্তি।
//  2) student_id+course_id এর ডুপ্লিকেট রো একত্র করে (পুরনো bug: unique key না থাকায়
//     attendance ভেরিফাই করলে প্রতিবারই নতুন রো তৈরি হতো), যাতে "active course" গোনা
//     নির্ভুল হয়।
//  3) academic_records(student_id, course_id) এর উপর UNIQUE KEY বসায়, যাতে ভবিষ্যতে আর
//     ডুপ্লিকেট রো তৈরি না হয়।

$host = "localhost";
$username = "root";
$password = "";
$dbname = "virtual_university";

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

echo "<pre>";

// ---- Step 1: add status column if missing ----
$col_check = $conn->query("
    SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = '$dbname' AND TABLE_NAME = 'academic_records' AND COLUMN_NAME = 'status'
");
$has_status = $col_check->fetch_assoc()['c'] > 0;

if (!$has_status) {
    if ($conn->query("ALTER TABLE academic_records ADD COLUMN status ENUM('active','completed') NOT NULL DEFAULT 'active'")) {
        echo "✅ Added 'status' column to academic_records.\n";
    } else {
        echo "❌ Failed to add 'status' column: " . $conn->error . "\n";
    }
} else {
    echo "ℹ️  'status' column already exists — skipped.\n";
}

// ---- Step 2: dedupe (student_id, course_id) pairs, merging attendance counters ----
$dupes = $conn->query("
    SELECT student_id, course_id, COUNT(*) AS cnt, MIN(id) AS keep_id,
           SUM(total_days) AS sum_total, SUM(present_days) AS sum_present
    FROM academic_records
    GROUP BY student_id, course_id
    HAVING COUNT(*) > 1
");

$dedup_count = 0;
if ($dupes && $dupes->num_rows > 0) {
    while ($row = $dupes->fetch_assoc()) {
        $conn->query("UPDATE academic_records SET total_days = {$row['sum_total']}, present_days = {$row['sum_present']} WHERE id = {$row['keep_id']}");
        $conn->query("DELETE FROM academic_records WHERE student_id = {$row['student_id']} AND course_id = {$row['course_id']} AND id <> {$row['keep_id']}");
        $dedup_count++;
    }
    echo "✅ Merged $dedup_count duplicate student+course record group(s).\n";
} else {
    echo "ℹ️  No duplicate academic_records rows found — skipped.\n";
}

// ---- Step 3: add UNIQUE KEY (student_id, course_id) if missing ----
$key_check = $conn->query("
    SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = '$dbname' AND TABLE_NAME = 'academic_records' AND INDEX_NAME = 'uq_student_course'
");
$has_key = $key_check->fetch_assoc()['c'] > 0;

if (!$has_key) {
    if ($conn->query("ALTER TABLE academic_records ADD UNIQUE KEY uq_student_course (student_id, course_id)")) {
        echo "✅ Added UNIQUE KEY uq_student_course(student_id, course_id).\n";
    } else {
        echo "❌ Failed to add unique key: " . $conn->error . "\n";
    }
} else {
    echo "ℹ️  UNIQUE KEY uq_student_course already exists — skipped.\n";
}

echo "\n🎉 Migration finished.\n";
echo "</pre>";

$conn->close();