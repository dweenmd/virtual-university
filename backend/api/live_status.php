<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include __DIR__ . '/../db.php';
include __DIR__ . '/../attendance_helpers.php';
header('Content-Type: application/json');
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['error' => 'unauthorized']);
    exit();
}
$student_id = $_SESSION['user_id'];
$response = ['meet' => null, 'attendance' => null];

// FIX: elapsed time now calculated inside MySQL (TIMESTAMPDIFF) instead of
// PHP's strtotime(), so PHP/MySQL timezone mismatches can no longer cause
// the countdown to be wrong.
$live_meet_query = $conn->query("
    SELECT oct.id, oct.course_id, oct.title, oct.zoom_link, oct.attendance_token, oct.attendance_started_at,
           TIMESTAMPDIFF(SECOND, oct.attendance_started_at, NOW()) AS elapsed_seconds,
           c.title AS course_title, c.course_code
    FROM online_class_tests oct
    JOIN courses c ON c.id = oct.course_id
    JOIN academic_records ar ON ar.course_id = oct.course_id AND ar.student_id = '$student_id'
    WHERE oct.status = 'LIVE NOW' AND oct.test_type = 'meet'
    ORDER BY oct.id DESC LIMIT 1
");
$live_meet = ($live_meet_query && $live_meet_query->num_rows > 0) ? $live_meet_query->fetch_assoc() : null;

if ($live_meet) {
    $response['meet'] = [
        'ct_id' => $live_meet['id'],
        'course_id' => $live_meet['course_id'],
        'title' => $live_meet['title'],
        'zoom_link' => $live_meet['zoom_link'],
        'course_title' => $live_meet['course_title'],
        'course_code' => $live_meet['course_code'],
    ];

    if (!empty($live_meet['attendance_started_at'])) {
        // Comes straight from MySQL now — no more strtotime() timezone drift.
        $elapsed = (int) $live_meet['elapsed_seconds'];

        // FIX (card-vanishing bug): DB-backed check scoped strictly to THIS
        // student's own student_id, via the attendance_verifications table.
        // One student verifying can never hide the card for a different student
        // anymore, because this lookup is keyed by (ct_id, student_id) — not by
        // a shared PHP session value.
        $already_verified = is_attendance_verified($conn, $live_meet['id'], $student_id);

        if ($elapsed < 120 && !$already_verified) {
            $response['attendance'] = [
                'ct_id' => $live_meet['id'],
                'course_id' => $live_meet['course_id'],
                'seconds_left' => 120 - $elapsed,
            ];
        }

        // Finalize (mark absentees) once the window has actually closed. This
        // endpoint is polled by every logged-in student every 5 seconds, so in
        // practice this fires within moments of the window expiring — but it's
        // safe to call redundantly, only the first caller after expiry wins.
        try_finalize_attendance($conn, $live_meet['id']);
    }
}

echo json_encode($response);
?>
