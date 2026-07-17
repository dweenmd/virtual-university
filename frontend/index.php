<?php
include '../backend/login_controller.php';
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
    <link rel="stylesheet" href="assets/css/index.css">
    <script>
        (function() {
            const savedTheme = localStorage.getItem('vv_theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
            if (savedTheme === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        })();
    </script>
</head>

<body class="bg-[color:var(--bg-app)] text-[color:var(--text-main)] font-sans antialiased transition-colors duration-300">

    <nav id="site-nav" class="bg-white/95 backdrop-blur-md sticky top-0 z-50 px-6 py-4">
        <div class="flex justify-between items-center">
            <a href="#hero" class="flex items-center space-x-3 focus-ring rounded-lg">
                <span
                    class="nav-logo-badge text-2xl w-11 h-11 flex items-center justify-center bg-gradient-to-br from-[color:var(--color-du-maroon)] to-[color:var(--color-du-maroon-dark)] rounded-xl text-white shadow-sm">🎓</span>
                <div class="flex flex-col leading-none">
                    <span class="font-display text-xl font-semibold text-stone-900 tracking-tight">Virtual
                        Varsity</span>
                    <span
                        class="text-xs uppercase tracking-[0.18em] text-[color:var(--color-du-gold)] font-bold mt-1">Unified
                        Academic Network</span>
                </div>
            </a>

            <div class="hidden md:flex items-center space-x-9 text-base font-semibold text-stone-600">
                <a href="#hero" data-nav-link class="nav-link active-link focus-ring rounded">Home</a>

                <a href="#login-portal" data-nav-link class="nav-link focus-ring rounded">LMS Portals</a>
                <a href="#curriculum" data-nav-link class="nav-link focus-ring rounded">Syllabus Explorer</a>
            </div>

            <div class="flex items-center space-x-3">
                <!-- Theme Toggle Button -->
                <button onclick="toggleTheme()" id="theme-toggle-btn" class="w-10 h-10 flex items-center justify-center rounded-xl border border-stone-200 dark:border-stone-800 text-stone-600 dark:text-stone-300 hover:bg-stone-100 dark:hover:bg-stone-800 transition-colors duration-200 cursor-pointer" aria-label="Toggle theme">
                    <span id="theme-toggle-icon" class="text-lg">🌙</span>
                </button>
                <a href="#login-portal"
                    class="nav-cta hidden sm:inline-flex bg-[color:var(--color-du-maroon)] text-white px-5 py-2.5 rounded-xl text-sm font-bold hover:bg-[color:var(--color-du-maroon-dark)] transition-all duration-200 focus-ring">Access
                    Portals</a>

                <button id="hamburger-btn" type="button" onclick="toggleMobileMenu()" aria-expanded="false"
                    aria-controls="mobile-menu" aria-label="Toggle navigation menu"
                    class="md:hidden w-10 h-10 flex flex-col items-center justify-center gap-[5px] rounded-lg border border-stone-200 focus-ring">
                    <span id="hamburger-icon" class="flex flex-col items-center gap-[5px] w-5">
                        <span class="block w-5 h-0.5 bg-stone-700 rounded-full"></span>
                        <span class="block w-5 h-0.5 bg-stone-700 rounded-full"></span>
                        <span class="block w-5 h-0.5 bg-stone-700 rounded-full"></span>
                    </span>
                </button>
            </div>
        </div>

        <div id="mobile-menu" class="md:hidden mt-4 flex flex-col space-y-1 text-base font-semibold text-stone-600">
            <a href="#hero" onclick="closeMobileMenu()"
                class="px-3 py-3 rounded-xl hover:bg-stone-50 hover:text-[color:var(--color-du-maroon)] transition-colors">Home</a>
            <a href="#curriculum" onclick="closeMobileMenu()"
                class="px-3 py-3 rounded-xl hover:bg-stone-50 hover:text-[color:var(--color-du-maroon)] transition-colors">Syllabus
                Explorer</a>
            <a href="#login-portal" onclick="closeMobileMenu()"
                class="px-3 py-3 rounded-xl hover:bg-stone-50 hover:text-[color:var(--color-du-maroon)] transition-colors">LMS
                Portals</a>
            <a href="#login-portal" onclick="closeMobileMenu()"
                class="mt-2 text-center bg-[color:var(--color-du-maroon)] text-white px-5 py-3 rounded-xl text-sm font-bold hover:bg-[color:var(--color-du-maroon-dark)] transition-all">Access
                Portals</a>
        </div>
    <!-- Gradient Separator -->
    <div class="h-[1px] w-full bg-gradient-to-r from-transparent via-[color:var(--color-du-gold)] to-transparent opacity-20 sticky top-[77px] z-50"></div>

    <section id="hero"
        class="max-w-6xl mx-auto px-6 py-16 md:py-24 grid grid-cols-1 md:grid-cols-2 gap-14 items-center">
        <div class="rise-in rise-in-1">
            <span
                class="bg-[color:var(--color-du-gold-soft)] text-[color:var(--color-du-maroon)] font-bold tracking-wider text-xs px-3.5 py-2 rounded-full uppercase border border-[color:var(--color-du-gold)]/40">Next-Gen
                Academic Infrastructure</span>
            <h1
                class="font-display text-5xl md:text-6xl font-semibold tracking-tight text-stone-900 mt-6 leading-[1.08]">
                Empowering Education Through
                <span class="text-[color:var(--color-du-maroon)] relative inline-block">
                    Live Virtualization
                    <svg class="absolute left-0 -bottom-1 w-full" height="8" viewBox="0 0 200 8"
                        preserveAspectRatio="none" aria-hidden="true">
                        <path d="M0,5 Q50,0 100,5 T200,5" stroke="var(--color-du-gold)" stroke-width="3" fill="none"
                            stroke-linecap="round" />
                    </svg>
                </span>
            </h1>
            <p class="text-stone-500 mt-7 text-lg leading-relaxed max-w-md">
                An integrated Learning Management System for live class attendance, instant online assessments, and
                structured course maps — built for one campus, end to end.
            </p>
            <div class="mt-10 flex items-center space-x-4">
                <a href="#login-portal"
                    class="btn-premium-maroon px-7 py-4 rounded-xl font-bold text-base transition focus-ring">Launch
                    Gateways</a>
                <a href="syllabus.html"
                    class="btn-premium-outline px-7 py-4 rounded-xl font-bold text-base transition focus-ring">View
                    Syllabus</a>
            </div>
        </div>

        <div
            class="rise-in rise-in-2 grain-card bg-gradient-to-br from-[color:var(--color-du-maroon)] via-[#5c0016] to-stone-950 rounded-3xl p-8 text-white shadow-xl flex flex-col justify-between min-h-[22rem] max-h-[28rem] relative overflow-hidden group border border-white/10">
            <div
                class="absolute -right-10 -bottom-10 w-44 h-44 bg-[color:var(--color-du-gold)]/20 rounded-full blur-2xl">
            </div>

            <div class="relative z-10 w-full flex flex-col h-full">
                <div class="flex justify-between items-center border-b border-white/10 pb-4 mb-4 shrink-0">
                    <div class="flex items-center space-x-2.5">
                        <span class="text-xl w-9 h-9 flex items-center justify-center bg-white/10 rounded-xl">🚀</span>
                        <div>
                            <h3
                                class="font-display text-base font-semibold text-[color:var(--color-du-gold-soft)] leading-none">
                                Live Virtualization Node</h3>
                            <p class="text-xs text-stone-300 tracking-wider uppercase mt-1.5">Real-time Dashboard
                                Monitoring</p>
                        </div>
                    </div>
                    <span
                        class="bg-emerald-500/10 text-emerald-400 font-mono text-xs font-bold px-2.5 py-1 rounded-md border border-emerald-500/20 flex items-center gap-1.5">
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
                                            class="text-xs font-mono bg-[color:var(--color-du-gold)]/20 text-[color:var(--color-du-gold-soft)] px-2 py-0.5 rounded font-bold">
                                            <?php echo htmlspecialchars($live_class['course_code']); ?>
                                        </span>
                                        <h4 class="font-display text-base font-medium mt-1.5 text-white tracking-tight">
                                            <?php echo htmlspecialchars($live_class['course_title']); ?>
                                        </h4>
                                    </div>
                                    <div class="text-right">
                                        <span
                                            class="bg-red-500/20 text-red-400 font-mono text-[10px] font-bold px-2 py-0.5 rounded border border-red-500/20 animate-pulse">
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
                                        <span class="font-mono text-sm text-emerald-400 font-bold run-timer">35:00</span>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="bg-white/5 p-6 rounded-xl border border-white/5 backdrop-blur-md text-center py-10">
                            <span class="text-2xl block mb-2">📡</span>
                            <h4 class="text-base font-medium text-stone-300">No active lectures detected</h4>
                            <p class="text-xs text-stone-500 mt-1.5">System is waiting for real-time faculty streams.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div
                class="bg-white/5 p-3.5 rounded-xl backdrop-blur-md border border-white/10 text-xs flex justify-between items-center z-10 w-full mt-4 shrink-0">
                <span class="font-medium text-stone-200">Active Live Nodes: <span id="node-status-text"
                        class="font-bold text-white"><?php echo $total_live_classes; ?> Active</span></span>
                <span class="text-xs text-[color:var(--color-du-gold-bright)] font-mono font-bold">SYNC_OK</span>
            </div>
        </div>
    </section>

    <!-- Gradient Separator -->
    <div class="h-[1px] w-full bg-gradient-to-r from-transparent via-[color:var(--color-du-gold)] to-transparent opacity-30"></div>

    <section id="login-portal" class="py-16 md:py-20 bg-gradient-to-br from-[color:var(--color-du-gold-soft)] to-transparent relative overflow-hidden">
        <div class="max-w-lg mx-auto px-6">
            <div class="glass-card p-8 rounded-3xl rise-in rise-in-1">
                <h2 class="font-display text-3xl font-semibold text-center text-[color:var(--text-main)] mb-2">Secure Management
                    Gateway</h2>
                <p class="text-center text-[color:var(--text-muted)] text-sm mb-8">Select your appropriate destination portal layer
                    below</p>

                <?php if ($error): ?>
                    <div role="alert"
                        class="text-red-700 text-sm bg-red-50 p-3.5 rounded-xl mb-5 text-center font-medium border border-red-200 flex items-center justify-center space-x-2">
                        <span>⚠️</span> <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" novalidate>
                    <input type="hidden" name="role_selection" id="role_selection" value="student">

                    <fieldset class="mb-7">
                        <legend
                            class="block text-[color:var(--text-muted)] text-sm font-bold uppercase tracking-wider mb-3 text-center w-full">
                            Select Account Access Layer</legend>
                        <div class="grid grid-cols-3 gap-2 role-tabs p-1.5 rounded-2xl" role="tablist"
                            aria-label="Portal role">
                            <button type="button" id="tab-student" onclick="selectRole('student')" role="tab"
                                aria-selected="true"
                                class="role-tab-btn flex flex-col items-center justify-center py-3.5 rounded-xl font-bold text-sm focus-ring cursor-pointer">
                                <span class="text-xl mb-1">🎓</span>
                                <span>Student</span>
                            </button>
                            <button type="button" id="tab-teacher" onclick="selectRole('teacher')" role="tab"
                                aria-selected="false"
                                class="role-tab-btn flex flex-col items-center justify-center py-3.5 rounded-xl font-bold text-sm focus-ring cursor-pointer">
                                <span class="text-xl mb-1">👨‍🏫</span>
                                <span>Faculty</span>
                            </button>
                            <button type="button" id="tab-admin" onclick="selectRole('admin')" role="tab"
                                aria-selected="false"
                                class="role-tab-btn flex flex-col items-center justify-center py-3.5 rounded-xl font-bold text-sm focus-ring cursor-pointer">
                                <span class="text-xl mb-1">⚙️</span>
                                <span>Admin</span>
                            </button>
                        </div>
                    </fieldset>

                    <div class="mb-5">
                        <label class="block text-[color:var(--text-muted)] text-sm font-bold uppercase tracking-wider mb-2">Email
                             Address</label>
                        <input type="email" name="email" required placeholder="name@domain.edu"
                            class="w-full px-4 py-3.5 premium-input rounded-xl focus:outline-hidden text-base">
                    </div>

                    <div class="mb-8">
                        <label class="block text-[color:var(--text-muted)] text-sm font-bold uppercase tracking-wider mb-2">Security
                            Password</label>
                        <div class="relative">
                            <input type="password" id="passwordField" name="password" required placeholder="••••••••"
                                class="w-full px-4 py-3.5 pr-16 premium-input rounded-xl focus:outline-hidden text-base">
                            <button type="button" onclick="togglePassword()"
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-stone-400 hover:text-stone-600 text-sm font-semibold focus:outline-hidden focus-ring rounded px-1 cursor-pointer">
                                <span id="toggleText">Show</span>
                            </button>
                        </div>
                    </div>

                    <button type="submit" name="login" id="submit-btn"
                        class="w-full btn-premium-maroon font-bold py-4 px-4 rounded-xl text-base tracking-wide focus-ring cursor-pointer">
                        Access Student Portal
                    </button>
                </form>

                <!-- Demo Access accounts helper -->
                <div class="mt-6 pt-6 border-t border-stone-200/60 dark:border-stone-800/40">
                    <div class="flex justify-between items-center mb-3">
                        <span class="text-xs font-bold uppercase tracking-wider text-[color:var(--text-muted)]">Quick Demo Accounts</span>
                        <span class="text-[10px] text-amber-800 bg-amber-500/10 dark:text-amber-300 dark:bg-amber-500/20 px-2 py-0.5 rounded-md font-bold">Staging Helper</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <button type="button" onclick="loadDemoAccount('student')" class="px-2 py-2 text-xs font-bold text-center border border-stone-200 dark:border-stone-850 hover:bg-stone-50 dark:hover:bg-stone-800/40 rounded-xl transition-all cursor-pointer text-[color:var(--text-main)]">
                            🎓 Student
                        </button>
                        <button type="button" onclick="loadDemoAccount('teacher')" class="px-2 py-2 text-xs font-bold text-center border border-stone-200 dark:border-stone-850 hover:bg-stone-50 dark:hover:bg-stone-800/40 rounded-xl transition-all cursor-pointer text-[color:var(--text-main)]">
                            👨‍🏫 Faculty
                        </button>
                        <button type="button" onclick="loadDemoAccount('admin')" class="px-2 py-2 text-xs font-bold text-center border border-stone-200 dark:border-stone-850 hover:bg-stone-50 dark:hover:bg-stone-800/40 rounded-xl transition-all cursor-pointer text-[color:var(--text-main)]">
                            ⚙️ Admin
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Gradient Separator -->
    <div class="h-[1px] w-full bg-gradient-to-r from-transparent via-[color:var(--color-du-gold)] to-transparent opacity-30"></div>

    <section id="curriculum" class="max-w-5xl mx-auto px-6 py-20 md:py-24">
        <div class="text-center mb-12">
            <span
                class="bg-[color:var(--color-du-gold-soft)] text-[color:var(--color-du-maroon)] font-bold tracking-wider text-xs px-3.5 py-2 rounded-full uppercase border border-[color:var(--color-du-gold)]/40">Academic
                Blueprint</span>
            <h2 class="font-display text-4xl font-semibold text-stone-900 tracking-tight mt-4">Structured Course
                Framework</h2>
        </div>

        <div
            class="glass-card rounded-2xl p-6 sm:p-8 flex flex-col sm:flex-row items-center justify-between gap-6">
            <div class="flex items-center space-x-4 text-center sm:text-left">
                <div
                    class="p-4 bg-[color:var(--color-du-gold-soft)] rounded-2xl border border-[color:var(--color-du-gold)]/30 text-3xl">
                    📚</div>
                <div>
                    <span
                        class="bg-[color:var(--color-du-gold-soft)] text-[color:var(--color-du-maroon)] font-bold tracking-wider text-xs px-2.5 py-1 rounded-full uppercase border border-[color:var(--color-du-gold)]/30">Official
                        Curriculum</span>
                    <h3 class="font-display text-xl font-semibold text-stone-900 tracking-tight mt-2">B.Sc. Computer
                        Science & Engineering Syllabus</h3>
                    <p class="text-stone-500 text-sm mt-1.5">Interactive 8-semester academic blueprints — credit counts,
                        hours, prerequisites, and full syllabus content maps.</p>
                </div>
            </div>
            <div class="w-full sm:w-auto text-center">
                <a href="syllabus.html"
                    class="inline-block w-full sm:w-auto bg-[color:var(--color-du-maroon)] hover:bg-[color:var(--color-du-maroon-dark)] text-white text-sm font-bold px-6 py-4 rounded-xl transition-all duration-200 shadow-sm hover:shadow-md focus-ring">Open
                    Syllabus Explorer →</a>
            </div>
        </div>
    </section>

    <footer class="border-t border-stone-200 py-8 text-center text-sm text-stone-400">
        © <?php echo date('Y'); ?> Virtual Varsity — Academic Platform
    </footer>

    <script>
        // Mobile menu toggle
        function toggleMobileMenu() {
            const menu = document.getElementById('mobile-menu');
            const icon = document.getElementById('hamburger-icon');
            const btn = document.getElementById('hamburger-btn');
            const isOpen = menu.classList.toggle('open');
            icon.classList.toggle('open', isOpen);
            btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        }

        function closeMobileMenu() {
            document.getElementById('mobile-menu').classList.remove('open');
            document.getElementById('hamburger-icon').classList.remove('open');
            document.getElementById('hamburger-btn').setAttribute('aria-expanded', 'false');
        }

        // Navbar shadow + compact padding on scroll
        (function () {
            const nav = document.getElementById('site-nav');
            if (!nav) return;
            const onScroll = () => {
                nav.classList.toggle('nav-scrolled', window.scrollY > 12);
            };
            window.addEventListener('scroll', onScroll, { passive: true });
            onScroll();
        })();

        // Scroll-spy: highlight the nav link for the section in view
        (function () {
            const links = document.querySelectorAll('[data-nav-link]');
            if (!links.length) return;
            const sections = Array.from(links)
                .map(link => document.querySelector(link.getAttribute('href')))
                .filter(Boolean);

            const setActive = (id) => {
                links.forEach(link => {
                    link.classList.toggle('active-link', link.getAttribute('href') === `#${id}`);
                });
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) setActive(entry.target.id);
                });
            }, { rootMargin: '-45% 0px -45% 0px', threshold: 0 });

            sections.forEach(section => observer.observe(section));
        })();

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
                    btn.setAttribute('aria-selected', 'true');
                } else {
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

        // Theme management controls
        function applyTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
            const btnIcon = document.getElementById('theme-toggle-icon');
            if (btnIcon) {
                btnIcon.textContent = theme === 'dark' ? '☀️' : '🌙';
            }
        }

        function toggleTheme() {
            const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
            const next = current === 'dark' ? 'light' : 'dark';
            localStorage.setItem('vv_theme', next);
            applyTheme(next);
        }

        // Demo credentials filler helper
        function loadDemoAccount(role) {
            selectRole(role);
            const emailInput = document.querySelector('input[name="email"]');
            const passInput = document.querySelector('input[name="password"]');
            if (role === 'student') {
                emailInput.value = 'student1@vu.com';
                passInput.value = '1234';
            } else if (role === 'teacher') {
                emailInput.value = 'teacher1@vu.com';
                passInput.value = '1234';
            } else if (role === 'admin') {
                emailInput.value = 'admin@vu.com';
                passInput.value = 'admin1234';
            }
        }


        async function syncLiveClasses() {
            const container = document.getElementById('live-container');
            const nodeStatusText = document.getElementById('node-status-text');

            if (!container) return;

            // Render shimmer skeletons if container is empty or we want to show loading
            if (container.children.length === 0) {
                container.innerHTML = `
                    <div class="skeleton-shimmer p-4 rounded-xl border border-white/5 h-24 opacity-40"></div>
                    <div class="skeleton-shimmer p-4 rounded-xl border border-white/5 h-24 opacity-40"></div>
                `;
            }

            try {
                const response = await fetch('../backend/api/get_live_classes.php');
                const liveClasses = await response.json();

                // 1. If there are no live classes in the database
                if (liveClasses.length === 0) {
                    container.innerHTML = `
                        <div class="bg-white/5 p-6 rounded-xl border border-white/5 backdrop-blur-md text-center py-10">
                            <span class="text-2xl block mb-2">📡</span>
                            <h4 class="text-base font-medium text-stone-300">No active lectures detected</h4>
                            <p class="text-xs text-stone-500 mt-1.5">System is waiting for real-time faculty streams.</p>
                        </div>
                    `;
                    if (nodeStatusText) nodeStatusText.innerHTML = '0 Active';
                    return;
                }

                // 2. If live classes are found in the database (loops through all, even if there are 10 at once)
                let cardsHtml = '';
                liveClasses.forEach(clazz => {
                    cardsHtml += `
                        <div class="bg-white/5 p-4 rounded-xl border border-white/5 backdrop-blur-md">
                            <div class="flex justify-between items-start">
                                <div>
                                    <span class="text-xs font-mono bg-amber-500/20 text-amber-200 px-2 py-0.5 rounded font-bold">
                                        ${escapeHtml(clazz.course_code)}
                                    </span>
                                    <h4 class="font-display text-base font-medium mt-1.5 text-white tracking-tight">
                                        ${escapeHtml(clazz.course_title)}
                                    </h4>
                                </div>
                                <div class="text-right">
                                    <span class="bg-red-500/20 text-red-400 font-mono text-[10px] font-bold px-2 py-0.5 rounded border border-red-500/20 animate-pulse">
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
                                    <span class="font-mono text-sm text-emerald-400 font-bold run-timer">35:00</span>
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

        // XSS injection protection function
        function escapeHtml(str) {
            return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }

        // Run the live sync loop once the DOM has loaded
        document.addEventListener('DOMContentLoaded', () => {
            // Initialize theme on first load
            const saved = localStorage.getItem('vv_theme') || 'light';
            applyTheme(saved);

            // Fetch data immediately on first page load
            syncLiveClasses();

            // Poll in the background every 5 seconds
            setInterval(syncLiveClasses, 5000);
        });
    </script>
</body>

</html>