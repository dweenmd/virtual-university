<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db.php';

// সিকিউরিটি গেটকিপার চেক
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'teacher' && $_SESSION['role'] !== 'admin')) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access!']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id = intval($_POST['course_id']);

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