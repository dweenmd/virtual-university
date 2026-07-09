<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$message = "";

// অ্যাডমিন অ্যাকশন: লাইভ ক্লাস বন্ধ করা
if (isset($_POST['stop_live'])) {
    $course_id = intval($_POST['course_id']);
    $sql_stop = "UPDATE online_class_tests SET status='completed' WHERE course_id='$course_id' AND status='LIVE NOW'";
    if ($conn->query($sql_stop)) {
        $message = "🔴 Live Session Terminated successfully!";
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
    } else {
        $sql = "INSERT INTO users (name, email, password, id_no, role, semester) VALUES ('$name', '$email', '$password', '$id_no', 'student', '$semester')";
        if ($conn->query($sql))
            $message = "🟢 Student '$name' enrolled successfully!";
    }
}

// কোর্স ক্রিয়েশন অ্যাকশন
if (isset($_POST['add_course'])) {
    $course_code = mysqli_real_escape_string($conn, $_POST['course_code']);
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $semester = intval($_POST['semester']);
    $teacher_id = intval($_POST['teacher_id']);

    $sql = "INSERT INTO courses (course_code, title, semester, teacher_id) VALUES ('$course_code', '$title', '$semester', '$teacher_id')";
    if ($conn->query($sql))
        $message = "🟢 Course '$course_code' deployed successfully!";
}

