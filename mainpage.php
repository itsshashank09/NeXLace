<?php
session_start();
require_once 'config/security_headers.php';
require_once 'config/csrf.php';
$csrfToken = generateCsrfToken();

// Check if user is logged in
if (!isset($_SESSION['name']) || empty($_SESSION['name'])) {
    // Redirect to login page if not logged in
    header('Location: login.html');
    exit();
}

// Include database connection
require_once 'config/database.php';

$name = $_SESSION['name'];
$email = $_SESSION['email'] ?? '';
$profileImage = '';
$headline = '';
$bio = '';

// Fetch profile data from database
$unreadNotifications = 0;
try {
    require_once 'includes/db_helper.php';
    $conn = getDB();

    if ($conn && !empty($email)) {
        $stmt = $conn->prepare("SELECT `Professional Headline`, `Bio`, `image` FROM register WHERE Email = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            $headline = $user['Professional Headline'] ?? '';
            $bio = $user['Bio'] ?? '';
            $profileImage = $user['image'] ?? '';
        }

        // Get unread notification count
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId) {
            $notifStmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = :user_id AND is_read = 0");
            $notifStmt->execute([':user_id' => $userId]);
            $notifResult = $notifStmt->fetch();
            $unreadNotifications = $notifResult['count'] ?? 0;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching profile data: " . $e->getMessage());
}

// Fetch user's posted jobs
$userJobs = [];
try {
    if (!isset($conn)) {
        require_once 'includes/db_helper.php';
        $conn = getDB();
    }

    $userId = $_SESSION['user_id'] ?? null;

    // Try to get user_id from email if not in session
    if (!$userId && !empty($email)) {
        $userStmt = $conn->prepare("SELECT id FROM register WHERE Email = :email");
        $userStmt->execute([':email' => $email]);
        $userRow = $userStmt->fetch();
        if ($userRow) {
            $userId = $userRow['id'];
            $_SESSION['user_id'] = $userId;
        }
    }

    if ($userId) {
        $jobsStmt = $conn->prepare("SELECT id, job_title, job_details, skills_required, estimated_budget, project_timeline, category, project_type, experience_level, created_at FROM post_jobs WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 10");
        $jobsStmt->execute([':user_id' => $userId]);
        $userJobs = $jobsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Error fetching user jobs: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html class="light" lang="en">

<head>
    <link rel="icon" type="image/png" href="assetes/logo.png" />
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>NeXLace - Dashboard</title>
    <link href="https://fonts.googleapis.com" rel="preconnect" />
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;900&amp;display=swap"
        rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
        rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#135bec",
                        "primary-dark": "#0e44b3",
                        "background-light": "#f6f6f8",
                        "background-dark": "#101622",
                        "card-light": "#ffffff",
                        "card-dark": "#151c2b",
                        "text-main": "#0d121b",
                        "text-sub": "#4c669a",
                    },
                    fontFamily: {
                        "display": ["Inter", "sans-serif"]
                    },
                    borderRadius: { "DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "2xl": "1rem", "full": "9999px" },
                },
            },
        }
    </script>
    <script>
            (function () {
                const theme = localStorage.getItem('theme') || 'system';
                const element = document.documentElement;
                if (theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    element.classList.add('dark');
                } else {
                    element.classList.remove('dark');
                }
            })();
    </script>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken); ?>">
    <script src="js/csrf.js"></script>
</head>

