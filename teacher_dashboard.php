<?php
// ১. নিরাপত্তা গেটকিপার এবং সেশন হ্যান্ডলার (সবার আগে থাকতে হবে)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db.php';

// শুধুমাত্র শিক্ষকরাই এই পেজ অ্যাক্সেস করতে পারবেন, না হলে লগইন পেজে রিডাইরেক্ট করবে
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: index.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];
$message = "";

// ১. অ্যাকশন হ্যান্ডলার: গুগুল মিট সেশন + সিক্রেট অ্যাটেনডেন্স টোকেন লঞ্চ
if (isset($_POST['launch_meet'])) {
    $course_id = $_POST['course_id'];
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $meet_url = mysqli_real_escape_string($conn, $_POST['meet_url']);
    $token = rand(1000, 9999); // ৪ ডিজিটের র্যান্ডম সিক্রেট টোকেন

    // আগের লাইভ ক্লাসগুলো কমপ্লিট করে নতুন ক্লাস লাইভ করা
    $conn->query("UPDATE online_class_tests SET status='completed' WHERE course_id='$course_id'");
    $conn->query("INSERT INTO online_class_tests (course_id, title, status, zoom_link, test_type, attendance_token) VALUES ('$course_id', '$title', 'LIVE NOW', '$meet_url', 'meet', '$token')");
    $message = "🟢 Live Class Launched! Secret Attendance Token for Students: <strong class='text-red-600 text-lg font-mono'>$token</strong>";
}

// ২. অ্যাকশন হ্যান্ডলার: ইনস্ট্যান্ট এমসিকিউ (MCQ) টেস্ট ডেপ্লয় (কারেক্ট আনসারসহ)
if (isset($_POST['launch_mcq'])) {
    $course_id = $_POST['course_id'];
    $question = mysqli_real_escape_string($conn, $_POST['mcq_question']);
    $a = mysqli_real_escape_string($conn, $_POST['op_a']);
    $b = mysqli_real_escape_string($conn, $_POST['op_b']);
    $c = mysqli_real_escape_string($conn, $_POST['op_c']);
    $d = mysqli_real_escape_string($conn, $_POST['op_d']);
    $correct_option = mysqli_real_escape_string($conn, $_POST['correct_option']); // ডাটাবেজে সঠিক উত্তর সেভের জন্য

    $conn->query("UPDATE online_class_tests SET status='completed' WHERE course_id='$course_id'");
    $conn->query("INSERT INTO online_class_tests (course_id, title, status, test_type, option_a, option_b, option_c, option_d, correct_option, zoom_link) VALUES ('$course_id', '$question', 'LIVE NOW', 'mcq', '$a', '$b', '$c', '$d', '$correct_option', '#')");
    $message = "⚡ Live MCQ Assessment deployed successfully with Correct Answer Set!";
}

// ৩. অ্যাকশন হ্যান্ডলার: পিডিএফ (PDF) অ্যাসাইনমেন্ট আপলোড
if (isset($_POST['launch_pdf'])) {
    $course_id = $_POST['course_id'];
    $title = mysqli_real_escape_string($conn, $_POST['pdf_title']);

    $pdf_name = "";
    if (isset($_FILES['question_file']) && $_FILES['question_file']['error'] == 0) {
        $target_dir = "uploads/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $file_ext = strtolower(pathinfo($_FILES['question_file']['name'], PHP_EXTENSION));
        if ($file_ext === 'pdf') {
            $pdf_name = $target_dir . "question_" . time() . "_" . uniqid() . ".pdf";
            move_uploaded_file($_FILES['question_file']['tmp_name'], $pdf_name);
        }
    }

    if (!empty($pdf_name)) {
        $conn->query("UPDATE online_class_tests SET status='completed' WHERE course_id='$course_id'");
        $conn->query("INSERT INTO online_class_tests (course_id, title, status, test_type, pdf_question, zoom_link) VALUES ('$course_id', '$title', 'LIVE NOW', 'pdf', '$pdf_name', '#')");
        $message = "📄 PDF Written Assignment deployed successfully!";
    } else {
        $message = "❌ Failed to upload PDF. Please ensure you are uploading a valid .pdf file.";
    }
}

