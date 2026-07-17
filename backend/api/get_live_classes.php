<?php
include __DIR__ . '/../db.php';
header('Content-Type: application/json');
// ডাটাবেজ থেকে এই মুহূর্তের সকল 'LIVE NOW' *lecture/meet* সেশন তুলে আনা
// FIX: আগে test_type filter ছিল না, তাই একই কোর্সের live Meet এবং live MCQ
// দুটোই একসাথে চলে থাকলে সেই কোর্স দুইবার (ডুপ্লিকেট মনে হয়) দেখাত।
$query = "SELECT t.*, c.course_code, c.title AS course_title 
          FROM online_class_tests t 
          JOIN courses c ON t.course_id = c.id 
          WHERE t.status = 'LIVE NOW' AND t.test_type = 'meet'
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
