<?php
require_once 'includes/auth_helper.php';
requireAuth();

// Include database connection
require_once 'config/database.php';
require_once 'config/security_headers.php';

$name = $_SESSION['name'];
$email = $_SESSION['email'] ?? '';
$userId = $_SESSION['user_id'] ?? null;
$profileImage = '';
$headline = '';
$bio = '';

// Initialize developers array
$developers = [];

// Fetch profile data and all developers from database
$unreadNotifications = 0;
try {
    require_once 'includes/db_helper.php';
    $conn = getDB();

    if ($conn && !empty($email)) {
        // Fetch current user's profile data
        $stmt = $conn->prepare("SELECT `Professional Headline`, `Bio`, `image` FROM register WHERE Email = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            $headline = $user['Professional Headline'] ?? '';
            $bio = $user['Bio'] ?? '';
            $profileImage = $user['image'] ?? '';
        }

        // Get unread notification count
        if ($userId) {
            $notifStmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = :user_id AND is_read = 0");
            $notifStmt->execute([':user_id' => $userId]);
            $notifResult = $notifStmt->fetch();
            $unreadNotifications = $notifResult['count'] ?? 0;
        }
    }

    // Fetch all developers
    if ($conn) {
        $stmt = $conn->prepare("
            SELECT d.*, r.Name, r.Email,
                   COALESCE(AVG(rev.rating), 0) as average_rating,
                   COUNT(rev.id) as total_reviews
            FROM developers d 
            JOIN register r ON d.user_id = r.id 
            LEFT JOIN reviews rev ON d.user_id = rev.reviewee_id
            GROUP BY d.id
            ORDER BY d.created_at DESC
        ");
        $stmt->execute();
        $developers = $stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Error fetching profile data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html class="light" lang="en">

<head>
    <link rel="icon" type="image/png" href="assetes/logo.png" />
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>NeXLace - Hire Talent</title>
    <link href="https://fonts.googleapis.com" rel="preconnect" />
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&amp;display=swap"
        rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
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
                        "surface-light": "#ffffff",
                        "surface-dark": "#151c2b",
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
                        <a class="text-sm font-bold text-primary dark:text-primary hover:text-primary dark:hover:text-primary transition-colors"
                            href="developer.php">Hire Talent</a>
                        <a class="text-sm font-medium text-text-main dark:text-[#e7ebf3] hover:text-primary dark:hover:text-primary transition-colors"
                            href="findwork.php">Find Work</a>
                        <a class="text-sm font-medium text-text-main dark:text-[#e7ebf3] hover:text-primary dark:hover:text-primary transition-colors"
                            href="messages.php">Messages</a>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <?php include 'includes/search_bar.php'; ?>
                    <button onclick="window.location.href='help.php'"
                        class="text-text-sub hover:text-primary dark:text-gray-400 dark:hover:text-white">
                        <span class="material-symbols-outlined">help</span>
                    </button>
                    <button onclick="window.location.href='notification.php'"
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
                        <button onclick="document.getElementById('profile-dropdown').classList.toggle('hidden')"
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
                                    href="#">
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
        <div class="layout-container flex h-full grow flex-col max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8">
            <div
                class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-8 border-b border-slate-200 dark:border-slate-800 pb-6">
                <div class="flex flex-col gap-2">
                    <h1
                        class="text-slate-900 dark:text-white text-3xl md:text-4xl font-black leading-tight tracking-[-0.033em]">
                        Top Developers for your Projects</h1>
                    <p class="text-slate-500 dark:text-slate-400 text-lg font-normal">Browse our curated list of vetted
                        experts ready to start.</p>
                </div>
                <div class="flex gap-3">
                    <button
                        class="flex items-center gap-2 px-4 py-2 bg-surface-light dark:bg-surface-dark border border-slate-200 dark:border-slate-700 rounded-lg text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
                        id="savedTalentBtn">
                        <span class="material-symbols-outlined text-[20px]">bookmark</span>
                        Saved Talent
                        <span id="saved-count-badge"
                            class="hidden bg-red-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full">0</span>
                    </button>
                </div>
            </div>
            <div class="flex flex-col lg:flex-row gap-8 items-start">
                <aside class="w-full lg:w-80 shrink-0 space-y-8 lg:sticky lg:top-24">
                    <div class="space-y-3">
                        <label class="text-sm font-semibold text-slate-900 dark:text-white">Search Talent</label>
                        <div
                            class="flex w-full items-center rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 overflow-hidden focus-within:ring-2 focus-within:ring-primary/50 transition-all shadow-sm">
                            <div class="text-slate-400 pl-3 pt-1">
                                <span class="material-symbols-outlined">search</span>
                            </div>
                            <input id="searchByName"
                                class="w-full bg-transparent border-none py-3 px-3 text-slate-900 dark:text-white placeholder:text-slate-400 focus:ring-0 text-sm"
                                placeholder="Search by name" />
                        </div>
                    </div>
                    <div
                        class="space-y-4 p-5 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
                        <div class="flex justify-between items-center">
                            <p class="text-slate-900 dark:text-white text-sm font-semibold">Hourly Rate</p>
                            <span class="text-xs text-slate-500 font-medium">INR</span>
                        </div>
                        <div class="pt-2 pb-2">
                            <div
                                class="flex items-center justify-between mb-3 bg-slate-50 dark:bg-slate-700/50 rounded-lg p-3">
                                <div class="text-center">
                                    <span class="text-xs text-slate-500 dark:text-slate-400 block">Min</span>
                                    <span id="minRateDisplay" class="text-lg font-bold text-primary">₹1k</span>
                                </div>
                                <div class="text-center">
                                    <span id="rateDifference"
                                        class="text-sm font-bold text-green-600 dark:text-green-400 block">₹199k</span>
                                    <span class="text-slate-400">—</span>
                                </div>
                                <div class="text-center">
                                    <span class="text-xs text-slate-500 dark:text-slate-400 block">Max</span>
                                    <span id="maxRateDisplay" class="text-lg font-bold text-primary">₹200k</span>
                                </div>
                            </div>
                            <div class="relative h-1 mt-6 mb-4">
                                <div class="absolute inset-0 rounded-full bg-slate-200 dark:bg-slate-700"></div>
                                <div id="sliderTrack" class="absolute top-0 bottom-0 rounded-full bg-primary"
                                    style="left: 0%; right: 0%;"></div>
                                <input type="range" id="minRateSlider" min="1" max="200" value="1"
                                    class="absolute w-full h-1 bg-transparent appearance-none pointer-events-none [&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:h-4 [&::-webkit-slider-thumb]:w-4 [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-primary [&::-webkit-slider-thumb]:shadow-lg [&::-webkit-slider-thumb]:ring-2 [&::-webkit-slider-thumb]:ring-white [&::-webkit-slider-thumb]:dark:ring-slate-800 [&::-webkit-slider-thumb]:cursor-pointer [&::-webkit-slider-thumb]:hover:scale-110 [&::-webkit-slider-thumb]:transition-transform [&::-moz-range-thumb]:pointer-events-auto [&::-moz-range-thumb]:appearance-none [&::-moz-range-thumb]:h-4 [&::-moz-range-thumb]:w-4 [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:bg-primary [&::-moz-range-thumb]:border-0 [&::-moz-range-thumb]:shadow-lg [&::-moz-range-thumb]:cursor-pointer"
                                    style="z-index: 3;" />
                                <input type="range" id="maxRateSlider" min="1" max="200" value="200"
                                    class="absolute w-full h-1 bg-transparent appearance-none pointer-events-none [&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:h-4 [&::-webkit-slider-thumb]:w-4 [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-primary [&::-webkit-slider-thumb]:shadow-lg [&::-webkit-slider-thumb]:ring-2 [&::-webkit-slider-thumb]:ring-white [&::-webkit-slider-thumb]:dark:ring-slate-800 [&::-webkit-slider-thumb]:cursor-pointer [&::-webkit-slider-thumb]:hover:scale-110 [&::-webkit-slider-thumb]:transition-transform [&::-moz-range-thumb]:pointer-events-auto [&::-moz-range-thumb]:appearance-none [&::-moz-range-thumb]:h-4 [&::-moz-range-thumb]:w-4 [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:bg-primary [&::-moz-range-thumb]:border-0 [&::-moz-range-thumb]:shadow-lg [&::-moz-range-thumb]:cursor-pointer"
                                    style="z-index: 4;" />
                            </div>
                        </div>
                        <div class="flex justify-between text-sm text-slate-500 dark:text-slate-400">
                            <span>₹1k</span>
                            <span>₹200k</span>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-semibold text-slate-900 dark:text-white">Tech Stack</label>
                            <button id="clearTechStack"
                                class="text-xs text-primary font-medium hover:underline">Clear</button>
                        </div>
                        <div id="techStackCheckboxes" class="space-y-2">
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input
                                    class="size-4 rounded border-slate-300 text-primary focus:ring-primary dark:bg-slate-800 dark:border-slate-600"
                                    type="checkbox" value="python" />
                                <span
                                    class="text-sm text-slate-600 dark:text-slate-300 group-hover:text-primary transition-colors">Python</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input
                                    class="size-4 rounded border-slate-300 text-primary focus:ring-primary dark:bg-slate-800 dark:border-slate-600"
                                    type="checkbox" value="java" />
                                <span
                                    class="text-sm text-slate-600 dark:text-slate-300 group-hover:text-primary transition-colors">Java</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input
                                    class="size-4 rounded border-slate-300 text-primary focus:ring-primary dark:bg-slate-800 dark:border-slate-600"
                                    type="checkbox" value="javascript" />
                                <span
                                    class="text-sm text-slate-600 dark:text-slate-300 group-hover:text-primary transition-colors">JavaScript</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input
                                    class="size-4 rounded border-slate-300 text-primary focus:ring-primary dark:bg-slate-800 dark:border-slate-600"
                                    type="checkbox" value="c/c++" />
                                <span
                                    class="text-sm text-slate-600 dark:text-slate-300 group-hover:text-primary transition-colors">C/C++</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input
                                    class="size-4 rounded border-slate-300 text-primary focus:ring-primary dark:bg-slate-800 dark:border-slate-600"
                                    type="checkbox" value="c#" />
                                <span
                                    class="text-sm text-slate-600 dark:text-slate-300 group-hover:text-primary transition-colors">C#</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input
                                    class="size-4 rounded border-slate-300 text-primary focus:ring-primary dark:bg-slate-800 dark:border-slate-600"
                                    type="checkbox" value="php" />
                                <span
                                    class="text-sm text-slate-600 dark:text-slate-300 group-hover:text-primary transition-colors">PHP</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input
                                    class="size-4 rounded border-slate-300 text-primary focus:ring-primary dark:bg-slate-800 dark:border-slate-600"
                                    type="checkbox" value="sql" />
                                <span
                                    class="text-sm text-slate-600 dark:text-slate-300 group-hover:text-primary transition-colors">SQL</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input
                                    class="size-4 rounded border-slate-300 text-primary focus:ring-primary dark:bg-slate-800 dark:border-slate-600"
                                    type="checkbox" value="ruby" />
                                <span
                                    class="text-sm text-slate-600 dark:text-slate-300 group-hover:text-primary transition-colors">Ruby</span>
                            </label>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-semibold text-slate-900 dark:text-white">Professional
                                Title</label>
                            <button id="clearProfessionalTitle"
                                class="text-xs text-primary font-medium hover:underline">Clear</button>
                        </div>
                        <div id="professionalTitleCheckboxes" class="space-y-2">
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input
                                    class="size-4 rounded border-slate-300 text-primary focus:ring-primary dark:bg-slate-800 dark:border-slate-600"
                                    type="checkbox" value="software engineer" />
                                <span
                                    class="text-sm text-slate-600 dark:text-slate-300 group-hover:text-primary transition-colors">Software
                                    Engineer/Developer</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input
                                    class="size-4 rounded border-slate-300 text-primary focus:ring-primary dark:bg-slate-800 dark:border-slate-600"
                                    type="checkbox" value="frontend" />
                                <span
                                    class="text-sm text-slate-600 dark:text-slate-300 group-hover:text-primary transition-colors">Frontend
                                    Developer</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input
                                    class="size-4 rounded border-slate-300 text-primary focus:ring-primary dark:bg-slate-800 dark:border-slate-600"
                                    type="checkbox" value="backend" />
                                <span
                                    class="text-sm text-slate-600 dark:text-slate-300 group-hover:text-primary transition-colors">Backend
                                    Developer</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input
                                    class="size-4 rounded border-slate-300 text-primary focus:ring-primary dark:bg-slate-800 dark:border-slate-600"
                                    type="checkbox" value="full stack" />
                                <span
                                    class="text-sm text-slate-600 dark:text-slate-300 group-hover:text-primary transition-colors">Full
                                    Stack Developer</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input
                                    class="size-4 rounded border-slate-300 text-primary focus:ring-primary dark:bg-slate-800 dark:border-slate-600"
                                    type="checkbox" value="mobile" />
                                <span
                                    class="text-sm text-slate-600 dark:text-slate-300 group-hover:text-primary transition-colors">Mobile
                                    Developer</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input
                                    class="size-4 rounded border-slate-300 text-primary focus:ring-primary dark:bg-slate-800 dark:border-slate-600"
                                    type="checkbox" value="devops" />
                                <span
                                    class="text-sm text-slate-600 dark:text-slate-300 group-hover:text-primary transition-colors">DevOps
                                    Engineer</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input
                                    class="size-4 rounded border-slate-300 text-primary focus:ring-primary dark:bg-slate-800 dark:border-slate-600"
                                    type="checkbox" value="cloud" />
                                <span
                                    class="text-sm text-slate-600 dark:text-slate-300 group-hover:text-primary transition-colors">Cloud
                                    Engineer/Architect</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input
                                    class="size-4 rounded border-slate-300 text-primary focus:ring-primary dark:bg-slate-800 dark:border-slate-600"
                                    type="checkbox" value="qa" />
                                <span
                                    class="text-sm text-slate-600 dark:text-slate-300 group-hover:text-primary transition-colors">QA
                                    Engineer/Tester</span>
                            </label>
                        </div>
                    </div>
                </aside>
                <main class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                        <p class="text-slate-600 dark:text-slate-400 font-medium">Showing <span id="resultsCount"
                                class="text-slate-900 dark:text-white font-bold"><?= count($developers) ?></span>
                            results</p>
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-slate-500 dark:text-slate-400">Sort by:</span>
                            <div class="relative">
                                <button id="sortBtn" onclick="toggleSortDropdown()"
                                    class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white hover:text-primary transition-colors">
                                    <span id="sortLabel">Best Match</span>
                                    <span class="material-symbols-outlined text-[20px]">expand_more</span>
                                </button>
                                <div id="sortDropdown"
                                    class="hidden absolute right-0 top-full mt-1 w-40 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-100 dark:border-slate-700 p-1 z-10">
                                    <button onclick="sortDevelopers('best_match'); closeSortDropdown();"
                                        class="block w-full text-left px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 rounded">Best
                                        Match</button>
                                    <button onclick="sortDevelopers('price_high'); closeSortDropdown();"
                                        class="block w-full text-left px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 rounded">High
                                        Price</button>
                                    <button onclick="sortDevelopers('price_low'); closeSortDropdown();"
                                        class="block w-full text-left px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 rounded">Lowest
                                        Price</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="developers-grid" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                        <?php
                        // Helper function to format numbers (must be defined outside the loop)
                        if (!function_exists('formatNumber')) {
                            function formatNumber($num)
                            {
                                if ($num >= 1000000) {
                                    return round($num / 1000000, 1) . 'M';
                                } elseif ($num >= 1000) {
                                    return round($num / 1000, 1) . 'K';
                                }
                                return $num;
                            }
                        }
                        ?>
                        <?php if (!empty($developers)): ?>
                            <?php foreach ($developers as $dev):
                                // Format name for display (First name + Last initial)
                                $devName = htmlspecialchars($dev['Name'] ?? 'Developer');
                                $nameParts = explode(' ', $devName);
                                $displayName = $nameParts[0] . (isset($nameParts[1]) ? ' ' . strtoupper($nameParts[1][0]) . '.' : '');

                                // Format rate
                                $devRate = number_format($dev['rate'] ?? 0);


                                // Get skills as comma separated
                                $devSkills = explode(',', $dev['skills'] ?? '');
                                $devSkills = array_map('trim', $devSkills);
                                $displaySkills = array_slice($devSkills, 0, 3);
                                $remainingSkills = count($devSkills) - 3;

                                // Format rating stats
                                $avgRating = number_format((float)($dev['average_rating'] ?? 0), 1);
                                $totalReviews = (int)($dev['total_reviews'] ?? 0);

                                // Profile image
                                $devImage = !empty($dev['image_path']) ? htmlspecialchars($dev['image_path']) : '';
                                ?>
                                <div class="developer-card group flex flex-col rounded-xl border border-slate-200 dark:border-slate-800 bg-surface-light dark:bg-surface-dark p-5 shadow-sm hover:shadow-lg hover:border-primary/50 transition-all duration-300"
                                    data-rate="<?= intval($dev['rate'] ?? 0) / 1000 ?>" data-rating="<?= $avgRating ?>"
                                    data-skills="<?= htmlspecialchars(strtolower($dev['skills'] ?? '')) ?>"
                                    data-jobtitle="<?= htmlspecialchars(strtolower($dev['title'] ?? '')) ?>">
                                    <a href="devprofiles.php?id=<?= $dev['id'] ?>">
                                        <div class="mb-4 flex items-start justify-between gap-3">
                                            <div class="flex gap-3">
                                                <div class="relative">
                                                    <?php if (!empty($devImage)): ?>
                                                        <div class="size-14 rounded-full bg-cover bg-center border border-slate-100 dark:border-slate-700"
                                                            style='background-image: url("<?= $devImage ?>");'>
                                                        </div>
                                                    <?php else: ?>
                                                        <div
                                                            class="size-14 rounded-full bg-primary flex items-center justify-center text-white font-bold text-lg border border-slate-100 dark:border-slate-700">
                                                            <?= strtoupper($devName[0]) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="absolute bottom-0 right-0 size-3.5 rounded-full bg-green-500 border-2 border-white dark:border-slate-800"
                                                        title="Online"></div>
                                                </div>
                                                <div>
                                                    <div class="flex items-center gap-1">
                                                        <h3
                                                            class="font-bold text-slate-900 dark:text-white group-hover:text-primary transition-colors overflow-hidden max-w-20 truncate">
                                                            <?= $displayName ?>
                                                        </h3>
                                                        <span class="material-symbols-outlined text-primary text-[18px] filled"
                                                            style="font-variation-settings: 'FILL' 1;">verified</span>
                                                    </div>
                                                    <p class="text-sm text-slate-500 dark:text-slate-400 line-clamp-1">
                                                        <?= htmlspecialchars($dev['title'] ?? 'Developer') ?>
                                                    </p>
                                                    <div class="flex items-center gap-1 mt-0.5">
                                                        <span class="material-symbols-outlined text-yellow-500 text-[16px]"
                                                            style="font-variation-settings: 'FILL' <?= (float)$avgRating >= 1 ? '1' : '0' ?>;">star</span>
                                                        <span
                                                            class="text-xs font-bold text-slate-900 dark:text-white"><?= $avgRating ?></span>
                                                        <span class="text-xs text-slate-400"><?= $totalReviews > 0 ? '(' . $totalReviews . ')' : '(New)' ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <div class="font-bold text-lg text-slate-900 dark:text-white">
                                                    ₹<?= formatNumber($dev['rate'] ?? 0) ?><span
                                                        class="text-sm font-normal text-slate-500">/hr</span>
                                                </div>
                                            </div>

                                        </div>
                                        <div
                                            class="mb-4 grid grid-cols-2 gap-px bg-slate-100 dark:bg-slate-700/50 rounded-lg overflow-hidden border border-slate-100 dark:border-slate-700/50">
                                            <div class="bg-white dark:bg-surface-dark p-2 text-center">
                                                <span
                                                    class="block text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wider">Location</span>
                                                <span
                                                    class="block font-bold text-slate-900 dark:text-white text-sm truncate"><?= htmlspecialchars($dev['location'] ?? 'Remote') ?></span>
                                            </div>
                                            <div class="bg-white dark:bg-surface-dark p-2 text-center">
                                                <span
                                                    class="block text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wider">Available</span>
                                                <span
                                                    class="block font-bold text-slate-900 dark:text-white text-sm"><?= ($dev['availability'] ?? 'more_than_30') === 'more_than_30' ? '30+ hrs' : ($dev['availability'] === 'less_than_30' ? '<30 hrs' : 'Flexible') ?></span>
                                            </div>
                                        </div>
                                        <?php
                                        $bioText = $dev['bio'] ?? '';
                                        // Check if bio is empty, contains only the label text, or is invalid
                                        $defaultBio = 'Professional developer ready to help with your projects.';
                                        if (empty($bioText) || preg_match('/^(professional\s*overview\s*)+$/i', trim($bioText))) {
                                            $bioDisplay = $defaultBio;
                                        } else {
                                            $bioDisplay = $bioText;
                                        }
                                        ?>
                                        <p class="text-sm text-slate-600 dark:text-slate-300 mb-4 line-clamp-2">
                                            <?= htmlspecialchars(substr($bioDisplay, 0, 150)) ?>...
                                        </p>
                                        <div class="mb-6 flex flex-wrap gap-2">
                                            <?php foreach ($displaySkills as $skill): ?>
                                                <?php if (!empty(trim($skill))): ?>
                                                    <span
                                                        class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300 border border-transparent hover:border-slate-300 transition-colors cursor-default"><?= htmlspecialchars(trim($skill)) ?></span>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                            <?php if ($remainingSkills > 0): ?>
                                                <span
                                                    class="rounded-md bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-400 dark:bg-slate-800/50 dark:text-slate-500">+<?= $remainingSkills ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mt-auto flex gap-3">
                                            <button
                                                class="flex-1 rounded-lg bg-primary py-2.5 text-sm font-semibold text-white shadow-md hover:bg-blue-700 hover:shadow-lg transition-all active:scale-95"
                                                data-dev-id="<?= $dev['user_id'] ?>" data-dev-name="<?= $displayName ?>"
                                                data-dev-image="<?= $devImage ?>">Invite
                                                to Job</button>
                                            <button
                                                class="flex items-center justify-center rounded-lg border border-slate-200 px-3 text-slate-400 hover:bg-slate-50 hover:text-red-500 hover:border-red-200 dark:border-slate-700 dark:hover:bg-slate-800 dark:hover:text-red-500 transition-all save-developer-btn"
                                                title="Save to shortlist" data-id="<?= htmlspecialchars($dev['user_id']) ?>"
                                                data-name="<?= $displayName ?>"
                                                data-title="<?= htmlspecialchars($dev['title'] ?? 'Developer') ?>"
                                                data-rate="<?= $devRate ?>" data-image="<?= $devImage ?>">
                                                <span class="material-symbols-outlined text-[22px]">favorite</span>
                                            </button>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>


                    </div>
                </main>
            </div>
        </div>
        <!-- Invite Modal -->
        <div id="invite-modal"
            class="fixed inset-0 z-[100] hidden flex items-center justify-center bg-black/60 backdrop-blur-[2px] opacity-0 transition-opacity duration-300">
            <div class="relative w-full max-w-[500px] scale-95 transform rounded-2xl bg-white p-6 shadow-2xl transition-all duration-300 dark:bg-[#1e293b]"
                id="modal-content">
                <!-- Close Button -->
                <button onclick="closeModal()"
                    class="absolute right-5 top-5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <span class="material-symbols-outlined text-[24px]">close</span>
                </button>

                <!-- Header -->
                <h2 class="text-xl font-bold text-slate-900 dark:text-white">Invite to Job</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Send an invitation to start a project with
                    this talent.</p>

                <!-- Form -->
                <div class="mt-6 space-y-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-900 dark:text-white">Enter work
                            type</label>
                        <input type="text" placeholder="e.g. Full-time Contract, Hourly Project"
                            class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-primary focus:ring-1 focus:ring-primary dark:border-slate-600 dark:bg-slate-800 dark:text-white" />
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-900 dark:text-white">Enter working
                            E-mail id</label>
                        <input type="email" placeholder="yourname@company.com"
                            class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-primary focus:ring-1 focus:ring-primary dark:border-slate-600 dark:bg-slate-800 dark:text-white" />
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-900 dark:text-white">Work
                            details</label>
                        <textarea placeholder="Describe the project scope, requirements, and timeline..."
                            class="h-32 w-full resize-none rounded-lg border border-slate-200 px-4 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-primary focus:ring-1 focus:ring-primary dark:border-slate-600 dark:bg-slate-800 dark:text-white"></textarea>
                    </div>
                </div>

                <!-- Actions -->
                <div class="mt-8 flex justify-end gap-3">
                    <button onclick="closeModal()"
                        class="rounded-lg border border-slate-200 bg-white px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">
                        Cancel
                    </button>
                    <button
                        class="rounded-lg bg-[#135bec] px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-700 shadow-sm shadow-blue-200 dark:shadow-none">
                        Send Invitation
                    </button>
                </div>
            </div>
        </div>

        <!-- Saved Talent Modal -->
        <div id="saved-talent-modal"
            class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/50 backdrop-blur-sm transition-opacity opacity-0">
            <div id="saved-talent-content"
                class="w-full max-w-3xl mx-4 max-h-[80vh] bg-white dark:bg-slate-900 rounded-2xl shadow-2xl transform scale-95 transition-all overflow-hidden">
                <!-- Header -->
                <div class="flex items-center justify-between p-6 border-b border-slate-200 dark:border-slate-700">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-red-500 text-[28px]"
                            style="font-variation-settings: 'FILL' 1;">favorite</span>
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white">Saved Talent</h2>
                        <span id="saved-count"
                            class="hidden bg-primary text-white text-xs font-bold px-2 py-0.5 rounded-full">0</span>
                    </div>
                    <button onclick="closeSavedTalentModal()"
                        class="p-2 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition-colors">
                        <span class="material-symbols-outlined text-slate-500">close</span>
                    </button>
                </div>
                <!-- Content -->
                <div class="p-6 overflow-y-auto max-h-[calc(80vh-88px)]">
                    <!-- Empty State -->
                    <div id="empty-saved-state" class="text-center py-12">
                        <span
                            class="material-symbols-outlined text-slate-300 dark:text-slate-600 text-[64px]">favorite_border</span>
                        <h3 class="mt-4 text-lg font-semibold text-slate-900 dark:text-white">No saved talent yet</h3>
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Click the heart icon on developer
                            cards to save them here.</p>
                    </div>
                    <!-- Saved Developers Grid -->
                    <div id="saved-developers-grid" class="hidden grid grid-cols-1 md:grid-cols-2 gap-4"></div>
                </div>
            </div>
        </div>

        <script>
            // Store current developer details
            let currentDev = {
                id: '',
                name: '',
                image: ''
            };

            // Define modal functions globally
            window.openModal = function () {
                const modal = document.getElementById('invite-modal');
                const modalContent = document.getElementById('modal-content');
                if (modal && modalContent) {
                    modal.classList.remove('hidden');
                    setTimeout(() => {
                        modal.classList.remove('opacity-0');
                        modalContent.classList.remove('scale-95');
                        modalContent.classList.add('scale-100');
                    }, 10);
                }
            };

            window.closeModal = function () {
                const modal = document.getElementById('invite-modal');
                const modalContent = document.getElementById('modal-content');
                if (modal && modalContent) {
                    modal.classList.add('opacity-0');
                    modalContent.classList.remove('scale-100');
                    modalContent.classList.add('scale-95');
                    setTimeout(() => {
                        modal.classList.add('hidden');
                    }, 300);
                }
            };

            // Send Invitation Logic
            document.querySelector('#invite-modal button.bg-\\[\\#135bec\\]').addEventListener('click', function () {
                const workType = document.querySelector('#invite-modal input[placeholder="e.g. Full-time Contract, Hourly Project"]').value;
                const workEmail = document.querySelector('#invite-modal input[type="email"]').value;
                const workDetails = document.querySelector('#invite-modal textarea').value;

                if (!workType || !workDetails) {
                    alert('Please fill in the work type and details.');
                    return;
                }

                const params = new URLSearchParams({
                    action: 'invite',
                    dev_id: currentDev.id,
                    dev_name: currentDev.name,
                    dev_image: currentDev.image,
                    work_type: workType,
                    work_email: workEmail,
                    work_details: workDetails
                });

                window.location.href = 'messages.php?' + params.toString();
            });

            // Invite to Job button handler
            document.addEventListener('click', function (e) {
                const btn = e.target.closest('button');
                if (btn) {
                    const text = btn.textContent.replace(/\s+/g, ' ').trim();
                    if (text === 'Invite to Job') {
                        e.preventDefault();
                        currentDev.id = btn.getAttribute('data-dev-id') || '';
                        currentDev.name = btn.getAttribute('data-dev-name') || '';
                        currentDev.image = btn.getAttribute('data-dev-image') || '';
                        window.openModal();
                    }
                }
                if (e.target.id === 'invite-modal') {
                    window.closeModal();
                }
            });

            // ========== SORTDROPDOWN TOGGLE ==========
            window.toggleSortDropdown = function () {
                const dropdown = document.getElementById('sortDropdown');
                if (dropdown) {
                    dropdown.classList.toggle('hidden');
                }
            };

            window.closeSortDropdown = function () {
                const dropdown = document.getElementById('sortDropdown');
                if (dropdown) {
                    dropdown.classList.add('hidden');
                }
            };

            // Close dropdown when clicking outside
            document.addEventListener('click', function (e) {
                const sortBtn = document.getElementById('sortBtn');
                const dropdown = document.getElementById('sortDropdown');
                if (dropdown && sortBtn && !sortBtn.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.classList.add('hidden');
                }
            });

            // ========== SORTING FUNCTIONALITY ==========
            window.sortDevelopers = function (criteria) {
                const grid = document.getElementById('developers-grid');
                if (!grid) return;

                const cards = Array.from(grid.querySelectorAll('.developer-card'));

                cards.sort((a, b) => {
                    if (criteria === 'best_match') {
                        const ratingA = parseFloat(a.getAttribute('data-rating')) || 0;
                        const ratingB = parseFloat(b.getAttribute('data-rating')) || 0;
                        return ratingB - ratingA;
                    } else if (criteria === 'price_low') {
                        const rateA = parseFloat(a.getAttribute('data-rate')) || 0;
                        const rateB = parseFloat(b.getAttribute('data-rate')) || 0;
                        return rateA - rateB;
                    } else if (criteria === 'price_high') {
                        const rateA = parseFloat(a.getAttribute('data-rate')) || 0;
                        const rateB = parseFloat(b.getAttribute('data-rate')) || 0;
                        return rateB - rateA;
                    }
                    return 0;
                });

                // Update label
                const sortLabel = document.getElementById('sortLabel');
                if (sortLabel) {
                    let label = 'Best Match';
                    if (criteria === 'price_high') label = 'High Price';
                    if (criteria === 'price_low') label = 'Lowest Price';
                    sortLabel.textContent = label;
                }

                cards.forEach(card => grid.appendChild(card));
            };

            // ========== SAVED TALENT FUNCTIONALITY ==========
            function getSavedDevelopers() {
                try {
                    const saved = localStorage.getItem('savedDevelopers');
                    return saved ? JSON.parse(saved) : [];
                } catch (e) {
                    return [];
                }
            }

            function saveDevelopers(developers) {
                localStorage.setItem('savedDevelopers', JSON.stringify(developers));
                updateSavedCount();
            }

            function updateSavedCount() {
                const count = getSavedDevelopers().length;
                const modalBadge = document.getElementById('saved-count');
                const btnBadge = document.getElementById('saved-count-badge');

                if (modalBadge) {
                    modalBadge.textContent = count;
                    modalBadge.classList.toggle('hidden', count === 0);
                }
                if (btnBadge) {
                    btnBadge.textContent = count;
                    btnBadge.classList.toggle('hidden', count === 0);
                }
            }

            function toggleSaveDeveloper(button) {
                const id = button.getAttribute('data-id');
                const name = button.getAttribute('data-name');
                const title = button.getAttribute('data-title');
                const rate = button.getAttribute('data-rate');
                const image = button.getAttribute('data-image');

                if (!id) return;

                const saved = getSavedDevelopers();
                const existingIndex = saved.findIndex(d => d.id === id);
                const heartIcon = button.querySelector('.material-symbols-outlined');

                if (existingIndex > -1) {
                    saved.splice(existingIndex, 1);
                    if (heartIcon) {
                        heartIcon.style.fontVariationSettings = "'FILL' 0";
                        heartIcon.classList.remove('text-red-500');
                        heartIcon.classList.add('text-slate-400');
                    }
                    button.classList.remove('bg-red-50', 'border-red-200');
                } else {
                    saved.push({ id, name, title, rate, image });
                    if (heartIcon) {
                        heartIcon.style.fontVariationSettings = "'FILL' 1";
                        heartIcon.classList.add('text-red-500');
                        heartIcon.classList.remove('text-slate-400');
                    }
                    button.classList.add('bg-red-50', 'border-red-200');
                }

                saveDevelopers(saved);
            }

            function updateButtonState(button) {
                const id = button.getAttribute('data-id');
                const saved = getSavedDevelopers();
                const isSaved = saved.some(d => d.id === id);
                const heartIcon = button.querySelector('.material-symbols-outlined');

                if (isSaved) {
                    if (heartIcon) {
                        heartIcon.style.fontVariationSettings = "'FILL' 1";
                        heartIcon.classList.add('text-red-500');
                        heartIcon.classList.remove('text-slate-400');
                    }
                    button.classList.add('bg-red-50', 'border-red-200');
                } else {
                    if (heartIcon) {
                        heartIcon.style.fontVariationSettings = "'FILL' 0";
                        heartIcon.classList.remove('text-red-500');
                        heartIcon.classList.add('text-slate-400');
                    }
                    button.classList.remove('bg-red-50', 'border-red-200');
                }
            }

            // Initialize heart buttons (adds data attributes to static cards dynamically)
            function initializeHeartButtons() {
                const grid = document.getElementById('developers-grid');
                if (!grid) return;

                // Find all developer cards (add class if missing)
                const allCards = grid.querySelectorAll('[class*="flex flex-col rounded-xl border"]');
                allCards.forEach((card, index) => {
                    if (!card.classList.contains('developer-card')) {
                        card.classList.add('developer-card');
                    }
                    // Add data-rate if missing (extract from displayed rate)
                    if (!card.getAttribute('data-rate')) {
                        const rateEl = card.querySelector('.font-bold.text-lg');
                        if (rateEl) {
                            const rateText = rateEl.textContent.match(/₹(\d+)/);
                            if (rateText) card.setAttribute('data-rate', rateText[1]);
                        }
                    }
                    // Add data-rating if missing (extract from star rating)
                    if (!card.getAttribute('data-rating')) {
                        const ratingEl = card.querySelector('.text-xs.font-bold');
                        if (ratingEl) {
                            card.setAttribute('data-rating', ratingEl.textContent.trim());
                        }
                    }
                });

                // Find all save buttons
                const saveButtons = grid.querySelectorAll('button[title="Save to shortlist"]');
                saveButtons.forEach((btn, index) => {
                    // Add class if missing
                    if (!btn.classList.contains('save-developer-btn')) {
                        btn.classList.add('save-developer-btn');
                    }

                    // Add data attributes if missing
                    if (!btn.getAttribute('data-id')) {
                        const card = btn.closest('.developer-card') || btn.closest('[class*="flex flex-col rounded-xl"]');
                        if (card) {
                            const nameEl = card.querySelector('h3');
                            const titleEl = card.querySelector('p.text-sm.text-slate-500');
                            const rateEl = card.querySelector('.font-bold.text-lg');
                            const imgEl = card.querySelector('.size-14.rounded-full');

                            btn.setAttribute('data-id', 'static_' + index);
                            btn.setAttribute('data-name', nameEl ? nameEl.textContent.trim() : 'Developer');
                            btn.setAttribute('data-title', titleEl ? titleEl.textContent.trim() : '');
                            btn.setAttribute('data-rate', rateEl ? rateEl.textContent.replace(/[^0-9]/g, '') : '0');

                            if (imgEl) {
                                const bgImg = imgEl.style.backgroundImage;
                                const imgUrl = bgImg.replace(/url\(['"]?([^'"]+)['"]?\)/, '$1');
                                btn.setAttribute('data-image', imgUrl);
                            }
                        }
                    }

                    // Set initial state
                    updateButtonState(btn);

                    // Add click handler (remove existing to avoid duplicates)
                    const newBtn = btn.cloneNode(true);
                    btn.parentNode.replaceChild(newBtn, btn);

                    newBtn.addEventListener('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        toggleSaveDeveloper(this);
                    });
                });

                updateSavedCount();
            }

            // Open Saved Talent Modal
            window.openSavedTalentModal = function () {
                const modal = document.getElementById('saved-talent-modal');
                const content = document.getElementById('saved-talent-content');
                const emptyState = document.getElementById('empty-saved-state');
                const grid = document.getElementById('saved-developers-grid');
                const saved = getSavedDevelopers();

                if (saved.length === 0) {
                    emptyState.classList.remove('hidden');
                    grid.classList.add('hidden');
                } else {
                    emptyState.classList.add('hidden');
                    grid.classList.remove('hidden');
                    grid.innerHTML = saved.map(dev => `
                        <div class="flex items-center gap-4 p-4 bg-slate-50 dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700">
                            <div class="size-12 rounded-full bg-cover bg-center border border-slate-200 dark:border-slate-600 shrink-0 flex items-center justify-center ${dev.image ? '' : 'bg-primary text-white font-bold'}"
                                style="${dev.image ? `background-image: url('${dev.image}')` : ''}">
                                ${!dev.image ? dev.name.charAt(0).toUpperCase() : ''}
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-bold text-slate-900 dark:text-white truncate">${dev.name}</h4>
                                <p class="text-sm text-slate-500 dark:text-slate-400 truncate">${dev.title || ''}</p>
                                <p class="text-sm font-semibold text-primary">₹${dev.rate}/hr</p>
                            </div>
                            <button onclick="removeSavedDeveloper('${dev.id}')"
                                class="p-2 hover:bg-red-100 dark:hover:bg-red-900/30 rounded-lg transition-colors group"
                                title="Remove from Saved">
                                <span class="material-symbols-outlined text-slate-400 group-hover:text-red-500">delete</span>
                            </button>
                        </div>
                    `).join('');
                }

                if (modal && content) {
                    modal.classList.remove('hidden');
                    setTimeout(() => {
                        modal.classList.remove('opacity-0');
                        content.classList.remove('scale-95');
                        content.classList.add('scale-100');
                    }, 10);
                }
            };

            // Close Saved Talent Modal
            window.closeSavedTalentModal = function () {
                const modal = document.getElementById('saved-talent-modal');
                const content = document.getElementById('saved-talent-content');
                if (modal && content) {
                    modal.classList.add('opacity-0');
                    content.classList.remove('scale-100');
                    content.classList.add('scale-95');
                    setTimeout(() => {
                        modal.classList.add('hidden');
                    }, 300);
                }
            };

            // Remove saved developer
            window.removeSavedDeveloper = function (devId) {
                const saved = getSavedDevelopers().filter(d => d.id !== devId);
                saveDevelopers(saved);

                // Update buttons on page
                const buttons = document.querySelectorAll(`.save-developer-btn[data-id="${devId}"]`);
                buttons.forEach(btn => updateButtonState(btn));

                // Refresh modal
                const modal = document.getElementById('saved-talent-modal');
                if (modal && !modal.classList.contains('hidden')) {
                    openSavedTalentModal();
                }
            };

            // Close modal on backdrop click
            const savedModal = document.getElementById('saved-talent-modal');
            if (savedModal) {
                savedModal.addEventListener('click', function (e) {
                    if (e.target === this) {
                        closeSavedTalentModal();
                    }
                });
            }

            // Saved Talent Button Click
            const savedTalentBtn = document.getElementById('savedTalentBtn');
            if (savedTalentBtn) {
                savedTalentBtn.addEventListener('click', openSavedTalentModal);
            }

            // Initialize on page load
            document.addEventListener('DOMContentLoaded', function () {
                initializeHeartButtons();

                // Search by Name Functionality
                const searchByNameInput = document.getElementById('searchByName');
                if (searchByNameInput) {
                    searchByNameInput.addEventListener('input', applySearchAndTitleFilters);
                }

                // Professional Title Filter Functionality
                const clearProfessionalTitleBtn = document.getElementById('clearProfessionalTitle');
                const professionalTitleCheckboxes = document.getElementById('professionalTitleCheckboxes');

                if (clearProfessionalTitleBtn && professionalTitleCheckboxes) {
                    clearProfessionalTitleBtn.addEventListener('click', function () {
                        const checkboxes = professionalTitleCheckboxes.querySelectorAll('input[type="checkbox"]');
                        checkboxes.forEach(checkbox => {
                            checkbox.checked = false;
                        });
                        applySearchAndTitleFilters();
                    });

                    const allTitleCheckboxes = professionalTitleCheckboxes.querySelectorAll('input[type="checkbox"]');
                    allTitleCheckboxes.forEach(checkbox => {
                        checkbox.addEventListener('change', applySearchAndTitleFilters);
                    });
                }

                // Tech Stack Filter Functionality
                const clearTechStackBtn = document.getElementById('clearTechStack');
                const techStackCheckboxesContainer = document.getElementById('techStackCheckboxes');

                if (clearTechStackBtn && techStackCheckboxesContainer) {
                    clearTechStackBtn.addEventListener('click', function () {
                        const checkboxes = techStackCheckboxesContainer.querySelectorAll('input[type="checkbox"]');
                        checkboxes.forEach(checkbox => {
                            checkbox.checked = false;
                        });
                        applySearchAndTitleFilters();
                    });

                    const allTechCheckboxes = techStackCheckboxesContainer.querySelectorAll('input[type="checkbox"]');
                    allTechCheckboxes.forEach(checkbox => {
                        checkbox.addEventListener('change', applySearchAndTitleFilters);
                    });
                }
            });

            // Filter Developers Function (for search and title filtering)
            function applySearchAndTitleFilters() {
                const developerCards = document.querySelectorAll('.developer-card');

                // Get search query
                const searchInput = document.getElementById('searchByName');
                const searchQuery = searchInput ? searchInput.value.trim().toLowerCase() : '';

                // Get selected professional titles (using value attribute)
                const titleCheckboxes = document.querySelectorAll('#professionalTitleCheckboxes input[type="checkbox"]:checked');
                const selectedTitles = Array.from(titleCheckboxes).map(cb => cb.value.toLowerCase());

                // Get selected tech stack
                const techStackCheckboxes = document.querySelectorAll('#techStackCheckboxes input[type="checkbox"]:checked');
                const selectedTechs = Array.from(techStackCheckboxes).map(cb => cb.value.toLowerCase());

                let visibleCount = 0;
                const resultsCountEl = document.getElementById('resultsCount');

                developerCards.forEach(card => {
                    // Get developer name from card
                    const nameEl = card.querySelector('h3');
                    const devName = nameEl ? nameEl.textContent.trim().toLowerCase() : '';

                    // Get data attributes
                    const devTitle = (card.getAttribute('data-jobtitle') || '').toLowerCase();
                    const skills = (card.getAttribute('data-skills') || '').toLowerCase();

                    // Check name search filter
                    const nameMatch = searchQuery === '' || devName.includes(searchQuery);

                    // Check professional title filter
                    let titleMatch = true;
                    if (selectedTitles.length > 0) {
                        titleMatch = selectedTitles.some(t => devTitle.includes(t));
                    }

                    // Check tech stack filter
                    let techMatch = true;
                    if (selectedTechs.length > 0) {
                        techMatch = selectedTechs.some(tech => skills.includes(tech));
                    }

                    if (nameMatch && titleMatch && techMatch) {
                        card.style.display = '';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                // Update results count
                if (resultsCountEl) {
                    resultsCountEl.textContent = visibleCount;
                }
            }

            // ========== HOURLY RATE SLIDER FUNCTIONALITY ==========
            const minSlider = document.getElementById('minRateSlider');
            const maxSlider = document.getElementById('maxRateSlider');
            const minDisplay = document.getElementById('minRateDisplay');
            const maxDisplay = document.getElementById('maxRateDisplay');
            const sliderTrack = document.getElementById('sliderTrack');
            const rateDifference = document.getElementById('rateDifference');
            const developerCards = document.querySelectorAll('.developer-card');

            function formatRate(value) {
                return '₹' + value + 'k';
            }

            function updateDifference() {
                if (minSlider && maxSlider && rateDifference) {
                    const diff = parseInt(maxSlider.value) - parseInt(minSlider.value);
                    rateDifference.textContent = '₹' + diff + 'k';
                }
            }

            function updateSliderTrack() {
                if (minSlider && maxSlider && sliderTrack) {
                    const min = parseInt(minSlider.value);
                    const max = parseInt(maxSlider.value);
                    const minPercent = ((min - 1) / 199) * 100;
                    const maxPercent = ((max - 1) / 199) * 100;
                    sliderTrack.style.left = minPercent + '%';
                    sliderTrack.style.right = (100 - maxPercent) + '%';
                }
            }

            function filterDevelopers() {
                if (!minSlider || !maxSlider) return;

                const minRate = parseInt(minSlider.value);
                const maxRate = parseInt(maxSlider.value);
                let visibleCount = 0;

                // Get search query
                const searchInput = document.getElementById('searchByName');
                const searchQuery = searchInput ? searchInput.value.trim().toLowerCase() : '';

                // Get selected tech stack
                const techStackCheckboxes = document.querySelectorAll('#techStackCheckboxes input[type="checkbox"]:checked');
                const selectedTechs = Array.from(techStackCheckboxes).map(cb => cb.value.toLowerCase());

                // Get selected professional titles (now using value attribute)
                const titleCheckboxes = document.querySelectorAll('#professionalTitleCheckboxes input[type=\"checkbox\"]:checked');
                const selectedTitles = Array.from(titleCheckboxes).map(cb => cb.value.toLowerCase());

                developerCards.forEach(card => {
                    const rate = parseFloat(card.getAttribute('data-rate')) || 0;
                    const skills = (card.getAttribute('data-skills') || '').toLowerCase();
                    const jobTitle = (card.getAttribute('data-jobtitle') || '').toLowerCase();

                    // Get developer name from card
                    const nameEl = card.querySelector('h3');
                    const devName = nameEl ? nameEl.textContent.trim().toLowerCase() : '';

                    // Check name search
                    let nameMatch = true;
                    if (searchQuery.length > 0) {
                        nameMatch = devName.includes(searchQuery);
                    }

                    // Check rate filter
                    const rateMatch = rate >= minRate && rate <= maxRate;

                    // Check tech stack filter
                    let techMatch = true;
                    if (selectedTechs.length > 0) {
                        techMatch = selectedTechs.some(tech => skills.includes(tech));
                    }

                    // Check job title filter
                    let titleMatch = true;
                    if (selectedTitles.length > 0) {
                        titleMatch = selectedTitles.some(title => jobTitle.includes(title));
                    }

                    if (nameMatch && rateMatch && techMatch && titleMatch) {
                        card.style.display = '';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                // Update results count
                const resultsCount = document.getElementById('resultsCount');
                if (resultsCount) {
                    resultsCount.textContent = visibleCount;
                }
            }

            function handleMinSlider() {
                if (!minSlider || !maxSlider) return;
                const minVal = parseInt(minSlider.value);
                const maxVal = parseInt(maxSlider.value);

                if (minVal > maxVal) {
                    minSlider.value = maxVal;
                }

                if (minDisplay) minDisplay.textContent = formatRate(minSlider.value);
                updateDifference();
                updateSliderTrack();
                filterDevelopers();
            }

            function handleMaxSlider() {
                if (!minSlider || !maxSlider) return;
                const minVal = parseInt(minSlider.value);
                const maxVal = parseInt(maxSlider.value);

                if (maxVal < minVal) {
                    maxSlider.value = minVal;
                }

                if (maxDisplay) maxDisplay.textContent = formatRate(maxSlider.value);
                updateDifference();
                updateSliderTrack();
                filterDevelopers();
            }

            if (minSlider) minSlider.addEventListener('input', handleMinSlider);
            if (maxSlider) maxSlider.addEventListener('input', handleMaxSlider);

            // Initialize sliders
            updateSliderTrack();
            updateDifference();

            // ========== TECH STACK FILTER FUNCTIONALITY ==========
            const clearTechStackBtn = document.getElementById('clearTechStack');
            const techStackCheckboxesContainer = document.getElementById('techStackCheckboxes');

            if (clearTechStackBtn && techStackCheckboxesContainer) {
                clearTechStackBtn.addEventListener('click', function () {
                    const checkboxes = techStackCheckboxesContainer.querySelectorAll('input[type="checkbox"]');
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = false;
                    });
                    filterDevelopers();
                });
            }

            // Add event listeners to tech stack checkboxes
            if (techStackCheckboxesContainer) {
                const checkboxes = techStackCheckboxesContainer.querySelectorAll('input[type="checkbox"]');
                checkboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', filterDevelopers);
                });
            }

            // ========== PROFESSIONAL TITLE FILTER FUNCTIONALITY ==========
            const clearTitleBtn = document.getElementById('clearProfessionalTitle');
            const titleCheckboxesContainer = document.getElementById('professionalTitleCheckboxes');

            if (clearTitleBtn && titleCheckboxesContainer) {
                clearTitleBtn.addEventListener('click', function () {
                    const checkboxes = titleCheckboxesContainer.querySelectorAll('input[type="checkbox"]');
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = false;
                    });
                    filterDevelopers();
                });
            }

            // Add event listeners to professional title checkboxes
            if (titleCheckboxesContainer) {
                const checkboxes = titleCheckboxesContainer.querySelectorAll('input[type="checkbox"]');
                checkboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', filterDevelopers);
                });
            }
            // ========== NAME SEARCH FUNCTIONALITY ==========
            const searchByNameInput = document.getElementById('searchByName');
            if (searchByNameInput) {
                searchByNameInput.addEventListener('input', filterDevelopers);
            }
        </script>
        <script src="js/search_engine.js"></script>
        <?php include 'includes/chatbot_widget.php'; ?>
        <script src="js/chatbot.js"></script>
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
</body>

</html>