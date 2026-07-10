<?php
// 1. Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db.php';

// 2. Session verify
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: index.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['name'];
$msg = "";
$msg_type = "";

// --- ATTENDANCE VERIFICATION ---
if (isset($_POST['verify_token'])) {
    $input_token = trim($_POST['token_code']);
    $ct_id = intval($_POST['ct_id']);
    $course_id = intval($_POST['course_id']);

   // FIX: elapsed time now calculated inside MySQL (TIMESTAMPDIFF) instead of
    // PHP's strtotime(), so a timezone mismatch can't let a stale token pass
    // (or reject a still-valid one).
    $check_token = $conn->query("SELECT id, attendance_token, attendance_started_at, TIMESTAMPDIFF(SECOND, attendance_started_at, NOW()) AS elapsed_seconds FROM online_class_tests WHERE id='$ct_id' AND status='LIVE NOW' AND test_type='meet' LIMIT 1");

    $valid = false;
    if ($check_token && $check_token->num_rows > 0) {
        $row = $check_token->fetch_assoc();
        $elapsed = !empty($row['attendance_started_at']) ? (int) $row['elapsed_seconds'] : PHP_INT_MAX;
        if (!empty($row['attendance_token']) && hash_equals((string) $row['attendance_token'], $input_token) && $elapsed < 120) {
            $valid = true;
        }
    }

    if ($valid) {
        $conn->query("INSERT INTO academic_records (student_id, course_id, ct_marks, total_days, present_days) VALUES ('$student_id', '$course_id', 0, 1, 1) ON DUPLICATE KEY UPDATE present_days=present_days+1, total_days=total_days+1");
        // Remember which token this student verified for this ct_id — a NEW window
        // (new token) will make the attendance card visible again.
        $_SESSION['verified_attendance'][$ct_id] = $input_token;
        $msg = "Success! Attendance recorded successfully.";
        $msg_type = "success";
    } else {
        $msg = "Invalid or expired Security Token! Verification failed.";
        $msg_type = "error";
    }
}

// --- SUBMISSIONS HANDLER ---
if (isset($_POST['submit_response'])) {
    $ct_id = intval($_POST['ct_id']);
    $selected_option = isset($_POST['selected_option']) ? mysqli_real_escape_string($conn, $_POST['selected_option']) : "";
    $ans_text = !empty($selected_option) ? "Selected Option: " . $selected_option : "";

    // Deadline check (only applies to PDF assignments)
    $ct_check = $conn->query("SELECT test_type, deadline FROM online_class_tests WHERE id='$ct_id' LIMIT 1");
    $ct_row = ($ct_check && $ct_check->num_rows > 0) ? $ct_check->fetch_assoc() : null;
    $deadline_passed = ($ct_row && $ct_row['test_type'] === 'pdf' && !empty($ct_row['deadline']) && strtotime($ct_row['deadline']) < time());

    if ($deadline_passed) {
        $msg = "Deadline is over. This assignment no longer accepts submissions.";
        $msg_type = "error";
    } else {
        $uploaded_pdf_path = "";
        if (isset($_FILES['student_pdf']) && $_FILES['student_pdf']['error'] == 0) {
            $target_dir = "uploads/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $file_ext = strtolower(pathinfo($_FILES['student_pdf']['name'], PATHINFO_EXTENSION));
            if ($file_ext === 'pdf') {
                $uploaded_pdf_path = $target_dir . "submission_" . time() . "_" . uniqid() . ".pdf";
                move_uploaded_file($_FILES['student_pdf']['tmp_name'], $uploaded_pdf_path);
            }
        }

        $check = $conn->query("SELECT id FROM quiz_submissions WHERE ct_id='$ct_id' AND student_id='$student_id'");
        if ($check && $check->num_rows == 0) {
            $conn->query("INSERT INTO quiz_submissions (ct_id, student_id, student_name, answers, pdf_submission) VALUES ('$ct_id', '$student_id', '$student_name', '$ans_text', '$uploaded_pdf_path')");
            $msg = "Your assessment response has been securely saved.";
            $msg_type = "success";

            if (!empty($selected_option)) {
                $_SESSION['last_submitted_mcq'][$ct_id] = $selected_option;
            }
        } else {
            $msg = "Response already logged for this assessment.";
            $msg_type = "error";
        }
    }
}

// --- COURSE-WISE ATTENDANCE FETCH & CALCULATION ---
$course_attendance = [];
$total_days_combined = 0;
$present_days_combined = 0;

