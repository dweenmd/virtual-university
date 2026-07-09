<?php
// Note: session_start() must be called at the very top of the file if using $_SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db.php';
$error = "";

// Handle secure multi-role login routing logic
if (isset($_POST['login'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $entered_password = $_POST['password'];
    $selected_role = mysqli_real_escape_string($conn, $_POST['role_selection']);

    $stmt = $conn->prepare("SELECT * FROM users WHERE email=? AND role=?");
    $stmt->bind_param("ss", $email, $selected_role);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if ($entered_password === $user['password'] || password_verify($entered_password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] == 'admin')
                header("Location: admin_dashboard.php");
            if ($user['role'] == 'teacher')
                header("Location: teacher_dashboard.php");
            if ($user['role'] == 'student')
                header("Location: student_dashboard.php");
            exit();
        } else {
            $error = "Invalid credentials for the selected destination portal layer!";
        }
    } else {
        $error = "Invalid credentials for the selected destination portal layer!";
    }
    $stmt->close();
}

// 🎯 ডাটাবেজ থেকে এই মুহূর্তের সকল 'LIVE NOW' ক্লাস তুলে আনা (কোনো লিমিট ছাড়া)
$live_class_query = "SELECT t.*, c.course_code, c.title AS course_title 
                     FROM online_class_tests t 
                     JOIN courses c ON t.course_id = c.id 
                     WHERE t.status = 'LIVE NOW' 
                     ORDER BY t.id DESC";