<body class="bg-background-light dark:bg-background-dark text-text-main dark:text-[#f8f9fc] font-display antialiased">
    <div class="relative flex h-auto min-h-screen w-full flex-col overflow-x-hidden">
        <header
            class="sticky top-0 z-50 w-full border-b border-[#e7ebf3] dark:border-[#2a3447] bg-white dark:bg-card-dark">
            <div class="mx-auto flex h-16 max-w-[1200px] items-center justify-between px-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-8">
                    <div class="flex items-center gap-2 text-primary">
                        <div class="flex items-center gap-2">
                            <a href="mainpage.php"><img src="assetes/logo.png" alt="NeXLace Logo"
                                    class="h-10 w-auto object-contain" /></a>
                            <a href="mainpage.php">
                                <h2 class="text-xl font-black tracking-tight text-[#0d121b] dark:text-white">NeXLace
                                </h2>
                            </a>
                        </div>
                    </div>
                    <div class="hidden md:flex items-center gap-6">
                        <a class="text-sm font-medium text-text-main dark:text-[#e7ebf3] hover:text-primary dark:hover:text-primary transition-colors"
                            href="developer.php">Hire Talent</a>
                        <a class="text-sm font-medium text-text-main dark:text-[#e7ebf3] hover:text-primary dark:hover:text-primary transition-colors"
                            href="findwork.php">Find Work</a>
                        <a class="text-sm font-medium text-text-main dark:text-[#e7ebf3] hover:text-primary dark:hover:text-primary transition-colors"
                            href="messages.php">Messages</a>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <?php include 'includes/search_bar.php'; ?>
                    <button id="nav-help-btn"
                        class="text-text-sub hover:text-primary dark:text-gray-400 dark:hover:text-white">
                        <span class="material-symbols-outlined">help</span>
                    </button>
                    <button id="nav-notification-btn"
                        class="relative text-text-sub hover:text-primary dark:text-gray-400 dark:hover:text-white">
                        <span class="material-symbols-outlined">notifications</span>
                        <?php if ($unreadNotifications > 0): ?>
                            <span id="notification-badge"
                                class="absolute -top-1 -right-1 h-5 w-5 flex items-center justify-center rounded-full bg-red-500 text-white text-xs font-bold animate-pulse">
                                <?= $unreadNotifications > 9 ? '9+' : $unreadNotifications ?>
                            </span>
                        <?php endif; ?>
                    </button>
                    <div class="relative">
                        <button id="nav-profile-dropdown-btn"
                            class="h-8 w-8 rounded-full bg-primary text-white flex items-center justify-center font-bold text-sm cursor-pointer hover:bg-primary-dark transition-colors focus:outline-none overflow-hidden">
                            <span id="headerProfileInitial" class="<?= !empty($profileImage) ? 'hidden' : '' ?>">
                                <?= strtoupper($name[0]); ?>
                            </span>
                            <img id="headerProfileImage" src="<?= htmlspecialchars($profileImage); ?>" alt="Profile"
                                class="<?= empty($profileImage) ? 'hidden' : '' ?> h-full w-full object-cover" />
                        </button>
                        <div id="profile-dropdown"
                            class="hidden absolute right-0 mt-3 w-72 origin-top-right rounded-xl border border-[#e7ebf3] bg-white shadow-xl dark:border-[#2a3447] dark:bg-card-dark dark:shadow-black/50 z-50">
                            <div class="border-b border-[#e7ebf3] px-5 py-4 dark:border-[#2a3447]">
                                <p class="font-bold text-text-main dark:text-white"><?= $name; ?></p>
                                <p class="text-xs text-text-sub mt-0.5"><?= $email; ?></p>
                            </div>
                            <div class="p-2">
                                <a class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-text-main hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-[#1f2937] transition-colors"
                                    href="profilesetting.php">
                                    <span class="material-symbols-outlined text-[20px] text-text-sub">settings</span>
                                    Profile Settings
                                </a>
                                <a class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-text-main hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-[#1f2937] transition-colors"
                                    href="billingpayment.php">
                                    <span class="material-symbols-outlined text-[20px] text-text-sub">credit_card</span>
                                    Billing &amp; Payments
                                </a>
                                <a class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-text-main hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-[#1f2937] transition-colors"
                                    href="help.php">
                                    <span class="material-symbols-outlined text-[20px] text-text-sub">help</span>
                                    Help &amp; Support
                                </a>
                            </div>
                            <div class="border-t border-[#e7ebf3] p-2 dark:border-[#2a3447]">
                                <form action="logout.php" method="post"
                                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20 transition-colors">
                                    <button type="submit"
                                        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20 transition-colors">
                                        <span class="material-symbols-outlined text-[20px]">logout</span>
                                        Logout
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
        </header>
        <main class="flex-grow py-8">
            <div class="mx-auto max-w-[1200px] px-4 sm:px-6 lg:px-8 space-y-10">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <?php
                    $hour = (int) date('G'); // 0–23
                    if ($hour >= 0 && $hour < 12) {
                        $greeting = 'Good morning ☀️';
                    } elseif ($hour >= 12 && $hour < 17) {
                        $greeting = 'Good afternoon 🌤️';
                    } elseif ($hour >= 17 && $hour < 19) {
                        $greeting = 'Good evening 🌙';
                    } else {
                        $greeting = 'Good night 🌛';
                    }
                    ?>
                    <h1 class="text-3xl font-bold tracking-tight text-text-main dark:text-white">
                        <?= $greeting ?>, <?= htmlspecialchars($name) ?>
                    </h1>
                    <button id="hero-post-job-btn"
                        class="flex h-10 items-center justify-center rounded-full bg-primary px-6 text-sm font-bold text-white hover:bg-primary-dark transition-colors shadow-sm">
                        <span class="material-symbols-outlined text-[18px] mr-1">add</span> Post a job
                    </button>
                </div>
                <!-- ═══════ NEXBOT EMBEDDED PANEL ═══════ -->
                <!-- Exact same widget HTML, just embedded — no FAB needed -->
                <style>
                    /* Override widget's position:fixed to sit inline in the page */
                    #nexbot-panel {
                        position: relative !important;
                        bottom: auto !important;
                        right: auto !important;
                        width: 100% !important;
                        max-width: 100% !important;
                        height: 580px !important;
                        max-height: 580px !important;
                        opacity: 1 !important;
                        visibility: visible !important;
                        transform: none !important;
                        border-radius: 20px !important;
                        box-shadow: 0 8px 40px rgba(19, 91, 236, 0.12), 0 2px 8px rgba(0, 0, 0, 0.08) !important;
                        z-index: 10 !important;
                        /* Keep below dropdown menus */
                    }
                </style>

                <!-- Panel (same HTML as chatbot_widget.php, no FAB) -->
                <div id="nexbot-panel" role="dialog" aria-label="NeXBot Chat Assistant">
                    <!-- Header -->
                    <div id="nexbot-header">
                        <div id="nexbot-header-info">
                            <div id="nexbot-avatar">
                                <img src="assetes/nexbot.svg" alt="NeXBot"
                                    style="width:32px;height:32px;object-fit:contain;" />
                            </div>
                            <div id="nexbot-header-text">
                                <h3>NeXBot</h3>
                                <p><span id="nexbot-status-dot"></span>Always here to help</p>
                            </div>
                        </div>
                        <!-- No close button needed since it's embedded -->
                    </div>

                    <!-- Messages -->
                    <div id="nexbot-messages"></div>

                    <!-- Input Area -->
                    <div id="nexbot-input-area">
                        <textarea id="nexbot-input" placeholder="Ask me anything…" rows="1" maxlength="1000"></textarea>
                        <button id="nexbot-send-btn" type="button" aria-label="Send message">
                            <span class="material-symbols-outlined">send</span>
                        </button>
                    </div>

                    <!-- Footer -->
                    <div id="nexbot-footer">
                        Powered by <a href="https://ai.google.dev" target="_blank" rel="noopener">Gemini AI</a> ·
                        NeXLace Assistant
                    </div>
                </div>
                <!-- ═══════ END NEXBOT PANEL ═══════ -->
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-bold text-text-main dark:text-white">Find experts by category</h2>
                        <a class="text-primary font-bold text-sm hover:underline flex items-center" href="#">Browse all
                            categories <span class="material-symbols-outlined text-sm ml-1">arrow_forward</span></a>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div
                            class="relative overflow-hidden rounded-xl bg-[#004546] text-white p-6 flex flex-col justify-between min-h-[220px] group cursor-pointer">
                            <div class="relative z-10">
                                <p class="text-xs font-bold uppercase tracking-wider text-[#7ddddd] mb-2">Guided Tour
                                </p>
                                <h3 class="text-xl font-bold leading-tight mb-4">Book a consultation with an expert to
                                    review your project scope.</h3>
                                <button
                                    class="mt-4 bg-white text-[#004546] px-4 py-2 rounded-full text-sm font-bold hover:bg-gray-100 transition-colors">Learn
                                    more</button>
                            </div>
                            <span
                                class="absolute right-[-20px] bottom-[-20px] material-symbols-outlined text-[140px] opacity-10 rotate-12">support_agent</span>
                            <button class="absolute top-4 right-4 text-white/70 hover:text-white"><span
                                    class="material-symbols-outlined">close</span></button>
                        </div>
                        <div
                            class="group rounded-xl border border-[#e7ebf3] dark:border-[#2a3447] bg-card-light dark:bg-card-dark p-6 flex flex-col items-center text-center hover:border-primary/50 hover:shadow-lg transition-all cursor-pointer">
                            <div
                                class="mb-4 h-16 w-16 rounded-full bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center text-blue-600 dark:text-blue-400 group-hover:scale-110 transition-transform">
                                <span class="material-symbols-outlined text-[32px]">terminal</span>
                            </div>
                            <h3 class="text-lg font-bold text-text-main dark:text-white mb-2">Development &amp; IT</h3>
                            <p class="text-sm text-text-sub">Software architecture, web development, and more.</p>
                        </div>
                        <div
                            class="group rounded-xl border border-[#e7ebf3] dark:border-[#2a3447] bg-card-light dark:bg-card-dark p-6 flex flex-col items-center text-center hover:border-primary/50 hover:shadow-lg transition-all cursor-pointer">
                            <div
                                class="mb-4 h-16 w-16 rounded-full bg-purple-50 dark:bg-purple-900/20 flex items-center justify-center text-purple-600 dark:text-purple-400 group-hover:scale-110 transition-transform">
                                <span class="material-symbols-outlined text-[32px]">smart_toy</span>
                            </div>
                            <h3 class="text-lg font-bold text-text-main dark:text-white mb-2">AI Services</h3>
                            <p class="text-sm text-text-sub">AI development, integration, and consulting.</p>
                        </div>
                    </div>
                </div>
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-bold text-text-main dark:text-white">Help and resources</h2>
                        <a class="text-primary font-bold text-sm hover:underline flex items-center" href="#">View all
                            resources <span class="material-symbols-outlined text-sm ml-1">arrow_forward</span></a>
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div
                            class="col-span-1 lg:col-span-2 rounded-xl border border-[#e7ebf3] dark:border-[#2a3447] bg-card-light dark:bg-card-dark p-8 flex flex-col md:flex-row items-center justify-between gap-8 relative overflow-hidden">
                            <div class="relative z-10 max-w-xl">
                                <span class="text-xs font-bold uppercase text-text-sub mb-2 block">Get Started</span>
                                <h3 class="text-2xl font-bold text-text-main dark:text-white mb-4">Get started and
                                    connect with talent to get work done</h3>
                                <button
                                    class="rounded-full border border-gray-300 dark:border-gray-600 px-6 py-2 text-sm font-bold text-text-main dark:text-white hover:bg-gray-50 dark:hover:bg-[#1f2937]">Learn
                                    more</button>
                            </div>
                            <div class="hidden md:block">
                                <span
                                    class="material-symbols-outlined text-[120px] text-primary/20 -rotate-12">rocket_launch</span>
                            </div>
                        </div>
                        <div
                            class="rounded-xl border border-[#e7ebf3] dark:border-[#2a3447] bg-card-light dark:bg-card-dark p-6 flex justify-between items-center hover:shadow-sm transition-shadow">
                            <div>
                                <span class="text-xs font-bold uppercase text-text-sub mb-2 block">Payments</span>
                                <h3 class="text-lg font-bold text-text-main dark:text-white max-w-[200px]">Everything
                                    you need to know about payments</h3>
                            </div>
                            <span class="material-symbols-outlined text-4xl text-green-500">lock</span>
                        </div>
                        <div
                            class="rounded-xl border border-[#e7ebf3] dark:border-[#2a3447] bg-card-light dark:bg-card-dark p-6 flex justify-between items-center hover:shadow-sm transition-shadow">
                            <div>
                                <span class="text-xs font-bold uppercase text-text-sub mb-2 block">Trust &amp;
                                    Safety</span>
                                <h3 class="text-lg font-bold text-text-main dark:text-white max-w-[200px]">Keep yourself
                                    and others safe on NeXLace</h3>
                            </div>
                            <span class="material-symbols-outlined text-4xl text-blue-500">security</span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        <footer class="mt-12 bg-[#101622] text-white py-12">
            <div class="mx-auto max-w-[1200px] px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-sm">
                    <div>
                        <h4 class="font-bold mb-4">About Us</h4>
                        <ul class="space-y-2 text-gray-400">
                            <li><a class="hover:text-white" href="#">Feedback</a></li>
                            <li><a class="hover:text-white" href="#">Community</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="font-bold mb-4">Trust &amp; Safety</h4>
                        <ul class="space-y-2 text-gray-400">
                            <li><a class="hover:text-white" href="#">Help &amp; Support</a></li>
                            <li><a class="hover:text-white" href="#">NeXLace Foundation</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="font-bold mb-4">Terms of Service</h4>
                        <ul class="space-y-2 text-gray-400">
                            <li><a class="hover:text-white" href="#">Privacy Policy</a></li>
                            <li><a class="hover:text-white" href="#">Accessibility</a></li>
                        </ul>
                    </div>

                </div>
                <div
                    class="border-t border-gray-800 mt-12 pt-8 flex flex-col md:flex-row justify-between items-center text-xs text-gray-500">
                    <p>© 2024 NeXLace Global Inc.</p>
                    <div class="flex gap-4 mt-4 md:mt-0">
                        <a class="hover:text-white" href="#">Follow Us</a>
                        <div class="flex gap-3">
                            <span class="material-symbols-outlined text-[16px]">public</span>
                            <span class="material-symbols-outlined text-[16px]">alternate_email</span>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
    </div>
    <script src="js/search_engine.js"></script>
    <!-- NeXBot — embedded widget (same as all other pages) -->
    <link rel="stylesheet" href="css/chatbot.css?v=<?= time() ?>" />
    <script src="js/chatbot.js?v=<?= time() ?>"></script>
    <!-- Use SSE instead of polling for better performance -->
    <script src="js/notifications_sse.js"></script>
    <script>
        // Close profile dropdown when clicking outside
        document.addEventListener('click', function (event) {
            const dropdown = document.getElementById('profile-dropdown');
            const button = event.target.closest('button');

            if (dropdown && !dropdown.classList.contains('hidden') &&
                !dropdown.contains(event.target) &&
                (!button || !button.contains(event.target) && button.nextElementSibling !== dropdown)) {
                const container = dropdown.parentElement;
                if (!container.contains(event.target)) {
                    dropdown.classList.add('hidden');
                }
            }
        });
    </script>


    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const buttons = [
                { id: 'nav-help-btn', url: 'help.php' },
                { id: 'nav-notification-btn', url: 'notification.php' },
                { id: 'hero-post-job-btn', url: 'postjob.php' }
            ];

            buttons.forEach(btn => {
                const element = document.getElementById(btn.id);
                if (element) {
                    element.addEventListener('click', () => {
                        window.location.href = btn.url;
                    });
                }
            });

            // Profile Dropdown Toggle
            const profileDropdownBtn = document.getElementById('nav-profile-dropdown-btn');
            if (profileDropdownBtn) {
                profileDropdownBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    document.getElementById('profile-dropdown').classList.toggle('hidden');
                });
            }
        });
    </script>
</body>

</html>