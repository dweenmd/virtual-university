<?php
// generate_mcq_pdf.php
// এই কোর্সের সব সম্পন্ন (completed) MCQ - প্রশ্ন + সঠিক উত্তরসহ - একটি PDF এ এক্সপোর্ট করে।
// ব্যবহারের আগে composer দিয়ে mPDF ইনস্টল করে নিতে হবে (Bangla/Unicode সাপোর্টের জন্য mPDF সবচেয়ে ভালো):
//   composer require mpdf/mpdf
// তারপর project root এ vendor/autoload.php তৈরি হবে, যেটা নিচে require করা হয়েছে।

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db.php';

// শুধুমাত্র লগইন করা শিক্ষকই PDF জেনারেট করতে পারবেন
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/vendor/autoload.php';

$teacher_id = $_SESSION['user_id'];
$course_id = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;

if ($course_id <= 0) {
    die("Invalid course.");
}

// নিরাপত্তা যাচাই: এই কোর্সটি সত্যিই এই শিক্ষকের কিনা চেক করা (prepared statement)
$stmt = $conn->prepare("SELECT course_code, title FROM courses WHERE id = ? AND teacher_id = ?");
$stmt->bind_param("ii", $course_id, $teacher_id);
$stmt->execute();
$course_result = $stmt->get_result();

if ($course_result->num_rows === 0) {
    die("Access denied. This course is not assigned to you.");
}
$course = $course_result->fetch_assoc();
$stmt->close();

// সম্পন্ন হওয়া সব MCQ টেস্ট ফেচ করা (prepared statement দিয়ে)
$stmt = $conn->prepare("
    SELECT title, option_a, option_b, option_c, option_d, correct_option
    FROM online_class_tests
    WHERE course_id = ? AND test_type = 'mcq' AND status = 'completed'
    ORDER BY id ASC
");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$mcq_result = $stmt->get_result();

if ($mcq_result->num_rows === 0) {
    die("No archived MCQs found for this course.");
}

// ---- PDF তৈরি করা (mPDF দিয়ে, UTF-8 / বাংলা ফন্ট সাপোর্টসহ) ----
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'default_font' => 'notosansbengali', // mPDF তে বিল্ট-ইন বাংলা ফন্ট
]);

$html = '<h2 style="text-align:center;">Virtual Varsity — MCQ Archive</h2>';
$html .= '<h4 style="text-align:center; color:#555;">' . htmlspecialchars($course['course_code']) . ' : ' . htmlspecialchars($course['title']) . '</h4>';
$html .= '<hr><br>';

$counter = 1;
while ($row = $mcq_result->fetch_assoc()) {
    $correct_letter = strtoupper($row['correct_option']);
    $options = [
        'A' => $row['option_a'],
        'B' => $row['option_b'],
        'C' => $row['option_c'],
        'D' => $row['option_d'],
    ];

    $html .= '<div style="margin-bottom:16px;">';
    $html .= '<p style="font-weight:bold; font-size:12px;">' . $counter . '. ' . htmlspecialchars($row['title']) . '</p>';
    $html .= '<table style="width:100%; font-size:11px;">';
    foreach ($options as $letter => $text) {
        $is_correct = ($letter === $correct_letter);
        $style = $is_correct ? 'color:#059669; font-weight:bold;' : '';
        $html .= '<tr><td style="padding:2px 8px; width:5%;">' . $letter . '.</td><td style="' . $style . '">' . htmlspecialchars($text) . ($is_correct ? ' ✅ (Correct Answer)' : '') . '</td></tr>';
    }
    $html .= '</table></div>';
    $counter++;
}

$mpdf->WriteHTML($html);
$mpdf->Output('mcq_archive_course_' . $course_id . '_' . date('Ymd_His') . '.pdf', 'D'); // 'D' = force download
exit();
