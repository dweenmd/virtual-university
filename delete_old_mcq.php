<?php
// delete_old_mcq.php
// এই কোর্সের সব সম্পন্ন (completed) MCQ ডাটাবেজ থেকে স্থায়ীভাবে ডিলিট করে।
// শুধুমাত্র dashboard এ "PDF ডাউনলোড" এর পর সক্রিয় হওয়া "Delete" বাটন থেকেই এটি কল হয় (client-side গার্ড),
// কিন্তু আসল নিরাপত্তা এখানেই সার্ভার-সাইডে হচ্ছে: session + course ownership যাচাই।

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
include 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit();
}

$teacher_id = $_SESSION['user_id'];
$course_id = isset($_POST['course_id']) ? (int) $_POST['course_id'] : 0;

if ($course_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid course ID.']);
    exit();
}

// নিরাপত্তা যাচাই: কোর্সটি এই শিক্ষকেরই কিনা
$stmt = $conn->prepare("SELECT id FROM courses WHERE id = ? AND teacher_id = ?");
$stmt->bind_param("ii", $course_id, $teacher_id);
$stmt->execute();
$check = $stmt->get_result();

if ($check->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Access denied for this course.']);
    exit();
}
$stmt->close();

// শুধুমাত্র completed MCQ গুলোই ডিলিট হবে (LIVE NOW বা অন্য test_type গুলো টাচ হবে না)
$stmt = $conn->prepare("DELETE FROM online_class_tests WHERE course_id = ? AND test_type = 'mcq' AND status = 'completed'");
$stmt->bind_param("i", $course_id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'deleted_rows' => $stmt->affected_rows]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
}
$stmt->close();
