<?php
include 'db.php';

header('Content-Type: application/json');

// ডাটাবেজ থেকে এই মুহূর্তের সকল 'LIVE NOW' ক্লাস তুলে আনা
$query = "SELECT t.*, c.course_code, c.title AS course_title 
          FROM online_class_tests t 
          JOIN courses c ON t.course_id = c.id 
          WHERE t.status = 'LIVE NOW' 
          ORDER BY t.id DESC";

$result = mysqli_query($conn, $query);
$classes = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        // সিকিউরিটির জন্য 'attendance_token' বা 'zoom_link' এখানে পাঠানো হচ্ছে না
        $classes[] = [
            'course_code' => $row['course_code'],
            'course_title' => $row['course_title']
        ];
    }
}

echo json_encode($classes);
?>