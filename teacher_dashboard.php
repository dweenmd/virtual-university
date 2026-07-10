<?php
// 1. Security gatekeeper and session handler (must run before anything else)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db.php';

// Only teachers may access this page; otherwise redirect to the login page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: index.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];
$message = "";

// 1. Action handler: launch a Google Meet session + secret attendance token
if (isset($_POST['launch_meet'])) {
    $course_id = $_POST['course_id'];
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $meet_url = mysqli_real_escape_string($conn, $_POST['meet_url']);
    $token = rand(1000, 9999); // 4-digit random secret token

    // FIX: only mark previous LIVE MEET sessions as completed for this course, not every
    // online_class_tests row. The old query ("WHERE course_id='$course_id'" with no
    // test_type filter) was also completing live PDF assignments and MCQ tests that had
    // nothing to do with this action, which made PDF assignments vanish from the student
    // dashboard before their deadline.
    $conn->query("UPDATE online_class_tests SET status='completed' WHERE course_id='$course_id' AND test_type='meet'");
    $conn->query("INSERT INTO online_class_tests (course_id, title, status, zoom_link, test_type, attendance_token) VALUES ('$course_id', '$title', 'LIVE NOW', '$meet_url', 'meet', '$token')");
    $message = "🟢 Live Class Launched! Secret Attendance Token for Students: <strong class='text-red-600 text-lg font-mono'>$token</strong>";
}

// 2. Action handler: deploy an instant MCQ test (with correct answer)
if (isset($_POST['launch_mcq'])) {
    $course_id = $_POST['course_id'];
    $question = mysqli_real_escape_string($conn, $_POST['mcq_question']);
    $a = mysqli_real_escape_string($conn, $_POST['op_a']);
    $b = mysqli_real_escape_string($conn, $_POST['op_b']);
    $c = mysqli_real_escape_string($conn, $_POST['op_c']);
    $d = mysqli_real_escape_string($conn, $_POST['op_d']);
    $correct_option = mysqli_real_escape_string($conn, $_POST['correct_option']); // saved to the database as the correct answer

    // FIX: only mark previous LIVE MCQ tests as completed for this course, not every
    // online_class_tests row (see note in launch_meet above for why this matters).
    $conn->query("UPDATE online_class_tests SET status='completed' WHERE course_id='$course_id' AND test_type='mcq'");
    $conn->query("INSERT INTO online_class_tests (course_id, title, status, test_type, option_a, option_b, option_c, option_d, correct_option, zoom_link) VALUES ('$course_id', '$question', 'LIVE NOW', 'mcq', '$a', '$b', '$c', '$d', '$correct_option', '#')");
    $message = "⚡ Live MCQ Assessment deployed successfully with Correct Answer Set!";
}

// 3. Action handler: upload a PDF assignment
if (isset($_POST['launch_pdf'])) {
    $course_id = $_POST['course_id'];
    $title = mysqli_real_escape_string($conn, $_POST['pdf_title']);
    $deadline_raw = $_POST['deadline'] ?? '';
    $deadline = !empty($deadline_raw) ? mysqli_real_escape_string($conn, str_replace('T', ' ', $deadline_raw) . ':00') : null;

    $pdf_name = "";
    if (isset($_FILES['question_file']) && $_FILES['question_file']['error'] == 0) {
        $target_dir = "uploads/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $file_ext = strtolower(pathinfo($_FILES['question_file']['name'], PATHINFO_EXTENSION));
        if ($file_ext === 'pdf') {
            $pdf_name = $target_dir . "question_" . time() . "_" . uniqid() . ".pdf";
            move_uploaded_file($_FILES['question_file']['tmp_name'], $pdf_name);
        }
    }

    if (!empty($pdf_name) && !empty($deadline)) {
        // FIX: removed the blanket "UPDATE ... SET status='completed' WHERE course_id='$course_id'"
        // that used to run here. PDF assignments are allowed to run concurrently (each with its
        // own deadline) and should only stop accepting submissions once their own deadline passes
        // — not be force-closed just because a new assignment/meet/mcq was launched for the course.
        $conn->query("INSERT INTO online_class_tests (course_id, title, status, test_type, pdf_question, deadline, zoom_link) VALUES ('$course_id', '$title', 'LIVE NOW', 'pdf', '$pdf_name', '$deadline', '#')");
        $message = "📄 PDF Written Assignment deployed successfully! Deadline: " . date("d M Y, h:i A", strtotime($deadline));
    } else {
        $message = "❌ Failed to upload PDF. Please ensure you upload a valid .pdf file and set a deadline.";
    }
}

// 4. Action handler: upload a lecture note
if (isset($_POST['upload_note'])) {
    $course_id = $_POST['course_id'];
    $title = mysqli_real_escape_string($conn, $_POST['note_title']);

    $file_name = "";
    if (isset($_FILES['note_file']) && $_FILES['note_file']['error'] == 0) {
        $target_dir = "uploads/notes/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $file_ext = strtolower(pathinfo($_FILES['note_file']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['pdf', 'docx', 'doc', 'png', 'jpg', 'jpeg'];

        if (in_array($file_ext, $allowed_ext)) {
            $file_name = $target_dir . "note_" . time() . "_" . uniqid() . "." . $file_ext;
            move_uploaded_file($_FILES['note_file']['tmp_name'], $file_name);
        }
    }

    if (!empty($file_name)) {
        $conn->query("INSERT INTO course_resources (course_id, title, file_path) VALUES ('$course_id', '$title', '$file_name')");
        $message = "📚 Lecture Note '$title' shared with all students successfully!";
    } else {
        $message = "❌ Failed to upload Lecture Note. Please upload a valid document/image file.";
    }
}

