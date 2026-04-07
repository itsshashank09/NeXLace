<?php
require_once 'includes/auth_helper.php';
requireAuth();

// Include database connection
require_once 'config/database.php';
require_once 'config/security_headers.php';
require_once 'config/csrf.php';
$csrfToken = generateCsrfToken();

$name = $_SESSION['name'];
$email = $_SESSION['email'] ?? '';
$profileImage = '';
$headline = '';
$bio = '';


// Get developer ID from URL
$developer_id = $_GET['id'] ?? null;

// Initialize developer data
$developer = null;
$devName = '';
$devTitle = '';
$devRate = 0;
$devBio = '';
$devSkills = [];
$devLocation = '';
$devPhone = '';
$devLanguages = '';
$devWorkHistory = '';
$devEducation = '';
$devProjectLink = '';
$devImage = '';
$devAvailability = '';

// Fetch developer profile from database
if ($developer_id) {
    try {
        require_once 'includes/db_helper.php';
        $conn = getDB();

        if ($conn) {
            // Fetch current logged-in user profile image
            if (!empty($email)) {
                $userStmt = $conn->prepare("SELECT `image` FROM register WHERE Email = :email");
                $userStmt->execute([':email' => $email]);
                $currentUser = $userStmt->fetch();
                if ($currentUser) {
                    $profileImage = $currentUser['image'] ?? '';
                }
            }

            $stmt = $conn->prepare("
                SELECT d.*, r.Name, r.Email 
                FROM developers d 
                JOIN register r ON d.user_id = r.id 
                WHERE d.id = ?
            ");
            $stmt->execute([$developer_id]);
            $developer = $stmt->fetch();

            if ($developer) {
                $devName = htmlspecialchars($developer['Name'] ?? 'Developer');
                $devTitle = htmlspecialchars($developer['title'] ?? 'Professional Developer');
                $devRate = number_format($developer['rate'] ?? 0);
                $devBio = htmlspecialchars($developer['bio'] ?? 'No bio available.');
                $devSkills = array_map('trim', explode(',', $developer['skills'] ?? ''));
                $devLocation = htmlspecialchars($developer['location'] ?? 'Remote');
                $devPhone = htmlspecialchars($developer['phone'] ?? 'N/A');
                $devLanguages = htmlspecialchars($developer['languages'] ?? 'English');
                $devWorkHistory = $developer['work_history'] ?? 'No work history provided.';
                $devEducation = $developer['education'] ?? 'No education information provided.';
                $devProjectLink = htmlspecialchars($developer['project_link'] ?? '#');
                $devImage = !empty($developer['image_path']) ? htmlspecialchars($developer['image_path']) : '';
                $devPortfolioImage = !empty($developer['portfolio_images']) ? htmlspecialchars($developer['portfolio_images']) : '';
                $devAvailability = $developer['availability'] ?? 'more_than_30';
                $devUserId = $developer['user_id'];
                
                // Fetch reviews
                try {
                    $reviewsStmt = $conn->prepare("
                        SELECT rev.*, r.Name as reviewer_name, r.image as reviewer_image 
                        FROM reviews rev 
                        JOIN register r ON rev.reviewer_id = r.id 
                        WHERE rev.reviewee_id = ? 
                        ORDER BY rev.created_at DESC
                    ");
                    $reviewsStmt->execute([$devUserId]);
                    $devReviews = $reviewsStmt->fetchAll();
                } catch(PDOException $e) {
                    $devReviews = []; // Table might not exist yet
                }
                
                $averageRating = '5.0'; // Check later if no reviews 
                $totalReviews = count($devReviews);
                if ($totalReviews > 0) {
                    $sum = 0;
                    foreach ($devReviews as $rev) {
                        $sum += $rev['rating'];
                    }
                    $averageRating = number_format($sum / $totalReviews, 1);
                } else {
                    $averageRating = '0.0';
                }
            } else {
                // Developer not found, redirect back
                header('Location: developer.php');
                exit();
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching developer data: " . $e->getMessage());
        header('Location: developer.php');
        exit();
    }
} else {
    // No ID provided, redirect back
    header('Location: developer.php');
    exit();
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
                        "surface-light": "#ffffff",
                        "surface-dark": "#151c2b",
                    },
                    fontFamily: {
                        "display": ["Inter", "sans-serif"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "2xl": "1rem",
                        "full": "9999px"
                    },
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
                    <button onclick="window.location.href='help.php'"
                        class="text-text-sub hover:text-primary dark:text-gray-400 dark:hover:text-white">
                        <span class="material-symbols-outlined">help</span>
                    </button>
                    <button onclick="window.location.href='notification.php'"
                        class="text-text-sub hover:text-primary dark:text-gray-400 dark:hover:text-white">
                        <span class="material-symbols-outlined">notifications</span>
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
        <div class="layout-container flex h-full grow flex-col max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
                <div class="flex flex-wrap gap-2 items-center">

                    <a class="text-slate-500 dark:text-slate-400 text-sm font-medium hover:text-primary transition-colors"
                        href="developer.php">Hire Talent</a>
                    <span class="text-slate-400 text-sm font-medium">/</span>
                    <span class="text-slate-900 dark:text-white text-sm font-medium">Freelancer Profile</span>
                </div>
                <div class="flex gap-3">
                    <button
                        class="flex items-center gap-2 px-4 py-2 bg-transparent text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white text-sm font-medium transition-colors">
                        <span class="material-symbols-outlined text-[20px]">share</span>
                        Share
                    </button>
                </div>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                <aside class="lg:col-span-4 space-y-6 lg:sticky lg:top-24">
                    <div
                        class="bg-surface-light dark:bg-surface-dark rounded-xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm overflow-hidden relative">
                        <div
                            class="absolute top-0 left-0 w-full h-24 bg-gradient-to-r from-blue-600 to-indigo-600 opacity-10 dark:opacity-20">
                        </div>
                        <div class="relative flex flex-col items-center text-center mt-6">
                            <div class="relative mb-4">
                                <?php if (!empty($devImage)): ?>
                                    <div class="size-32 rounded-full bg-cover bg-center border-4 border-white dark:border-surface-dark shadow-md"
                                        style='background-image: url("<?= $devImage ?>");'>
                                    </div>
                                <?php else: ?>
                                    <div
                                        class="size-32 rounded-full bg-primary flex items-center justify-center text-white font-bold text-4xl border-4 border-white dark:border-surface-dark shadow-md">
                                        <?= strtoupper($devName[0] ?? 'D') ?>
                                    </div>
                                <?php endif; ?>
                                <div class="absolute bottom-1 right-1 size-5 rounded-full bg-green-500 border-2 border-white dark:border-surface-dark"
                                    title="Online Now"></div>
                            </div>
                            <div class="flex items-center gap-1 mb-1">
                                <h1 class="text-2xl font-bold text-slate-900 dark:text-white"><?= $devName ?></h1>
                                <span class="material-symbols-outlined text-primary text-[20px] filled"
                                    style="font-variation-settings: 'FILL' 1;">verified</span>
                            </div>
                            <p class="text-slate-500 dark:text-slate-400 font-medium mb-4"><?= $devTitle ?></p>
                            <div class="flex items-center gap-4 mb-6">
                                <div class="flex items-center gap-1">
                                    <span class="material-symbols-outlined text-yellow-500 text-[20px]"
                                        style="font-variation-settings: 'FILL' <?= (float)$averageRating >= 1 ? '1' : '0' ?>;">star</span>
                                    <span class="font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($averageRating) ?></span>
                                    <span class="text-sm text-slate-500 dark:text-slate-400">(<?= htmlspecialchars($totalReviews) ?>)</span>
                                </div>
                                <div class="h-4 w-px bg-slate-300 dark:bg-slate-700"></div>
                                <div class="flex items-center gap-1">
                                    <span
                                        class="material-symbols-outlined text-slate-400 text-[18px]">location_on</span>
                                    <span class="text-sm text-slate-500 dark:text-slate-400"><?= $devLocation ?></span>
                                </div>
                            </div>
                            <div class="w-full grid grid-cols-2 gap-3 mb-6">
                                <button
                                    class="col-span-2 w-full py-3 rounded-lg bg-primary hover:bg-blue-700 text-white font-semibold shadow-md transition-all active:scale-95">Invite
                                    to Job</button>
                                <button onclick="openMessageRequestModal()"
                                    class="w-full py-2.5 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 font-medium transition-colors">Request
                                    Message</button>
                                <button
                                    class="w-full py-2.5 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 font-medium transition-colors">Save</button>
                            </div>
                            <div
                                class="w-full grid grid-cols-3 gap-px bg-slate-100 dark:bg-slate-700/50 rounded-lg overflow-hidden border border-slate-100 dark:border-slate-700/50">
                                <div class="bg-white dark:bg-surface-dark p-3 text-center">
                                    <span
                                        class="block font-bold text-slate-900 dark:text-white text-base">₹<?= $devRate ?></span>
                                    <span
                                        class="block text-[10px] text-slate-500 dark:text-slate-400 uppercase tracking-wide">Hourly</span>
                                </div>
                                <div class="bg-white dark:bg-surface-dark p-3 text-center">
                                    <span class="block font-bold text-slate-900 dark:text-white text-base">98%</span>
                                    <span
                                        class="block text-[10px] text-slate-500 dark:text-slate-400 uppercase tracking-wide">Success</span>
                                </div>
                                <div class="bg-white dark:bg-surface-dark p-3 text-center">
                                    <span class="block font-bold text-slate-900 dark:text-white text-base">200k+</span>
                                    <span
                                        class="block text-[10px] text-slate-500 dark:text-slate-400 uppercase tracking-wide">Earned</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        class="bg-surface-light dark:bg-surface-dark rounded-xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-6">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider mb-3">
                                Details</h3>
                            <ul class="space-y-3">
                                <li class="flex items-start gap-3 text-sm">
                                    <span class="material-symbols-outlined text-slate-400 text-[20px]">schedule</span>
                                    <div>
                                        <p class="text-slate-900 dark:text-white font-medium">Availability</p>
                                        <p class="text-slate-500 dark:text-slate-400">
                                            <?= $devAvailability === 'more_than_30' ? 'More than 30 hrs/week' : ($devAvailability === 'less_than_30' ? 'Less than 30 hrs/week' : 'Flexible') ?>
                                        </p>
                                        <p class="text-xs text-green-600 dark:text-green-400 mt-0.5">Response time: &lt;
                                            2 hours</p>
                                    </div>
                                </li>
                                <li class="flex items-start gap-3 text-sm">
                                    <span class="material-symbols-outlined text-slate-400 text-[20px]">translate</span>
                                    <div>
                                        <p class="text-slate-900 dark:text-white font-medium">Languages</p>
                                        <p class="text-slate-500 dark:text-slate-400"><?= nl2br($devLanguages) ?></p>
                                    </div>
                                </li>
                                <li class="flex items-start gap-3 text-sm">
                                    <span class="material-symbols-outlined text-slate-400 text-[20px]">school</span>
                                    <div>
                                        <p class="text-slate-900 dark:text-white font-medium">Education</p>
                                        <p class="text-slate-500 dark:text-slate-400"><?= nl2br($devEducation) ?></p>
                                    </div>
                                </li>
                            </ul>
                        </div>
                        <div class="pt-4 border-t border-slate-100 dark:border-slate-800">
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider mb-3">
                                Verifications</h3>
                            <div class="flex flex-col gap-2">
                                <div class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                                    <span
                                        class="material-symbols-outlined text-green-500 text-[18px]">check_circle</span>
                                    ID Verified
                                </div>
                                <div class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                                    <span
                                        class="material-symbols-outlined text-green-500 text-[18px]">check_circle</span>
                                    Email Verified
                                </div>
                                <div class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                                    <span
                                        class="material-symbols-outlined text-green-500 text-[18px]">check_circle</span>
                                    Phone Verified
                                </div>
                            </div>
                        </div>
                    </div>
                </aside>
                <main class="lg:col-span-8 space-y-8">
                    <section
                        class="bg-surface-light dark:bg-surface-dark rounded-xl border border-slate-200 dark:border-slate-800 p-6 md:p-8 shadow-sm">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-xl font-bold text-slate-900 dark:text-white">About Me</h2>
                        </div>
                        <div
                            class="prose prose-slate dark:prose-invert max-w-none text-slate-600 dark:text-slate-300 text-sm leading-relaxed">
                            <p><?= nl2br($devBio) ?></p>
                        </div>
                    </section>
                    <section
                        class="bg-surface-light dark:bg-surface-dark rounded-xl border border-slate-200 dark:border-slate-800 p-6 md:p-8 shadow-sm">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-6">Skills & Expertise</h2>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($devSkills as $skill): ?>
                                <?php if (!empty(trim($skill))): ?>
                                    <span
                                        class="px-3 py-1.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-sm font-medium border border-slate-200 dark:border-slate-700"><?= htmlspecialchars(trim($skill)) ?></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    <section
                        class="bg-surface-light dark:bg-surface-dark rounded-xl border border-slate-200 dark:border-slate-800 p-6 md:p-8 shadow-sm">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Portfolio</h2>
                            <?php if (!empty($devProjectLink) && $devProjectLink !== '#'): ?>
                                <a class="text-sm font-semibold text-primary hover:underline" href="<?= $devProjectLink ?>"
                                    target="_blank">
                                    <span class="flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[16px]">open_in_new</span>
                                        View Project
                                    </span>
                                </a>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($devPortfolioImage) || (!empty($devProjectLink) && $devProjectLink !== '#')): ?>
                            <div class="space-y-6">
                                <?php if (!empty($devPortfolioImage)): ?>
                                    <!-- Portfolio Image -->
                                    <div class="group cursor-pointer">
                                        <a href="<?= $devPortfolioImage ?>" target="_blank" title="View full image">
                                            <div
                                                class="aspect-video w-full rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 overflow-hidden relative mb-3">
                                                <img src="<?= $devPortfolioImage ?>" alt="Portfolio Project"
                                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                            </div>
                                        </a>
                                        <?php if (!empty($devProjectLink) && $devProjectLink !== '#'): ?>
                                            <a href="<?= $devProjectLink ?>" target="_blank"
                                                class="text-base font-bold text-slate-900 dark:text-white group-hover:text-primary transition-colors flex items-center gap-2">
                                                <span class="material-symbols-outlined text-[18px]">code</span>
                                                View Project Repository
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($devProjectLink) && $devProjectLink !== '#'): ?>
                                    <!-- Project Link -->
                                    <div
                                        class="p-4 rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700">
                                        <p
                                            class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">
                                            Project Link</p>
                                        <a href="<?= $devProjectLink ?>" target="_blank"
                                            class="flex items-center gap-2 text-primary hover:underline break-all">
                                            <span class="material-symbols-outlined text-[20px]">link</span>
                                            <?= $devProjectLink ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <!-- No Portfolio Available -->
                            <div class="text-center py-12">
                                <div
                                    class="w-16 h-16 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center mx-auto mb-4">
                                    <span class="material-symbols-outlined text-slate-400 text-[32px]">folder_open</span>
                                </div>
                                <p class="text-slate-500 dark:text-slate-400">No portfolio items available yet.</p>
                            </div>
                        <?php endif; ?>
                    </section>
                    <section
                        class="bg-surface-light dark:bg-surface-dark rounded-xl border border-slate-200 dark:border-slate-800 p-6 md:p-8 shadow-sm">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-6">Work History</h2>
                        <div class="text-slate-600 dark:text-slate-300 text-sm leading-relaxed whitespace-pre-line">
                            <?= nl2br($devWorkHistory) ?>
                        </div>
                    </section>
                    <section
                        class="bg-surface-light dark:bg-surface-dark rounded-xl border border-slate-200 dark:border-slate-800 p-6 md:p-8 shadow-sm">
                        <div class="flex justify-between items-end mb-6">
                            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Client Reviews</h2>
                            <div class="flex items-center gap-2">
                                <span class="text-2xl font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($averageRating) ?></span>
                                <div class="flex text-yellow-500">
                                    <?php 
                                    $fullStars = floor((float)$averageRating);
                                    for($i = 0; $i < 5; $i++): 
                                    ?>
                                        <span class="material-symbols-outlined text-[20px]"
                                            style="font-variation-settings: 'FILL' <?= $i < $fullStars ? '1' : '0' ?>;">star</span>
                                    <?php endfor; ?>
                                </div>
                                <span class="text-sm text-slate-500 dark:text-slate-400">(<?= htmlspecialchars($totalReviews) ?>)</span>
                            </div>
                        </div>
                        <div class="space-y-6">
                            <?php if (!empty($devReviews)): ?>
                                <?php foreach ($devReviews as $review): ?>
                                    <div class="border-b border-slate-100 dark:border-slate-800 pb-6 last:border-0 last:pb-0">
                                        <div class="flex justify-between mb-2">
                                            <h3 class="font-bold text-slate-900 dark:text-white">
                                                Review
                                            </h3>
                                            <span class="text-xs text-slate-400"><?= date('M j, Y', strtotime($review['created_at'])) ?></span>
                                        </div>
                                        <div class="flex items-center gap-1 mb-3">
                                            <?php for($i = 0; $i < 5; $i++): ?>
                                                <span class="material-symbols-outlined text-yellow-500 text-[16px]"
                                                    style="font-variation-settings: 'FILL' <?= $i < $review['rating'] ? '1' : '0' ?>;">star</span>
                                            <?php endfor; ?>
                                        </div>
                                        <?php if (!empty($review['review_text'])): ?>
                                            <p class="text-sm text-slate-600 dark:text-slate-300 italic mb-4">
                                                "<?= nl2br(htmlspecialchars($review['review_text'])) ?>"
                                            </p>
                                        <?php endif; ?>
                                        <div class="flex items-center gap-3">
                                            <?php if (!empty($review['reviewer_image']) && $review['reviewer_image'] !== 'null' && trim($review['reviewer_image']) !== ''): ?>
                                                <img src="<?= htmlspecialchars($review['reviewer_image']) ?>" alt="<?= htmlspecialchars($review['reviewer_name']) ?>" class="size-8 rounded-full object-cover">
                                            <?php else: ?>
                                                <div class="size-8 rounded-full bg-primary flex items-center justify-center text-white font-bold text-xs uppercase">
                                                    <?= substr($review['reviewer_name'] ?? 'U', 0, 1) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <p class="text-sm font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($review['reviewer_name']) ?></p>
                                                <p class="text-xs text-slate-500">Client</p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-slate-500 dark:text-slate-400 italic">No reviews yet.</p>
                            <?php endif; ?>
                        </div>
                    </section>
                </main>
                <!-- Job Invitation Modal -->
                <div id="invite-modal"
                    class="fixed inset-0 z-[100] hidden flex items-center justify-center bg-black/60 backdrop-blur-sm opacity-0 transition-opacity duration-300 p-4">
                    <div class="relative w-full max-w-[520px] scale-95 transform rounded-2xl bg-white shadow-2xl transition-all duration-300 dark:bg-[#1e293b] overflow-hidden"
                        id="modal-content">

                        <!-- Header with gradient -->
                        <div class="bg-gradient-to-r from-primary to-blue-600 px-6 py-5">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-10 h-10 rounded-full bg-white/20 backdrop-blur flex items-center justify-center">
                                        <span class="material-symbols-outlined text-white">work</span>
                                    </div>
                                    <div>
                                        <h2 class="text-lg font-bold text-white">Send Job Invitation</h2>
                                        <p class="text-sm text-blue-100">Invite this talent to your project</p>
                                    </div>
                                </div>
                                <button onclick="closeModal()" class="text-white/80 hover:text-white transition-colors">
                                    <span class="material-symbols-outlined text-[24px]">close</span>
                                </button>
                            </div>
                        </div>

                        <!-- Developer Preview -->
                        <div
                            class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700">
                            <p
                                class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">
                                Sending To</p>
                            <div class="flex items-center gap-3">
                                <?php if (!empty($devImage)): ?>
                                    <div class="w-10 h-10 rounded-full bg-cover bg-center border-2 border-white dark:border-slate-700 shadow-sm"
                                        style='background-image: url("<?= $devImage ?>");'></div>
                                <?php else: ?>
                                    <div
                                        class="w-10 h-10 rounded-full bg-primary flex items-center justify-center text-white font-bold border-2 border-white dark:border-slate-700 shadow-sm">
                                        <?= strtoupper($devName[0] ?? 'D') ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <p class="font-semibold text-slate-900 dark:text-white"><?= $devName ?></p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400"><?= $devTitle ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Form -->
                        <div class="p-6 space-y-5">
                            <div>
                                <label
                                    class="mb-2 block text-sm font-medium text-slate-900 dark:text-white flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm text-primary">category</span>
                                    Project Type <span class="text-red-500">*</span>
                                </label>
                                <input id="invite-work-type" type="text"
                                    placeholder="e.g. Full-time Contract, Hourly Project"
                                    class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white transition-all" />
                            </div>

                            <div>
                                <label
                                    class="mb-2 block text-sm font-medium text-slate-900 dark:text-white flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm text-primary">mail</span>
                                    Contact Email <span class="text-slate-400 text-xs font-normal">(optional)</span>
                                </label>
                                <input id="invite-work-email" type="email" placeholder="yourname@company.com"
                                    class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white transition-all" />
                            </div>

                            <div>
                                <label
                                    class="mb-2 block text-sm font-medium text-slate-900 dark:text-white flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm text-primary">description</span>
                                    Project Details <span class="text-red-500">*</span>
                                </label>
                                <textarea id="invite-work-details"
                                    placeholder="Describe the project scope, requirements, timeline, and any other relevant details..."
                                    class="h-32 w-full resize-none rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white transition-all"></textarea>
                            </div>

                            <!-- Error/Success message -->
                            <div id="invite-message" class="hidden p-3 rounded-lg text-sm font-medium"></div>
                        </div>

                        <!-- Actions -->
                        <div
                            class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-200 dark:border-slate-700 flex justify-end gap-3">
                            <button onclick="closeModal()"
                                class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600 transition-colors">
                                Cancel
                            </button>
                            <button id="send-invitation-btn"
                                class="rounded-xl bg-gradient-to-r from-primary to-blue-600 px-6 py-2.5 text-sm font-semibold text-white hover:from-primary-dark hover:to-blue-700 shadow-lg shadow-blue-500/25 transition-all flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                                <span class="material-symbols-outlined text-[18px]">send</span>
                                Send Invitation
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Toast notification for feedback -->
                <div id="toast-notification"
                    class="fixed bottom-6 right-6 z-[200] transform translate-y-full opacity-0 transition-all duration-300 pointer-events-none">
                    <div
                        class="flex items-center gap-3 px-5 py-4 rounded-xl shadow-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
                        <span id="toast-icon" class="material-symbols-outlined text-2xl"></span>
                        <div>
                            <p id="toast-title" class="font-semibold text-slate-900 dark:text-white text-sm"></p>
                            <p id="toast-text" class="text-slate-500 dark:text-slate-400 text-xs"></p>
                        </div>
                    </div>
                </div>

                <!-- Message Request Modal -->
                <div id="message-request-modal"
                    class="fixed inset-0 z-[100] hidden flex items-center justify-center bg-black/60 backdrop-blur-sm opacity-0 transition-opacity duration-300 p-4">
                    <div class="relative w-full max-w-[520px] scale-95 transform rounded-2xl bg-white shadow-2xl transition-all duration-300 dark:bg-[#1e293b] overflow-hidden"
                        id="message-modal-content">

                        <!-- Header with gradient -->
                        <div class="bg-gradient-to-r from-primary to-blue-600 px-6 py-5">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-10 h-10 rounded-full bg-white/20 backdrop-blur flex items-center justify-center">
                                        <span class="material-symbols-outlined text-white">chat</span>
                                    </div>
                                    <div>
                                        <h2 class="text-lg font-bold text-white">Send Message Request</h2>
                                        <p class="text-sm text-blue-100">Request to connect with this developer</p>
                                    </div>
                                </div>
                                <button onclick="closeMessageRequestModal()"
                                    class="text-white/80 hover:text-white transition-colors">
                                    <span class="material-symbols-outlined text-[24px]">close</span>
                                </button>
                            </div>
                        </div>

                        <!-- Developer Preview -->
                        <div
                            class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700">
                            <p
                                class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">
                                Sending To</p>
                            <div class="flex items-center gap-3">
                                <?php if (!empty($devImage)): ?>
                                    <div class="w-10 h-10 rounded-full bg-cover bg-center border-2 border-white dark:border-slate-700 shadow-sm"
                                        style='background-image: url("<?= $devImage ?>");'></div>
                                <?php else: ?>
                                    <div
                                        class="w-10 h-10 rounded-full bg-primary flex items-center justify-center text-white font-bold border-2 border-white dark:border-slate-700 shadow-sm">
                                        <?= strtoupper($devName[0] ?? 'D') ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <p class="font-semibold text-slate-900 dark:text-white"><?= $devName ?></p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400"><?= $devTitle ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Form -->
                        <div class="p-6 space-y-5">
                            <div>
                                <label
                                    class="mb-2 block text-sm font-medium text-slate-900 dark:text-white flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm text-primary">subject</span>
                                    Subject <span class="text-slate-400 text-xs font-normal">(optional)</span>
                                </label>
                                <input id="msg-request-subject" type="text"
                                    placeholder="e.g. Collaboration Inquiry, Project Discussion"
                                    class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white transition-all" />
                            </div>

                            <div>
                                <label
                                    class="mb-2 block text-sm font-medium text-slate-900 dark:text-white flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm text-primary">message</span>
                                    Your Message <span class="text-red-500">*</span>
                                </label>
                                <textarea id="msg-request-message"
                                    placeholder="Introduce yourself and explain why you'd like to connect with this developer..."
                                    class="h-32 w-full resize-none rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white transition-all"></textarea>
                            </div>

                            <!-- Info box -->
                            <div
                                class="flex items-start gap-3 p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                                <span class="material-symbols-outlined text-primary text-lg mt-0.5">info</span>
                                <p class="text-xs text-blue-700 dark:text-blue-300">
                                    The developer will receive your message request and can choose to accept or decline.
                                    Once accepted, you can start a conversation.
                                </p>
                            </div>

                            <!-- Error/Success message -->
                            <div id="msg-request-status" class="hidden p-3 rounded-lg text-sm font-medium"></div>
                        </div>

                        <!-- Actions -->
                        <div
                            class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-200 dark:border-slate-700 flex justify-end gap-3">
                            <button onclick="closeMessageRequestModal()"
                                class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600 transition-colors">
                                Cancel
                            </button>
                            <button id="send-message-request-btn"
                                class="rounded-xl bg-gradient-to-r from-primary to-blue-600 px-6 py-2.5 text-sm font-semibold text-white hover:from-primary-dark hover:to-blue-700 shadow-lg shadow-blue-500/25 transition-all flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                                <span class="material-symbols-outlined text-[18px]">send</span>
                                Send Request
                            </button>
                        </div>
                    </div>
                </div>

                <script>
                    // Developer ID from PHP
                    const DEVELOPER_USER_ID = <?= json_encode($developer['user_id'] ?? null) ?>;
                    const DEVELOPER_NAME = <?= json_encode($devName ?? '') ?>;

                    // Show toast notification
                    function showToast(title, message, type = 'success') {
                        const toast = document.getElementById('toast-notification');
                        const toastIcon = document.getElementById('toast-icon');
                        const toastTitle = document.getElementById('toast-title');
                        const toastText = document.getElementById('toast-text');

                        const icons = {
                            success: 'check_circle',
                            error: 'error',
                            info: 'info'
                        };
                        const colors = {
                            success: 'text-emerald-500',
                            error: 'text-red-500',
                            info: 'text-blue-500'
                        };

                        toastIcon.textContent = icons[type] || icons.info;
                        toastIcon.className = `material-symbols-outlined text-2xl ${colors[type] || colors.info}`;
                        toastTitle.textContent = title;
                        toastText.textContent = message;

                        // Show toast
                        toast.classList.remove('translate-y-full', 'opacity-0');
                        toast.classList.add('translate-y-0', 'opacity-100');

                        // Hide after 4 seconds
                        setTimeout(() => {
                            toast.classList.add('translate-y-full', 'opacity-0');
                            toast.classList.remove('translate-y-0', 'opacity-100');
                        }, 4000);
                    }

                    // Modal functions
                    window.openModal = function () {
                        const modal = document.getElementById('invite-modal');
                        const modalContent = document.getElementById('modal-content');
                        const messageDiv = document.getElementById('invite-message');
                        const sendBtn = document.getElementById('send-invitation-btn');

                        // Reset form
                        document.getElementById('invite-work-type').value = '';
                        document.getElementById('invite-work-email').value = '';
                        document.getElementById('invite-work-details').value = '';
                        messageDiv.classList.add('hidden');
                        sendBtn.disabled = false;
                        sendBtn.innerHTML = '<span class="material-symbols-outlined text-[18px]">send</span> Send Invitation';

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

                    // Send Invitation via API
                    async function sendInvitation() {
                        const workTypeInput = document.getElementById('invite-work-type');
                        const workEmailInput = document.getElementById('invite-work-email');
                        const workDetailsInput = document.getElementById('invite-work-details');
                        const messageDiv = document.getElementById('invite-message');
                        const sendBtn = document.getElementById('send-invitation-btn');

                        const workType = workTypeInput.value.trim();
                        const workEmail = workEmailInput.value.trim();
                        const workDetails = workDetailsInput.value.trim();

                        // Reset styles
                        workTypeInput.classList.remove('border-red-500');
                        workDetailsInput.classList.remove('border-red-500');
                        messageDiv.classList.add('hidden');

                        // Validation
                        let isValid = true;
                        if (!workType) {
                            workTypeInput.classList.add('border-red-500');
                            isValid = false;
                        }
                        if (!workDetails) {
                            workDetailsInput.classList.add('border-red-500');
                            isValid = false;
                        }

                        if (!isValid) {
                            messageDiv.className = 'p-3 rounded-lg text-sm font-medium bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-800';
                            messageDiv.innerHTML = '<span class="flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">error</span> Please fill in the required fields.</span>';
                            messageDiv.classList.remove('hidden');
                            return;
                        }

                        if (!DEVELOPER_USER_ID) {
                            messageDiv.className = 'p-3 rounded-lg text-sm font-medium bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-800';
                            messageDiv.innerHTML = '<span class="flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">error</span> Developer information not found.</span>';
                            messageDiv.classList.remove('hidden');
                            return;
                        }

                        // Show loading
                        sendBtn.disabled = true;
                        sendBtn.innerHTML = '<span class="material-symbols-outlined text-[18px] animate-spin">progress_activity</span> Sending...';

                        try {
                            const response = await csrfFetch('api/send_invitation.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    receiver_id: DEVELOPER_USER_ID,
                                    work_type: workType,
                                    work_email: workEmail,
                                    work_details: workDetails
                                })
                            });

                            const data = await response.json();

                            if (data.success) {
                                // Success
                                messageDiv.className = 'p-3 rounded-lg text-sm font-medium bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800';
                                messageDiv.innerHTML = '<span class="flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">check_circle</span> Invitation sent successfully! Redirecting to messages...</span>';
                                messageDiv.classList.remove('hidden');

                                showToast('Invitation Sent!', `Your job invitation was sent to ${DEVELOPER_NAME}`, 'success');

                                // Redirect to messages
                                setTimeout(() => {
                                    window.location.href = 'messages.php';
                                }, 1500);
                            } else {
                                // Error from API
                                messageDiv.className = 'p-3 rounded-lg text-sm font-medium bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-800';
                                messageDiv.innerHTML = `<span class="flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">error</span> ${data.error || 'Failed to send invitation'}</span>`;
                                messageDiv.classList.remove('hidden');

                                sendBtn.disabled = false;
                                sendBtn.innerHTML = '<span class="material-symbols-outlined text-[18px]">send</span> Send Invitation';
                            }
                        } catch (error) {
                            console.error('Error:', error);
                            messageDiv.className = 'p-3 rounded-lg text-sm font-medium bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-800';
                            messageDiv.innerHTML = '<span class="flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">error</span> Network error. Please try again.</span>';
                            messageDiv.classList.remove('hidden');

                            sendBtn.disabled = false;
                            sendBtn.innerHTML = '<span class="material-symbols-outlined text-[18px]">send</span> Send Invitation';
                        }
                    }

                    // Attach send button handler
                    document.getElementById('send-invitation-btn').addEventListener('click', sendInvitation);

                    // Event delegation for "Invite to Job" buttons
                    document.addEventListener('click', function (e) {
                        const btn = e.target.closest('button');
                        if (btn) {
                            const text = btn.textContent.replace(/\s+/g, ' ').trim();
                            if (text === 'Invite to Job') {
                                e.preventDefault();
                                window.openModal();
                            }
                        }
                        // Close modal on backdrop click
                        if (e.target.id === 'invite-modal') {
                            window.closeModal();
                        }
                    });

                    // Close on escape key
                    document.addEventListener('keydown', function (e) {
                        if (e.key === 'Escape') {
                            window.closeModal();
                            window.closeMessageRequestModal();
                        }
                    });

                    // ========== MESSAGE REQUEST MODAL FUNCTIONS ==========

                    // Open message request modal
                    window.openMessageRequestModal = function () {
                        const modal = document.getElementById('message-request-modal');
                        const modalContent = document.getElementById('message-modal-content');
                        const statusDiv = document.getElementById('msg-request-status');
                        const sendBtn = document.getElementById('send-message-request-btn');

                        // Reset form
                        document.getElementById('msg-request-subject').value = '';
                        document.getElementById('msg-request-message').value = '';
                        statusDiv.classList.add('hidden');
                        sendBtn.disabled = false;
                        sendBtn.innerHTML = '<span class="material-symbols-outlined text-[18px]">send</span> Send Request';

                        if (modal && modalContent) {
                            modal.classList.remove('hidden');
                            setTimeout(() => {
                                modal.classList.remove('opacity-0');
                                modalContent.classList.remove('scale-95');
                                modalContent.classList.add('scale-100');
                            }, 10);
                        }
                    };

                    // Close message request modal
                    window.closeMessageRequestModal = function () {
                        const modal = document.getElementById('message-request-modal');
                        const modalContent = document.getElementById('message-modal-content');
                        if (modal && modalContent) {
                            modal.classList.add('opacity-0');
                            modalContent.classList.remove('scale-100');
                            modalContent.classList.add('scale-95');
                            setTimeout(() => {
                                modal.classList.add('hidden');
                            }, 300);
                        }
                    };

                    // Send message request via API
                    async function sendMessageRequest() {
                        const subjectInput = document.getElementById('msg-request-subject');
                        const messageInput = document.getElementById('msg-request-message');
                        const statusDiv = document.getElementById('msg-request-status');
                        const sendBtn = document.getElementById('send-message-request-btn');

                        const subject = subjectInput.value.trim();
                        const message = messageInput.value.trim();

                        // Reset styles
                        messageInput.classList.remove('border-red-500');
                        statusDiv.classList.add('hidden');

                        // Validation
                        if (!message) {
                            messageInput.classList.add('border-red-500');
                            statusDiv.className = 'p-3 rounded-lg text-sm font-medium bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-800';
                            statusDiv.innerHTML = '<span class="flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">error</span> Please enter a message.</span>';
                            statusDiv.classList.remove('hidden');
                            return;
                        }

                        if (!DEVELOPER_USER_ID) {
                            statusDiv.className = 'p-3 rounded-lg text-sm font-medium bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-800';
                            statusDiv.innerHTML = '<span class="flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">error</span> Developer information not found.</span>';
                            statusDiv.classList.remove('hidden');
                            return;
                        }

                        // Show loading
                        sendBtn.disabled = true;
                        sendBtn.innerHTML = '<span class="material-symbols-outlined text-[18px] animate-spin">progress_activity</span> Sending...';

                        try {
                            const response = await csrfFetch('api/send_message_request.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    receiver_id: DEVELOPER_USER_ID,
                                    subject: subject || 'Message Request',
                                    message: message
                                })
                            });

                            const data = await response.json();

                            if (data.success) {
                                // Success - check if it was a regular message or new request
                                const isMessage = data.type === 'message';

                                statusDiv.className = 'p-3 rounded-lg text-sm font-medium bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800';

                                if (isMessage) {
                                    statusDiv.innerHTML = '<span class="flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">check_circle</span> Message sent! Redirecting to chat...</span>';
                                    showToast('Message Sent!', `Your message was sent to ${DEVELOPER_NAME}`, 'success');
                                } else {
                                    statusDiv.innerHTML = '<span class="flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">check_circle</span> Message request sent! Redirecting to messages...</span>';
                                    showToast('Request Sent!', `Your message request was sent to ${DEVELOPER_NAME}`, 'success');
                                }
                                statusDiv.classList.remove('hidden');

                                // Redirect to messages
                                setTimeout(() => {
                                    window.location.href = 'messages.php?filter=messages';
                                }, 1500);
                            } else {
                                // Error from API
                                statusDiv.className = 'p-3 rounded-lg text-sm font-medium bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-800';
                                statusDiv.innerHTML = `<span class="flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">error</span> ${data.error || 'Failed to send request'}</span>`;
                                statusDiv.classList.remove('hidden');

                                sendBtn.disabled = false;
                                sendBtn.innerHTML = '<span class="material-symbols-outlined text-[18px]">send</span> Send Request';
                            }
                        } catch (error) {
                            console.error('Error:', error);
                            statusDiv.className = 'p-3 rounded-lg text-sm font-medium bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-800';
                            statusDiv.innerHTML = '<span class="flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">error</span> Network error. Please try again.</span>';
                            statusDiv.classList.remove('hidden');

                            sendBtn.disabled = false;
                            sendBtn.innerHTML = '<span class="material-symbols-outlined text-[18px]">send</span> Send Request';
                        }
                    }

                    // Attach send message request button handler
                    document.getElementById('send-message-request-btn').addEventListener('click', sendMessageRequest);

                    // Close message request modal on backdrop click
                    document.getElementById('message-request-modal').addEventListener('click', function (e) {
                        if (e.target.id === 'message-request-modal') {
                            window.closeMessageRequestModal();
                        }
                    });
                </script>
                <script src="js/search_engine.js"></script>
                <?php include 'includes/chatbot_widget.php'; ?>
                <script src="js/chatbot.js"></script>
                <!-- Use SSE instead of polling for better performance -->
                <script src="js/notifications_sse.js"></script>
                <script>
                    // Profile Dropdown Toggle
                    const profileDropdownBtn = document.getElementById('nav-profile-dropdown-btn');
                    if (profileDropdownBtn) {
                        profileDropdownBtn.addEventListener('click', (e) => {
                            e.stopPropagation();
                            document.getElementById('profile-dropdown').classList.toggle('hidden');
                        });
                    }

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