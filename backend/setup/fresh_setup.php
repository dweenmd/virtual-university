<?php
// fresh_setup.php
// পুরনো করাপ্ট ডাটাবেজ রিসেট করার পর এই স্ক্রিপ্টটি একবার ব্রাউজারে রান করলেই
// (http://localhost/virtual-university/backend/setup/fresh_setup.php) সম্পূর্ণ ফ্রেশ ডাটাবেজ,
// সব টেবিল এবং ৭টি ডেমো অ্যাকাউন্ট (১ admin + ২ teacher + ৪ student) + কিছু
// নমুনা কোর্স/enrollment তৈরি হয়ে যাবে।
//
// এই স্ক্রিপ্টটি safe/idempotent — বারবার রান করলে সমস্যা হবে না (DROP+CREATE)।
// ⚠️ সাবধান: এটা রান করলে আগের সব ডেমো ডাটা মুছে নতুন করে বসবে (fresh start)।
//
// db.php এর সাথে মিলিয়ে port=3307 ব্যবহার করা হয়েছে। তোমার XAMPP এ port ভিন্ন হলে
// নিচের $port ভ্যারিয়েবলটা বদলে নাও।

$host = "localhost";
$username = "root";
$password = "";
$dbname = "virtual_university";
$port = 3306; // fresh XAMPP install ke default MySQL port eta

$conn = new mysqli($host, $username, $password, "", $port);
if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}

echo "<pre style='font-family:monospace; font-size:14px; line-height:1.6;'>";

