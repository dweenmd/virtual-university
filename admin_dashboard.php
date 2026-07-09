<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// এই রিকোয়েস্টটা AJAX (fetch) থেকে এসেছে কিনা চেক করা - এসে থাকলে HTML না পাঠিয়ে শুধু JSON পাঠানো হবে
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

$message = "";

// অ্যাডমিন অ্যাকশন: লাইভ ক্লাস বন্ধ করা
if (isset($_POST['stop_live'])) {
    $course_id = intval($_POST['course_id']);
    $sql_stop = "UPDATE online_class_tests SET status='completed' WHERE course_id='$course_id' AND status='LIVE NOW'";
    if ($conn->query($sql_stop)) {
        $message = "🔴 Live Session Terminated successfully!";
        if ($is_ajax) {
            echo json_encode(['status' => 'success', 'message' => $message, 'course_id' => $course_id]);
            exit();
        }
    } elseif ($is_ajax) {
        echo json_encode(['status' => 'error', 'message' => 'Could not terminate the session.']);
        exit();
    }
}

// স্টুডেন্ট এনরোলমেন্ট অ্যাকশন
if (isset($_POST['add_student'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $id_no = mysqli_real_escape_string($conn, $_POST['id_no']);
    $semester = intval($_POST['semester']);

    $check = $conn->query("SELECT id FROM users WHERE email='$email'");
    if ($check->num_rows > 0) {
        $message = "❌ Error: Email already registered!";
        if ($is_ajax) {
            echo json_encode(['status' => 'error', 'message' => $message]);
            exit();
        }
    } else {
        $sql = "INSERT INTO users (name, email, password, id_no, role, semester) VALUES ('$name', '$email', '$password', '$id_no', 'student', '$semester')";
        if ($conn->query($sql)) {
            $message = "🟢 Student '$name' enrolled successfully!";
            if ($is_ajax) {
                echo json_encode(['status' => 'success', 'message' => $message]);
                exit();
            }
        } elseif ($is_ajax) {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
            exit();
        }
    }
}

// কোর্স ক্রিয়েশন অ্যাকশন
if (isset($_POST['add_course'])) {
    $course_code = mysqli_real_escape_string($conn, $_POST['course_code']);
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $semester = intval($_POST['semester']);
    $teacher_id = intval($_POST['teacher_id']);

    $sql = "INSERT INTO courses (course_code, title, semester, teacher_id) VALUES ('$course_code', '$title', '$semester', '$teacher_id')";
    if ($conn->query($sql)) {
        $message = "🟢 Course '$course_code' deployed successfully!";
        if ($is_ajax) {
            $teacher_name = '';
            $t_lookup = $conn->query("SELECT name FROM users WHERE id='$teacher_id'");
            if ($t_lookup && $t_lookup->num_rows > 0) {
                $teacher_name = $t_lookup->fetch_assoc()['name'];
            }
            echo json_encode([
                'status' => 'success',
                'message' => $message,
                'course' => [
                    'course_code' => htmlspecialchars($course_code),
                    'title' => htmlspecialchars($title),
                    'semester' => $semester,
                    'teacher_name' => htmlspecialchars($teacher_name),
                ]
            ]);
            exit();
        }
    } elseif ($is_ajax) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
        exit();
    }
}

// নতুন অ্যাকশন: স্টুডেন্ট সেমিস্টার GPA এন্ট্রি ও আপডেট লজিক
if (isset($_POST['submit_gpa'])) {
    $student_id = intval($_POST['student_id']);
    $semester_no = intval($_POST['semester_no']);
    $gpa = floatval($_POST['gpa']);

    // ON DUPLICATE KEY UPDATE ব্যবহারের ফলে একই স্টুডেন্টের নির্দিষ্ট সেমিস্টারের রেজাল্ট বারবার এন্ট্রি না হয়ে আপডেট হবে
    $stmt = $conn->prepare("INSERT INTO student_cgpa_records (student_id, semester_no, gpa) VALUES (?, ?, ?) 
                            ON DUPLICATE KEY UPDATE gpa = ?");
    $stmt->bind_param("iidd", $student_id, $semester_no, $gpa, $gpa);

    if ($stmt->execute()) {
        $message = "🟢 GPA Updated Successfully for Student ID: $student_id (Semester $semester_no)!";
        if ($is_ajax) {
            echo json_encode(['status' => 'success', 'message' => $message]);
            exit();
        }
    } else {
        $message = "❌ Error updating GPA: " . $conn->error;
        if ($is_ajax) {
            echo json_encode(['status' => 'error', 'message' => $message]);
            exit();
        }
    }
    $stmt->close();
}

// কাউন্টার ডেটা আনা (ড্যাশবোর্ড স্ট্যাটস এর জন্য)
$count_students = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role='student'")->fetch_assoc()['total'];
$count_teachers = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role='teacher'")->fetch_assoc()['total'];
$count_live = $conn->query("SELECT COUNT(*) AS total FROM online_class_tests WHERE status='LIVE NOW'")->fetch_assoc()['total'];

$teachers_list = $conn->query("SELECT id, name FROM users WHERE role='teacher' ORDER BY name ASC");

// এখানে সাবকোয়েরিতে UNIX_TIMESTAMP(oct.created_at) নিয়ে আসা হয়েছে ডাইনামিক টাইমিং ট্র্যাকিংয়ের জন্য
$courses_list = $conn->query("
    SELECT c.*, u.name AS teacher_name,
    (SELECT COUNT(*) FROM online_class_tests oct WHERE oct.course_id = c.id AND oct.status = 'LIVE NOW') AS is_live,
    (SELECT UNIX_TIMESTAMP(oct.created_at) FROM online_class_tests oct WHERE oct.course_id = c.id AND oct.status = 'LIVE NOW' LIMIT 1) AS start_timestamp
    FROM courses c 
    LEFT JOIN users u ON c.teacher_id = u.id 
    ORDER BY c.semester ASC, c.course_code ASC
");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Matrix Admin Panel — Virtual Varsity</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --bg-app: #f3f4f8;
            --bg-sidebar: #0a1128;
            --bg-sidebar-hover: #16234f;
            --bg-card: #ffffff;
            --border-card: #e2e4ec;
            --text-main: #101322;
            --text-muted: #6b7089;
            --gold: #b8902e;
            --gold-soft: #f6ecd2;
            --input-bg: #f8f9fc;
            --input-border: #e2e4ec;
            --shadow-card: 0 1px 3px rgba(16, 19, 34, 0.06), 0 1px 2px rgba(16, 19, 34, 0.04);
        }

        [data-theme="dark"] {
            --bg-app: #0b0e1c;
            --bg-card: #131729;
            --border-card: #232842;
            --text-main: #eef0f8;
            --text-muted: #8b8fae;
            --gold: #d4af5a;
            --gold-soft: #241f11;
            --input-bg: #1a1f38;
            --input-border: #2a3050;
            --shadow-card: 0 1px 3px rgba(0, 0, 0, 0.3);
        }

        * {
            transition: background-color .2s ease, border-color .2s ease, color .2s ease;
        }

        body {
            background: var(--bg-app);
            color: var(--text-main);
            font-family: 'Inter', sans-serif;
        }

        .font-display {
            font-family: 'Fraunces', serif;
        }

        .card {
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            box-shadow: var(--shadow-card);
        }

        .text-main {
            color: var(--text-main);
        }

        .text-muted-c {
            color: var(--text-muted);
        }

        .input-field {
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            color: var(--text-main);
        }

        .input-field::placeholder {
            color: var(--text-muted);
        }

        .input-field:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(184, 144, 46, 0.15);
        }

        .gold-accent {
            color: var(--gold);
        }

        .gold-bg {
            background: var(--gold);
        }

        .gold-soft-bg {
            background: var(--gold-soft);
        }

        .sidebar-link {
            color: #8b90b3;
        }

        .sidebar-link:hover,
        .sidebar-link.active {
            background: var(--bg-sidebar-hover);
            color: #fff;
        }

        table.data-table thead {
            background: var(--input-bg);
        }

        table.data-table tbody tr {
            border-color: var(--border-card);
        }

        table.data-table tbody tr:hover {
            background: var(--gold-soft);
        }

        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border-card);
            border-radius: 8px;
        }

        @keyframes toast-in {
            from {
                transform: translateX(120%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes count-fade {
            from {
                opacity: 0;
                transform: translateY(4px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-count {
            animation: count-fade .5s ease both;
        }

        @media (prefers-reduced-motion: reduce) {
            * {
                animation: none !important;
                transition: none !important;
            }
        }

        .tab-btn.active {
            background: var(--bg-sidebar);
            color: #fff;
        }

        .tab-btn:not(.active) {
            color: var(--text-muted);
        }

        #mobile-sidebar-backdrop {
            backdrop-filter: blur(2px);
        }
    </style>
</head>

<body class="antialiased">

    <!-- টোস্ট নোটিফিকেশন কন্টেইনার -->
    <div id="toast-container" class="fixed top-4 right-4 z-[100] flex flex-col gap-2 w-[min(90vw,340px)]"></div>

    <!-- কাস্টম কনফার্ম মোডাল (নেটিভ confirm() এর বদলে) -->
    <div id="confirm-modal" class="fixed inset-0 z-[90] hidden items-center justify-center bg-black/50 p-4">
        <div class="card rounded-2xl p-6 max-w-sm w-full space-y-4">
            <h3 class="font-display text-base font-semibold text-main" id="confirm-modal-title">Are you sure?</h3>
            <p class="text-xs text-muted-c" id="confirm-modal-message"></p>
            <div class="flex justify-end gap-2 pt-1">
                <button onclick="closeConfirmModal()"
                    class="px-4 py-2 rounded-xl text-xs font-bold border border-current text-muted-c hover:opacity-70 transition cursor-pointer">Cancel</button>
                <button id="confirm-modal-confirm-btn"
                    class="px-4 py-2 rounded-xl text-xs font-bold bg-red-600 text-white hover:bg-red-700 transition cursor-pointer">Confirm</button>
            </div>
        </div>
    </div>

    <!-- মোবাইল সাইডবার ওভারলে -->
    <div id="mobile-sidebar-backdrop" class="fixed inset-0 bg-black/40 z-30 hidden md:hidden"
        onclick="toggleMobileSidebar(false)"></div>

    <div class="min-h-screen flex flex-col md:flex-row">

        <!-- মোবাইল টপবার -->
        <div class="md:hidden flex items-center justify-between px-4 py-3" style="background: var(--bg-sidebar);">
            <button onclick="toggleMobileSidebar(true)" class="text-white text-lg cursor-pointer"
                aria-label="Open menu">☰</button>
            <span class="text-white text-xs font-bold tracking-wide font-display">Virtual Varsity · Admin</span>
            <button onclick="toggleTheme()" id="mobile-theme-btn" class="text-white text-base cursor-pointer"
                aria-label="Toggle theme">🌙</button>
        </div>

        <aside id="sidebar"
            class="fixed md:static inset-y-0 left-0 z-40 w-64 p-6 flex flex-col justify-between shadow-xl transform -translate-x-full md:translate-x-0 transition-transform duration-300"
            style="background: var(--bg-sidebar);">
            <div class="space-y-8">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3 text-white">
                        <div class="h-9 w-9 gold-bg rounded-xl flex items-center justify-center text-lg font-bold shadow-md font-display"
                            style="color:#151020;">
                            V
                        </div>
                        <div>
                            <h2 class="text-sm font-bold text-white tracking-wide font-display">Virtual Varsity</h2>
                            <span class="text-[10px] gold-accent font-mono tracking-widest uppercase">Admin
                                Matrix</span>
                        </div>
                    </div>
                    <button onclick="toggleMobileSidebar(false)" class="md:hidden text-slate-400 cursor-pointer"
                        aria-label="Close menu">✕</button>
                </div>

                <nav class="space-y-1.5">
                    <a href="#overview" onclick="toggleMobileSidebar(false)"
                        class="sidebar-link active flex items-center space-x-3 px-4 py-2.5 rounded-xl text-xs font-semibold transition">
                        <span>📊</span> <span>Core Dashboard</span>
                    </a>
                    <a href="#student-section" onclick="toggleMobileSidebar(false)"
                        class="sidebar-link flex items-center space-x-3 px-4 py-2.5 rounded-xl text-xs font-medium transition">
                        <span>🎓</span> <span>Students Directory</span>
                    </a>
                    <a href="#course-section" onclick="toggleMobileSidebar(false)"
                        class="sidebar-link flex items-center space-x-3 px-4 py-2.5 rounded-xl text-xs font-medium transition">
                        <span>📚</span> <span>Course Allocation</span>
                    </a>
                </nav>
            </div>

            <div class="pt-6 border-t border-white/10 space-y-3">
                <button onclick="toggleTheme()" id="desktop-theme-btn"
                    class="hidden md:flex items-center justify-center space-x-2 w-full bg-white/5 hover:bg-white/10 text-slate-300 text-xs font-bold py-2.5 rounded-xl transition border border-white/10 cursor-pointer">
                    <span id="theme-icon">🌙</span> <span id="theme-label">Dark Mode</span>
                </button>
                <a href="index.php"
                    class="flex items-center justify-center space-x-2 w-full bg-red-500/10 hover:bg-red-500 text-red-400 hover:text-white text-xs font-bold py-2.5 rounded-xl transition border border-red-500/20 shadow-sm">
                    <span>🚪</span> <span>Exit Admin Panel</span>
                </a>
            </div>
        </aside>

        <main class="flex-1 p-5 md:p-10 max-w-7xl mx-auto w-full space-y-8" id="overview">

            <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-3 border-b pb-5"
                style="border-color: var(--border-card);">
                <div>
                    <h1 class="text-xl font-black tracking-tight font-display text-main">System Control Panel</h1>
                    <p class="text-xs text-muted-c mt-0.5">ম্যানেজ করুন আপনার ক্যাম্পাসের লাইভ সেশন, কোর্স কারিকুলাম ও
                        ইনভেন্টরি।</p>
                </div>
                <div class="text-left sm:text-right">
                    <span class="text-xs font-mono input-field px-3 py-1.5 rounded-xl">Role:
                        Central_Admin</span>
                </div>
            </div>

            <!-- স্ট্যাটস কার্ড -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                <div class="card p-5 rounded-2xl flex items-center space-x-4 animate-count">
                    <div class="p-3 rounded-xl text-xl gold-soft-bg gold-accent">👥</div>
                    <div>
                        <span class="block text-[10px] font-bold text-muted-c uppercase tracking-wider">Total
                            Students</span>
                        <span class="text-lg font-black text-main font-display"
                            data-countup="<?php echo (int) $count_students; ?>">0</span>
                    </div>
                </div>
                <div class="card p-5 rounded-2xl flex items-center space-x-4 animate-count"
                    style="animation-delay:.05s;">
                    <div class="p-3 rounded-xl text-xl gold-soft-bg gold-accent">👨‍🏫</div>
                    <div>
                        <span class="block text-[10px] font-bold text-muted-c uppercase tracking-wider">Active
                            Faculty</span>
                        <span class="text-lg font-black text-main font-display"
                            data-countup="<?php echo (int) $count_teachers; ?>">0</span>
                    </div>
                </div>
                <div class="card p-5 rounded-2xl flex items-center space-x-4 animate-count <?php echo $count_live > 0 ? 'ring-2 ring-red-500/30' : ''; ?>"
                    style="animation-delay:.1s;">
                    <div
                        class="p-3 bg-red-50 text-red-500 rounded-xl text-xl <?php echo $count_live > 0 ? 'animate-pulse' : ''; ?>">
                        📡</div>
                    <div>
                        <span class="block text-[10px] font-bold text-muted-c uppercase tracking-wider">Live Rooms
                            Now</span>
                        <span class="text-lg font-black text-main flex items-center space-x-1.5 font-display">
                            <span data-countup="<?php echo (int) $count_live; ?>">0</span>
                            <?php if ($count_live > 0)
                                echo '<span class="h-2 w-2 rounded-full bg-red-500 animate-ping"></span>'; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- ট্যাব সুইচার: Add Student / Add Course / Publish GPA -->
            <div class="card rounded-2xl overflow-hidden">
                <div class="flex flex-wrap border-b" style="border-color: var(--border-card);">
                    <button onclick="switchAdminTab('student')" id="tab-btn-student"
                        class="tab-btn active flex-1 min-w-[140px] text-center py-3 text-xs font-bold transition cursor-pointer">🎓
                        Enroll
                        Student</button>
                    <button onclick="switchAdminTab('course')" id="tab-btn-course"
                        class="tab-btn flex-1 min-w-[140px] text-center py-3 text-xs font-bold transition cursor-pointer">📚
                        Deploy
                        Course</button>
                    <button onclick="switchAdminTab('gpa')" id="tab-btn-gpa"
                        class="tab-btn flex-1 min-w-[140px] text-center py-3 text-xs font-bold transition cursor-pointer">🏅
                        Publish
                        GPA</button>
                </div>

                <div class="p-6">
                    <!-- স্টুডেন্ট এনরোলমেন্ট ফর্ম -->
                    <form id="form-student" class="admin-tab-panel space-y-4" data-toast="Enrolling student...">
                        <div class="mb-1">
                            <h3 class="text-xs font-black uppercase gold-accent tracking-wider font-display">Student
                                Matrix
                                Registration</h3>
                            <p class="text-[10px] text-muted-c">নতুন ছাত্র ডেটাবেজে সাবমিট করুন</p>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-muted-c uppercase mb-1">Full Name</label>
                            <input type="text" name="name" required placeholder="e.g., Kawser Ahmed Ratul"
                                class="w-full input-field p-2.5 rounded-xl text-xs transition">
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-muted-c uppercase mb-1">Institutional
                                    Email</label>
                                <input type="email" name="email" required placeholder="student@varsity.edu"
                                    class="w-full input-field p-2.5 rounded-xl text-xs transition">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-muted-c uppercase mb-1">Student ID
                                    No</label>
                                <input type="text" name="id_no" required placeholder="e.g., CSE-2026-01"
                                    class="w-full input-field p-2.5 rounded-xl text-xs transition">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-muted-c uppercase mb-1">Password</label>
                                <input type="password" name="password" required placeholder="••••••••"
                                    class="w-full input-field p-2.5 rounded-xl text-xs transition">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-muted-c uppercase mb-1">Active
                                    Semester</label>
                                <select name="semester" required
                                    class="w-full input-field p-2.5 rounded-xl text-xs transition">
                                    <?php for ($i = 1; $i <= 8; $i++)
                                        echo "<option value='$i'>{$i}th Semester</option>"; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" name="add_student"
                            class="w-full gold-bg hover:opacity-90 text-white font-bold py-2.5 rounded-xl text-xs transition shadow-md cursor-pointer"
                            style="color:#1a1508;">
                            Enroll Student Node
                        </button>
                    </form>

                    <!-- কোর্স ক্রিয়েশন ফর্ম -->
                    <form id="form-course" class="admin-tab-panel hidden space-y-4">
                        <div class="mb-1">
                            <h3 class="text-xs font-black uppercase gold-accent tracking-wider font-display">Course
                                Curriculum
                                Node</h3>
                            <p class="text-[10px] text-muted-c">নতুন কোর্স এবং শিক্ষক ম্যাপিং করুন</p>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-muted-c uppercase mb-1">Course
                                    Code</label>
                                <input type="text" name="course_code" required placeholder="e.g., CSE-3112"
                                    class="w-full input-field p-2.5 rounded-xl text-xs transition">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-muted-c uppercase mb-1">Target
                                    Semester</label>
                                <select name="semester" required
                                    class="w-full input-field p-2.5 rounded-xl text-xs transition">
                                    <?php for ($i = 1; $i <= 8; $i++)
                                        echo "<option value='$i'>Semester $i</option>"; ?>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-muted-c uppercase mb-1">Course Curriculum
                                Title</label>
                            <input type="text" name="title" required placeholder="e.g., Software Engineering Lab"
                                class="w-full input-field p-2.5 rounded-xl text-xs transition">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-muted-c uppercase mb-1">Assign Faculty
                                Teacher</label>
                            <select name="teacher_id" required
                                class="w-full input-field p-2.5 rounded-xl text-xs transition">
                                <option value="">-- Select a Faculty Member --</option>
                                <?php
                                if ($teachers_list->num_rows > 0) {
                                    while ($t = $teachers_list->fetch_assoc()) {
                                        echo "<option value='" . $t['id'] . "'>👨‍🏫 " . htmlspecialchars($t['name']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <button type="submit" name="add_course"
                            class="w-full gold-bg hover:opacity-90 font-bold py-2.5 rounded-xl text-xs transition shadow-md cursor-pointer"
                            style="color:#1a1508;">
                            Deploy Course Blueprint
                        </button>
                    </form>

                    <!-- GPA ফর্ম -->
                    <form id="form-gpa" class="admin-tab-panel hidden space-y-4">
                        <div class="mb-1">
                            <h3 class="text-xs font-black uppercase gold-accent tracking-wider font-display">Academic
                                Performance Node</h3>
                            <p class="text-[10px] text-muted-c">সেমিস্টার ভিত্তিক স্টুডেন্ট GPA আপডেট বা ইনসার্ট করুন
                            </p>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-muted-c uppercase mb-1">Student SQL
                                    ID</label>
                                <input type="number" name="student_id" required placeholder="e.g., 5"
                                    class="w-full input-field p-2.5 rounded-xl text-xs transition">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-muted-c uppercase mb-1">Target
                                    Semester</label>
                                <select name="semester_no" required
                                    class="w-full input-field p-2.5 rounded-xl text-xs transition">
                                    <?php for ($i = 1; $i <= 8; $i++)
                                        echo "<option value='$i'>Semester $i</option>"; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-muted-c uppercase mb-1">Earned
                                    GPA</label>
                                <input type="number" step="0.01" min="0.00" max="4.00" name="gpa" required
                                    placeholder="e.g., 3.85"
                                    class="w-full input-field p-2.5 rounded-xl text-xs transition">
                            </div>
                        </div>
                        <button type="submit" name="submit_gpa"
                            class="w-full gold-bg hover:opacity-90 font-bold py-2.5 rounded-xl text-xs transition shadow-md cursor-pointer"
                            style="color:#1a1508;">
                            Publish Semester Result
                        </button>
                    </form>
                </div>
            </div>

            <!-- কোর্স টেবিল -->
            <div id="course-section" class="card rounded-2xl overflow-hidden">
                <div class="p-5 border-b flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
                    style="border-color: var(--border-card);">
                    <h4 class="text-xs font-black uppercase text-muted-c tracking-wider font-display">🖥️ Active Course
                        Allocation Hub</h4>
                    <input type="text" id="course-search" placeholder="🔍 Search by code, title, or faculty..."
                        oninput="filterCourseTable()"
                        class="input-field px-3 py-2 rounded-xl text-xs w-full sm:w-72 transition">
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse data-table" id="course-table">
                        <thead>
                            <tr class="border-b text-muted-c font-bold uppercase tracking-wider text-[10px]"
                                style="border-color: var(--border-card);">
                                <th class="p-4">Course Code</th>
                                <th class="p-4">Course Title</th>
                                <th class="p-4">Semester map</th>
                                <th class="p-4">Faculty Member</th>
                                <th class="p-4 text-center">Live Monitoring / Command</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y" id="course-table-body" style="border-color: var(--border-card);">
                            <?php
                            if ($courses_list->num_rows > 0) {
                                while ($c = $courses_list->fetch_assoc()) {
                                    $row_live_class = $c['is_live'] > 0 ? 'bg-red-50/30 border-l-4 border-l-red-500' : '';
                                    $search_blob = strtolower($c['course_code'] . ' ' . $c['title'] . ' ' . ($c['teacher_name'] ?? ''));
                                    ?>
                                    <tr class="transition <?php echo $row_live_class; ?>"
                                        data-search="<?php echo htmlspecialchars($search_blob); ?>">
                                        <td class="p-4 font-mono font-bold gold-accent">
                                            <?php echo htmlspecialchars($c['course_code']); ?>
                                        </td>
                                        <td class="p-4 font-semibold text-main"><?php echo htmlspecialchars($c['title']); ?>
                                        </td>
                                        <td class="p-4">
                                            <span class="input-field font-bold px-2 py-0.5 rounded-md text-[10px]">Sem
                                                <?php echo $c['semester']; ?></span>
                                        </td>
                                        <td class="p-4 text-muted-c font-medium">👨‍🏫
                                            <?php echo !empty($c['teacher_name']) ? htmlspecialchars($c['teacher_name']) : '<span class="text-red-400 italic">Unassigned</span>'; ?>
                                        </td>
                                        <td class="p-4 text-center" data-live-cell data-course-id="<?php echo $c['id']; ?>">
                                            <?php if ($c['is_live'] > 0 && !empty($c['start_timestamp'])) { ?>
                                                <div class="flex items-center justify-center space-x-3">
                                                    <span data-start="<?php echo $c['start_timestamp']; ?>"
                                                        class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-black bg-emerald-100 text-emerald-700 ring-1 ring-emerald-600/10 animate-pulse run-live-badge">
                                                        🟢 ON AIR
                                                    </span>
                                                    <button type="button" onclick="confirmTerminate(<?php echo $c['id']; ?>)"
                                                        class="bg-red-500 hover:bg-red-600 text-white text-[10px] font-bold px-2.5 py-1 rounded-lg transition shadow-sm cursor-pointer">
                                                        Terminate
                                                    </button>
                                                </div>
                                            <?php } else { ?>
                                                <span class="text-muted-c text-[11px] font-medium">Idle Mode</span>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                    <?php
                                }
                            } else {
                                echo "<tr id='no-courses-row'><td colspan='5' class='text-center p-8 text-muted-c italic'>No data mapping loops registered yet.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                    <p id="no-search-results" class="hidden text-center p-8 text-muted-c italic text-xs">No courses
                        match your search.</p>
                </div>
            </div>

            <div id="student-section"></div>

        </main>
    </div>

    <script>
        /* ---------- থিম (ডার্ক/লাইট) টগল ---------- */
        function applyTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            const icon = theme === 'dark' ? '☀️' : '🌙';
            const label = theme === 'dark' ? 'Light Mode' : 'Dark Mode';
            document.getElementById('theme-icon').textContent = icon;
            document.getElementById('theme-label').textContent = label;
            document.getElementById('mobile-theme-btn').textContent = icon;
        }
        function toggleTheme() {
            const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
            const next = current === 'dark' ? 'light' : 'dark';
            localStorage.setItem('vv_theme', next);
            applyTheme(next);
        }
        (function initTheme() {
            const saved = localStorage.getItem('vv_theme') ||
                (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            applyTheme(saved);
        })();

        /* ---------- মোবাইল সাইডবার ---------- */
        function toggleMobileSidebar(open) {
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('mobile-sidebar-backdrop');
            if (open) {
                sidebar.classList.remove('-translate-x-full');
                backdrop.classList.remove('hidden');
            } else {
                sidebar.classList.add('-translate-x-full');
                backdrop.classList.add('hidden');
            }
        }

        /* ---------- টোস্ট নোটিফিকেশন ---------- */
        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            const borderColor = type === 'success' ? '#16a34a' : '#dc2626';
            toast.className = 'card rounded-xl p-3.5 text-xs font-semibold text-main shadow-lg';
            toast.style.cssText = `border-left: 4px solid ${borderColor}; animation: toast-in .3s ease;`;
            toast.textContent = message;
            container.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(120%)';
                toast.style.transition = 'all .3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }

        /* ---------- ট্যাব সুইচার ---------- */
        function switchAdminTab(name) {
            document.querySelectorAll('.admin-tab-panel').forEach(p => p.classList.add('hidden'));
            document.getElementById(`form-${name}`).classList.remove('hidden');
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById(`tab-btn-${name}`).classList.add('active');
        }

        /* ---------- কাউন্ট-আপ স্ট্যাটস অ্যানিমেশন ---------- */
        document.querySelectorAll('[data-countup]').forEach(el => {
            const target = parseInt(el.getAttribute('data-countup'), 10) || 0;
            let current = 0;
            const step = Math.max(1, Math.ceil(target / 30));
            const timer = setInterval(() => {
                current += step;
                if (current >= target) { current = target; clearInterval(timer); }
                el.textContent = current;
            }, 20);
        });

        /* ---------- কোর্স টেবিল সার্চ/ফিল্টার ---------- */
        function filterCourseTable() {
            const query = document.getElementById('course-search').value.trim().toLowerCase();
            const rows = document.querySelectorAll('#course-table-body tr[data-search]');
            let visibleCount = 0;
            rows.forEach(row => {
                const matches = row.getAttribute('data-search').includes(query);
                row.style.display = matches ? '' : 'none';
                if (matches) visibleCount++;
            });
            document.getElementById('no-search-results').classList.toggle('hidden', visibleCount !== 0 || rows.length === 0);
        }

        /* ---------- কাস্টম কনফার্ম মোডাল ---------- */
        let _confirmCallback = null;
        function openConfirmModal(message, onConfirm) {
            document.getElementById('confirm-modal-message').textContent = message;
            _confirmCallback = onConfirm;
            const modal = document.getElementById('confirm-modal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
        function closeConfirmModal() {
            const modal = document.getElementById('confirm-modal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            _confirmCallback = null;
        }
        document.getElementById('confirm-modal-confirm-btn').addEventListener('click', () => {
            if (_confirmCallback) _confirmCallback();
            closeConfirmModal();
        });

        /* ---------- লাইভ ক্লাস টার্মিনেট (AJAX, পেজ রিলোড ছাড়াই) ---------- */
        function confirmTerminate(courseId) {
            openConfirmModal('Force terminate this faculty live stream? Students will lose access to the live session immediately.', () => {
                terminateLive(courseId);
            });
        }

        async function terminateLive(courseId) {
            const formData = new FormData();
            formData.append('course_id', courseId);
            formData.append('stop_live', '1');
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                const result = await response.json();
                if (result.status === 'success') {
                    const cell = document.querySelector(`[data-live-cell][data-course-id="${courseId}"]`);
                    if (cell) cell.innerHTML = '<span class="text-muted-c text-[11px] font-medium">Idle Mode</span>';
                    showToast(result.message.replace(/^[^\w]*/, ''), 'success');
                } else {
                    showToast(result.message || 'Something went wrong.', 'error');
                }
            } catch (err) {
                console.error(err);
                showToast('Network error while terminating session.', 'error');
            }
        }

        /* ---------- ফর্ম AJAX সাবমিশন (Student / Course / GPA) - পেজ রিলোড ছাড়াই ---------- */
        function setupAjaxForm(formId) {
            const form = document.getElementById(formId);
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.textContent = 'Processing...';
                submitBtn.classList.add('opacity-60', 'cursor-not-allowed');

                const formData = new FormData(form);
                try {
                    const response = await fetch('', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: formData
                    });
                    const result = await response.json();
                    showToast(result.message.replace(/^[^\w]*/, ''), result.status === 'success' ? 'success' : 'error');
                    if (result.status === 'success') {
                        form.reset();
                        if (formId === 'form-course' && result.course) {
                            addCourseRowToTable(result.course);
                        }
                    }
                } catch (err) {
                    console.error(err);
                    showToast('Network error. Please try again.', 'error');
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                    submitBtn.classList.remove('opacity-60', 'cursor-not-allowed');
                }
            });
        }
        ['form-student', 'form-course', 'form-gpa'].forEach(setupAjaxForm);

        function addCourseRowToTable(course) {
            const noRow = document.getElementById('no-courses-row');
            if (noRow) noRow.remove();

            const tbody = document.getElementById('course-table-body');
            const tr = document.createElement('tr');
            const searchBlob = `${course.course_code} ${course.title} ${course.teacher_name}`.toLowerCase();
            tr.className = 'transition';
            tr.setAttribute('data-search', searchBlob);
            tr.innerHTML = `
                <td class="p-4 font-mono font-bold gold-accent">${course.course_code}</td>
                <td class="p-4 font-semibold text-main">${course.title}</td>
                <td class="p-4"><span class="input-field font-bold px-2 py-0.5 rounded-md text-[10px]">Sem ${course.semester}</span></td>
                <td class="p-4 text-muted-c font-medium">👨‍🏫 ${course.teacher_name || '<span class="text-red-400 italic">Unassigned</span>'}</td>
                <td class="p-4 text-center"><span class="text-muted-c text-[11px] font-medium">Idle Mode</span></td>
            `;
            tbody.appendChild(tr);
        }

        /* ---------- লাইভ ON AIR টাইমার ---------- */
        document.addEventListener('DOMContentLoaded', () => {
            const liveBadges = document.querySelectorAll('.run-live-badge');

            liveBadges.forEach((el) => {
                const timerSpan = document.createElement('span');
                timerSpan.className = "ml-2 font-mono text-[10px] bg-slate-900 text-white px-1.5 py-0.5 rounded-md tracking-wider font-bold";
                el.appendChild(timerSpan);

                const startTime = parseInt(el.getAttribute('data-start'), 10);

                function updateLiveTimer() {
                    const now = Math.floor(Date.now() / 1000);
                    const totalSecondsPassed = now - startTime;

                    if (totalSecondsPassed < 0) {
                        timerSpan.innerText = "00:00";
                        return;
                    }

                    const hours = Math.floor(totalSecondsPassed / 3600);
                    const minutes = Math.floor((totalSecondsPassed % 3600) / 60);
                    const seconds = totalSecondsPassed % 60;

                    if (hours > 0) {
                        timerSpan.innerText = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                    } else {
                        timerSpan.innerText = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                    }
                }

                updateLiveTimer();
                setInterval(updateLiveTimer, 1000);
            });

            <?php if ($message && !$is_ajax): ?>
                showToast(<?php echo json_encode(preg_replace('/^[^\w]*/u', '', strip_tags($message))); ?>, <?php echo strpos($message, '❌') === 0 ? "'error'" : "'success'"; ?>);
        <?php endif; ?>
        });
    </script>
</body>

</html>