// Find every course assigned to this specific teacher
$my_courses_query = $conn->query("SELECT * FROM courses WHERE teacher_id='$teacher_id' ORDER BY semester ASC, course_code ASC");
$courses_array = [];
if ($my_courses_query) {
    while ($row = $my_courses_query->fetch_assoc()) {
        $courses_array[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Workspace - Virtual Varsity</title>
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

        ::selection {
            background: var(--gold-soft);
            color: var(--maroon-deep);
        }
    </style>
</head>

<body class="antialiased">

    <nav
        class="bg-white/90 backdrop-blur sticky top-0 z-50 border-b border-[var(--line)] px-6 py-4 flex justify-between items-center">
        <div class="flex items-center space-x-3">
            <span class="text-2xl">👨‍🏫</span>
            <h1 class="font-display text-lg font-semibold text-[var(--maroon)] tracking-wide">Faculty Portal Workspace:
                <?php echo htmlspecialchars($_SESSION['name']); ?>
            </h1>
        </div>
        <a href="index.php"
            class="bg-[var(--maroon)] px-5 py-2.5 rounded-lg text-sm font-semibold text-white tracking-wide hover:bg-[var(--maroon-deep)] transition">Logout</a>
    </nav>

    <div class="max-w-7xl mx-auto mt-8 px-4">

        <?php if ($message): ?>
            <div
                class="mb-6 text-base font-semibold text-emerald-800 bg-emerald-50 p-5 rounded-xl border border-emerald-200 shadow-xs">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="mb-6">
            <h2 class="font-display text-2xl font-semibold text-[var(--ink)]">Academic Curriculum Control Panel</h2>
            <p class="text-sm text-[var(--ink-soft)] mt-1">Your assigned courses are listed below, each with its own
                semester details and student roster.</p>
        </div>

        <div class="space-y-10 mb-16">
            <?php
            if (empty($courses_array)) {
                echo "<p class='text-sm text-[var(--ink-soft)] italic bg-white p-8 rounded-2xl border border-dashed border-[var(--line)] text-center shadow-xs'>No active courses mapped to your teacher profile grid matrix yet.</p>";
            }

            foreach ($courses_array as $course) {
                $c_id = $course['id'];
                $c_sem = $course['semester'];

                // 1. Query: filter for actually enrolled students
                $course_students = $conn->query("
                    SELECT u.id, u.name, u.email, u.id_no 
                    FROM users u
                    JOIN academic_records ar ON u.id = ar.student_id
                    WHERE ar.course_id = '$c_id' AND u.role = 'student'
                    ORDER BY u.id_no ASC
                ");

                // 2. Query: quiz/assignment submission data
                $course_submissions = $conn->query("
                    SELECT q.*, o.title AS test_title, o.test_type 
                    FROM quiz_submissions q 
                    JOIN online_class_tests o ON q.ct_id = o.id 
                    WHERE o.course_id = '$c_id' 
                    ORDER BY q.id DESC
                ");

                // 3. Query: check whether a live session is currently active
                $check_live = $conn->query("SELECT id, title, test_type FROM online_class_tests WHERE course_id = '$c_id' AND status = 'LIVE NOW' LIMIT 1");
                $live_session = ($check_live && $check_live->num_rows > 0) ? $check_live->fetch_assoc() : null;

                // 4. Query: attendance-present data
                $attendance_students = $conn->query("
                    SELECT u.name, u.id_no, ar.present_days
                    FROM users u
                    JOIN academic_records ar ON u.id = ar.student_id
                    WHERE ar.course_id = '$c_id' AND ar.present_days > 0
                    ORDER BY ar.present_days DESC
                ");

                // 5. Query: completed (older) MCQ tests — archived here for PDF export and deletion
                $old_mcqs = $conn->query("
                    SELECT id, title, correct_option 
                    FROM online_class_tests 
                    WHERE course_id = '$c_id' AND test_type = 'mcq' AND status = 'completed' 
                    ORDER BY id DESC
                ");

                // 6. Query: every PDF assignment for this course (with deadline) — for the submission tracker
                $course_assignments = $conn->query("
                    SELECT id, title, deadline, status 
                    FROM online_class_tests 
                    WHERE course_id = '$c_id' AND test_type = 'pdf' 
                    ORDER BY id DESC
                ");
                $assignments_array = [];
                if ($course_assignments) {
                    while ($a = $course_assignments->fetch_assoc()) {
                        $assignments_array[] = $a;
                    }
                }

                // 7. Query: who has submitted against each assignment
                $assignment_subs_query = $conn->query("
                    SELECT q.ct_id, q.student_id, q.student_name, q.pdf_submission 
                    FROM quiz_submissions q
                    JOIN online_class_tests o ON q.ct_id = o.id
                    WHERE o.course_id = '$c_id' AND o.test_type = 'pdf'
                    ORDER BY q.id DESC
                ");
                $assignment_submissions = [];
                if ($assignment_subs_query) {
                    while ($row = $assignment_subs_query->fetch_assoc()) {
                        $assignment_submissions[$row['ct_id']][] = $row;
                    }
                }

                $total_students = ($course_students) ? $course_students->num_rows : 0;
                $total_submissions = ($course_submissions) ? $course_submissions->num_rows : 0;
                $total_attendance = ($attendance_students) ? $attendance_students->num_rows : 0;
                $total_old_mcqs = ($old_mcqs) ? $old_mcqs->num_rows : 0;
                ?>
                <div class="bg-white border border-[var(--line)] rounded-3xl shadow-xs overflow-hidden">

                    <div class="bg-[var(--maroon)] text-white px-6 py-5 flex flex-wrap justify-between items-center">
                        <div>
                            <span
                                class="bg-[var(--gold-soft)] text-[var(--maroon-deep)] font-data text-xs font-black uppercase px-2.5 py-1 rounded-md tracking-wider">Semester
                                <?php echo $c_sem; ?> Core</span>
                            <h3 class="font-display text-lg font-semibold tracking-wide mt-2">📚
                                <?php echo htmlspecialchars($course['course_code']); ?> :
                                <?php echo htmlspecialchars($course['title']); ?>
                            </h3>
                        </div>
                        <span
                            class="bg-white/10 text-[var(--gold-soft)] text-sm font-semibold px-3.5 py-1.5 rounded-xl border border-white/10 mt-2 sm:mt-0">
                            Total: <?php echo $total_students; ?> Students Enrolled
                        </span>
                    </div>

                    <div class="p-6 grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

                        <div class="space-y-4">

                            <div
                                class="bg-[var(--paper-dim-60)] border border-[var(--line)] rounded-2xl overflow-hidden transition-all duration-300 shadow-xs">
                                <button
                                    onclick="toggleSection('enrolled-content-<?php echo $c_id; ?>', 'arrow-enrolled-<?php echo $c_id; ?>')"
                                    class="w-full p-4 flex justify-between items-center text-left focus:outline-hidden cursor-pointer hover:bg-[var(--paper-dim)] transition-colors">
                                    <div>
                                        <h4
                                            class="text-sm font-bold text-[var(--ink)] uppercase tracking-wider flex items-center gap-1.5">
                                            🎓 Enrolled Roster
                                            <span
                                                class="bg-[var(--maroon)] text-white font-data text-xs px-2 py-0.5 rounded-full font-black"><?php echo $total_students; ?></span>
                                        </h4>
                                        <p class="text-xs text-[var(--ink-soft)] mt-1">Full list of enrolled students</p>
                                    </div>
                                    <span id="arrow-enrolled-<?php echo $c_id; ?>"
                                        class="text-[var(--ink-soft)] transition-transform duration-300 transform">▼</span>
                                </button>

                                <div id="enrolled-content-<?php echo $c_id; ?>"
                                    class="hidden px-4 pb-4 border-t border-[var(--line)] bg-white/50 pt-3 max-h-[220px] overflow-y-auto space-y-2">
                                    <?php if ($total_students > 0) {
                                        mysqli_data_seek($course_students, 0);
                                        while ($st = $course_students->fetch_assoc()) { ?>
                                            <div
                                                class="p-2.5 bg-white border border-[var(--line)] rounded-xl flex justify-between items-center shadow-2xs">
                                                <span
                                                    class="font-semibold text-[var(--ink)] text-sm"><?php echo htmlspecialchars($st['name']); ?></span>
                                                <span
                                                    class="font-data text-xs text-[var(--maroon)] bg-[#faf3e2] px-2 py-0.5 rounded"><?php echo htmlspecialchars($st['id_no']); ?></span>
                                            </div>
                                        <?php }
                                    } else { ?>
                                        <p class="text-sm text-[var(--ink-soft)] italic text-center py-2">No students active.
                                        </p>
                                    <?php } ?>
                                </div>
                            </div>

                            <div
                                class="bg-emerald-50/50 border border-emerald-100 rounded-2xl overflow-hidden transition-all duration-300 shadow-xs">
                                <button
                                    onclick="toggleSection('attendance-content-<?php echo $c_id; ?>', 'arrow-att-<?php echo $c_id; ?>')"
                                    class="w-full p-4 flex justify-between items-center text-left focus:outline-hidden cursor-pointer hover:bg-emerald-50 transition-colors">
                                    <div>
                                        <h4
                                            class="text-sm font-bold text-emerald-700 uppercase tracking-wider flex items-center gap-1.5">
                                            📝 Attendance Log Roll
                                            <span
                                                class="bg-emerald-600 text-white font-data text-xs px-2 py-0.5 rounded-full font-black"><?php echo $total_attendance; ?></span>
                                        </h4>
                                        <p class="text-xs text-[var(--ink-soft)] mt-1">List of present students</p>
                                    </div>
                                    <span id="arrow-att-<?php echo $c_id; ?>"
                                        class="text-emerald-600 transition-transform duration-300 transform">▼</span>
                                </button>

                                <div id="attendance-content-<?php echo $c_id; ?>"
                                    class="hidden px-4 pb-4 border-t border-emerald-100 bg-white/50 pt-3 max-h-[220px] overflow-y-auto space-y-2">
                                    <?php if ($total_attendance > 0) {
                                        mysqli_data_seek($attendance_students, 0);
                                        while ($att = $attendance_students->fetch_assoc()) { ?>
                                            <div
                                                class="p-3 bg-white border border-emerald-100 rounded-xl flex justify-between items-center text-sm shadow-2xs">
                                                <div>
                                                    <p class="font-bold text-[var(--ink)] text-sm">
                                                        <?php echo htmlspecialchars($att['name']); ?>
                                                    </p>
                                                    <p class="text-xs text-[var(--ink-soft)]">ID:
                                                        <?php echo htmlspecialchars($att['id_no']); ?>
                                                    </p>
                                                </div>
                                                <span
                                                    class="bg-emerald-100 text-emerald-800 font-data font-bold text-xs px-2.5 py-1 rounded-md border border-emerald-200">
                                                    <?php echo $att['present_days']; ?> Days
                                                </span>
                                            </div>
                                        <?php }
                                    } else { ?>
                                        <p
                                            class="text-sm text-[var(--ink-soft)] italic py-3 text-center bg-white rounded-xl border border-dashed border-emerald-200">
                                            No students have submitted a token for today's class yet.</p>
                                    <?php } ?>
                                </div>
                            </div>

                            <div id="mcq-archive-card-<?php echo $c_id; ?>"
                                class="bg-amber-50/50 border border-amber-100 rounded-2xl overflow-hidden transition-all duration-300 shadow-xs">
                                <button
                                    onclick="toggleSection('archive-content-<?php echo $c_id; ?>', 'arrow-archive-<?php echo $c_id; ?>')"
                                    class="w-full p-4 flex justify-between items-center text-left focus:outline-hidden cursor-pointer hover:bg-amber-50 transition-colors">
                                    <div>
                                        <h4
                                            class="text-sm font-bold text-amber-700 uppercase tracking-wider flex items-center gap-1.5">
                                            🗄️ Previous MCQ Archive
                                            <span id="archive-count-<?php echo $c_id; ?>"
                                                class="bg-amber-600 text-white font-data text-xs px-2 py-0.5 rounded-full font-black"><?php echo $total_old_mcqs; ?></span>
                                        </h4>
                                        <p class="text-xs text-[var(--ink-soft)] mt-1">Save old MCQs as a PDF, then
                                            delete them</p>
                                    </div>
                                    <span id="arrow-archive-<?php echo $c_id; ?>"
                                        class="text-amber-600 transition-transform duration-300 transform">▼</span>
                                </button>

                                <div id="archive-content-<?php echo $c_id; ?>"
                                    class="hidden px-4 pb-4 border-t border-amber-100 bg-white/50 pt-3 space-y-2">
                                    <?php if ($total_old_mcqs > 0): ?>
                                        <p class="text-sm text-[var(--ink-soft)] mb-1">
                                            <?php echo $total_old_mcqs; ?> completed MCQ(s) found. Download the
                                            PDF first — the delete button below will then unlock.
                                        </p>
                                        <a href="generate_mcq_pdf.php?course_id=<?php echo $c_id; ?>" target="_blank"
                                            onclick="enableMcqDelete(<?php echo $c_id; ?>)"
                                            class="block text-center bg-amber-600 text-white font-bold py-2.5 rounded-xl text-sm hover:bg-amber-700 transition shadow-xs cursor-pointer">
                                            📥 Download PDF (Question + Correct Answer)
                                        </a>
                                        <button id="delete-mcq-btn-<?php echo $c_id; ?>"
                                            onclick="deleteOldMcqs(<?php echo $c_id; ?>)" disabled
                                            class="w-full bg-slate-300 text-slate-500 font-bold py-2.5 rounded-xl text-sm cursor-not-allowed transition shadow-xs">
                                            🗑️ Delete Old MCQs (download PDF first)
                                        </button>
                                    <?php else: ?>
                                        <p class="text-sm text-[var(--ink-soft)] italic text-center py-2">No archived MCQs
                                            yet.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="lg:col-span-2 space-y-4">

                            <div id="live-status-box-<?php echo $c_id; ?>"
                                class="p-5 rounded-2xl border transition-all duration-300 <?php echo $live_session ? 'bg-rose-50/80 border-rose-200 shadow-xs' : 'bg-[var(--paper-dim-60)] border-[var(--line)]'; ?>">
                                <div class="flex flex-wrap justify-between items-center mb-1">
                                    <h5 class="text-sm font-bold text-[var(--ink)] uppercase tracking-wide">📡 Transmission
                                        Status</h5>
                                    <span id="status-badge-<?php echo $c_id; ?>"
                                        class="text-xs font-data font-black px-2.5 py-1 rounded-md <?php echo $live_session ? 'bg-rose-600 text-white animate-pulse' : 'bg-slate-300 text-slate-600'; ?>">
                                        <?php echo $live_session ? 'LIVE NOW (' . strtoupper($live_session['test_type']) . ')' : 'OFFLINE'; ?>
                                    </span>
                                </div>
                                <div id="live-details-<?php echo $c_id; ?>"
                                    class="<?php echo $live_session ? '' : 'hidden'; ?>">
                                    <p class="text-sm text-[var(--ink)] font-semibold mt-1.5">Current Topic: <span
                                            class="text-rose-600 font-data"
                                            id="live-title-text-<?php echo $c_id; ?>"><?php echo $live_session ? htmlspecialchars($live_session['title']) : ''; ?></span>
                                    </p>
                                    <button onclick="stopLiveSession(<?php echo $c_id; ?>)"
                                        class="mt-3 bg-rose-600 hover:bg-rose-700 text-white font-bold py-1.5 px-4 rounded-lg text-xs transition shadow-xs cursor-pointer">End
                                        Live Session 🛑</button>
                                </div>
                                <p id="offline-placeholder-<?php echo $c_id; ?>"
                                    class="text-sm text-[var(--ink-soft)] italic <?php echo $live_session ? 'hidden' : ''; ?>">
                                    No active live transmission generated for this course node.</p>
                            </div>

                            <div
                                class="bg-[var(--paper-dim)] p-1.5 rounded-xl flex flex-wrap sm:flex-nowrap gap-1 border border-[var(--line)]">
                                <button onclick="switchTab(<?php echo $c_id; ?>, 'meet')"
                                    id="tab-btn-meet-<?php echo $c_id; ?>"
                                    class="flex-1 text-center py-2.5 text-sm font-bold rounded-lg transition-all cursor-pointer bg-[var(--maroon)] text-white shadow-xs">🟢
                                    Live Meet</button>
                                <button onclick="switchTab(<?php echo $c_id; ?>, 'mcq')"
                                    id="tab-btn-mcq-<?php echo $c_id; ?>"
                                    class="flex-1 text-center py-2.5 text-sm font-bold rounded-lg transition-all cursor-pointer text-[var(--ink-soft)] hover:text-[var(--ink)]">⚡
                                    Instant MCQ</button>
                                <button onclick="switchTab(<?php echo $c_id; ?>, 'pdf')"
                                    id="tab-btn-pdf-<?php echo $c_id; ?>"
                                    class="flex-1 text-center py-2.5 text-sm font-bold rounded-lg transition-all cursor-pointer text-[var(--ink-soft)] hover:text-[var(--ink)]">📄
                                    PDF Task</button>
                                <button onclick="switchTab(<?php echo $c_id; ?>, 'note')"
                                    id="tab-btn-note-<?php echo $c_id; ?>"
                                    class="flex-1 text-center py-2.5 text-sm font-bold rounded-lg transition-all cursor-pointer text-[var(--ink-soft)] hover:text-[var(--ink)]">📚
                                    Lecture Note</button>
                            </div>

                            <div class="bg-white border border-[var(--line)] rounded-2xl p-6 shadow-xs">

                                <div id="tab-content-meet-<?php echo $c_id; ?>"
                                    class="tab-panel-<?php echo $c_id; ?> space-y-3">
                                    <div class="mb-2">
                                        <h5 class="text-sm font-black text-[var(--ink)] uppercase tracking-wide">🟢 Launch
                                            Live
                                            Lecture Session</h5>
                                    </div>
                                    <form method="POST" action="" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <input type="hidden" name="course_id" value="<?php echo $c_id; ?>">
                                        <input type="text" name="title" placeholder="Topic: e.g., Agile Architecture"
                                            required
                                            class="border border-[var(--line)] focus:border-[var(--maroon)] focus:ring-2 focus:ring-[var(--gold-25)] p-3 rounded-xl text-sm bg-white transition-all outline-hidden">
                                        <input type="url" name="meet_url" placeholder="Google Meet Link URL..." required
                                            class="border border-[var(--line)] focus:border-[var(--maroon)] focus:ring-2 focus:ring-[var(--gold-25)] p-3 rounded-xl text-sm bg-white transition-all outline-hidden">
                                        <button type="submit" name="launch_meet"
                                            class="md:col-span-2 bg-[var(--maroon)] text-white font-bold py-3 rounded-xl text-sm hover:bg-[var(--maroon-deep)] transition shadow-sm cursor-pointer">Generate
                                            Token & Broadcast Class</button>
                                    </form>
                                </div>

                                <div id="tab-content-mcq-<?php echo $c_id; ?>"
                                    class="tab-panel-<?php echo $c_id; ?> hidden space-y-3">
                                    <div class="mb-2">
                                        <h5 class="text-sm font-black text-[var(--ink)] uppercase tracking-wide">⚡ Launch
                                            Instant MCQ Test Node</h5>
                                    </div>
                                    <form method="POST" action="" class="space-y-3">
                                        <input type="hidden" name="course_id" value="<?php echo $c_id; ?>">
                                        <input type="text" name="mcq_question"
                                            placeholder="Core MCQ Exam Question Statement..." required
                                            class="w-full border border-[var(--line)] focus:border-blue-500 focus:ring-2 focus:ring-blue-100 p-3 rounded-xl text-sm bg-white transition-all outline-hidden">

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
                                            <input type="text" name="op_a" placeholder="Option A" required
                                                class="border border-[var(--line)] focus:border-blue-500 focus:ring-2 focus:ring-blue-100 p-2.5 rounded-xl text-sm bg-white transition-all outline-hidden">
                                            <input type="text" name="op_b" placeholder="Option B" required
                                                class="border border-[var(--line)] focus:border-blue-500 focus:ring-2 focus:ring-blue-100 p-2.5 rounded-xl text-sm bg-white transition-all outline-hidden">
                                            <input type="text" name="op_c" placeholder="Option C" required
                                                class="border border-[var(--line)] focus:border-blue-500 focus:ring-2 focus:ring-blue-100 p-2.5 rounded-xl text-sm bg-white transition-all outline-hidden">
                                            <input type="text" name="op_d" placeholder="Option D" required
                                                class="border border-[var(--line)] focus:border-blue-500 focus:ring-2 focus:ring-blue-100 p-2.5 rounded-xl text-sm bg-white transition-all outline-hidden">
                                        </div>

                                        <div
                                            class="flex items-center gap-2 bg-blue-50/50 p-3 rounded-xl border border-blue-100">
                                            <label
                                                class="text-xs font-bold text-blue-700 uppercase tracking-wider pl-1">Select
                                                Correct Answer:</label>
                                            <select name="correct_option" required
                                                class="flex-1 border border-[var(--line)] focus:border-blue-500 focus:ring-2 focus:ring-blue-100 p-2 rounded-lg text-sm bg-white font-bold text-[var(--ink)] outline-hidden">
                                                <option value="" disabled selected>Choose correct option...</option>
                                                <option value="A">Option A</option>
                                                <option value="B">Option B</option>
                                                <option value="C">Option C</option>
                                                <option value="D">Option D</option>
                                            </select>
                                        </div>

                                        <button type="submit" name="launch_mcq"
                                            class="w-full bg-blue-600 text-white font-bold py-3 rounded-xl text-sm hover:bg-blue-700 transition shadow-sm cursor-pointer">Deploy
                                            Live MCQ</button>
                                    </form>
                                </div>

                                <div id="tab-content-pdf-<?php echo $c_id; ?>"
                                    class="tab-panel-<?php echo $c_id; ?> hidden space-y-3">
                                    <div class="mb-2">
                                        <h5 class="text-sm font-black text-[var(--ink)] uppercase tracking-wide">📄 Launch
                                            PDF
                                            Assignment Node</h5>
                                    </div>
                                    <form method="POST" enctype="multipart/form-data" action="" class="space-y-3">
                                        <input type="hidden" name="course_id" value="<?php echo $c_id; ?>">
                                        <input type="text" name="pdf_title"
                                            placeholder="Assignment Title (e.g., Mid Term Written Exam)" required
                                            class="w-full border border-[var(--line)] focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 p-3 rounded-xl text-sm bg-white transition-all outline-hidden">
                                        <div>
                                            <label
                                                class="text-xs font-bold text-[var(--ink-soft)] uppercase tracking-wide block mb-1.5">Submission
                                                Deadline</label>
                                            <input type="datetime-local" name="deadline" required
                                                class="w-full border border-[var(--line)] focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 p-3 rounded-xl text-sm bg-white transition-all outline-hidden">
                                        </div>
                                        <input type="file" name="question_file" accept="application/pdf" required
                                            class="text-sm text-[var(--ink-soft)] block w-full file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-bold file:bg-[var(--paper-dim)] file:text-[var(--ink)] hover:file:bg-[var(--paper-dim-60)] file:cursor-pointer">
                                        <button type="submit" name="launch_pdf"
                                            class="w-full bg-emerald-600 text-white font-bold py-3 rounded-xl text-sm hover:bg-emerald-700 transition shadow-sm cursor-pointer">Deploy
                                            PDF Question</button>
                                    </form>
                                </div>

                                <div id="tab-content-note-<?php echo $c_id; ?>"
                                    class="tab-panel-<?php echo $c_id; ?> hidden space-y-3">
                                    <div class="mb-2">
                                        <h5 class="text-sm font-black text-[var(--ink)] uppercase tracking-wide">📚 Quick
                                            Share: Class Lecture Note</h5>
                                    </div>
                                    <form method="POST" enctype="multipart/form-data" action=""
                                        class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <input type="hidden" name="course_id" value="<?php echo $c_id; ?>">
                                        <input type="text" name="note_title"
                                            placeholder="Lecture Title: e.g., Chapter 03 Handnote" required
                                            class="border border-[var(--line)] focus:border-purple-500 focus:ring-2 focus:ring-purple-100 p-3 rounded-xl text-sm bg-white transition-all outline-hidden">
                                        <input type="file" name="note_file" accept=".pdf,.docx,.doc,.png,.jpg,.jpeg"
                                            required
                                            class="text-sm text-[var(--ink-soft)] block w-full self-center file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-bold file:bg-[var(--paper-dim)] file:text-[var(--ink)] hover:file:bg-[var(--paper-dim-60)] file:cursor-pointer">
                                        <button type="submit" name="upload_note"
                                            class="md:col-span-2 bg-purple-600 text-white font-bold py-3 rounded-xl text-sm hover:bg-purple-700 transition shadow-sm cursor-pointer">Upload
                                            Note & Broadcast to Nodes 🚀</button>
                                    </form>
                                </div>

                            </div>

                            <div class="p-5 bg-white border border-[var(--line)] rounded-2xl">
                                <h5 class="text-sm font-bold text-[var(--ink)] mb-2 uppercase tracking-wide">🗂️ Assignment
                                    Submission Tracker</h5>
                                <p class="text-xs text-[var(--ink-soft)] mb-3">Choose an assignment to see who has
                                    submitted and who hasn't yet.</p>

                                <?php if (!empty($assignments_array)): ?>
                                    <select onchange="showAssignmentSubs(<?php echo $c_id; ?>, this.value)"
                                        class="w-full border border-[var(--line)] focus:border-[var(--maroon)] focus:ring-2 focus:ring-[var(--gold-25)] p-2.5 rounded-xl text-sm bg-white transition-all outline-hidden mb-3">
                                        <option value="">-- Select Assignment --</option>
                                        <?php foreach ($assignments_array as $a):
                                            $sub_count = isset($assignment_submissions[$a['id']]) ? count($assignment_submissions[$a['id']]) : 0;
                                            $dl_label = !empty($a['deadline']) ? date("d M, h:i A", strtotime($a['deadline'])) : "No deadline";
                                            ?>
                                            <option value="<?php echo $a['id']; ?>">
                                                <?php echo htmlspecialchars($a['title']); ?> —
                                                <?php echo $sub_count; ?>/<?php echo $total_students; ?>
                                                submitted (Deadline: <?php echo $dl_label; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <?php foreach ($assignments_array as $a):
                                        $submitted_list = isset($assignment_submissions[$a['id']]) ? $assignment_submissions[$a['id']] : [];
                                        $submitted_ids = array_column($submitted_list, 'student_id');

                                        $not_submitted = [];
                                        if ($course_students) {
                                            mysqli_data_seek($course_students, 0);
                                            while ($st = $course_students->fetch_assoc()) {
                                                if (!in_array($st['id'], $submitted_ids)) {
                                                    $not_submitted[] = $st;
                                                }
                                            }
                                        }

                                        $is_overdue = !empty($a['deadline']) && strtotime($a['deadline']) < time();
                                        ?>
                                        <div id="assignment-subs-<?php echo $a['id']; ?>"
                                            class="assignment-subs-panel-<?php echo $c_id; ?> hidden space-y-3">

                                            <div
                                                class="flex justify-between items-center text-xs font-bold uppercase tracking-wide px-1">
                                                <span class="<?php echo $is_overdue ? 'text-rose-600' : 'text-emerald-600'; ?>">
                                                    <?php echo $is_overdue ? '⏰ Deadline Passed' : '🟢 Accepting Submissions'; ?>
                                                </span>
                                                <span class="text-[var(--ink-soft)]">
                                                    Deadline:
                                                    <?php echo !empty($a['deadline']) ? date("d M Y, h:i A", strtotime($a['deadline'])) : 'N/A'; ?>
                                                </span>
                                            </div>

                                            <div>
                                                <p class="text-xs font-bold text-emerald-700 uppercase mb-1.5">✅
                                                    Submitted
                                                    (<?php echo count($submitted_list); ?>)</p>
                                                <div class="space-y-1.5 max-h-[150px] overflow-y-auto pr-1">
                                                    <?php if (!empty($submitted_list)): ?>
                                                        <?php foreach ($submitted_list as $sub): ?>
                                                            <div
                                                                class="p-2.5 bg-emerald-50 border border-emerald-100 rounded-xl flex items-center justify-between text-sm">
                                                                <span
                                                                    class="font-bold text-[var(--ink)] text-sm"><?php echo htmlspecialchars($sub['student_name']); ?></span>
                                                                <a href="<?php echo htmlspecialchars($sub['pdf_submission']); ?>"
                                                                    target="_blank"
                                                                    class="text-xs text-blue-600 font-bold hover:underline">View
                                                                    PDF</a>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <p class="text-sm text-[var(--ink-soft)] italic text-center py-2">No
                                                            one has submitted yet.</p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <div>
                                                <p class="text-xs font-bold text-rose-600 uppercase mb-1.5">❌ Not
                                                    Submitted
                                                    (<?php echo count($not_submitted); ?>)</p>
                                                <div class="space-y-1.5 max-h-[150px] overflow-y-auto pr-1">
                                                    <?php if (!empty($not_submitted)): ?>
                                                        <?php foreach ($not_submitted as $st): ?>
                                                            <div
                                                                class="p-2.5 bg-rose-50 border border-rose-100 rounded-xl flex items-center justify-between text-sm">
                                                                <span
                                                                    class="font-bold text-[var(--ink)] text-sm"><?php echo htmlspecialchars($st['name']); ?></span>
                                                                <span
                                                                    class="font-data text-xs text-rose-500"><?php echo htmlspecialchars($st['id_no']); ?></span>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <p class="text-sm text-[var(--ink-soft)] italic text-center py-2">
                                                            Everyone has submitted! 🎉</p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-sm text-[var(--ink-soft)] italic text-center py-2">No PDF assignments
                                        have been deployed yet.</p>
                                <?php endif; ?>
                            </div>

                            <div class="p-5 bg-[var(--paper-dim-60)] border border-[var(--line)] rounded-2xl">
                                <h5 class="text-sm font-bold text-[var(--ink)] mb-2 uppercase tracking-wide">📥 Received
                                    Answers Log</h5>
                                <div class="space-y-1.5 max-h-[150px] overflow-y-auto pr-1">
                                    <?php if ($total_submissions > 0) {
                                        mysqli_data_seek($course_submissions, 0);
                                        while ($sub = $course_submissions->fetch_assoc()) { ?>
                                            <div
                                                class="p-2.5 bg-white border border-[var(--line)] rounded-xl flex items-center justify-between text-sm shadow-2xs">
                                                <div>
                                                    <p class="font-bold text-[var(--ink)] text-sm">
                                                        <?php echo htmlspecialchars($sub['student_name']); ?>
                                                    </p>
                                                    <span
                                                        class="text-xs text-[var(--maroon)] font-bold uppercase tracking-wider"><?php echo htmlspecialchars($sub['test_type']); ?></span>
                                                </div>
                                                <div
                                                    class="font-data text-xs text-[var(--ink-soft)] bg-[var(--paper-dim-60)] border border-[var(--line)] px-2.5 py-1 rounded-md max-w-[180px] truncate">
                                                    <?php echo !empty($sub['pdf_submission']) ? "<a href='" . htmlspecialchars($sub['pdf_submission']) . "' target='_blank' class='text-blue-600 font-bold hover:underline'>View PDF</a>" : htmlspecialchars($sub['answers']); ?>
                                                </div>
                                            </div>
                                        <?php }
                                    } else { ?>
                                        <p class="text-sm text-[var(--ink-soft)] italic text-center py-2">No submission scripts
                                            cached yet.</p>
                                    <?php } ?>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>

    <script>
        // 1. AJAX mechanism to end a live session
        async function stopLiveSession(courseId) {
            if (!confirm("Are you sure you want to end this live session?")) return;

            const formData = new FormData();
            formData.append('course_id', courseId);

            try {
                const response = await fetch('update_live_status.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.status === 'success') {
                    const statusBadge = document.getElementById(`status-badge-${courseId}`);
                    const liveDetails = document.getElementById(`live-details-${courseId}`);
                    const placeholder = document.getElementById(`offline-placeholder-${courseId}`);
                    const statusBox = document.getElementById(`live-status-box-${courseId}`);

                    if (statusBadge) {
                        statusBadge.innerText = 'OFFLINE';
                        statusBadge.className = "text-xs font-data font-black px-2.5 py-1 rounded-md bg-slate-300 text-slate-600";
                    }
                    if (liveDetails) liveDetails.classList.add('hidden');
                    if (placeholder) placeholder.classList.remove('hidden');
                    if (statusBox) {
                        statusBox.className = "p-5 rounded-2xl border transition-all duration-300 bg-[var(--paper-dim-60)] border-[var(--line)]";
                    }
                } else {
                    alert("Error: " + result.message);
                }
            } catch (error) {
                console.error("AJAX Error:", error);
                alert("Something went wrong while ending the session.");
            }
        }

        // 2. Tab switching logic
        function switchTab(courseId, tabName) {
            const panels = document.querySelectorAll(`.tab-panel-${courseId}`);
            panels.forEach(panel => panel.classList.add('hidden'));

            const targetPanel = document.getElementById(`tab-content-${tabName}-${courseId}`);
            if (targetPanel) targetPanel.classList.remove('hidden');

            const tabButtons = ['meet', 'mcq', 'pdf', 'note'];
            tabButtons.forEach(btn => {
                const btnEl = document.getElementById(`tab-btn-${btn}-${courseId}`);
                if (btnEl) {
                    btnEl.className = "flex-1 text-center py-2.5 text-sm font-bold rounded-lg transition-all cursor-pointer text-[var(--ink-soft)] hover:text-[var(--ink)]";
                }
            });

            const activeBtn = document.getElementById(`tab-btn-${tabName}-${courseId}`);
            if (activeBtn) {
                activeBtn.className = "flex-1 text-center py-2.5 text-sm font-bold rounded-lg transition-all cursor-pointer bg-[var(--maroon)] text-white shadow-sm";
            }
        }

        // 3. Enables the Delete button only after the archive PDF has been downloaded — a safe two-step delete flow
        function enableMcqDelete(courseId) {
            const btn = document.getElementById(`delete-mcq-btn-${courseId}`);
            if (btn) {
                btn.disabled = false;
                btn.classList.remove('bg-slate-300', 'text-slate-500', 'cursor-not-allowed');
                btn.classList.add('bg-rose-600', 'text-white', 'hover:bg-rose-700', 'cursor-pointer');
                btn.innerText = "🗑️ Delete Old MCQs Now (Permanent)";
            }
        }

        // 4. AJAX mechanism to delete old MCQs (only enabled once the PDF has been downloaded)
        async function deleteOldMcqs(courseId) {
            if (!confirm("Once you confirm, every archived MCQ for this course will be permanently deleted. Are you sure?")) return;

            const formData = new FormData();
            formData.append('course_id', courseId);

            try {
                const response = await fetch('delete_old_mcq.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.status === 'success') {
                    const archiveCount = document.getElementById(`archive-count-${courseId}`);
                    const archiveContent = document.getElementById(`archive-content-${courseId}`);
                    if (archiveCount) archiveCount.innerText = '0';
                    if (archiveContent) {
                        archiveContent.innerHTML = `<p class="text-sm text-[var(--ink-soft)] italic text-center py-2">No archived MCQs yet.</p>`;
                    }
                } else {
                    alert("Error: " + result.message);
                }
            } catch (error) {
                console.error("AJAX Error:", error);
                alert("Something went wrong while deleting.");
            }
        }

        // 5. Generalized section toggle mechanism (used by Enrolled, Attendance and Archive alike)
        function toggleSection(contentId, arrowId) {
            const content = document.getElementById(contentId);
            const arrow = document.getElementById(arrowId);

            if (content.classList.contains('hidden')) {
                content.classList.remove('hidden');
                arrow.style.transform = "rotate(180deg)";
            } else {
                content.classList.add('hidden');
                arrow.style.transform = "rotate(0deg)";
            }
        }
        // 6. Shows the submission list for whichever assignment is selected in the dropdown
        function showAssignmentSubs(courseId, ctId) {
            document.querySelectorAll(`.assignment-subs-panel-${courseId}`).forEach(p => p.classList.add('hidden'));
            if (ctId) {
                const panel = document.getElementById(`assignment-subs-${ctId}`);
                if (panel) panel.classList.remove('hidden');
            }
        }
    </script>
</body>

</html>