// ---- 1) Drop & recreate database (guaranteed clean slate) ----
$conn->query("DROP DATABASE IF EXISTS `$dbname`");
$conn->query("CREATE DATABASE `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->select_db($dbname);
echo "✅ Database '$dbname' dropped & recreated fresh.\n\n";

// ---- 2) Create tables (dependency order respected for foreign keys) ----
$tables = [

    "users" => "CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('student', 'teacher', 'admin') NOT NULL,
        id_no VARCHAR(50) NOT NULL UNIQUE,
        semester INT DEFAULT 1
    ) ENGINE=InnoDB",

    "courses" => "CREATE TABLE courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_code VARCHAR(20) NOT NULL UNIQUE,
        title VARCHAR(100) NOT NULL,
        semester INT NOT NULL DEFAULT 1,
        teacher_id INT NULL,
        FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB",

    "academic_records" => "CREATE TABLE academic_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        course_id INT NOT NULL,
        ct_marks INT DEFAULT 0,
        total_days INT DEFAULT 0,
        present_days INT DEFAULT 0,
        status ENUM('active', 'completed') NOT NULL DEFAULT 'active',
        UNIQUE KEY uq_student_course (student_id, course_id),
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",

    "student_cgpa_records" => "CREATE TABLE student_cgpa_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        semester_no INT NOT NULL,
        gpa DECIMAL(3,2) NOT NULL DEFAULT 0.00,
        UNIQUE KEY uq_student_semester (student_id, semester_no),
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",

    "videos" => "CREATE TABLE videos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        video_url TEXT NOT NULL,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",

    "course_resources" => "CREATE TABLE course_resources (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",

    // Note: includes correct_option, deadline, attendance_started_at, created_at —
    // all of which are used by teacher_dashboard.php / student_dashboard.php /
    // live_status.php / start_attendance.php / admin_dashboard.php but were
    // missing from the original setup.php definition.
    "online_class_tests" => "CREATE TABLE online_class_tests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        status ENUM('LIVE NOW', 'completed') NOT NULL DEFAULT 'LIVE NOW',
        zoom_link TEXT,
        test_type ENUM('meet', 'mcq', 'pdf') NOT NULL,
        attendance_token VARCHAR(10) NULL,
        attendance_started_at DATETIME NULL,
        option_a VARCHAR(255) NULL,
        option_b VARCHAR(255) NULL,
        option_c VARCHAR(255) NULL,
        option_d VARCHAR(255) NULL,
        correct_option VARCHAR(5) NULL,
        pdf_question TEXT NULL,
        deadline DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",

    "quiz_submissions" => "CREATE TABLE quiz_submissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ct_id INT NOT NULL,
        student_id INT NOT NULL,
        student_name VARCHAR(100) NOT NULL,
        answers TEXT NULL,
        pdf_submission TEXT NULL,
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (ct_id) REFERENCES online_class_tests(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",
];

foreach ($tables as $name => $query) {
    if ($conn->query($query) === TRUE) {
        echo "✅ Table '$name' created.\n";
    } else {
        echo "❌ Error creating table '$name': " . $conn->error . "\n";
    }
}

echo "\n";

// ---- 3) Seed users (1 admin, 2 teachers, 4 students) ----
// Passwords stored as plain text on purpose — index.php's login check accepts
// either an exact plain-text match OR password_verify(), so this works as-is.
$users = [
    // name, email, password, role, id_no, semester
    ["Admin", "admin@vu.com", "admin1234", "admin", "ADMIN-01", 1],
    ["Teacher One", "teacher1@vu.com", "1234", "teacher", "T-01", 1],
    ["Teacher Two", "teacher2@vu.com", "1234", "teacher", "T-02", 1],
    ["Student One", "student1@vu.com", "1234", "student", "S-01", 5],
    ["Student Two", "student2@vu.com", "1234", "student", "S-02", 5],
    ["Student Three", "student3@vu.com", "1234", "student", "S-03", 5],
    ["Student Four", "student4@vu.com", "1234", "student", "S-04", 5],
];

$stmt = $conn->prepare("INSERT INTO users (name, email, password, role, id_no, semester) VALUES (?, ?, ?, ?, ?, ?)");
$ids = []; // email => id
foreach ($users as [$name, $email, $pass, $role, $id_no, $sem]) {
    $stmt->bind_param("sssssi", $name, $email, $pass, $role, $id_no, $sem);
    $stmt->execute();
    $ids[$email] = $stmt->insert_id;
    echo "✅ Created $role: $email (password: $pass)\n";
}
$stmt->close();

$teacher1_id = $ids["teacher1@vu.com"];
$teacher2_id = $ids["teacher2@vu.com"];

echo "\n";

// ---- 4) Seed a few sample courses, split between the two teachers ----
$courses = [
    ["CSE-3201", "Operating Systems", 5, $teacher1_id],
    ["CSE-3203", "Design and Analysis of Algorithms II", 5, $teacher1_id],
    ["CSE-3204", "Theory of Computation", 5, $teacher2_id],
    ["CSE-3205", "Probability & Statistics", 5, $teacher2_id],
];

$stmt = $conn->prepare("INSERT INTO courses (course_code, title, semester, teacher_id) VALUES (?, ?, ?, ?)");
$course_ids = [];
foreach ($courses as [$code, $title, $sem, $tid]) {
    $stmt->bind_param("ssii", $code, $title, $sem, $tid);
    $stmt->execute();
    $course_ids[] = $stmt->insert_id;
    echo "✅ Created course: $code — $title (teacher_id=$tid)\n";
}
$stmt->close();

echo "\n";

// ---- 5) Enroll all 4 students into all 4 courses (active) ----
$stmt = $conn->prepare("INSERT INTO academic_records (student_id, course_id, ct_marks, total_days, present_days, status) VALUES (?, ?, 0, 0, 0, 'active')");
$student_emails = ["student1@vu.com", "student2@vu.com", "student3@vu.com", "student4@vu.com"];
foreach ($student_emails as $email) {
    $sid = $ids[$email];
    foreach ($course_ids as $cid) {
        $stmt->bind_param("ii", $sid, $cid);
        $stmt->execute();
    }
    echo "✅ Enrolled $email into all 4 sample courses.\n";
}
$stmt->close();

echo "\n🎉 Fresh database setup complete!\n\n";
echo "==================== LOGIN CREDENTIALS ====================\n";
echo "Admin:     admin@vu.com     / admin1234\n";
echo "Teacher 1: teacher1@vu.com  / 1234\n";
echo "Teacher 2: teacher2@vu.com  / 1234\n";
echo "Student 1: student1@vu.com  / 1234\n";
echo "Student 2: student2@vu.com  / 1234\n";
echo "Student 3: student3@vu.com  / 1234\n";
echo "Student 4: student4@vu.com  / 1234\n";
echo "=============================================================\n";

echo "</pre>";

$conn->close();
?>