// নতুন অ্যাকশন: স্টুডেন্ট সেমিস্টার GPA এন্ট্রি ও আপডেট লজিক
if (isset($_POST['submit_gpa'])) {
    $student_id = intval($_POST['student_id']);
    $semester_no = intval($_POST['semester_no']);
    $gpa = floatval($_POST['gpa']);

    // ON DUPLICATE KEY UPDATE ব্যবহারের ফলে একই স্টুডেন্টের নির্দিষ্ট সেমিস্টারের রেজাল্ট বারবার এন্ট্রি না হয়ে আপডেট হবে
    $stmt = $conn->prepare("INSERT INTO student_cgpa_records (student_id, semester_no, gpa) VALUES (?, ?, ?) 
                            ON DUPLICATE KEY UPDATE gpa = ?");
    $stmt->bind_param("iidd", $student_id, $semester_no, $gpa, $gpa);

    if ($stmt->execute()) {
        $message = "🟢 GPA Updated Successfully for Student ID: $student_id (Semester $semester_no)!";
    } else {
        $message = "❌ Error updating GPA: " . $conn->error;
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
    <title>Matrix Admin Panel</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>

<body class="bg-slate-50 text-slate-900 font-sans antialiased selection:bg-indigo-500 selection:text-white">

    <div class="min-h-screen flex flex-col md:flex-row">

        <aside class="w-full md:w-64 bg-slate-900 text-slate-400 p-6 flex flex-col justify-between shadow-xl">
            <div class="space-y-8">
                <div class="flex items-center space-x-3 text-white">
                    <div
                        class="h-9 w-9 bg-gradient-to-tr from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center text-lg font-bold shadow-md shadow-indigo-500/20">
                        V
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-white tracking-wide">Virtual Varsity</h2>
                        <span class="text-[10px] text-indigo-400 font-mono tracking-widest uppercase">Admin
                            Matrix</span>
                    </div>
                </div>

                <nav class="space-y-1.5">
                    <a href="#"
                        class="flex items-center space-x-3 px-4 py-2.5 rounded-xl bg-slate-800 text-white text-xs font-semibold transition">
                        <span>📊</span> <span>Core Dashboard</span>
                    </a>
                    <a href="#"
                        class="flex items-center space-x-3 px-4 py-2.5 rounded-xl hover:bg-slate-800/60 hover:text-white text-xs font-medium transition">
                        <span>🎓</span> <span>Students Directory</span>
                    </a>
                    <a href="#"
                        class="flex items-center space-x-3 px-4 py-2.5 rounded-xl hover:bg-slate-800/60 hover:text-white text-xs font-medium transition">
                        <span>📚</span> <span>Course Allocation</span>
                    </a>
                </nav>
            </div>

            <div class="pt-6 border-t border-slate-800">
                <a href="index.php"
                    class="flex items-center justify-center space-x-2 w-full bg-red-500/10 hover:bg-red-500 text-red-400 hover:text-white text-xs font-bold py-2.5 rounded-xl transition border border-red-500/20 shadow-sm">
                    <span>🚪</span> <span>Exit Admin Panel</span>
                </a>
            </div>
        </aside>

        <main class="flex-1 p-6 md:p-10 max-w-7xl mx-auto w-full space-y-8">

            <div class="flex justify-between items-center border-b border-slate-200 pb-5">
                <div>
                    <h1 class="text-xl font-black text-slate-900 tracking-tight">System Control Panel</h1>
                    <p class="text-xs text-slate-400 mt-0.5">ম্যানেজ করুন আপনার ক্যাম্পাসের লাইভ সেশন, কোর্স কারিকুলাম ও
                        ইনভেন্টরি।</p>
                </div>
                <div class="text-right hidden sm:block">
                    <span
                        class="text-xs font-mono bg-slate-200/60 text-slate-700 px-3 py-1.5 rounded-xl border border-slate-300/40">Role:
                        Central_Admin</span>
                </div>
            </div>

            <?php if ($message) { ?>
                <div
                    class="p-4 rounded-2xl bg-white border border-slate-200 shadow-xl shadow-slate-100/40 text-xs font-bold text-slate-800 flex items-center space-x-2 animate-fade-in">
                    <span>🔔</span> <span><?php echo $message; ?></span>
                </div>
            <?php } ?>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                <div
                    class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-md shadow-slate-100/50 flex items-center space-x-4">
                    <div class="p-3 bg-indigo-50 text-indigo-600 rounded-xl text-xl">👥</div>
                    <div>
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total
                            Students</span>
                        <span class="text-lg font-black text-slate-800"><?php echo $count_students; ?></span>
                    </div>
                </div>
                <div
                    class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-md shadow-slate-100/50 flex items-center space-x-4">
                    <div class="p-3 bg-emerald-50 text-emerald-600 rounded-xl text-xl">👨‍🏫</div>
                    <div>
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Active
                            Faculty</span>
                        <span class="text-lg font-black text-slate-800"><?php echo $count_teachers; ?></span>
                    </div>
                </div>
                <div
                    class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-md shadow-slate-100/50 flex items-center space-x-4 <?php echo $count_live > 0 ? 'ring-2 ring-red-500/20' : ''; ?>">
                    <div
                        class="p-3 bg-red-50 text-red-500 rounded-xl text-xl <?php echo $count_live > 0 ? 'animate-pulse' : ''; ?>">
                        📡</div>
                    <div>
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Live Rooms
                            Now</span>
                        <span class="text-lg font-black text-slate-800 flex items-center space-x-1.5">
                            <span><?php echo $count_live; ?></span>
                            <?php if ($count_live > 0)
                                echo '<span class="h-2 w-2 rounded-full bg-red-500 animate-ping"></span>'; ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-md shadow-slate-100/50 space-y-5">
                <div class="flex items-center space-x-3">
                    <div class="h-8 w-8 bg-purple-50 rounded-lg flex items-center justify-center text-xs">🎓</div>
                    <div>
                        <h3 class="text-xs font-black uppercase text-purple-600 tracking-wider">Academic Performance
                            Node</h3>
                        <p class="text-[10px] text-slate-400">সেমিস্টার ভিত্তিক স্টুডেন্ট GPA আপডেট বা ইনসার্ট করুন</p>
                    </div>
                </div>
                <form method="POST" action="" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Student SQL ID</label>
                        <input type="number" name="student_id" required placeholder="e.g., 5"
                            class="w-full border border-slate-200 p-2.5 rounded-xl text-xs bg-slate-50 focus:bg-white focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 outline-none transition">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Target Semester</label>
                        <select name="semester_no" required
                            class="w-full border border-slate-200 p-2.5 rounded-xl text-xs bg-slate-50 outline-none focus:bg-white focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 transition">
                            <?php for ($i = 1; $i <= 8; $i++)
                                echo "<option value='$i'>Semester $i</option>"; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Earned GPA</label>
                        <input type="number" step="0.01" min="0.00" max="4.00" name="gpa" required
                            placeholder="e.g., 3.85"
                            class="w-full border border-slate-200 p-2.5 rounded-xl text-xs bg-slate-50 focus:bg-white focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 outline-none transition">
                    </div>
                    <button type="submit" name="submit_gpa"
                        class="w-full bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 text-white font-bold py-2.5 rounded-xl text-xs transition shadow-md shadow-purple-500/10 cursor-pointer">
                        Publish Semester Result
                    </button>
                </form>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">

                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-md shadow-slate-100/50 space-y-5">
                    <div class="flex items-center space-x-3">
                        <div class="h-8 w-8 bg-indigo-50 rounded-lg flex items-center justify-center text-xs">👤</div>
                        <div>
                            <h3 class="text-xs font-black uppercase text-indigo-600 tracking-wider">Student Matrix
                                Registration</h3>
                            <p class="text-[10px] text-slate-400">নতুন ছাত্র ডেটাবেজে সাবমিট করুন</p>
                        </div>
                    </div>
                    <form method="POST" action="" class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Full Name</label>
                            <input type="text" name="name" required placeholder="e.g., Kawser Ahmed Ratul"
                                class="w-full border border-slate-200 p-2.5 rounded-xl text-xs bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition">
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Institutional
                                    Email</label>
                                <input type="email" name="email" required placeholder="student@varsity.edu"
                                    class="w-full border border-slate-200 p-2.5 rounded-xl text-xs bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Student ID
                                    No</label>
                                <input type="text" name="id_no" required placeholder="e.g., CSE-2026-01"
                                    class="w-full border border-slate-200 p-2.5 rounded-xl text-xs bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label
                                    class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Password</label>
                                <input type="password" name="password" required placeholder="••••••••"
                                    class="w-full border border-slate-200 p-2.5 rounded-xl text-xs bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Active
                                    Semester</label>
                                <select name="semester" required
                                    class="w-full border border-slate-200 p-2.5 rounded-xl text-xs bg-slate-50 outline-none focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition">
                                    <?php for ($i = 1; $i <= 8; $i++)
                                        echo "<option value='$i'>{$i}th Semester</option>"; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" name="add_student"
                            class="w-full bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 text-white font-bold py-2.5 rounded-xl text-xs transition shadow-md shadow-indigo-500/10 cursor-pointer">
                            Enroll Student Node
                        </button>
                    </form>
                </div>

                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-md shadow-slate-100/50 space-y-5">
                    <div class="flex items-center space-x-3">
                        <div class="h-8 w-8 bg-emerald-50 rounded-lg flex items-center justify-center text-xs">📚</div>
                        <div>
                            <h3 class="text-xs font-black uppercase text-emerald-600 tracking-wider">Course Curriculum
                                Node</h3>
                            <p class="text-[10px] text-slate-400">নতুন কোর্স এবং শিক্ষক ম্যাপিং করুন</p>
                        </div>
                    </div>
                    <form method="POST" action="" class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Course
                                    Code</label>
                                <input type="text" name="course_code" required placeholder="e.g., CSE-3112"
                                    class="w-full border border-slate-200 p-2.5 rounded-xl text-xs bg-slate-50 focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Target
                                    Semester</label>
                                <select name="semester" required
                                    class="w-full border border-slate-200 p-2.5 rounded-xl text-xs bg-slate-50 outline-none focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
                                    <?php for ($i = 1; $i <= 8; $i++)
                                        echo "<option value='$i'>Semester $i</option>"; ?>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Course Curriculum
                                Title</label>
                            <input type="text" name="title" required placeholder="e.g., Software Engineering Lab"
                                class="w-full border border-slate-200 p-2.5 rounded-xl text-xs bg-slate-50 focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Assign Faculty
                                Teacher</label>
                            <select name="teacher_id" required
                                class="w-full border border-slate-200 p-2.5 rounded-xl text-xs bg-slate-50 outline-none focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
                                <option value="">-- Select a Faculty Member --</option>
                                <?php
                                if ($teachers_list->num_rows > 0) {
                                    while ($t = $teachers_list->fetch_assoc()) {
                                        echo "<option value='" . $t['id'] . "'>👨‍🏫 " . $t['name'] . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <button type="submit" name="add_course"
                            class="w-full bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-700 hover:to-emerald-800 text-white font-bold py-2.5 rounded-xl text-xs transition shadow-md shadow-emerald-500/10 cursor-pointer">
                            Deploy Course Blueprint
                        </button>
                    </form>
                </div>

            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-md shadow-slate-100/50 overflow-hidden">
                <div class="p-5 border-b border-slate-100 bg-slate-50/50">
                    <h4 class="text-xs font-black uppercase text-slate-500 tracking-wider">🖥️ Active Course Allocation
                        Hub</h4>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr
                                class="bg-slate-50 border-b border-slate-200 text-slate-400 font-bold uppercase tracking-wider text-[10px]">
                                <th class="p-4">Course Code</th>
                                <th class="p-4">Course Title</th>
                                <th class="p-4">Semester map</th>
                                <th class="p-4">Faculty Member</th>
                                <th class="p-4 text-center">Live Monitoring / Command</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php
                            if ($courses_list->num_rows > 0) {
                                while ($c = $courses_list->fetch_assoc()) {
                                    $row_live_class = $c['is_live'] > 0 ? 'bg-red-50/30 border-l-4 border-l-red-500' : '';
                                    ?>
                                    <tr class="hover:bg-slate-50/60 transition <?php echo $row_live_class; ?>">
                                        <td class="p-4 font-mono font-bold text-indigo-600"><?php echo $c['course_code']; ?>
                                        </td>
                                        <td class="p-4 font-semibold text-slate-800"><?php echo $c['title']; ?></td>
                                        <td class="p-4">
                                            <span
                                                class="bg-slate-100 border border-slate-200 text-slate-600 font-bold px-2 py-0.5 rounded-md text-[10px]">Sem
                                                <?php echo $c['semester']; ?></span>
                                        </td>
                                        <td class="p-4 text-slate-600 font-medium">👨‍🏫
                                            <?php echo !empty($c['teacher_name']) ? $c['teacher_name'] : '<span class="text-red-400 italic">Unassigned</span>'; ?>
                                        </td>
                                        <td class="p-4 text-center">
                                            <?php if ($c['is_live'] > 0 && !empty($c['start_timestamp'])) { ?>
                                                <div class="flex items-center justify-center space-x-3">
                                                    <span data-start="<?php echo $c['start_timestamp']; ?>"
                                                        class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-black bg-emerald-100 text-emerald-700 ring-1 ring-emerald-600/10 animate-pulse run-live-badge">
                                                        🟢 ON AIR
                                                    </span>
                                                    <form method="POST" action=""
                                                        onsubmit="return confirm('Force terminate this faculty live stream?');">
                                                        <input type="hidden" name="course_id" value="<?php echo $c['id']; ?>">
                                                        <button type="submit" name="stop_live"
                                                            class="bg-red-500 hover:bg-red-600 text-white text-[10px] font-bold px-2.5 py-1 rounded-lg transition shadow-sm cursor-pointer">
                                                            Terminate
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php } else { ?>
                                                <span class="text-slate-400 text-[11px] font-medium">Idle Mode</span>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                    <?php
                                }
                            } else {
                                echo "<tr><td colspan='5' class='text-center p-8 text-slate-400 italic'>No data mapping loops registered yet.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

    <script>
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
        });
    </script>
</body>

</html>