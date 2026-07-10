<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$course_id = intval($_POST['course_id'] ?? 0);
if ($course_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid course']);
    exit();
}

$teacher_id = $_SESSION['user_id'];

// Make sure this course belongs to the requesting teacher and has a live meet session
$check = $conn->query("
    SELECT oct.id 
    FROM online_class_tests oct 
    JOIN courses c ON c.id = oct.course_id 
    WHERE oct.course_id = '$course_id' 
      AND oct.status = 'LIVE NOW' 
      AND oct.test_type = 'meet' 
      AND c.teacher_id = '$teacher_id' 
    LIMIT 1
");

if (!$check || $check->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'No live class is running for this course.']);
    exit();
}

// Generate a fresh 4-digit token and open a new 2-minute attendance window
$token = rand(1000, 9999);
$conn->query("
    UPDATE online_class_tests 
    SET attendance_token = '$token', attendance_started_at = NOW() 
    WHERE course_id = '$course_id' AND status = 'LIVE NOW' AND test_type = 'meet'
");

echo json_encode(['status' => 'success', 'token' => $token, 'duration' => 120]);