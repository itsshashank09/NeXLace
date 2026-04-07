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
$userEmail = $_SESSION['email'] ?? '';

// Check if user has a developer profile
$hasDeveloperProfile = false;
$userId = null;

// Fetch profile data from database
try {
    require_once 'includes/db_helper.php';
    $conn = getDB();

    if ($conn && !empty($email)) {
        // Get user ID
        $stmt = $conn->prepare("SELECT id FROM register WHERE Email = :email");
        $stmt->execute([':email' => $email]);
        $userInfo = $stmt->fetch();
        if ($userInfo) {
            $userId = $userInfo['id'];
        }

        // Check if developer profile exists
        if ($userId) {
            $stmt = $conn->prepare("SELECT id FROM developers WHERE user_id = ?");
            $stmt->execute([$userId]);
            $hasDeveloperProfile = $stmt->fetch() !== false;
        }

        // Fetch regular profile data
        $stmt = $conn->prepare("SELECT `Professional Headline`, `Bio`, `image` FROM register WHERE Email = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            $headline = $user['Professional Headline'] ?? '';
            $bio = $user['Bio'] ?? '';
            $profileImage = $user['image'] ?? '';
        }
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
    <title>NeXLace - Profile Settings</title>
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
            // Check local storage and apply theme immediately to avoid flash
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

                    <div class="flex items-center gap-2">
                        <a href="mainpage.php"><img src="assetes/logo.png" alt="NeXLace Logo"
                                class="h-10 w-auto object-contain" /></a>
                        <a href="mainpage.php">
                            <h2 class="text-xl font-black tracking-tight text-[#0d121b] dark:text-white">NeXLace</h2>
                        </a>
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
                                <p class="font-bold text-text-main dark:text-white">
                                    <?= $name; ?>
                                </p>
                                <p class="text-xs text-text-sub mt-0.5">
                                    <?= $email; ?>
                                </p>
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
            <div class="mx-auto max-w-[1200px] px-4 sm:px-6 lg:px-8">
                <div class="mb-8">
                    <h1 class="text-3xl font-bold tracking-tight text-text-main dark:text-white">Profile Settings</h1>
                    <p class="text-text-sub mt-2">Manage your account information, preferences, and workspace settings.
                    </p>
                </div>
                <div class="flex flex-col lg:flex-row gap-8">
                    <aside class="w-full lg:w-64 flex-shrink-0">
                        <nav class="sticky top-24 space-y-1">
                            <a class="flex items-center gap-3 px-4 py-2.5 text-sm font-bold rounded-lg bg-primary/10 text-primary"
                                href="profilesetting.php">
                                <span class="material-symbols-outlined text-[20px]">person</span>
                                My Profile
                            </a>
                            <a class="flex items-center gap-3 px-4 py-2.5 text-sm font-medium rounded-lg text-text-sub hover:bg-gray-50 hover:text-text-main dark:text-gray-400 dark:hover:bg-[#1f2937] dark:hover:text-white transition-colors"
                                href="#theme">
                                <span class="material-symbols-outlined text-[20px]">palette</span>
                                Theme
                            </a>
                            <a class="flex items-center gap-3 px-4 py-2.5 text-sm font-medium rounded-lg text-text-sub hover:bg-gray-50 hover:text-text-main dark:text-gray-400 dark:hover:bg-[#1f2937] dark:hover:text-white transition-colors"
                                href="login&security.php">
                                <span class="material-symbols-outlined text-[20px]">lock</span>
                                Login &amp; Security
                            </a>
                            <a class="flex items-center gap-3 px-4 py-2.5 text-sm font-medium rounded-lg text-text-sub hover:bg-gray-50 hover:text-text-main dark:text-gray-400 dark:hover:bg-[#1f2937] dark:hover:text-white transition-colors"
                                href="notification.php">
                                <span class="material-symbols-outlined text-[20px]">notifications</span>
                                Notifications
                            </a>
                            <a class="flex items-center gap-3 px-4 py-2.5 text-sm font-medium rounded-lg text-text-sub hover:bg-gray-50 hover:text-text-main dark:text-gray-400 dark:hover:bg-[#1f2937] dark:hover:text-white transition-colors"
                                href="billingpayment.php">
                                <span class="material-symbols-outlined text-[20px]">credit_card</span>
                                Billing Methods
                            </a>
                        </nav>
                    </aside>
                    <div class="flex-1 space-y-8">
                        <div
                            class="relative overflow-hidden rounded-xl bg-gradient-to-r from-[#101622] to-[#1e2a44] p-8 text-white shadow-lg border border-[#2a3447]">
                            <div
                                class="relative z-10 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-6">
                                <div>
                                    <div class="flex items-center gap-2 mb-2">
                                        <span
                                            class="inline-flex items-center rounded-full bg-green-500/20 px-2 py-0.5 text-xs font-medium text-green-400 ring-1 ring-inset ring-green-500/30"><?= $hasDeveloperProfile ? 'Active' : 'New' ?></span>
                                        <h2 class="text-xl font-bold">
                                            <?= $hasDeveloperProfile ? 'Update Your Developer Profile' : 'Become a NeXLace Developer' ?>
                                        </h2>
                                    </div>
                                    <p class="text-gray-300 max-w-lg">
                                        <?= $hasDeveloperProfile ? 'Keep your developer profile up to date with your latest skills, projects, and experience to attract more clients.' : 'Create a specialized developer profile to showcase your skills, portfolio, and start bidding on high-quality projects today.' ?>
                                    </p>
                                </div>
                                <button onclick="window.location.href='createdeveloperprofile.php'"
                                    class="flex-shrink-0 rounded-full bg-primary px-6 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-primary-dark transition-all active:scale-95">
                                    <?= $hasDeveloperProfile ? 'Update Developer Profile' : 'Create Developer Profile' ?>
                                </button>
                            </div>
                            <div
                                class="absolute right-0 top-0 h-full w-1/3 bg-gradient-to-l from-primary/20 to-transparent">
                            </div>
                            <span
                                class="absolute right-[-20px] bottom-[-40px] material-symbols-outlined text-[180px] text-white opacity-5 rotate-12">code</span>
                        </div>
                        <div
                            class="rounded-xl border border-[#e7ebf3] dark:border-[#2a3447] bg-white dark:bg-card-dark shadow-sm">
                            <div class="border-b border-[#e7ebf3] px-6 py-4 dark:border-[#2a3447]">
                                <h3 class="text-lg font-bold text-text-main dark:text-white">Basic Information</h3>
                                <p class="text-sm text-text-sub">This information will be displayed on your public
                                    profile.</p>
                            </div>
                            <div class="p-6">
                                <div class="mb-8 flex items-center gap-6">
                                    <!-- Hidden file input for image upload -->
                                    <input type="file" id="profileImageInput" accept="image/*" class="hidden" />
                                    <div class="relative">
                                        <div id="profileImageCircle"
                                            class="h-24 w-24 rounded-full bg-primary text-white flex items-center justify-center text-2xl font-bold border-4 border-white dark:border-[#2a3447] shadow-md overflow-hidden">
                                            <span id="profileInitial"
                                                class="<?= !empty($profileImage) ? 'hidden' : '' ?>">
                                                <?= strtoupper($name[0]); ?>
                                            </span>
                                            <img id="profileImagePreview" src="<?= htmlspecialchars($profileImage); ?>"
                                                alt="Profile"
                                                class="<?= empty($profileImage) ? 'hidden' : '' ?> h-full w-full object-cover" />
                                        </div>
                                        <button onclick="openCameraModal()"
                                            class="absolute bottom-0 right-0 rounded-full bg-white dark:bg-[#2a3447] border border-gray-200 dark:border-gray-600 p-1.5 text-text-sub hover:text-primary transition-colors shadow-sm">
                                            <span class="material-symbols-outlined text-[18px]">photo_camera</span>
                                        </button>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-text-main dark:text-white">Profile Photo</h4>
                                        <p class="text-sm text-text-sub mb-3">Recommended 300x300px JPG or PNG.</p>
                                        <div class="flex gap-3">
                                            <button onclick="document.getElementById('profileImageInput').click()"
                                                class="rounded-md border border-[#e7ebf3] dark:border-[#2a3447] bg-white dark:bg-[#1a2333] px-3 py-1.5 text-sm font-medium text-text-main dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-[#2a3447] transition-colors">Change
                                                Photo</button>
                                            <button onclick="removeProfileImage()"
                                                class="rounded-md px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">Remove</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label
                                            class="mb-1.5 block text-sm font-medium text-text-main dark:text-gray-300"
                                            for="firstName">Full Name</label>
                                        <input
                                            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-[#0f1520] shadow-sm sm:text-sm cursor-not-allowed text-gray-500 dark:text-gray-400"
                                            id="firstName" type="text" value="<?= $name; ?>" readonly />
                                    </div>
                                    <div class="md:col-span-2">
                                        <label
                                            class="mb-1.5 block text-sm font-medium text-text-main dark:text-gray-300"
                                            for="email">Email Address</label>
                                        <div class="relative">
                                            <span
                                                class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-gray-400 text-[18px]">mail</span>
                                            <input
                                                class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-[#0f1520] pl-10 shadow-sm sm:text-sm cursor-not-allowed text-gray-500 dark:text-gray-400"
                                                id="email" type="email" value="<?= $email; ?>" readonly />
                                        </div>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label
                                            class="mb-1.5 block text-sm font-medium text-text-main dark:text-gray-300"
                                            for="headline">Professional Headline</label>
                                        <input
                                            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-[#1a2333] shadow-sm focus:border-primary focus:ring-primary sm:text-sm"
                                            id="headline" placeholder="e.g. Senior Frontend Developer" type="text"
                                            value="<?= htmlspecialchars($headline); ?>" maxlength="500" />
                                    </div>
                                    <div class="md:col-span-2">
                                        <label
                                            class="mb-1.5 block text-sm font-medium text-text-main dark:text-gray-300"
                                            for="bio">Bio</label>
                                        <textarea
                                            class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-[#1a2333] shadow-sm focus:border-primary focus:ring-primary sm:text-sm"
                                            id="bio" placeholder="Tell us a little bit about yourself..." rows="4"
                                            maxlength="500"><?= htmlspecialchars($bio); ?></textarea>
                                        <p class="mt-1.5 text-xs text-text-sub text-right"><span
                                                id="bioCharCount"><?= strlen($bio); ?></span>/500 characters</p>
                                    </div>
                                </div>
                                <div
                                    class="mt-8 flex justify-end gap-3 border-t border-[#e7ebf3] pt-6 dark:border-[#2a3447]">
                                    <button type="button" onclick="resetProfileForm()"
                                        class="rounded-lg px-4 py-2 text-sm font-bold text-text-sub hover:bg-gray-100 dark:hover:bg-[#1f2937] transition-colors">Cancel</button>
                                    <button type="button" onclick="saveProfileChanges()" id="saveProfileBtn"
                                        class="rounded-lg bg-primary px-6 py-2 text-sm font-bold text-white hover:bg-primary-dark transition-colors shadow-sm">Save
                                        Changes</button>
                                </div>
                            </div>
                        </div>
                        <div class="rounded-xl border border-[#e7ebf3] dark:border-[#2a3447] bg-white dark:bg-card-dark shadow-sm"
                            id="theme">
                            <div class="border-b border-[#e7ebf3] px-6 py-4 dark:border-[#2a3447]">
                                <h3 class="text-lg font-bold text-text-main dark:text-white">Interface Theme</h3>
                                <p class="text-sm text-text-sub">Choose how NeXLace looks to you.</p>
                            </div>
                            <div class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <label class="relative cursor-pointer group">
                                        <input class="peer sr-only" name="theme" type="radio" value="light" />
                                        <div
                                            class="rounded-lg border-2 border-transparent bg-gray-100 p-4 ring-2 ring-transparent peer-checked:border-primary peer-checked:ring-primary/20 hover:bg-gray-200 transition-all dark:bg-[#1a2333]">
                                            <div
                                                class="mb-3 h-24 rounded bg-white border border-gray-200 flex flex-col overflow-hidden shadow-sm">
                                                <div class="h-4 w-full bg-gray-100 border-b border-gray-200"></div>
                                                <div class="flex flex-1">
                                                    <div class="w-1/4 border-r border-gray-200 bg-gray-50"></div>
                                                    <div class="flex-1 bg-white"></div>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="material-symbols-outlined text-gray-500">light_mode</span>
                                                <span class="font-bold text-text-main dark:text-white">Light Mode</span>
                                            </div>
                                        </div>
                                        <div class="absolute right-4 top-4 hidden text-primary peer-checked:block">
                                            <span class="material-symbols-outlined">check_circle</span>
                                        </div>
                                    </label>
                                    <label class="relative cursor-pointer group">
                                        <input class="peer sr-only" name="theme" type="radio" value="dark" />
                                        <div
                                            class="rounded-lg border-2 border-transparent bg-gray-100 p-4 ring-2 ring-transparent peer-checked:border-primary peer-checked:ring-primary/20 hover:bg-gray-200 transition-all dark:bg-[#1a2333]">
                                            <div
                                                class="mb-3 h-24 rounded bg-[#151c2b] border border-[#2a3447] flex flex-col overflow-hidden shadow-sm">
                                                <div class="h-4 w-full bg-[#101622] border-b border-[#2a3447]"></div>
                                                <div class="flex flex-1">
                                                    <div class="w-1/4 border-r border-[#2a3447] bg-[#151c2b]"></div>
                                                    <div class="flex-1 bg-[#101622]"></div>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="material-symbols-outlined text-gray-500">dark_mode</span>
                                                <span class="font-bold text-text-main dark:text-white">Dark Mode</span>
                                            </div>
                                        </div>
                                        <div class="absolute right-4 top-4 hidden text-primary peer-checked:block">
                                            <span class="material-symbols-outlined">check_circle</span>
                                        </div>
                                    </label>
                                    <label class="relative cursor-pointer group">
                                        <input checked="" class="peer sr-only" name="theme" type="radio"
                                            value="system" />
                                        <div
                                            class="rounded-lg border-2 border-transparent bg-gray-100 p-4 ring-2 ring-transparent peer-checked:border-primary peer-checked:ring-primary/20 hover:bg-gray-200 transition-all dark:bg-[#1a2333]">
                                            <div
                                                class="mb-3 h-24 rounded bg-gray-100 flex overflow-hidden shadow-sm relative">
                                                <div class="absolute inset-0 flex">
                                                    <div class="w-1/2 bg-white border-r border-gray-200 flex flex-col">
                                                        <div class="h-4 w-full bg-gray-100 border-b border-gray-200">
                                                        </div>
                                                    </div>
                                                    <div class="w-1/2 bg-[#151c2b] flex flex-col">
                                                        <div class="h-4 w-full bg-[#101622] border-b border-[#2a3447]">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span
                                                    class="material-symbols-outlined text-gray-500">desktop_windows</span>
                                                <span class="font-bold text-text-main dark:text-white">System
                                                    Default</span>
                                            </div>
                                        </div>
                                        <div class="absolute right-4 top-4 hidden text-primary peer-checked:block">
                                            <span class="material-symbols-outlined">check_circle</span>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Camera Modal -->
        <div id="cameraModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/70">
            <div class="bg-white dark:bg-card-dark rounded-xl shadow-2xl max-w-lg w-full mx-4 overflow-hidden">
                <div
                    class="flex items-center justify-between px-6 py-4 border-b border-[#e7ebf3] dark:border-[#2a3447]">
                    <h3 class="text-lg font-bold text-text-main dark:text-white">Take a Photo</h3>
                    <button onclick="closeCameraModal()"
                        class="text-text-sub hover:text-text-main dark:hover:text-white transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="p-6">
                    <div class="relative rounded-lg overflow-hidden bg-black aspect-square max-w-sm mx-auto">
                        <video id="cameraVideo" autoplay playsinline class="w-full h-full object-cover"></video>
                        <canvas id="cameraCanvas" class="hidden"></canvas>
                    </div>
                    <div class="flex justify-center gap-4 mt-6">
                        <button onclick="closeCameraModal()"
                            class="rounded-lg px-6 py-2.5 text-sm font-bold text-text-sub hover:bg-gray-100 dark:hover:bg-[#1f2937] transition-colors">Cancel</button>
                        <button onclick="capturePhoto()"
                            class="rounded-lg bg-primary px-6 py-2.5 text-sm font-bold text-white hover:bg-primary-dark transition-colors shadow-sm flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px]">photo_camera</span>
                            Capture
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        // Logic for Theme Radio Buttons
        document.addEventListener('DOMContentLoaded', () => {
            const themeRadios = document.getElementsByName('theme');
            const savedTheme = localStorage.getItem('theme') || 'system';

            // Set initial checked state
            themeRadios.forEach(radio => {
                if (radio.value === savedTheme) {
                    radio.checked = true;
                }

                radio.addEventListener('change', (e) => {
                    if (e.target.checked) {
                        const newTheme = e.target.value;
                        localStorage.setItem('theme', newTheme);

                        const element = document.documentElement;
                        if (newTheme === 'dark' || (newTheme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                            element.classList.add('dark');
                        } else {
                            element.classList.remove('dark');
                        }
                    }
                });
            });

            // Profile Image Upload Preview
            const profileImageInput = document.getElementById('profileImageInput');
            const profileImagePreview = document.getElementById('profileImagePreview');
            const profileInitial = document.getElementById('profileInitial');

            profileImageInput.addEventListener('change', function (e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function (event) {
                        profileImagePreview.src = event.target.result;
                        profileImagePreview.classList.remove('hidden');
                        profileInitial.classList.add('hidden');

                        // Also update header profile image
                        const headerProfileImage = document.getElementById('headerProfileImage');
                        const headerProfileInitial = document.getElementById('headerProfileInitial');
                        headerProfileImage.src = event.target.result;
                        headerProfileImage.classList.remove('hidden');
                        headerProfileInitial.classList.add('hidden');
                    };
                    reader.readAsDataURL(file);
                }
            });
        });

        // Camera Modal Functions
        let cameraStream = null;

        function openCameraModal() {
            const modal = document.getElementById('cameraModal');
            const video = document.getElementById('cameraVideo');

            modal.classList.remove('hidden');

            // Check if mediaDevices API is available (requires HTTPS or localhost)
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                console.error('mediaDevices API not supported');
                alert('Camera access is blocked by your browser. Please access the site via localhost or ensure you are using a secure connection (HTTPS).');
                closeCameraModal();
                return;
            }

            // Request camera access
            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } })
                .then(stream => {
                    cameraStream = stream;
                    video.srcObject = stream;
                    
                    // Explicitly play the video after metadata loads to prevent black screen
                    video.onloadedmetadata = function(e) {
                        video.play().catch(err => {
                            console.error('Error auto-playing video:', err);
                        });
                    };
                })
                .catch(err => {
                    console.error('Camera access denied or failed:', err);
                    alert('Unable to access camera: ' + (err.message || 'Permission denied.'));
                    closeCameraModal();
                });
        }

        function closeCameraModal() {
            const modal = document.getElementById('cameraModal');
            const video = document.getElementById('cameraVideo');

            // Stop camera stream
            if (cameraStream) {
                cameraStream.getTracks().forEach(track => track.stop());
                cameraStream = null;
            }
            video.srcObject = null;
            modal.classList.add('hidden');
        }

        function capturePhoto() {
            const video = document.getElementById('cameraVideo');
            const canvas = document.getElementById('cameraCanvas');
            const profileImagePreview = document.getElementById('profileImagePreview');
            const profileInitial = document.getElementById('profileInitial');

            // Set canvas size to video size
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;

            // Draw the video frame to canvas
            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

            // Get the image data URL
            const imageDataUrl = canvas.toDataURL('image/png');

            // Update profile image
            profileImagePreview.src = imageDataUrl;
            profileImagePreview.classList.remove('hidden');
            profileInitial.classList.add('hidden');

            // Also update header profile image
            const headerProfileImage = document.getElementById('headerProfileImage');
            const headerProfileInitial = document.getElementById('headerProfileInitial');
            headerProfileImage.src = imageDataUrl;
            headerProfileImage.classList.remove('hidden');
            headerProfileInitial.classList.add('hidden');

            // Close modal
            closeCameraModal();
        }

        function removeProfileImage() {
            // Clear main profile image
            const profileImagePreview = document.getElementById('profileImagePreview');
            const profileInitial = document.getElementById('profileInitial');
            profileImagePreview.src = '';
            profileImagePreview.classList.add('hidden');
            profileInitial.classList.remove('hidden');

            // Clear header profile image
            const headerProfileImage = document.getElementById('headerProfileImage');
            const headerProfileInitial = document.getElementById('headerProfileInitial');
            headerProfileImage.src = '';
            headerProfileImage.classList.add('hidden');
            headerProfileInitial.classList.remove('hidden');

            // Clear the file input
            document.getElementById('profileImageInput').value = '';

            // Mark image as removed
            window.imageRemoved = true;
        }

        // Store original values for reset
        const originalHeadline = document.getElementById('headline').value;
        const originalBio = document.getElementById('bio').value;
        const originalImageSrc = document.getElementById('profileImagePreview').src;
        window.imageRemoved = false;

        // Bio character counter
        const bioTextarea = document.getElementById('bio');
        const bioCharCount = document.getElementById('bioCharCount');

        bioTextarea.addEventListener('input', function () {
            const currentLength = this.value.length;
            bioCharCount.textContent = currentLength;

            if (currentLength >= 450) {
                bioCharCount.classList.add('text-orange-500');
                bioCharCount.classList.remove('text-red-500');
            }
            if (currentLength >= 490) {
                bioCharCount.classList.remove('text-orange-500');
                bioCharCount.classList.add('text-red-500');
            }
            if (currentLength < 450) {
                bioCharCount.classList.remove('text-orange-500', 'text-red-500');
            }
        });

        // Save profile changes to database
        function saveProfileChanges() {
            const headline = document.getElementById('headline').value.trim();
            const bio = document.getElementById('bio').value.trim();
            const saveBtn = document.getElementById('saveProfileBtn');
            const profileImagePreview = document.getElementById('profileImagePreview');

            // Disable button and show loading state
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="flex items-center gap-2"><svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Saving...</span>';

            const formData = new FormData();
            formData.append('headline', headline);
            formData.append('bio', bio);

            // Add image data if it's a new image (base64)
            const imageSrc = profileImagePreview.src;
            if (imageSrc && imageSrc.startsWith('data:image')) {
                formData.append('image', imageSrc);
            }

            // Check if image was removed
            if (window.imageRemoved) {
                formData.append('removeImage', 'true');
            }

            csrfFetch('api/save_profile.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                        window.imageRemoved = false;
                    } else {
                        showNotification('Error: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Save error:', error);
                    showNotification('Failed to save profile. Please try again.', 'error');
                })
                .finally(() => {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = 'Save Changes';
                });
        }

        // Reset profile form to original values
        function resetProfileForm() {
            document.getElementById('headline').value = originalHeadline;
            document.getElementById('bio').value = originalBio;
            document.getElementById('bioCharCount').textContent = originalBio.length;
            document.getElementById('bioCharCount').classList.remove('text-orange-500', 'text-red-500');

            // Reset image
            const profileImagePreview = document.getElementById('profileImagePreview');
            const profileInitial = document.getElementById('profileInitial');
            const headerProfileImage = document.getElementById('headerProfileImage');
            const headerProfileInitial = document.getElementById('headerProfileInitial');

            if (originalImageSrc && originalImageSrc !== window.location.href) {
                profileImagePreview.src = originalImageSrc;
                profileImagePreview.classList.remove('hidden');
                profileInitial.classList.add('hidden');
                headerProfileImage.src = originalImageSrc;
                headerProfileImage.classList.remove('hidden');
                headerProfileInitial.classList.add('hidden');
            } else {
                profileImagePreview.src = '';
                profileImagePreview.classList.add('hidden');
                profileInitial.classList.remove('hidden');
                headerProfileImage.src = '';
                headerProfileImage.classList.add('hidden');
                headerProfileInitial.classList.remove('hidden');
            }

            window.imageRemoved = false;
            document.getElementById('profileImageInput').value = '';

            showNotification('Changes discarded', 'success');
        }

        // Show notification function
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `fixed bottom-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 transition-all duration-300 transform translate-y-0 ${type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
                }`;
            notification.innerHTML = `
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-[20px]">${type === 'success' ? 'check_circle' : 'error'}</span>
                    <span>${message}</span>
                </div>
            `;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.classList.add('translate-y-full', 'opacity-0');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
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