// ৪. অ্যাকশন হ্যান্ডলার: ক্লাস লেকচার নোট আপলোড
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
        $conn->query("INSERT INTO online_class_tests (course_id, title, status, test_type, pdf_question, zoom_link) VALUES ('$course_id', '$title', 'completed', 'note', '$file_name', '#')");
        $message = "📚 Lecture Note '$title' shared with all students successfully!";
    } else {
        $message = "❌ Failed to upload Lecture Note. Please upload a valid document/image file.";
    }
}

// ডাটাবেজ থেকে এই নির্দিষ্ট শিক্ষকের আন্ডারে থাকা সব কোর্স খুঁজে বের করা
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
</head>

<body class="bg-slate-50 text-slate-800 font-sans antialiased">

    <nav class="bg-indigo-950 px-6 py-4 text-white flex justify-between items-center shadow-md">
        <div class="flex items-center space-x-2">
            <span class="text-xl">👨‍🏫</span>
            <h1 class="text-base font-bold tracking-wide">Faculty Portal Workspace:
                <?php echo htmlspecialchars($_SESSION['name']); ?>
            </h1>
        </div>
        <a href="index.php"
            class="bg-red-500 px-4 py-2 rounded-xl text-xs font-bold hover:bg-red-600 transition shadow">Logout</a>
    </nav>

    <div class="max-w-7xl mx-auto mt-8 px-4">

        <?php if ($message): ?>
            <div
                class="mb-6 text-sm font-semibold text-emerald-800 bg-emerald-50 p-4 rounded-xl border border-emerald-100 shadow-xs">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="mb-6">
            <h2 class="text-xl font-black text-slate-900">Academic Curriculum Control Panel</h2>
            <p class="text-xs text-slate-400">আপনার অ্যাসাইন করা কোর্সগুলোর সেমিস্টার এবং স্টুডেন্টদের তালিকা আলাদা
                সেকশনে নিচে দেওয়া হলো।</p>
        </div>

        <div class="space-y-10 mb-16">
            <?php
            if (empty($courses_array)) {
                echo "<p class='text-xs text-slate-400 italic bg-white p-8 rounded-2xl border border-dashed text-center shadow-xs'>No active courses mapped to your teacher profile grid matrix yet.</p>";
            }

            foreach ($courses_array as $course) {
                $c_id = $course['id'];
                $c_sem = $course['semester'];

                // ১. কুয়েরি: এনরোল করা আসল স্টুডেন্টদের ফিল্টার করা
                $course_students = $conn->query("
                    SELECT u.name, u.email, u.id_no 
                    FROM users u
                    JOIN academic_records ar ON u.id = ar.student_id
                    WHERE ar.course_id = '$c_id' AND u.role = 'student'
                    ORDER BY u.id_no ASC
                ");

                // ২. কুয়েরি: কুইজ/অ্যাসাইনমেন্ট সাবমিশন ডাটা
                $course_submissions = $conn->query("
                    SELECT q.*, o.title AS test_title, o.test_type 
                    FROM quiz_submissions q 
                    JOIN online_class_tests o ON q.ct_id = o.id 
                    WHERE o.course_id = '$c_id' 
                    ORDER BY q.id DESC
                ");

                // ৩. কুয়েরি: কোনো লাইভ সেশন অ্যাক্টিভ আছে কিনা চেক করা
                $check_live = $conn->query("SELECT id, title, test_type FROM online_class_tests WHERE course_id = '$c_id' AND status = 'LIVE NOW' LIMIT 1");
                $live_session = ($check_live && $check_live->num_rows > 0) ? $check_live->fetch_assoc() : null;

                // ৪. কুয়েরি: অ্যাটেনডেন্স প্রেজেন্ট ডাটা
                $attendance_students = $conn->query("
                    SELECT u.name, u.id_no, ar.present_days
                    FROM users u
                    JOIN academic_records ar ON u.id = ar.student_id
                    WHERE ar.course_id = '$c_id' AND ar.present_days > 0
                    ORDER BY ar.present_days DESC
                ");

                // ৫. কুয়েরি: সম্পন্ন হওয়া (completed) পুরনো MCQ টেস্টগুলো - এগুলো PDF এক্সপোর্ট ও ডিলিটের জন্য আর্কাইভ
                $old_mcqs = $conn->query("
                    SELECT id, title, correct_option 
                    FROM online_class_tests 
                    WHERE course_id = '$c_id' AND test_type = 'mcq' AND status = 'completed' 
                    ORDER BY id DESC
                ");

                $total_students = ($course_students) ? $course_students->num_rows : 0;
                $total_submissions = ($course_submissions) ? $course_submissions->num_rows : 0;
                $total_attendance = ($attendance_students) ? $attendance_students->num_rows : 0;
                $total_old_mcqs = ($old_mcqs) ? $old_mcqs->num_rows : 0;
                ?>
                <div class="bg-white border border-slate-200 rounded-3xl shadow-xs overflow-hidden">

                    <div class="bg-slate-900 text-white px-6 py-4 flex flex-wrap justify-between items-center">
                        <div>
                            <span
                                class="bg-indigo-600 text-white font-mono text-[9px] font-black uppercase px-2 py-0.5 rounded-md tracking-wider">Semester
                                <?php echo $c_sem; ?> Core</span>
                            <h3 class="text-sm font-black tracking-wide mt-1">📚
                                <?php echo htmlspecialchars($course['course_code']); ?> :
                                <?php echo htmlspecialchars($course['title']); ?>
                            </h3>
                        </div>
                        <span
                            class="bg-white/10 text-indigo-200 text-xs font-bold px-3 py-1 rounded-xl border border-white/10 mt-2 sm:mt-0">
                            Total: <?php echo $total_students; ?> Students Enrolled
                        </span>
                    </div>

                    <div class="p-6 grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

                        <div class="space-y-4">

                            <div
                                class="bg-slate-50 border border-slate-200 rounded-2xl overflow-hidden transition-all duration-300 shadow-xs">
                                <button
                                    onclick="toggleSection('enrolled-content-<?php echo $c_id; ?>', 'arrow-enrolled-<?php echo $c_id; ?>')"
                                    class="w-full p-4 flex justify-between items-center text-left focus:outline-hidden cursor-pointer hover:bg-slate-100/80 transition-colors">
                                    <div>
                                        <h4
                                            class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                                            🎓 Enrolled Roster
                                            <span
                                                class="bg-slate-600 text-white font-mono text-[10px] px-1.5 py-0.5 rounded-full font-black"><?php echo $total_students; ?></span>
                                        </h4>
                                        <p class="text-[10px] text-slate-400 mt-0.5">সব এনরোল করা স্টুডেন্টদের লিস্ট</p>
                                    </div>
                                    <span id="arrow-enrolled-<?php echo $c_id; ?>"
                                        class="text-slate-500 transition-transform duration-300 transform">▼</span>
                                </button>

                                <div id="enrolled-content-<?php echo $c_id; ?>"
                                    class="hidden px-4 pb-4 border-t border-slate-200 bg-white/50 pt-3 max-h-[200px] overflow-y-auto space-y-2">
                                    <?php if ($total_students > 0) {
                                        mysqli_data_seek($course_students, 0);
                                        while ($st = $course_students->fetch_assoc()) { ?>
                                            <div
                                                class="p-2 bg-white border border-slate-100 rounded-xl flex justify-between items-center shadow-2xs">
                                                <span
                                                    class="font-semibold text-slate-700 text-xs"><?php echo htmlspecialchars($st['name']); ?></span>
                                                <span
                                                    class="font-mono text-[10px] text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded"><?php echo htmlspecialchars($st['id_no']); ?></span>
                                            </div>
                                        <?php }
                                    } else { ?>
                                        <p class="text-[11px] text-slate-400 italic text-center py-2">No students active.</p>
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
                                            class="text-xs font-bold text-emerald-700 uppercase tracking-wider flex items-center gap-1.5">
                                            📝 Attendance Log Roll
                                            <span
                                                class="bg-emerald-600 text-white font-mono text-[10px] px-1.5 py-0.5 rounded-full font-black"><?php echo $total_attendance; ?></span>
                                        </h4>
                                        <p class="text-[10px] text-slate-400 mt-0.5">উপস্থিত ছাত্রদের তালিকা</p>
                                    </div>
                                    <span id="arrow-att-<?php echo $c_id; ?>"
                                        class="text-emerald-600 transition-transform duration-300 transform">▼</span>
                                </button>

                                <div id="attendance-content-<?php echo $c_id; ?>"
                                    class="hidden px-4 pb-4 border-t border-emerald-100 bg-white/50 pt-3 max-h-[200px] overflow-y-auto space-y-2">
                                    <?php if ($total_attendance > 0) {
                                        mysqli_data_seek($attendance_students, 0);
                                        while ($att = $attendance_students->fetch_assoc()) { ?>
                                            <div
                                                class="p-2.5 bg-white border border-emerald-100 rounded-xl flex justify-between items-center text-xs shadow-2xs">
                                                <div>
                                                    <p class="font-bold text-slate-800 text-[11px]">
                                                        <?php echo htmlspecialchars($att['name']); ?>
                                                    </p>
                                                    <p class="text-[9px] text-slate-400">ID:
                                                        <?php echo htmlspecialchars($att['id_no']); ?>
                                                    </p>
                                                </div>
                                                <span
                                                    class="bg-emerald-100 text-emerald-800 font-mono font-bold text-[10px] px-2 py-0.5 rounded-md border border-emerald-200">
                                                    <?php echo $att['present_days']; ?> Days
                                                </span>
                                            </div>
                                        <?php }
                                    } else { ?>
                                        <p
                                            class="text-[11px] text-slate-400 italic py-3 text-center bg-white rounded-xl border border-dashed border-emerald-200">
                                            আজকের ক্লাসে এখনও কেউ টোকেন সাবমিট করেনি।</p>
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
                                            class="text-xs font-bold text-amber-700 uppercase tracking-wider flex items-center gap-1.5">
                                            🗄️ Previous MCQ Archive
                                            <span id="archive-count-<?php echo $c_id; ?>"
                                                class="bg-amber-600 text-white font-mono text-[10px] px-1.5 py-0.5 rounded-full font-black"><?php echo $total_old_mcqs; ?></span>
                                        </h4>
                                        <p class="text-[10px] text-slate-400 mt-0.5">পুরনো MCQ গুলো PDF করে সেভ করুন,
                                            তারপর ডিলিট করুন</p>
                                    </div>
                                    <span id="arrow-archive-<?php echo $c_id; ?>"
                                        class="text-amber-600 transition-transform duration-300 transform">▼</span>
                                </button>

                                <div id="archive-content-<?php echo $c_id; ?>"
                                    class="hidden px-4 pb-4 border-t border-amber-100 bg-white/50 pt-3 space-y-2">
                                    <?php if ($total_old_mcqs > 0): ?>
                                        <p class="text-[11px] text-slate-500 mb-1">
                                            <?php echo $total_old_mcqs; ?>টি সম্পন্ন MCQ পাওয়া গেছে। প্রথমে PDF
                                            ডাউনলোড করুন — এরপর নিচের ডিলিট বাটন সক্রিয় হবে।
                                        </p>
                                        <a href="generate_mcq_pdf.php?course_id=<?php echo $c_id; ?>" target="_blank"
                                            onclick="enableMcqDelete(<?php echo $c_id; ?>)"
                                            class="block text-center bg-amber-600 text-white font-bold py-2 rounded-xl text-xs hover:bg-amber-700 transition shadow-xs cursor-pointer">
                                            📥 Download PDF (Question + Correct Answer)
                                        </a>
                                        <button id="delete-mcq-btn-<?php echo $c_id; ?>"
                                            onclick="deleteOldMcqs(<?php echo $c_id; ?>)" disabled
                                            class="w-full bg-slate-300 text-slate-500 font-bold py-2 rounded-xl text-xs cursor-not-allowed transition shadow-xs">
                                            🗑️ Delete Old MCQs (আগে PDF ডাউনলোড করুন)
                                        </button>
                                    <?php else: ?>
                                        <p class="text-[11px] text-slate-400 italic text-center py-2">কোনো পুরনো MCQ
                                            নেই।</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="lg:col-span-2 space-y-4">

                            <div id="live-status-box-<?php echo $c_id; ?>"
                                class="p-4 rounded-2xl border transition-all duration-300 <?php echo $live_session ? 'bg-rose-50/80 border-rose-200 shadow-xs' : 'bg-slate-50 border-slate-200'; ?>">
                                <div class="flex flex-wrap justify-between items-center mb-1">
                                    <h5 class="text-xs font-bold text-slate-700 uppercase tracking-wide">📡 Transmission
                                        Status</h5>
                                    <span id="status-badge-<?php echo $c_id; ?>"
                                        class="text-[9px] font-mono font-black px-2 py-0.5 rounded-md <?php echo $live_session ? 'bg-rose-600 text-white animate-pulse' : 'bg-slate-300 text-slate-600'; ?>">
                                        <?php echo $live_session ? 'LIVE NOW (' . strtoupper($live_session['test_type']) . ')' : 'OFFLINE'; ?>
                                    </span>
                                </div>
                                <div id="live-details-<?php echo $c_id; ?>"
                                    class="<?php echo $live_session ? '' : 'hidden'; ?>">
                                    <p class="text-xs text-slate-700 font-semibold mt-1">Current Topic: <span
                                            class="text-rose-600 font-mono"
                                            id="live-title-text-<?php echo $c_id; ?>"><?php echo $live_session ? htmlspecialchars($live_session['title']) : ''; ?></span>
                                    </p>
                                    <button onclick="stopLiveSession(<?php echo $c_id; ?>)"
                                        class="mt-2 bg-rose-600 hover:bg-rose-700 text-white font-bold py-1 px-3 rounded-lg text-[11px] transition shadow-xs cursor-pointer">End
                                        Live Session 🛑</button>
                                </div>
                                <p id="offline-placeholder-<?php echo $c_id; ?>"
                                    class="text-[11px] text-slate-400 italic <?php echo $live_session ? 'hidden' : ''; ?>">
                                    No active live transmission generated for this course node.</p>
                            </div>

                            <div
                                class="bg-slate-100 p-1 rounded-xl flex flex-wrap sm:flex-nowrap gap-1 border border-slate-200">
                                <button onclick="switchTab(<?php echo $c_id; ?>, 'meet')"
                                    id="tab-btn-meet-<?php echo $c_id; ?>"
                                    class="flex-1 text-center py-2 text-xs font-bold rounded-lg transition-all cursor-pointer bg-slate-900 text-white shadow-xs">🟢
                                    Live Meet</button>
                                <button onclick="switchTab(<?php echo $c_id; ?>, 'mcq')"
                                    id="tab-btn-mcq-<?php echo $c_id; ?>"
                                    class="flex-1 text-center py-2 text-xs font-bold rounded-lg transition-all cursor-pointer text-slate-500 hover:text-slate-800">⚡
                                    Instant MCQ</button>
                                <button onclick="switchTab(<?php echo $c_id; ?>, 'pdf')"
                                    id="tab-btn-pdf-<?php echo $c_id; ?>"
                                    class="flex-1 text-center py-2 text-xs font-bold rounded-lg transition-all cursor-pointer text-slate-500 hover:text-slate-800">📄
                                    PDF Task</button>
                                <button onclick="switchTab(<?php echo $c_id; ?>, 'note')"
                                    id="tab-btn-note-<?php echo $c_id; ?>"
                                    class="flex-1 text-center py-2 text-xs font-bold rounded-lg transition-all cursor-pointer text-slate-500 hover:text-slate-800">📚
                                    Lecture Note</button>
                            </div>

                            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs">

                                <div id="tab-content-meet-<?php echo $c_id; ?>"
                                    class="tab-panel-<?php echo $c_id; ?> space-y-3">
                                    <div class="mb-2">
                                        <h5 class="text-xs font-black text-slate-800 uppercase tracking-wide">🟢 Launch Live
                                            Lecture Session</h5>
                                    </div>
                                    <form method="POST" action="" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <input type="hidden" name="course_id" value="<?php echo $c_id; ?>">
                                        <input type="text" name="title" placeholder="Topic: e.g., Agile Architecture"
                                            required
                                            class="border border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 p-2.5 rounded-xl text-xs bg-white transition-all outline-hidden">
                                        <input type="url" name="meet_url" placeholder="Google Meet Link URL..." required
                                            class="border border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 p-2.5 rounded-xl text-xs bg-white transition-all outline-hidden">
                                        <button type="submit" name="launch_meet"
                                            class="md:col-span-2 bg-indigo-600 text-white font-bold py-2.5 rounded-xl text-xs hover:bg-indigo-700 transition shadow-sm cursor-pointer">Generate
                                            Token & Broadcast Class</button>
                                    </form>
                                </div>

                                <div id="tab-content-mcq-<?php echo $c_id; ?>"
                                    class="tab-panel-<?php echo $c_id; ?> hidden space-y-3">
                                    <div class="mb-2">
                                        <h5 class="text-xs font-black text-slate-800 uppercase tracking-wide">⚡ Launch
                                            Instant MCQ Test Node</h5>
                                    </div>
                                    <form method="POST" action="" class="space-y-3">
                                        <input type="hidden" name="course_id" value="<?php echo $c_id; ?>">
                                        <input type="text" name="mcq_question"
                                            placeholder="Core MCQ Exam Question Statement..." required
                                            class="w-full border border-slate-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 p-2.5 rounded-xl text-xs bg-white transition-all outline-hidden">

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
                                            <input type="text" name="op_a" placeholder="Option A" required
                                                class="border border-slate-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 p-2 rounded-xl text-xs bg-white transition-all outline-hidden">
                                            <input type="text" name="op_b" placeholder="Option B" required
                                                class="border border-slate-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 p-2 rounded-xl text-xs bg-white transition-all outline-hidden">
                                            <input type="text" name="op_c" placeholder="Option C" required
                                                class="border border-slate-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 p-2 rounded-xl text-xs bg-white transition-all outline-hidden">
                                            <input type="text" name="op_d" placeholder="Option D" required
                                                class="border border-slate-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 p-2 rounded-xl text-xs bg-white transition-all outline-hidden">
                                        </div>

                                        <div
                                            class="flex items-center gap-2 bg-blue-50/50 p-2 rounded-xl border border-blue-100">
                                            <label
                                                class="text-[11px] font-bold text-blue-700 uppercase tracking-wider pl-1">Select
                                                Correct Answer:</label>
                                            <select name="correct_option" required
                                                class="flex-1 border border-slate-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 p-1.5 rounded-lg text-xs bg-white font-bold text-slate-700 outline-hidden">
                                                <option value="" disabled selected>Choose correct option...</option>
                                                <option value="A">Option A</option>
                                                <option value="B">Option B</option>
                                                <option value="C">Option C</option>
                                                <option value="D">Option D</option>
                                            </select>
                                        </div>

                                        <button type="submit" name="launch_mcq"
                                            class="w-full bg-blue-600 text-white font-bold py-2.5 rounded-xl text-xs hover:bg-blue-700 transition shadow-sm cursor-pointer">Deploy
                                            Live MCQ</button>
                                    </form>
                                </div>

                                <div id="tab-content-pdf-<?php echo $c_id; ?>"
                                    class="tab-panel-<?php echo $c_id; ?> hidden space-y-3">
                                    <div class="mb-2">
                                        <h5 class="text-xs font-black text-slate-800 uppercase tracking-wide">📄 Launch PDF
                                            Assignment Node</h5>
                                    </div>
                                    <form method="POST" enctype="multipart/form-data" action="" class="space-y-3">
                                        <input type="hidden" name="course_id" value="<?php echo $c_id; ?>">
                                        <input type="text" name="pdf_title"
                                            placeholder="Assignment Title (e.g., Mid Term Written Exam)" required
                                            class="w-full border border-slate-200 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 p-2.5 rounded-xl text-xs bg-white transition-all outline-hidden">
                                        <input type="file" name="question_file" accept="application/pdf" required
                                            class="text-xs text-slate-500 block w-full file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-[11px] file:font-bold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 file:cursor-pointer">
                                        <button type="submit" name="launch_pdf"
                                            class="w-full bg-emerald-600 text-white font-bold py-2.5 rounded-xl text-xs hover:bg-emerald-700 transition shadow-sm cursor-pointer">Deploy
                                            PDF Question</button>
                                    </form>
                                </div>

                                <div id="tab-content-note-<?php echo $c_id; ?>"
                                    class="tab-panel-<?php echo $c_id; ?> hidden space-y-3">
                                    <div class="mb-2">
                                        <h5 class="text-xs font-black text-slate-800 uppercase tracking-wide">📚 Quick
                                            Share: Class Lecture Note</h5>
                                    </div>
                                    <form method="POST" enctype="multipart/form-data" action=""
                                        class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <input type="hidden" name="course_id" value="<?php echo $c_id; ?>">
                                        <input type="text" name="note_title"
                                            placeholder="Lecture Title: e.g., Chapter 03 Handnote" required
                                            class="border border-slate-200 focus:border-purple-500 focus:ring-2 focus:ring-purple-100 p-2.5 rounded-xl text-xs bg-white transition-all outline-hidden">
                                        <input type="file" name="note_file" accept=".pdf,.docx,.doc,.png,.jpg,.jpeg"
                                            required
                                            class="text-xs text-slate-500 block w-full self-center file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-[11px] file:font-bold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 file:cursor-pointer">
                                        <button type="submit" name="upload_note"
                                            class="md:col-span-2 bg-purple-600 text-white font-bold py-2.5 rounded-xl text-xs hover:bg-purple-700 transition shadow-sm cursor-pointer">Upload
                                            Note & Broadcast to Nodes 🚀</button>
                                    </form>
                                </div>

                            </div>

                            <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl">
                                <h5 class="text-xs font-bold text-slate-700 mb-2 uppercase tracking-wide">📥 Received
                                    Answers Log</h5>
                                <div class="space-y-1.5 max-h-[140px] overflow-y-auto pr-1">
                                    <?php if ($total_submissions > 0) {
                                        mysqli_data_seek($course_submissions, 0);
                                        while ($sub = $course_submissions->fetch_assoc()) { ?>
                                            <div
                                                class="p-2 bg-white border border-slate-200 rounded-xl flex items-center justify-between text-xs shadow-2xs">
                                                <div>
                                                    <p class="font-bold text-slate-800 text-[11px]">
                                                        <?php echo htmlspecialchars($sub['student_name']); ?>
                                                    </p>
                                                    <span
                                                        class="text-[9px] text-indigo-600 font-bold uppercase tracking-wider"><?php echo htmlspecialchars($sub['test_type']); ?></span>
                                                </div>
                                                <div
                                                    class="font-mono text-[10px] text-slate-500 bg-slate-50 border px-2 py-0.5 rounded-md max-w-[180px] truncate">
                                                    <?php echo !empty($sub['pdf_submission']) ? "<a href='" . htmlspecialchars($sub['pdf_submission']) . "' target='_blank' class='text-blue-600 font-bold hover:underline'>View PDF</a>" : htmlspecialchars($sub['answers']); ?>
                                                </div>
                                            </div>
                                        <?php }
                                    } else { ?>
                                        <p class="text-[11px] text-slate-400 italic text-center py-2">No submission scripts
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
        // ১. লাইভ সেশন এন্ড করার AJAX মেকানিজম
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
                        statusBadge.className = "text-[9px] font-mono font-black px-2 py-0.5 rounded-md bg-slate-300 text-slate-600";
                    }
                    if (liveDetails) liveDetails.classList.add('hidden');
                    if (placeholder) placeholder.classList.remove('hidden');
                    if (statusBox) {
                        statusBox.className = "p-4 rounded-2xl border transition-all duration-300 bg-slate-50 border-slate-200";
                    }
                } else {
                    alert("Error: " + result.message);
                }
            } catch (error) {
                console.error("AJAX Error:", error);
                alert("Something went wrong while ending the session.");
            }
        }

        // ২. ট্যাব সুইচিং কোড লজিক
        function switchTab(courseId, tabName) {
            const panels = document.querySelectorAll(`.tab-panel-${courseId}`);
            panels.forEach(panel => panel.classList.add('hidden'));

            const targetPanel = document.getElementById(`tab-content-${tabName}-${courseId}`);
            if (targetPanel) targetPanel.classList.remove('hidden');

            const tabButtons = ['meet', 'mcq', 'pdf', 'note'];
            tabButtons.forEach(btn => {
                const btnEl = document.getElementById(`tab-btn-${btn}-${courseId}`);
                if (btnEl) {
                    btnEl.className = "flex-1 text-center py-2 text-xs font-bold rounded-lg transition-all cursor-pointer text-slate-500 hover:text-slate-800";
                }
            });

            const activeBtn = document.getElementById(`tab-btn-${tabName}-${courseId}`);
            if (activeBtn) {
                activeBtn.className = "flex-1 text-center py-2 text-xs font-bold rounded-lg transition-all cursor-pointer bg-slate-900 text-white shadow-sm";
            }
        }

        // ৪. PDF ডাউনলোড করলে Delete বাটন সক্রিয় (enable) হবে - দুই ধাপের নিরাপদ ডিলিট ফ্লো
        function enableMcqDelete(courseId) {
            const btn = document.getElementById(`delete-mcq-btn-${courseId}`);
            if (btn) {
                btn.disabled = false;
                btn.classList.remove('bg-slate-300', 'text-slate-500', 'cursor-not-allowed');
                btn.classList.add('bg-rose-600', 'text-white', 'hover:bg-rose-700', 'cursor-pointer');
                btn.innerText = "🗑️ Delete Old MCQs Now (Permanent)";
            }
        }

        // ৫. পুরনো MCQ ডিলিট করার AJAX মেকানিজম (শুধু PDF ডাউনলোড হওয়ার পরই সক্রিয় হয়)
        async function deleteOldMcqs(courseId) {
            if (!confirm("PDF ডাউনলোড নিশ্চিত হওয়ার পরই এই কোর্সের সব পুরনো MCQ স্থায়ীভাবে ডিলিট হয়ে যাবে। আপনি কি নিশ্চিত?")) return;

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
                        archiveContent.innerHTML = `<p class="text-[11px] text-slate-400 italic text-center py-2">কোনো পুরনো MCQ নেই।</p>`;
                    }
                } else {
                    alert("Error: " + result.message);
                }
            } catch (error) {
                console.error("AJAX Error:", error);
                alert("Something went wrong while deleting.");
            }
        }

        // ৬. জেনারালাইজড সেকশন টগল মেকানিজম (Enrolled, Attendance ও Archive - সবার জন্য একই ফাংশন)
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
    </script>
</body>

</html>