$live_class_result = mysqli_query($conn, $live_class_query);
$total_live_classes = ($live_class_result) ? mysqli_num_rows($live_class_result) : 0;
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Virtual Varsity — LMS Portal</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --color-du-maroon: #73001a;
            --color-du-maroon-dark: #4c0011;
            --color-du-maroon-light: #8f0021;
            --color-du-gold: #c9a227;
            --color-du-gold-soft: #f4ecd2;
        }

        html {
            font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
        }

        .font-display {
            font-family: 'Fraunces', ui-serif, Georgia, serif;
        }

        .focus-ring:focus-visible {
            outline: 2px solid var(--color-du-gold);
            outline-offset: 2px;
        }

        .grain-card {
            position: relative;
            isolation: isolate;
        }

        .grain-card::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image: radial-gradient(circle at 1px 1px, rgba(255, 255, 255, 0.08) 1px, transparent 0);
            background-size: 22px 22px;
            pointer-events: none;
            border-radius: inherit;
        }

        @keyframes riseIn {
            from {
                opacity: 0;
                transform: translateY(14px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .rise-in {
            animation: riseIn 0.7s cubic-bezier(0.16, 1, 0.3, 1) both;
        }

        .rise-in-1 {
            animation-delay: 0.05s;
        }

        .rise-in-2 {
            animation-delay: 0.15s;
        }
    </style>
</head>

<body class="bg-[#fbfaf8] font-sans text-stone-800 antialiased">

    <nav
        class="bg-white/95 backdrop-blur-md sticky top-0 z-50 border-b border-stone-200/80 px-6 py-3.5 flex justify-between items-center">
        <div class="flex items-center space-x-3">
            <span
                class="text-xl w-10 h-10 flex items-center justify-center bg-gradient-to-br from-[#73001a] to-[#4c0011] rounded-xl text-white shadow-sm">🎓</span>
            <div class="flex flex-col leading-none">
                <span class="font-display text-lg font-semibold text-stone-900 tracking-tight">Virtual Varsity</span>
                <span
                    class="text-[10px] uppercase tracking-[0.18em] text-[color:var(--color-du-gold)] font-bold mt-0.5">Academic
                    Platform</span>
            </div>
        </div>
        <div class="hidden md:flex space-x-8 text-sm font-semibold text-stone-600">
            <a href="#hero" class="hover:text-[#73001a] transition-colors duration-200 focus-ring rounded">Home</a>
            <a href="#curriculum"
                class="hover:text-[#73001a] transition-colors duration-200 focus-ring rounded">Syllabus Explorer</a>
            <a href="#login-portal" class="hover:text-[#73001a] transition-colors duration-200 focus-ring rounded">LMS
                Portals</a>
        </div>
        <a href="#login-portal"
            class="bg-[#73001a] text-white px-5 py-2.5 rounded-xl text-xs font-bold hover:bg-[#4c0011] transition-all duration-200 focus-ring">Access
            Portals</a>
    </nav>

    <section id="hero"
        class="max-w-6xl mx-auto px-6 py-16 md:py-24 grid grid-cols-1 md:grid-cols-2 gap-14 items-center">
        <div class="rise-in rise-in-1">
            <span
                class="bg-[color:var(--color-du-gold-soft)] text-[#73001a] font-bold tracking-wider text-[11px] px-3 py-1.5 rounded-full uppercase border border-[color:var(--color-du-gold)]/40">Next-Gen
                Academic Infrastructure</span>
            <h1
                class="font-display text-4xl md:text-[3.25rem] font-semibold tracking-tight text-stone-900 mt-5 leading-[1.08]">
                Empowering Education Through
                <span class="text-[#73001a] relative inline-block">
                    Live Virtualization
                    <svg class="absolute left-0 -bottom-1 w-full" height="8" viewBox="0 0 200 8"
                        preserveAspectRatio="none" aria-hidden="true">
                        <path d="M0,5 Q50,0 100,5 T200,5" stroke="var(--color-du-gold)" stroke-width="3" fill="none"
                            stroke-linecap="round" />
                    </svg>
                </span>
            </h1>
            <p class="text-stone-500 mt-6 text-base leading-relaxed max-w-md">
                An integrated Learning Management System for live class attendance, instant online assessments, and
                structured course maps — built for one campus, end to end.
            </p>
            <div class="mt-9 flex items-center space-x-4">
                <a href="#login-portal"
                    class="bg-stone-900 text-white px-6 py-3.5 rounded-xl font-bold text-sm hover:bg-stone-800 transition shadow-md hover:shadow-lg focus-ring">Launch
                    Gateways</a>
                <a href="#curriculum"
                    class="bg-white border border-stone-200 text-stone-700 px-6 py-3.5 rounded-xl font-bold text-sm hover:bg-stone-50 transition focus-ring">View
                    Syllabus</a>
            </div>
        </div>

        <div
            class="rise-in rise-in-2 grain-card bg-gradient-to-br from-[#73001a] via-[#5c0016] to-stone-950 rounded-3xl p-8 text-white shadow-xl flex flex-col justify-between min-h-[22rem] max-h-[28rem] relative overflow-hidden group border border-white/10">
            <div
                class="absolute -right-10 -bottom-10 w-44 h-44 bg-[color:var(--color-du-gold)]/15 rounded-full blur-2xl">
            </div>

            <div class="relative z-10 w-full flex flex-col h-full">
                <div class="flex justify-between items-center border-b border-white/10 pb-4 mb-4 shrink-0">
                    <div class="flex items-center space-x-2.5">
                        <span class="text-xl w-9 h-9 flex items-center justify-center bg-white/10 rounded-xl">🚀</span>
                        <div>
                            <h3
                                class="font-display text-sm font-semibold text-[color:var(--color-du-gold-soft)] leading-none">
                                Live Virtualization Node</h3>
                            <p class="text-[10px] text-stone-300 tracking-wider uppercase mt-1">Real-time Dashboard
                                Monitoring</p>
                        </div>
                    </div>
                    <span
                        class="bg-emerald-500/10 text-emerald-400 font-mono text-[10px] font-bold px-2.5 py-1 rounded-md border border-emerald-500/20 flex items-center gap-1.5">
                        <span class="flex h-1.5 w-1.5 relative">
                            <span
                                class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-500"></span>
                        </span>
                        SYS_ONLINE
                    </span>
                </div>

                <div id="live-container" class="space-y-3 overflow-y-auto pr-1 max-h-[14rem] custom-scrollbar">
                    <?php if ($total_live_classes > 0): ?>
                        <?php while ($live_class = mysqli_fetch_assoc($live_class_result)): ?>
                            <div class="bg-white/5 p-4 rounded-xl border border-white/5 backdrop-blur-md">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <span
                                            class="text-[10px] font-mono bg-[color:var(--color-du-gold)]/20 text-[color:var(--color-du-gold-soft)] px-2 py-0.5 rounded font-bold">
                                            <?php echo htmlspecialchars($live_class['course_code']); ?>
                                        </span>
                                        <h4 class="font-display text-sm font-medium mt-1.5 text-white tracking-tight">
                                            <?php echo htmlspecialchars($live_class['course_title']); ?>
                                        </h4>
                                    </div>
                                    <div class="text-right">
                                        <span
                                            class="bg-red-500/20 text-red-400 font-mono text-[9px] font-bold px-2 py-0.5 rounded border border-red-500/20 animate-pulse">
                                            ● LIVE NOW
                                        </span>
                                    </div>
                                </div>

                                <div class="mt-4 pt-3 border-t border-white/5 flex items-center justify-between gap-4">
                                    <div class="w-full bg-white/10 h-1.5 rounded-full overflow-hidden">
                                        <div
                                            class="bg-gradient-to-r from-[color:var(--color-du-gold)] to-emerald-400 h-full rounded-full w-2/3">
                                        </div>
                                    </div>
                                    <div
                                        class="flex items-center space-x-1.5 bg-black/30 px-2.5 py-1 rounded-lg border border-white/5 shrink-0">
                                        <span>⏱️</span>
                                        <span class="font-mono text-xs text-emerald-400 font-bold run-timer">35:00</span>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="bg-white/5 p-6 rounded-xl border border-white/5 backdrop-blur-md text-center py-10">
                            <span class="text-2xl block mb-2">📡</span>
                            <h4 class="text-sm font-medium text-stone-300">No active lectures detected</h4>
                            <p class="text-[11px] text-stone-500 mt-1">System is waiting for real-time faculty streams.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div
                class="bg-white/5 p-3 rounded-xl backdrop-blur-md border border-white/10 text-[11px] flex justify-between items-center z-10 w-full mt-4 shrink-0">
                <span class="font-medium text-stone-200">Active Live Nodes: <span id="node-status-text"
                        class="font-bold text-white"><?php echo $total_live_classes; ?> Active</span></span>
                <span class="text-xs text-[color:var(--color-du-gold)] font-mono font-bold">SYNC_OK</span>
            </div>
        </div>
    </section>

    <section id="login-portal" class="bg-stone-100/70 py-16 md:py-20 border-t border-b border-stone-200">
        <div class="max-w-lg mx-auto px-6">
            <div class="bg-white p-8 rounded-3xl shadow-xl border border-stone-200/80 rise-in rise-in-1">
                <h2 class="font-display text-2xl font-semibold text-center text-stone-900 mb-1">Secure Management
                    Gateway</h2>
                <p class="text-center text-stone-400 text-xs mb-7">Select your appropriate destination portal layer
                    below</p>

                <?php if ($error): ?>
                    <div role="alert"
                        class="text-red-700 text-xs bg-red-50 p-3 rounded-xl mb-5 text-center font-medium border border-red-200 flex items-center justify-center space-x-2">
                        <span>⚠️</span> <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" novalidate>
                    <input type="hidden" name="role_selection" id="role_selection" value="student">

                    <fieldset class="mb-6">
                        <legend
                            class="block text-stone-500 text-xs font-bold uppercase tracking-wider mb-3 text-center w-full">
                            Select Account Access Layer</legend>
                        <div class="grid grid-cols-3 gap-2 bg-stone-100 p-1.5 rounded-2xl" role="tablist"
                            aria-label="Portal role">
                            <button type="button" id="tab-student" onclick="selectRole('student')" role="tab"
                                aria-selected="true"
                                class="flex flex-col items-center justify-center py-3 rounded-xl font-bold text-xs transition-all duration-300 bg-white text-[#73001a] shadow-sm border border-stone-200 focus-ring">
                                <span class="text-lg mb-1">🎓</span>
                                <span>Student</span>
                            </button>
                            <button type="button" id="tab-teacher" onclick="selectRole('teacher')" role="tab"
                                aria-selected="false"
                                class="flex flex-col items-center justify-center py-3 rounded-xl font-bold text-xs transition-all duration-300 text-stone-600 hover:bg-white/70 focus-ring">
                                <span class="text-lg mb-1">👨‍🏫</span>
                                <span>Faculty</span>
                            </button>
                            <button type="button" id="tab-admin" onclick="selectRole('admin')" role="tab"
                                aria-selected="false"
                                class="flex flex-col items-center justify-center py-3 rounded-xl font-bold text-xs transition-all duration-300 text-stone-600 hover:bg-white/70 focus-ring">
                                <span class="text-lg mb-1">⚙️</span>
                                <span>Admin</span>
                            </button>
                        </div>
                    </fieldset>

                    <div class="mb-4">
                        <label class="block text-stone-500 text-xs font-bold uppercase tracking-wider mb-1.5">Email
                            Address</label>
                        <input type="email" name="email" required placeholder="name@domain.edu"
                            class="w-full px-4 py-3 border border-stone-300 rounded-xl focus:outline-none focus:border-[#73001a] focus:ring-4 focus:ring-[#73001a]/10 text-sm transition-all duration-200">
                    </div>

                    <div class="mb-7">
                        <label class="block text-stone-500 text-xs font-bold uppercase tracking-wider mb-1.5">Security
                            Password</label>
                        <div class="relative">
                            <input type="password" id="passwordField" name="password" required placeholder="••••••••"
                                class="w-full px-4 py-3 pr-16 border border-stone-300 rounded-xl focus:outline-none focus:border-[#73001a] focus:ring-4 focus:ring-[#73001a]/10 text-sm transition-all duration-200">
                            <button type="button" onclick="togglePassword()"
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-stone-400 hover:text-stone-600 text-xs font-semibold focus:outline-none focus-ring rounded px-1">
                                <span id="toggleText">Show</span>
                            </button>
                        </div>
                    </div>

                    <button type="submit" name="login" id="submit-btn"
                        class="w-full bg-[#73001a] text-white font-bold py-3.5 px-4 rounded-xl hover:bg-[#4c0011] shadow-md hover:shadow-lg transition-all duration-200 transform active:scale-[0.99] text-sm tracking-wide focus-ring">
                        Access Student Portal
                    </button>
                </form>
            </div>
        </div>
    </section>

    <section id="curriculum" class="max-w-5xl mx-auto px-6 py-20 md:py-24">
        <div class="text-center mb-10">
            <span
                class="bg-[color:var(--color-du-gold-soft)] text-[#73001a] font-bold tracking-wider text-[11px] px-3 py-1.5 rounded-full uppercase border border-[color:var(--color-du-gold)]/40">Academic
                Blueprint</span>
            <h2 class="font-display text-3xl font-semibold text-stone-900 tracking-tight mt-3">Structured Course
                Framework</h2>
        </div>

        <div
            class="bg-white rounded-2xl border border-stone-200 p-6 sm:p-8 shadow-xs hover:border-[#73001a]/30 hover:shadow-md transition-all duration-300 flex flex-col sm:flex-row items-center justify-between gap-6">
            <div class="flex items-center space-x-4 text-center sm:text-left">
                <div
                    class="p-4 bg-[color:var(--color-du-gold-soft)] rounded-2xl border border-[color:var(--color-du-gold)]/30 text-2xl">
                    📚</div>
                <div>
                    <span
                        class="bg-[color:var(--color-du-gold-soft)] text-[#73001a] font-bold tracking-wider text-[10px] px-2.5 py-1 rounded-full uppercase border border-[color:var(--color-du-gold)]/30">Official
                        Curriculum</span>
                    <h3 class="font-display text-lg font-semibold text-stone-900 tracking-tight mt-1.5">B.Sc. Computer
                        Science & Engineering Syllabus</h3>
                    <p class="text-stone-500 text-xs mt-1">Interactive 8-semester academic blueprints — credit counts,
                        hours, prerequisites, and full syllabus content maps.</p>
                </div>
            </div>
            <div class="w-full sm:w-auto text-center">
                <a href="syllabus.html"
                    class="inline-block w-full sm:w-auto bg-[#73001a] hover:bg-[#4c0011] text-white text-xs font-bold px-6 py-3.5 rounded-xl transition-all duration-200 shadow-sm hover:shadow-md focus-ring">Open
                    Syllabus Explorer →</a>
            </div>
        </div>
    </section>

    <footer class="border-t border-stone-200 py-8 text-center text-xs text-stone-400">
        © <?php echo date('Y'); ?> Virtual Varsity — Academic Platform
    </footer>

    <script>
        function selectRole(role) {
            document.getElementById('role_selection').value = role;
            const roles = ['student', 'teacher', 'admin'];
            const buttonLabels = {
                'student': 'Access Student Portal',
                'teacher': 'Access Faculty Grade Panel',
                'admin': 'Access Central Administration'
            };

            roles.forEach(r => {
                const btn = document.getElementById(`tab-${r}`);
                if (r === role) {
                    btn.classList.add('bg-white', 'text-[#73001a]', 'shadow-sm', 'border', 'border-stone-200');
                    btn.classList.remove('text-stone-600', 'hover:bg-white/70');
                    btn.setAttribute('aria-selected', 'true');
                } else {
                    btn.classList.remove('bg-white', 'text-[#73001a]', 'shadow-sm', 'border', 'border-stone-200');
                    btn.classList.add('text-stone-600', 'hover:bg-white/70');
                    btn.setAttribute('aria-selected', 'false');
                }
            });
            document.getElementById('submit-btn').innerText = buttonLabels[role];
        }

        function togglePassword() {
            const passwordField = document.getElementById('passwordField');
            const toggleText = document.getElementById('toggleText');
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleText.innerText = 'Hide';
            } else {
                passwordField.type = 'password';
                toggleText.innerText = 'Show';
            }
        }

        // 🎯 রিয়েল-টাইম ডাটাবেজ সিঙ্কিং মেকানিজম (AJAX Short Polling)
        async function syncLiveClasses() {
            const container = document.getElementById('live-container');
            const nodeStatusText = document.getElementById('node-status-text');

            // যদি পেজে এলিমেন্ট কোনো কারণে মিসিং থাকে, জাভাস্ক্রিপ্ট এরর এড়ানোর প্রটেকশন
            if (!container) return;

            try {
                const response = await fetch('get_live_classes.php');
                const liveClasses = await response.json();

                // ১. যদি ডাটাবেজে কোনো লাইভ ক্লাস না থাকে
                if (liveClasses.length === 0) {
                    container.innerHTML = `
                        <div class="bg-white/5 p-6 rounded-xl border border-white/5 backdrop-blur-md text-center py-10">
                            <span class="text-2xl block mb-2">📡</span>
                            <h4 class="text-sm font-medium text-stone-300">No active lectures detected</h4>
                            <p class="text-[11px] text-stone-500 mt-1">System is waiting for real-time faculty streams.</p>
                        </div>
                    `;
                    if (nodeStatusText) nodeStatusText.innerHTML = '0 Active';
                    return;
                }

                // ২. যদি ডাটাবেজে লাইভ ক্লাস পাওয়া যায় (১০টি ক্লাস একসাথে হলেও লুপে ঘুরবে)
                let cardsHtml = '';
                liveClasses.forEach(clazz => {
                    cardsHtml += `
                        <div class="bg-white/5 p-4 rounded-xl border border-white/5 backdrop-blur-md">
                            <div class="flex justify-between items-start">
                                <div>
                                    <span class="text-[10px] font-mono bg-amber-500/20 text-amber-200 px-2 py-0.5 rounded font-bold">
                                        ${escapeHtml(clazz.course_code)}
                                    </span>
                                    <h4 class="font-display text-sm font-medium mt-1.5 text-white tracking-tight">
                                        ${escapeHtml(clazz.course_title)}
                                    </h4>
                                </div>
                                <div class="text-right">
                                    <span class="bg-red-500/20 text-red-400 font-mono text-[9px] font-bold px-2 py-0.5 rounded border border-red-500/20 animate-pulse">
                                        ● LIVE NOW
                                    </span>
                                </div>
                            </div>

                            <div class="mt-4 pt-3 border-t border-white/5 flex items-center justify-between gap-4">
                                <div class="w-full bg-white/10 h-1.5 rounded-full overflow-hidden">
                                    <div class="bg-gradient-to-r from-amber-500 to-emerald-400 h-full rounded-full w-2/3"></div>
                                </div>
                                <div class="flex items-center space-x-1.5 bg-black/30 px-2.5 py-1 rounded-lg border border-white/5 shrink-0">
                                    <span>⏱️</span>
                                    <span class="font-mono text-xs text-emerald-400 font-bold run-timer">35:00</span>
                                </div>
                            </div>
                        </div>
                    `;
                });

                container.innerHTML = cardsHtml;
                if (nodeStatusText) nodeStatusText.innerHTML = `${liveClasses.length} Active`;

            } catch (error) {
                console.error("Realtime Sync Error: ", error);
            }
        }

        // XSS ইঞ্জেকশন প্রটেকশন ফাংশন
        function escapeHtml(str) {
            return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }

        // ডম লোড হলে লাইভ সিঙ্ক লুপ রান করানো
        document.addEventListener('DOMContentLoaded', () => {
            // প্রথমবার পেজ লোডেই ডাটা নিয়ে আসবে
            syncLiveClasses();

            // প্রতি ৫ সেকেন্ড পর পর ব্যাকগ্রাউন্ডে চেক করবে
            setInterval(syncLiveClasses, 5000);
        });
    </script>
</body>

</html>