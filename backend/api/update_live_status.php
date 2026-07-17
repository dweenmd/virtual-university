<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include __DIR__ . '/../db.php';
include __DIR__ . '/../attendance_helpers.php';

// সিকিউরিটি গেটকিপার চেক
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'teacher' && $_SESSION['role'] !== 'admin')) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access!']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id = intval($_POST['course_id']);

    // যদি এই কোর্সের একটি LIVE meet সেশন চলমান থাকে এবং attendance window খোলা থাকে,
    // "End Live Session" চাপার সাথে সাথেই অ্যাটেনডেন্স ফাইনালাইজ করে দেওয়া হয় (force=true) —
    // অর্থাৎ ২ মিনিট শেষ হওয়ার জন্য অপেক্ষা না করেই যারা ভেরিফাই করেনি তাদের absent
    // হিসেবে মার্ক করে ফেলা হয়। এটা করা না হলে ক্লাস মাঝপথে শেষ করলে সেই ক্লাসের
    // absent-মার্কিং কখনোই হতো না।
    $live_meet = $conn->prepare("SELECT id FROM online_class_tests WHERE course_id = ? AND status = 'LIVE NOW' AND test_type = 'meet' LIMIT 1");
    $live_meet->bind_param("i", $course_id);
    $live_meet->execute();
    $live_meet_result = $live_meet->get_result();
    if ($live_meet_result && $live_meet_result->num_rows > 0) {
        $live_meet_row = $live_meet_result->fetch_assoc();
        try_finalize_attendance($conn, $live_meet_row['id'], true);
    }
    $live_meet->close();

    // এই কোর্সের আন্ডারে যে ক্লাসটি বর্তমানে 'LIVE NOW' আছে, সেটিকে 'completed' করা হবে
    $stmt = $conn->prepare("UPDATE online_class_tests SET status = 'completed' WHERE course_id = ? AND status = 'LIVE NOW'");
    $stmt->bind_param("i", $course_id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Live session stopped successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database update failed!']);
    }
    $stmt->close();
}
?>