// Pulling each course's data separately from academic_records via JOIN
$attendance_query = $conn->query("
    SELECT ar.*, c.title AS course_title, c.course_code 
    FROM academic_records ar
    JOIN courses c ON ar.course_id = c.id
    WHERE ar.student_id = '$student_id'
");

if ($attendance_query) {
    while ($row = $attendance_query->fetch_assoc()) {
        $t_days = intval($row['total_days']);
        $p_days = intval($row['present_days']);
        $rate = $t_days > 0 ? round(($p_days / $t_days) * 100, 1) : 0;

        $course_attendance[] = [
            'code' => $row['course_code'],
            'title' => $row['course_title'],
            'total' => $t_days,
            'present' => $p_days,
            'rate' => $rate
        ];

        // Adding total days for the overall summation
        $total_days_combined += $t_days;
        $present_days_combined += $p_days;
    }
}
$overall_attendance_avg = $total_days_combined > 0 ? round(($present_days_combined / $total_days_combined) * 100, 1) : 0;


// --- CGPA FETCH ---
$semester_results = [];
$total_gpa_sum = 0;
$completed_sem_count = 0;
$actual_cgpa = 0.00;

$result_query = $conn->query("SELECT semester_no, gpa FROM student_cgpa_records WHERE student_id='$student_id' ORDER BY semester_no ASC");
if ($result_query) {
    while ($row = $result_query->fetch_assoc()) {
        $semester_results[$row['semester_no']] = floatval($row['gpa']);
        $total_gpa_sum += floatval($row['gpa']);
        $completed_sem_count++;
    }
    if ($completed_sem_count > 0) {
        $actual_cgpa = round($total_gpa_sum / $completed_sem_count, 2);
    }
}

// --- LIVE MEET SESSION (attendance card) — independent query so an MCQ launch
// for the same course can never hide an in-progress meet session ---
$live_meet_query = $conn->query("
    SELECT online_class_tests.*, courses.title AS course_title, courses.course_code 
    FROM online_class_tests 
    JOIN courses ON online_class_tests.course_id = courses.id 
    JOIN academic_records ar ON ar.course_id = online_class_tests.course_id AND ar.student_id = '$student_id'
    WHERE online_class_tests.status = 'LIVE NOW' AND online_class_tests.test_type = 'meet'
    ORDER BY online_class_tests.id DESC LIMIT 1
");
$live_meet = ($live_meet_query) ? $live_meet_query->fetch_assoc() : null;

// If this student already verified the attendance token for the current live meet
// session, there's nothing left for them to do here — hide the card entirely.
// Work out whether an attendance window is currently open for this live meet
$attendance_active = false;
$attendance_remaining = 0;
if ($live_meet && !empty($live_meet['attendance_started_at'])) {
    // FIX: elapsed time now calculated inside MySQL (TIMESTAMPDIFF) instead of
    // PHP's strtotime(), so PHP/MySQL timezone mismatches can no longer cause
    // the countdown to run longer than 2 minutes.
    $elapsed_query = $conn->query("SELECT TIMESTAMPDIFF(SECOND, attendance_started_at, NOW()) AS elapsed_seconds FROM online_class_tests WHERE id = '{$live_meet['id']}' LIMIT 1");
    $elapsed_row = ($elapsed_query) ? $elapsed_query->fetch_assoc() : null;
    $elapsed = $elapsed_row ? (int) $elapsed_row['elapsed_seconds'] : PHP_INT_MAX;

    $already_verified = isset($_SESSION['verified_attendance'][$live_meet['id']])
        && $_SESSION['verified_attendance'][$live_meet['id']] === $live_meet['attendance_token'];

    if ($elapsed < 120 && !$already_verified) {
        $attendance_active = true;
        $attendance_remaining = 120 - $elapsed;
    }
}

// --- LIVE MCQ SESSION — independent query, same reasoning as above ---
$live_mcq_query = $conn->query("
    SELECT online_class_tests.*, courses.title AS course_title, courses.course_code 
    FROM online_class_tests 
    JOIN courses ON online_class_tests.course_id = courses.id 
    JOIN academic_records ar ON ar.course_id = online_class_tests.course_id AND ar.student_id = '$student_id'
    WHERE online_class_tests.status = 'LIVE NOW' AND online_class_tests.test_type = 'mcq'
    ORDER BY online_class_tests.id DESC LIMIT 1
");
$live_mcq = ($live_mcq_query) ? $live_mcq_query->fetch_assoc() : null;

$has_submitted_mcq = false;
$submitted_option = "";
if ($live_mcq) {
    $check_sub = $conn->query("SELECT answers FROM quiz_submissions WHERE ct_id='{$live_mcq['id']}' AND student_id='$student_id' LIMIT 1");
    if ($check_sub && $check_sub->num_rows > 0) {
        $has_submitted_mcq = true;
        $sub_row = $check_sub->fetch_assoc();
        $submitted_option = str_replace("Selected Option: ", "", $sub_row['answers']);
    }
}

// --- ACTIVE PDF ASSIGNMENTS (course-wise, with deadline, all assignments in the student's enrolled courses) ---
// FIX: Previously filtered by "status = 'LIVE NOW'". But launching a new Meet/MCQ session for the
// course marks ALL online_class_tests rows for that course (including PDF assignments) as
// 'completed', which made assignments disappear from the student view even though their
// deadline hadn't passed yet. An assignment should stay visible until its own deadline expires,
// regardless of what other live sessions are started. So we now filter by deadline instead of status.
$assignments_query = $conn->query("
    SELECT oct.*, c.title AS course_title, c.course_code,
        (SELECT pdf_submission FROM quiz_submissions WHERE ct_id = oct.id AND student_id = '$student_id' LIMIT 1) AS my_submission
    FROM online_class_tests oct
    JOIN academic_records ar ON ar.course_id = oct.course_id AND ar.student_id = '$student_id'
    JOIN courses c ON c.id = oct.course_id
    WHERE oct.test_type = 'pdf' AND (oct.deadline IS NULL OR oct.deadline >= NOW())
    ORDER BY oct.deadline ASC
");
$active_assignments = [];
if ($assignments_query) {
    while ($row = $assignments_query->fetch_assoc()) {
        $active_assignments[] = $row;
    }
}

// --- LECTURE NOTES / RESOURCES (course-wise, always visible — not tied to LIVE NOW status) ---
$lecture_notes_query = $conn->query("
    SELECT cr.id, cr.title, cr.file_path AS pdf_question, cr.course_id, c.title AS course_title, c.course_code
    FROM course_resources cr
    JOIN academic_records ar ON ar.course_id = cr.course_id AND ar.student_id = '$student_id'
    JOIN courses c ON c.id = cr.course_id
    ORDER BY c.course_code ASC, cr.id DESC
");

$lecture_notes_by_course = [];
if ($lecture_notes_query) {
    while ($n = $lecture_notes_query->fetch_assoc()) {
        $cid = $n['course_id'];
        if (!isset($lecture_notes_by_course[$cid])) {
            $lecture_notes_by_course[$cid] = [
                'course_title' => $n['course_title'],
                'course_code' => $n['course_code'],
                'notes' => []
            ];
        }
        $lecture_notes_by_course[$cid]['notes'][] = $n;
    }
}

// Registered Syllabus list fetch
$courses = $conn->query("SELECT courses.*, users.name AS teacher_name FROM courses LEFT JOIN users ON courses.teacher_id = users.id ORDER BY courses.semester ASC");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Terminal - Virtual Varsity</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@500;600;700&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --maroon: #73001a;
            --maroon-deep: #3d0510;
            --maroon-line: #a31d34;
            --gold: #a3781f;
            --gold-soft: #c9a227;
            --paper: #f7f4ee;
            --paper-dim: #efe9dd;
            --ink: #251b16;
            --ink-soft: #7c7166;
            --line: #e2d9c8;

            /* Pre-mixed tints: Tailwind's arbitrary-value opacity modifier
               (e.g. bg-[var(--gold-25)]) does not reliably resolve against a
               CSS custom property in the browser JIT compiler, so those
               utilities were silently producing no color at all. Baking the
               opacity into the variable itself avoids that failure mode. */
            --gold-25: rgba(163, 120, 31, 0.25);
            --gold-30: rgba(163, 120, 31, 0.30);
            --gold-40: rgba(163, 120, 31, 0.40);
            --paper-dim-50: rgba(239, 233, 221, 0.5);
            --paper-dim-60: rgba(239, 233, 221, 0.6);
            --ink-soft-70: rgba(124, 113, 102, 0.7);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--paper);
            color: var(--ink);
            font-size: 16px;
        }

        .font-display {
            font-family: 'Fraunces', serif;
            font-optical-sizing: auto;
        }

        .font-data {
            font-family: 'IBM Plex Mono', monospace;
        }

        /* .ledger previously drew faint horizontal rule-lines as a "transcript
           paper" texture; removed per feedback, kept as a no-op so existing
           markup doesn't need to change. */
        .ledger {}

        .seal {
            width: 30px;
            height: 30px;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1.5px solid var(--gold);
            color: var(--gold);
            background: radial-gradient(circle at 30% 30%, #fff9ea, #fbf1d9 70%);
            box-shadow: inset 0 0 0 1px rgba(163, 120, 31, 0.15);
            font-size: 14px;
        }

        ::selection {
            background: var(--gold-soft);
            color: var(--maroon-deep);
        }

        input[type="range"]::-webkit-slider-thumb {
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.35);
        }
    </style>
</head>

<body class="antialiased">

    <nav
        class="bg-white/90 backdrop-blur sticky top-0 z-50 border-b border-[var(--line)] px-6 py-4 flex justify-between items-center">
        <div class="flex items-center space-x-3">
            <svg width="42" height="42" viewBox="0 0 38 38" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M19 2 L35 8 V19 C35 28 28 33.5 19 36 C10 33.5 3 28 3 19 V8 Z" fill="var(--maroon)"
                    stroke="var(--gold)" stroke-width="1" />
                <text x="19" y="24" text-anchor="middle" font-family="Fraunces, serif" font-size="14" font-weight="600"
                    fill="#f7f4ee">VV</text>
            </svg>
            <div class="flex flex-col leading-none">
                <span class="font-display text-2xl font-semibold text-[var(--maroon)] tracking-tight">Virtual
                    Varsity</span>
                <span class="text-xs uppercase tracking-[0.18em] text-[var(--ink-soft)] font-semibold mt-1">Secure
                    Student Workspace</span>
            </div>
        </div>
        <div class="flex items-center space-x-4">
            <div class="text-right hidden sm:block">
                <p class="text-base font-semibold text-[var(--ink)]"><?php echo htmlspecialchars($student_name); ?></p>
                <p class="text-xs text-[var(--ink-soft)] font-data uppercase tracking-wide">ID
                    #<?php echo $student_id; ?></p>
            </div>
            <a href="index.php"
                class="bg-[var(--maroon)] text-white px-5 py-2.5 rounded-lg text-sm font-semibold tracking-wide hover:bg-[var(--maroon-deep)] transition">Logout</a>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto mt-8 px-4 grid grid-cols-1 lg:grid-cols-3 gap-8 pb-16">

        <div class="lg:col-span-2 space-y-6">

            <?php if ($msg): ?>
                <div
                    class="px-5 py-4 rounded-lg font-semibold text-sm border-l-4 flex items-center gap-2 <?php echo ($msg_type === 'success') ? 'bg-emerald-50 border-emerald-500 text-emerald-800' : 'bg-rose-50 border-rose-500 text-rose-800'; ?>">
                    <span><?php echo ($msg_type === 'success') ? '✓' : '✕'; ?></span>
                    <span><?php echo htmlspecialchars($msg); ?></span>
                </div>
            <?php endif; ?>

            <!-- live meet and attendance cards are independent of any live MCQ session, so they are displayed first. The
            attendance card is only shown if the teacher has opened a 2-minute window and the student hasn't verified
            yet.-->

            <?php if ($live_meet) { ?>
                <!-- Meet Link card — stays visible for the entire duration of the live class,
         independent of attendance. Only disappears when the teacher ends the class. -->
                <div id="meet-card"
                    class="bg-gradient-to-br from-[var(--maroon)] via-[var(--maroon-deep)] to-[#1a0a0a] p-6 sm:p-8 rounded-2xl text-white shadow-lg shadow-black/10 border border-[var(--gold-25)] relative overflow-hidden">
                    <div class="absolute -right-10 -top-10 w-40 h-40 rounded-full border border-white/5"></div>
                    <span
                        class="bg-white/10 text-[var(--gold-soft)] font-semibold text-xs px-3 py-1.5 rounded-full uppercase tracking-wider inline-flex items-center space-x-1.5 relative">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span>Live Session In Progress</span>
                    </span>
                    <h2 id="meet-course-title" class="font-display text-3xl font-semibold mt-3 text-white relative">
                        <?php echo htmlspecialchars($live_meet['course_title']); ?>
                    </h2>
                    <p id="meet-course-code" class="text-sm text-white/50 mb-3 font-data relative">
                        <?php echo htmlspecialchars($live_meet['course_code']); ?>
                    </p>
                    <div
                        class="text-base font-medium bg-white/[0.06] border border-white/10 p-4 rounded-xl mb-5 text-amber-50/90 relative">
                        <span class="text-[var(--gold-soft)] font-semibold">Topic —
                        </span><span id="meet-topic-text"><?php echo htmlspecialchars($live_meet['title']); ?></span>
                    </div>

                    <a id="meet-link-btn" href="<?php echo htmlspecialchars($live_meet['zoom_link']); ?>" target="_blank"
                        class="inline-flex bg-white text-[var(--maroon)] font-semibold px-5 py-3 rounded-lg text-sm hover:bg-amber-50 transition">Open
                        Google Meet / Zoom →</a>
                </div>
            <?php } else { ?>
                <div id="meet-card" class="hidden"></div>
            <?php } ?>

            <!-- Attendance card — only shown while the teacher's 2-minute attendance window is open.
     Auto-hides when the timer runs out (JS) or once this student has verified. -->
            <div id="attendance-card"
                class="<?php echo $attendance_active ? '' : 'hidden'; ?> bg-gradient-to-br from-amber-600 via-amber-700 to-[#4a2e05] p-6 sm:p-7 rounded-2xl text-white shadow-lg shadow-black/10 border border-amber-300/30 relative overflow-hidden">
                <span
                    class="bg-white/10 text-white font-semibold text-xs px-3 py-1.5 rounded-full uppercase tracking-wider inline-flex items-center space-x-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                    <span>Attendance Window Open</span>
                </span>
                <h3 class="font-display text-xl font-semibold mt-3">Enter the PIN announced by your teacher</h3>
                <p class="text-sm text-white/70 mt-1">You have a limited time to submit — don't wait!</p>

                <div class="w-full bg-white/20 h-2 rounded-full overflow-hidden mt-4">
                    <div id="att-timer-fill" class="h-full bg-white rounded-full transition-all duration-1000 linear"
                        style="width: <?php echo $attendance_active ? round(($attendance_remaining / 120) * 100) : 100; ?>%">
                    </div>
                </div>
                <p class="text-xs text-white/80 mt-1.5 font-data">Time left: <span
                        id="att-seconds"><?php echo $attendance_active ? $attendance_remaining : 120; ?></span>s</p>

                <form id="attendance-form" method="POST" action="" class="mt-4 flex space-x-2">
                    <input type="hidden" name="ct_id" id="att-ct-id"
                        value="<?php echo $attendance_active ? $live_meet['id'] : ''; ?>">
                    <input type="hidden" name="course_id" id="att-course-id"
                        value="<?php echo $attendance_active ? $live_meet['course_id'] : ''; ?>">
                    <input type="text" name="token_code" maxlength="4" placeholder="Pin" required
                        class="bg-white text-[var(--ink)] px-4 py-2.5 rounded-lg text-base font-data font-semibold tracking-[0.3em] w-36 text-center uppercase focus:outline-none focus:ring-2 focus:ring-white">
                    <button type="submit" name="verify_token"
                        class="bg-white text-amber-700 text-sm font-semibold px-6 py-2.5 rounded-lg hover:bg-amber-50 transition cursor-pointer">Verify
                        Attendance</button>
                </form>
            </div>

            <?php if ($live_mcq) { ?>
                <!-- Live MCQ session — independent of any live Meet session -->
                <div
                    class="bg-gradient-to-br from-[var(--maroon)] via-[var(--maroon-deep)] to-[#1a0a0a] p-6 sm:p-8 rounded-2xl text-white shadow-lg shadow-black/10 border border-[var(--gold-25)] relative overflow-hidden">
                    <div class="absolute -right-10 -top-10 w-40 h-40 rounded-full border border-white/5"></div>
                    <span
                        class="bg-white/10 text-[var(--gold-soft)] font-semibold text-xs px-3 py-1.5 rounded-full uppercase tracking-wider inline-flex items-center space-x-1.5 relative">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span>Live MCQ In Progress</span>
                    </span>
                    <h2 class="font-display text-3xl font-semibold mt-3 text-white relative">
                        <?php echo htmlspecialchars($live_mcq['course_title']); ?>
                    </h2>
                    <p class="text-sm text-white/50 mb-3 font-data relative">
                        <?php echo htmlspecialchars($live_mcq['course_code']); ?>
                    </p>
                    <div
                        class="text-base font-medium bg-white/[0.06] border border-white/10 p-4 rounded-xl mb-5 text-amber-50/90 relative">
                        <span class="text-[var(--gold-soft)] font-semibold">Question —
                        </span><?php echo htmlspecialchars($live_mcq['title']); ?>
                    </div>

                    <?php if ($has_submitted_mcq):
                        $correct_ans = $live_mcq['correct_option'];
                        $is_correct = ($submitted_option === $correct_ans);
                        ?>
                        <div class="bg-white/[0.06] border border-white/10 p-5 rounded-xl space-y-4">
                            <div class="flex items-center justify-between border-b border-white/10 pb-3">
                                <span class="text-sm font-semibold uppercase tracking-wider text-white/50">Result</span>
                                <span
                                    class="px-3.5 py-1.5 rounded-full text-xs font-semibold uppercase <?php echo $is_correct ? 'bg-emerald-500 text-white' : 'bg-rose-500 text-white'; ?>"><?php echo $is_correct ? 'Correct ✓' : 'Incorrect ✗'; ?></span>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                                <?php
                                $options = ['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'];
                                foreach ($options as $key => $col):
                                    $bg_class = "bg-white text-[var(--ink)]";
                                    $border_badge = "";
                                    if ($key === $correct_ans) {
                                        $bg_class = "bg-emerald-50 border-2 border-emerald-500 text-emerald-900 font-semibold";
                                        $border_badge = " <span class='ml-auto bg-emerald-600 text-white text-[10px] font-semibold px-2 py-0.5 rounded'>Correct</span>";
                                    } elseif ($key === $submitted_option && !$is_correct) {
                                        $bg_class = "bg-rose-50 border-2 border-rose-400 text-rose-900 font-semibold";
                                        $border_badge = " <span class='ml-auto bg-rose-600 text-white text-[10px] font-semibold px-2 py-0.5 rounded'>Your Pick</span>";
                                    } elseif ($key === $submitted_option && $is_correct) {
                                        $border_badge = " <span class='ml-auto bg-emerald-600 text-white text-[10px] font-semibold px-2 py-0.5 rounded'>Your Pick ✓</span>";
                                    }
                                    ?>
                                    <div class="p-4 rounded-xl flex items-center shadow-xs <?php echo $bg_class; ?>">
                                        <span class="font-data text-[var(--ink-soft)] mr-2"><?php echo $key; ?></span>
                                        <span><?php echo htmlspecialchars($live_mcq[$col]); ?></span>
                                        <?php echo $border_badge; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="" class="bg-white/[0.06] p-5 rounded-xl border border-white/10">
                            <input type="hidden" name="ct_id" value="<?php echo $live_mcq['id']; ?>">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm mb-4 text-[var(--ink)]">
                                <label
                                    class="bg-white p-4 rounded-xl flex items-center space-x-3 cursor-pointer hover:bg-amber-50/60 transition has-[:checked]:ring-2 has-[:checked]:ring-[var(--gold)]">
                                    <input type="radio" name="selected_option" value="A" required
                                        class="accent-[var(--maroon)] w-4 h-4">
                                    <span><span class="font-data text-[var(--ink-soft)]">A</span>
                                        <?php echo htmlspecialchars($live_mcq['option_a']); ?></span>
                                </label>
                                <label
                                    class="bg-white p-4 rounded-xl flex items-center space-x-3 cursor-pointer hover:bg-amber-50/60 transition has-[:checked]:ring-2 has-[:checked]:ring-[var(--gold)]">
                                    <input type="radio" name="selected_option" value="B" class="accent-[var(--maroon)] w-4 h-4">
                                    <span><span class="font-data text-[var(--ink-soft)]">B</span>
                                        <?php echo htmlspecialchars($live_mcq['option_b']); ?></span>
                                </label>
                                <label
                                    class="bg-white p-4 rounded-xl flex items-center space-x-3 cursor-pointer hover:bg-amber-50/60 transition has-[:checked]:ring-2 has-[:checked]:ring-[var(--gold)]">
                                    <input type="radio" name="selected_option" value="C" class="accent-[var(--maroon)] w-4 h-4">
                                    <span><span class="font-data text-[var(--ink-soft)]">C</span>
                                        <?php echo htmlspecialchars($live_mcq['option_c']); ?></span>
                                </label>
                                <label
                                    class="bg-white p-4 rounded-xl flex items-center space-x-3 cursor-pointer hover:bg-amber-50/60 transition has-[:checked]:ring-2 has-[:checked]:ring-[var(--gold)]">
                                    <input type="radio" name="selected_option" value="D" class="accent-[var(--maroon)] w-4 h-4">
                                    <span><span class="font-data text-[var(--ink-soft)]">D</span>
                                        <?php echo htmlspecialchars($live_mcq['option_d']); ?></span>
                                </label>
                            </div>
                            <button type="submit" name="submit_response"
                                class="bg-[var(--gold-soft)] text-[var(--maroon-deep)] text-sm font-semibold px-6 py-3 rounded-lg hover:brightness-95 transition cursor-pointer">Submit
                                Answer</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php } ?>

            <!-- PDF Assignments (course-wise, with deadline) -->
            <div class="bg-white p-6 sm:p-7 rounded-2xl border border-[var(--line)] space-y-5">
                <div>
                    <h3 class="font-display text-xl font-semibold text-[var(--ink)]">📄 Assignments</h3>
                    <p class="text-sm text-[var(--ink-soft)] mt-1">Written assignments posted by your instructors —
                        submit your PDF before the deadline.</p>
                </div>

                <?php if (!empty($active_assignments)): ?>
                    <div class="space-y-4">
                        <?php foreach ($active_assignments as $asn):
                            $is_overdue = !empty($asn['deadline']) && strtotime($asn['deadline']) < time();
                            $already_submitted = !empty($asn['my_submission']);
                            ?>
                            <div class="p-5 rounded-xl border border-[var(--line)] ledger space-y-3">
                                <div class="flex items-center justify-between gap-2 flex-wrap">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="font-data text-[11px] uppercase font-semibold text-[var(--maroon)] bg-[#faf3e2] px-2.5 py-1 rounded border border-[var(--gold-25)]"><?php echo htmlspecialchars($asn['course_code']); ?></span>
                                        <span
                                            class="text-sm font-semibold text-[var(--ink)]"><?php echo htmlspecialchars($asn['title']); ?></span>
                                    </div>
                                    <span
                                        class="text-[11px] font-bold uppercase px-2.5 py-1 rounded-full <?php echo $is_overdue ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'; ?>">
                                        <?php echo $is_overdue ? '⏰ Deadline Over' : '🟢 Open'; ?>
                                    </span>
                                </div>

                                <p class="text-xs text-[var(--ink-soft)]">Deadline:
                                    <span class="font-semibold text-[var(--ink)]">
                                        <?php echo !empty($asn['deadline']) ? date("d M Y, h:i A", strtotime($asn['deadline'])) : 'N/A'; ?>
                                    </span>
                                </p>

                                <a href="<?php echo htmlspecialchars($asn['pdf_question']); ?>" target="_blank"
                                    class="inline-flex bg-[var(--gold-soft)] text-[var(--maroon-deep)] font-semibold px-5 py-2.5 rounded-lg text-sm hover:brightness-95 transition">Download
                                    Question Sheet ↓</a>

                                <?php if ($already_submitted): ?>
                                    <div
                                        class="bg-emerald-50 border border-emerald-200 p-4 rounded-xl text-sm text-emerald-800 flex items-center justify-between">
                                        <span>✅ Submitted successfully</span>
                                        <a href="<?php echo htmlspecialchars($asn['my_submission']); ?>" target="_blank"
                                            class="font-semibold hover:underline">View my script</a>
                                    </div>
                                <?php elseif ($is_overdue): ?>
                                    <div class="bg-rose-50 border border-rose-200 p-4 rounded-xl text-sm text-rose-700">
                                        ❌ Deadline is over. Submission is no longer accepted.
                                    </div>
                                <?php else: ?>
                                    <form method="POST" enctype="multipart/form-data" action=""
                                        class="border-t border-[var(--line)] pt-3 space-y-2">
                                        <input type="hidden" name="ct_id" value="<?php echo $asn['id']; ?>">
                                        <label
                                            class="block text-xs uppercase font-semibold tracking-wider text-[var(--ink-soft)]">Upload
                                            your script (PDF only)</label>
                                        <input type="file" name="student_pdf" accept="application/pdf" required
                                            class="text-sm text-[var(--ink-soft)] file:mr-3 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-[var(--paper-dim-60)] file:text-[var(--ink)]">
                                        <button type="submit" name="submit_response"
                                            class="bg-emerald-600 text-white text-sm font-semibold px-6 py-2.5 rounded-lg hover:bg-emerald-500 transition cursor-pointer">Submit
                                            PDF Script</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-sm text-[var(--ink-soft)] italic text-center py-6 bg-[var(--paper-dim-60)] rounded-xl">
                        No assignments have been posted yet.</p>
                <?php endif; ?>
            </div>

            <!-- Lecture Notes / Resources -->
            <div class="bg-white p-6 sm:p-7 rounded-2xl border border-[var(--line)] space-y-5">
                <div>
                    <h3 class="font-display text-xl font-semibold text-[var(--ink)]">Lecture Notes & Resources</h3>
                    <p class="text-sm text-[var(--ink-soft)] mt-1">Lecture notes shared by your instructors, organized
                        by course — download anytime.</p>
                </div>

                <?php if (!empty($lecture_notes_by_course)): ?>
                    <div class="space-y-6">
                        <?php foreach ($lecture_notes_by_course as $course_notes): ?>
                            <div>
                                <div class="flex items-center gap-2 mb-3">
                                    <span
                                        class="font-data text-[11px] uppercase font-semibold text-[var(--maroon)] bg-[#faf3e2] px-2.5 py-1 rounded border border-[var(--gold-25)]"><?php echo htmlspecialchars($course_notes['course_code']); ?></span>
                                    <span
                                        class="text-sm font-semibold text-[var(--ink)]"><?php echo htmlspecialchars($course_notes['course_title']); ?></span>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <?php foreach ($course_notes['notes'] as $note): ?>
                                        <a href="<?php echo htmlspecialchars($note['pdf_question']); ?>" target="_blank"
                                            class="flex items-center gap-3 p-4 rounded-xl border border-[var(--line)] ledger hover:border-[var(--gold-40)] transition group">
                                            <span
                                                class="w-10 h-10 rounded-lg bg-[#faf3e2] border border-[var(--gold-25)] flex items-center justify-center text-lg shrink-0">📄</span>
                                            <div class="min-w-0">
                                                <p
                                                    class="text-sm font-semibold text-[var(--ink)] truncate group-hover:text-[var(--maroon)] transition">
                                                    <?php echo htmlspecialchars($note['title']); ?>
                                                </p>
                                                <span
                                                    class="text-[11px] text-[var(--ink-soft)] uppercase tracking-wide font-semibold">Download
                                                    ↓</span>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-sm text-[var(--ink-soft)] italic text-center py-6 bg-[var(--paper-dim-60)] rounded-xl">
                        No lecture notes have been shared yet.</p>
                <?php endif; ?>
            </div>

            <!-- Course-wise attendance -->
            <div class="bg-white p-6 sm:p-7 rounded-2xl border border-[var(--line)] space-y-5">
                <div class="flex items-baseline justify-between">
                    <div>
                        <h3 class="font-display text-xl font-semibold text-[var(--ink)]">Attendance Record</h3>
                        <p class="text-sm text-[var(--ink-soft)] mt-1">Track your attendance rate separately for
                            each course.</p>
                    </div>
                    <span class="font-data text-xs text-[var(--ink-soft)] uppercase tracking-wider hidden sm:inline">75%
                        minimum</span>
                </div>

                <?php if (!empty($course_attendance)): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($course_attendance as $record): ?>
                            <div
                                class="p-5 rounded-xl border border-[var(--line)] ledger flex flex-col justify-between space-y-3">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <span
                                            class="font-data text-[11px] uppercase font-semibold text-[var(--maroon)] tracking-wide"><?php echo htmlspecialchars($record['code']); ?></span>
                                        <h4 class="text-sm font-semibold text-[var(--ink)] mt-1.5 line-clamp-1">
                                            <?php echo htmlspecialchars($record['title']); ?>
                                        </h4>
                                    </div>
                                    <span
                                        class="font-data text-xl font-semibold <?php echo ($record['rate'] >= 75) ? 'text-emerald-700' : 'text-rose-700'; ?>"><?php echo $record['rate']; ?>%</span>
                                </div>
                                <div class="space-y-2">
                                    <div class="w-full bg-[var(--paper-dim)] h-2 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full <?php echo ($record['rate'] >= 75) ? 'bg-emerald-500' : 'bg-rose-500'; ?>"
                                            style="width: <?php echo $record['rate']; ?>%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-[var(--ink-soft)] font-medium">
                                        <span>Present <strong
                                                class="font-data text-[var(--ink)]"><?php echo $record['present']; ?></strong>
                                            days</span>
                                        <span>of <strong
                                                class="font-data text-[var(--ink)]"><?php echo $record['total']; ?></strong>
                                            days</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-sm text-[var(--ink-soft)] italic text-center py-6 bg-[var(--paper-dim-60)] rounded-xl">No
                        attendance metrics recorded yet.</p>
                <?php endif; ?>

                <!-- Combined totals -->
                <div class="pt-4 border-t border-[var(--line)] grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div
                        class="bg-[#faf3e2] border border-[var(--gold-25)] p-5 rounded-xl flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-[var(--gold)] uppercase tracking-wide">Overall
                                Average
                            </p>
                            <p class="font-data text-3xl font-semibold text-[var(--ink)] mt-1">
                                <?php echo $overall_attendance_avg; ?>%
                            </p>
                        </div>
                        <span class="seal">◈</span>
                    </div>
                    <div
                        class="bg-emerald-50/70 border border-emerald-200/70 p-5 rounded-xl flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-emerald-800 uppercase tracking-wide">Cumulative
                                Presences</p>
                            <p class="font-data text-3xl font-semibold text-[var(--ink)] mt-1">
                                <?php echo $present_days_combined; ?>
                                <span class="text-sm font-medium text-[var(--ink-soft)]">/
                                    <?php echo $total_days_combined; ?></span>
                            </p>
                        </div>
                        <span
                            class="text-xl bg-white p-2.5 rounded-lg border border-emerald-200 text-emerald-700">✓</span>
                    </div>
                </div>
            </div>

            <!-- Grade sheet -->
            <div class="bg-white p-6 sm:p-7 rounded-2xl border border-[var(--line)] space-y-4">
                <div class="flex items-baseline justify-between">
                    <div>
                        <h3 class="font-display text-xl font-semibold text-[var(--ink)]">Grade Sheet</h3>
                        <p class="text-sm text-[var(--ink-soft)] mt-1">Verified semester results published by the
                            admin panel.</p>
                    </div>
                    <?php if ($completed_sem_count > 0): ?>
                        <div class="text-right">
                            <p class="text-xs uppercase font-semibold text-[var(--ink-soft)] tracking-wide">CGPA</p>
                            <p class="font-data text-2xl font-semibold text-[var(--maroon)]">
                                <?php echo number_format($actual_cgpa, 2); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="ledger rounded-xl p-1">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <?php for ($i = 1; $i <= 8; $i++): ?>
                            <div
                                class="p-4 rounded-xl border transition flex flex-col justify-between <?php echo isset($semester_results[$i]) ? 'bg-white border-[var(--gold-30)]' : 'bg-white/40 border-dashed border-[var(--line)]'; ?>">
                                <div class="flex items-start justify-between">
                                    <span class="text-xs uppercase font-semibold text-[var(--ink-soft)]">Sem
                                        0<?php echo $i; ?></span>
                                    <?php if (isset($semester_results[$i])): ?>
                                        <span class="seal !w-6 !h-6 !text-[11px]">✓</span>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-2">
                                    <?php if (isset($semester_results[$i])): ?>
                                        <span
                                            class="font-data text-lg font-semibold text-[var(--ink)]"><?php echo number_format($semester_results[$i], 2); ?></span>
                                        <span
                                            class="block text-[11px] text-emerald-700 font-semibold uppercase tracking-wide mt-1">Published</span>
                                    <?php else: ?>
                                        <span class="text-sm font-medium text-[var(--ink-soft)] italic">Pending</span>
                                        <span
                                            class="block text-[11px] text-[var(--ink-soft-70)] uppercase tracking-wide mt-1">Unreleased</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <!-- CGPA predictor -->
            <div class="bg-white p-6 sm:p-7 rounded-2xl border border-[var(--line)] space-y-5">
                <div>
                    <h3 class="font-display text-xl font-semibold text-[var(--ink)]">CGPA Forecast</h3>
                    <p class="text-sm text-[var(--ink-soft)] mt-1">Simulate next semester's outcome against your
                        graduation benchmark.</p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-[var(--ink-soft)] uppercase mb-1.5">Current
                            CGPA</label>
                        <input type="number" id="curr_cgpa" step="0.01" max="4.0" min="0" oninput="predictCGPA()"
                            class="w-full border border-[var(--line)] p-3.5 rounded-xl text-base font-data bg-[var(--paper-dim-60)] font-semibold focus:outline-none focus:border-[var(--maroon)] focus:ring-1 focus:ring-[var(--maroon)]">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[var(--ink-soft)] uppercase mb-1.5">Completed
                            Semesters</label>
                        <input type="number" id="comp_sem" max="8" min="0" oninput="predictCGPA()"
                            class="w-full border border-[var(--line)] p-3.5 rounded-xl text-base font-data bg-[var(--paper-dim-60)] font-semibold focus:outline-none focus:border-[var(--maroon)] focus:ring-1 focus:ring-[var(--maroon)]">
                    </div>
                    <div>
                        <div class="flex justify-between items-center mb-1.5">
                            <label class="text-xs font-semibold text-[var(--ink-soft)] uppercase">Target (Next
                                Sem)</label>
                            <span id="slider_val"
                                class="font-data text-xs font-semibold text-[var(--gold)] bg-[#faf3e2] px-2 py-0.5 rounded border border-[var(--gold-30)]">3.85</span>
                        </div>
                        <input type="range" id="target_gpa_slider" min="0.00" max="4.00" step="0.01" value="3.85"
                            oninput="syncTargetInput(this.value)"
                            class="w-full h-2 bg-[var(--paper-dim)] rounded-lg appearance-none cursor-pointer accent-[var(--maroon)] mt-3">
                        <input type="number" id="target_gpa" step="0.01" max="4.0" min="0" value="3.85"
                            oninput="syncTargetSlider(this.value)"
                            class="w-full border border-[var(--gold-40)] p-2.5 rounded-xl text-sm font-data font-semibold text-center mt-2 focus:outline-none focus:ring-1 focus:ring-[var(--gold)]">
                    </div>
                </div>
                <div id="prediction_result"
                    class="p-5 rounded-xl text-center ledger border border-[var(--line)] transition-all duration-300">
                    <p class="text-sm text-[var(--ink-soft)] font-medium">Forecasted CGPA</p>
                    <div class="flex items-center justify-center space-x-2 mt-1">
                        <strong id="predicted_val"
                            class="text-4xl font-semibold text-[var(--maroon)] font-data tracking-tight">0.000</strong>
                        <span id="trend_badge"
                            class="text-xs font-semibold px-2.5 py-1 rounded-full flex items-center space-x-1"></span>
                    </div>
                    <p id="motivational_text" class="text-xs text-[var(--ink-soft)] italic mt-2"></p>
                </div>
            </div>
        </div>

        <!-- Sidebar: registered courses -->
        <div class="bg-white p-6 rounded-2xl border border-[var(--line)] h-fit lg:sticky lg:top-24">
            <h3 class="font-display text-lg font-semibold text-[var(--ink)] mb-4 pb-3 border-b border-[var(--line)]">
                Registered Courses</h3>
            <div class="space-y-3 max-h-[550px] overflow-y-auto pr-1">
                <?php if ($courses && $courses->num_rows > 0) {
                    while ($c = $courses->fetch_assoc()) { ?>
                        <div
                            class="p-4 bg-[var(--paper-dim-50)] border border-[var(--line)] rounded-xl text-sm hover:border-[var(--gold-40)] transition">
                            <span
                                class="font-data font-semibold text-[var(--maroon)] bg-white px-2.5 py-1 rounded border border-[var(--gold-25)] text-[11px] tracking-wide uppercase"><?php echo htmlspecialchars($c['course_code']); ?></span>
                            <h4 class="font-semibold text-[var(--ink)] mt-2 leading-tight">
                                <?php echo htmlspecialchars($c['title']); ?>
                            </h4>
                            <p class="text-xs text-[var(--ink-soft)] mt-1">Faculty —
                                <?php echo htmlspecialchars($c['teacher_name'] ?? 'Not Assigned'); ?>
                            </p>
                        </div>
                    <?php }
                } else {
                    echo "<p class='text-sm text-[var(--ink-soft)] text-center py-4'>No courses found.</p>";
                } ?>
            </div>
        </div>
    </div>

    <script>
        window.addEventListener('DOMContentLoaded', () => {
            const dbCGPA = "<?php echo isset($actual_cgpa) ? $actual_cgpa : '3.52'; ?>";
            const dbSemesters = "<?php echo isset($completed_sem_count) ? $completed_sem_count : '5'; ?>";

            if (document.getElementById('curr_cgpa').value === "") {
                document.getElementById('curr_cgpa').value = dbSemesters > 0 ? dbCGPA : "3.52";
                document.getElementById('comp_sem').value = dbSemesters > 0 ? dbSemesters : "5";
                predictCGPA();
            }
        });

        function syncTargetInput(val) {
            document.getElementById('target_gpa').value = parseFloat(val).toFixed(2);
            document.getElementById('slider_val').innerText = parseFloat(val).toFixed(2);
            predictCGPA();
        }

        function syncTargetSlider(val) {
            let num = parseFloat(val);
            if (!isNaN(num) && num >= 0 && num <= 4.0) {
                document.getElementById('target_gpa_slider').value = num;
                document.getElementById('slider_val').innerText = num.toFixed(2);
            }
            predictCGPA();
        }

        function predictCGPA() {
            const currentCGPA = parseFloat(document.getElementById('curr_cgpa').value);
            const completedSem = parseInt(document.getElementById('comp_sem').value);
            const targetGPA = parseFloat(document.getElementById('target_gpa').value);

            const trendBadge = document.getElementById('trend_badge');
            const motivationalText = document.getElementById('motivational_text');

            if (isNaN(currentCGPA) || isNaN(completedSem) || isNaN(targetGPA) || completedSem < 0) {
                document.getElementById('predicted_val').innerText = "0.000";
                trendBadge.className = "hidden";
                motivationalText.innerText = "";
                return;
            }

            if (currentCGPA > 4.0 || targetGPA > 4.0 || currentCGPA < 0 || targetGPA < 0) {
                document.getElementById('predicted_val').innerText = "ERROR";
                trendBadge.className = "bg-rose-50 text-rose-600 text-xs px-2.5 py-1 rounded font-bold";
                trendBadge.innerText = "Invalid Bounds";
                motivationalText.innerText = "GPA scale must be between 0.00 and 4.00";
                return;
            }

            const totalPointsEarned = currentCGPA * completedSem;
            const integratedNextPoints = totalPointsEarned + targetGPA;
            const finalPredictedCGPA = integratedNextPoints / (completedSem + 1);

            document.getElementById('predicted_val').innerText = finalPredictedCGPA.toFixed(3);

            trendBadge.classList.remove('hidden');
            if (finalPredictedCGPA > currentCGPA) {
                trendBadge.className = "bg-emerald-50 text-emerald-700 text-xs font-black uppercase px-2.5 py-1 rounded-full border border-emerald-200";
                trendBadge.innerHTML = "↑ Improved";
                motivationalText.innerText = "Great trajectory! This will push your portfolio benchmark upwards.";
            } else if (finalPredictedCGPA < currentCGPA) {
                trendBadge.className = "bg-rose-50 text-rose-700 text-xs font-black uppercase px-2.5 py-1 rounded-full border border-rose-200";
                trendBadge.innerHTML = "↓ Dropping";
                motivationalText.innerText = "Target is lower than current CGPA. Consider aiming higher to avoid dilution.";
            } else {
                trendBadge.className = "bg-slate-100 text-slate-600 text-xs font-black uppercase px-2.5 py-1 rounded-full";
                trendBadge.innerHTML = "→ Stable";
                motivationalText.innerText = "Maintaining consistency perfectly across semesters.";
            }
        }
        // --- Live Meet + Attendance window polling ---
        let attendanceTimerInterval = null;
        let localSecondsLeft = <?php echo $attendance_active ? $attendance_remaining : 0; ?>;

        function updateMeetCard(meet) {
            const card = document.getElementById('meet-card');
            if (!card) return;
            if (meet) {
                const titleEl = document.getElementById('meet-course-title');
                const codeEl = document.getElementById('meet-course-code');
                const topicEl = document.getElementById('meet-topic-text');
                const linkBtn = document.getElementById('meet-link-btn');
                if (titleEl) titleEl.innerText = meet.course_title;
                if (codeEl) codeEl.innerText = meet.course_code;
                if (topicEl) topicEl.innerText = meet.title;
                if (linkBtn) linkBtn.href = meet.zoom_link;
                card.classList.remove('hidden');
            } else {
                card.classList.add('hidden');
            }
        }

        function updateAttendanceTimerDisplay() {
            const secEl = document.getElementById('att-seconds');
            const fillEl = document.getElementById('att-timer-fill');
            if (secEl) secEl.innerText = Math.max(localSecondsLeft, 0);
            if (fillEl) fillEl.style.width = Math.max((localSecondsLeft / 120) * 100, 0) + '%';
        }

        function startLocalCountdown() {
            if (attendanceTimerInterval) clearInterval(attendanceTimerInterval);
            attendanceTimerInterval = setInterval(() => {
                localSecondsLeft--;
                if (localSecondsLeft <= 0) {
                    hideAttendanceCard();
                    return;
                }
                updateAttendanceTimerDisplay();
            }, 1000);
        }

        function showAttendanceCard(att) {
            const card = document.getElementById('attendance-card');
            if (!card) return;
            const ctIdEl = document.getElementById('att-ct-id');
            const courseIdEl = document.getElementById('att-course-id');
            if (ctIdEl) ctIdEl.value = att.ct_id;
            if (courseIdEl) courseIdEl.value = att.course_id;

            localSecondsLeft = att.seconds_left;
            updateAttendanceTimerDisplay();
            card.classList.remove('hidden');
            startLocalCountdown();
        }

        function hideAttendanceCard() {
            const card = document.getElementById('attendance-card');
            if (card) card.classList.add('hidden');
            if (attendanceTimerInterval) {
                clearInterval(attendanceTimerInterval);
                attendanceTimerInterval = null;
            }
        }

        async function pollLiveStatus() {
            try {
                const res = await fetch('live_status.php');
                const data = await res.json();

                updateMeetCard(data.meet);

                if (data.attendance) {
                    showAttendanceCard(data.attendance);
                } else {
                    hideAttendanceCard();
                }
            } catch (e) {
                console.error('Live status poll failed', e);
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            <?php if ($attendance_active): ?>
                updateAttendanceTimerDisplay();
                startLocalCountdown();
            <?php endif; ?>
            pollLiveStatus();
            setInterval(pollLiveStatus, 5000);
        });
    </script>
</body>

</html>