<?php
require_once 'includes/auth_helper.php';
requireAuth();

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

// Fetch profile image from database
try {
    require_once 'includes/db_helper.php';
    $conn = getDB();
    if ($conn && !empty($email)) {
        $userStmt = $conn->prepare("SELECT `image` FROM register WHERE Email = :email");
        $userStmt->execute([':email' => $email]);
        $currentUser = $userStmt->fetch();
        if ($currentUser) {
            $profileImage = $currentUser['image'] ?? '';
        }
    }
} catch (Exception $e) {
    error_log("Error fetching profile image: " . $e->getMessage());
}


// Include database configuration
require_once 'config/database.php';
require_once 'config/security_headers.php';
require_once 'config/csrf.php';
$csrfToken = generateCsrfToken();

$successMessage = '';
$errorMessage = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['publish_profile'])) {
    try {
        // Create database connection
        require_once 'includes/db_helper.php';
        $conn = getDB();

        if ($conn === null) {
            throw new Exception("Database connection failed");
        }

        // Get user_id from session (assuming it's stored during login)
        $user_id = $_SESSION['user_id'] ?? null;

        // If user_id is not in session, try to get it from register table using email
        if ($user_id === null && !empty($email)) {
            $stmt = $conn->prepare("SELECT id FROM register WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            $user_id = $user['id'] ?? null;
        }

        if ($user_id === null) {
            throw new Exception("User ID not found. Please log in again.");
        }

        // Get form data
        $title = trim($_POST['headline'] ?? '');
        $rate = floatval($_POST['rate'] ?? 0);
        $availability = $_POST['availability'] ?? 'more_than_30';
        $location = trim($_POST['location'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $languages = trim($_POST['languages'] ?? '');
        $work_history = trim($_POST['work_history'] ?? '');
        $education = trim($_POST['education_info'] ?? '');
        $project_link = trim($_POST['project_link'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $skills = trim($_POST['skills'] ?? '');

        // Validate required fields
        if (empty($title) || $rate <= 0 || empty($bio) || empty($skills) || empty($location) || empty($phone)) {
            throw new Exception("Please fill in all required fields");
        }

        // Handle profile image upload
        $imagePath = null;
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'assetes/profile_images/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $fileExtension = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (!in_array($fileExtension, $allowedExtensions)) {
                throw new Exception("Invalid profile image format. Allowed: JPG, PNG, GIF, WEBP");
            }
            $fileName = 'profile_' . $user_id . '_' . time() . '.' . $fileExtension;
            $imagePath = $uploadDir . $fileName;
            if (!move_uploaded_file($_FILES['profile_image']['tmp_name'], $imagePath)) {
                throw new Exception("Failed to upload profile image");
            }
        }

        // Handle portfolio image upload (single image only)
        $portfolioPath = null;
        if (isset($_FILES['portfolio_image']) && $_FILES['portfolio_image']['error'] === UPLOAD_ERR_OK) {
            $portfolioDir = 'assetes/portfolio_images/';
            if (!is_dir($portfolioDir)) {
                mkdir($portfolioDir, 0755, true);
            }
            $pExtension = strtolower(pathinfo($_FILES['portfolio_image']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($pExtension, $allowedExtensions)) {
                $pFileName = 'portfolio_' . $user_id . '_' . time() . '.' . $pExtension;
                $portfolioPath = $portfolioDir . $pFileName;
                if (!move_uploaded_file($_FILES['portfolio_image']['tmp_name'], $portfolioPath)) {
                    $portfolioPath = null;
                }
            }
        }

        // Check if developer profile already exists for this user
        $checkStmt = $conn->prepare("SELECT id, image_path, portfolio_images FROM developers WHERE user_id = ?");
        $checkStmt->execute([$user_id]);
        $existingProfile = $checkStmt->fetch();

        if ($existingProfile) {
            // Keep existing image if no new one uploaded
            $finalImagePath = $imagePath ?? $existingProfile['image_path'];

            // Keep existing portfolio image if no new one uploaded
            $finalPortfolio = $portfolioPath ?? $existingProfile['portfolio_images'];

            $sql = "UPDATE developers SET title = ?, rate = ?, availability = ?, location = ?, phone = ?, languages = ?, work_history = ?, education = ?, project_link = ?, bio = ?, skills = ?, image_path = ?, portfolio_images = ?, created_at = NOW() WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$title, $rate, $availability, $location, $phone, $languages, $work_history, $education, $project_link, $bio, $skills, $finalImagePath, $finalPortfolio, $user_id]);

            $successMessage = "Developer profile updated successfully!";
        } else {
            // Insert new profile
            $sql = "INSERT INTO developers (user_id, title, rate, availability, location, phone, languages, work_history, education, project_link, bio, skills, image_path, portfolio_images, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$user_id, $title, $rate, $availability, $location, $phone, $languages, $work_history, $education, $project_link, $bio, $skills, $imagePath, $portfolioPath]);
            $successMessage = "Developer profile created successfully!";
        }

        // Set session flag for developer profile
        $_SESSION['is_developer'] = true;

    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
        error_log("Developer Profile Error: " . $e->getMessage());
    }
}

// Fetch existing profile data if not a POST request (or even if it is, to show updated data)
$existingData = [];
// Initialize with empty defaults to avoid warnings
$existingData = [
    'title' => '',
    'rate' => '',
    'availability' => 'more_than_30',
    'location' => '',
    'phone' => '',
    'languages' => '',
    'work_history' => '',
    'education' => '',
    'project_link' => '',
    'bio' => '',
    'skills' => '',
    'image_path' => '',
    'portfolio_images' => '[]'
];

try {
    require_once 'includes/db_helper.php';
    $conn = getDB();
    $user_id = $_SESSION['user_id'] ?? null;

    if ($user_id === null && !empty($email)) {
        $stmt = $conn->prepare("SELECT id FROM register WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        $user_id = $user['id'] ?? null;
    }

    if ($user_id) {
        $stmt = $conn->prepare("SELECT * FROM developers WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $profile = $stmt->fetch();
        if ($profile) {
            $existingData = array_merge($existingData, $profile);
        }
    }
} catch (Exception $e) {
    // Silent error or log it
    error_log("Error fetching profile: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<!-- saved from url=(0080)file:///C:/Users/lohit/Downloads/NeXLace%20-%20Create%20Developer%20Profile.html -->
<html class="">

<head>
    <link rel="icon" type="image/png" href="assetes/logo.png" />
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">

    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>NeXLace - Create Developer Profile</title>
    <link href="https://fonts.googleapis.com/" rel="preconnect">
    <link crossorigin="" href="https://fonts.gstatic.com/" rel="preconnect">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">

    <!-- Tailwind CDN must load FIRST -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Then configure Tailwind -->
    <script>
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

    <!-- Dark mode detection -->
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
                    <a class="inline-flex items-center gap-2 text-sm text-text-sub hover:text-primary mb-4 transition-colors"
                        href="profilesetting.php">
                        <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                        Back to Settings
                    </a>
                    <h1 class="text-3xl font-bold tracking-tight text-text-main dark:text-white">Create Developer
                        Profile</h1>
                    <p class="text-text-sub mt-2">Set up your professional presence to start bidding on projects.</p>

                    <?php if (!empty($successMessage)): ?>
                        <div
                            class="mt-4 p-4 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 flex items-center gap-3">
                            <span class="material-symbols-outlined text-green-600 dark:text-green-400">check_circle</span>
                            <p class="text-green-800 dark:text-green-300 font-medium">
                                <?= htmlspecialchars($successMessage); ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errorMessage)): ?>
                        <div
                            class="mt-4 p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 flex items-center gap-3">
                            <span class="material-symbols-outlined text-red-600 dark:text-red-400">error</span>
                            <p class="text-red-800 dark:text-red-300 font-medium"><?= htmlspecialchars($errorMessage); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="flex flex-col lg:flex-row gap-8">
                    <aside class="w-full lg:w-64 flex-shrink-0 hidden lg:block">
                        <nav class="sticky top-24">
                            <!-- Updated Navigation with Dynamic Tracking -->
                            <div class="relative pl-4 border-l-2 border-gray-200 dark:border-gray-700 space-y-6"
                                id="nav-sidebar">
                                <a class="block group relative nav-link active" href="#basic-info"
                                    data-section="basic-info">
                                    <span
                                        class="nav-dot absolute -left-[21px] top-1 h-3 w-3 rounded-full bg-primary ring-4 ring-white dark:ring-card-dark transition-colors"></span>
                                    <span class="nav-text text-sm font-bold text-primary transition-colors">1. Basic
                                        Info</span>
                                </a>
                                <a class="block group relative nav-link" href="#skills-bio" data-section="skills-bio">
                                    <span
                                        class="nav-dot absolute -left-[21px] top-1 h-3 w-3 rounded-full bg-gray-300 dark:bg-gray-600 ring-4 ring-white dark:ring-card-dark group-hover:bg-primary/50 transition-colors"></span>
                                    <span
                                        class="nav-text text-sm font-medium text-text-sub group-hover:text-text-main dark:group-hover:text-white transition-colors">2.
                                        Skills &amp; Bio</span>
                                </a>
                                <a class="block group relative nav-link" href="#portfolio" data-section="portfolio">
                                    <span
                                        class="nav-dot absolute -left-[21px] top-1 h-3 w-3 rounded-full bg-gray-300 dark:bg-gray-600 ring-4 ring-white dark:ring-card-dark group-hover:bg-primary/50 transition-colors"></span>
                                    <span
                                        class="nav-text text-sm font-medium text-text-sub group-hover:text-text-main dark:group-hover:text-white transition-colors">3.
                                        Portfolio</span>
                                </a>
                                <a class="block group relative nav-link" href="#experience" data-section="experience">
                                    <span
                                        class="nav-dot absolute -left-[21px] top-1 h-3 w-3 rounded-full bg-gray-300 dark:bg-gray-600 ring-4 ring-white dark:ring-card-dark group-hover:bg-primary/50 transition-colors"></span>
                                    <span
                                        class="nav-text text-sm font-medium text-text-sub group-hover:text-text-main dark:group-hover:text-white transition-colors">4.
                                        Experience</span>
                                </a>
                                <a class="block group relative nav-link" href="#education" data-section="education">
                                    <span
                                        class="nav-dot absolute -left-[21px] top-1 h-3 w-3 rounded-full bg-gray-300 dark:bg-gray-600 ring-4 ring-white dark:ring-card-dark group-hover:bg-primary/50 transition-colors"></span>
                                    <span
                                        class="nav-text text-sm font-medium text-text-sub group-hover:text-text-main dark:group-hover:text-white transition-colors">5.
                                        Education</span>
                                </a>
                            </div>
                        </nav>
                    </aside>

                    <!-- Form with proper action and method -->
                    <form id="developerProfileForm" action="createdeveloperprofile.php" method="POST"
                        enctype="multipart/form-data" class="flex-1 space-y-6">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                        <!-- Basic Info Section -->
                        <div class="rounded-xl border border-[#e7ebf3] dark:border-[#2a3447] bg-white dark:bg-card-dark shadow-sm scroll-mt-24"
                            id="basic-info">
                            <div
                                class="border-b border-[#e7ebf3] px-6 py-4 dark:border-[#2a3447] flex justify-between items-center">
                                <h3 class="text-lg font-bold text-text-main dark:text-white">Basic Information</h3>
                            </div>
                            <div class="p-6">
                                <div class="mb-8 flex flex-col sm:flex-row items-center gap-6">
                                    <div class="relative group cursor-pointer">
                                        <div
                                            class="h-28 w-28 rounded-full bg-gray-100 dark:bg-[#1a2333] flex items-center justify-center border-2 border-dashed border-gray-300 dark:border-gray-600 hover:border-primary dark:hover:border-primary transition-colors overflow-hidden">
                                            <?php if (!empty($existingData['image_path'])): ?>
                                                <img src="<?= htmlspecialchars($existingData['image_path']) ?>"
                                                    alt="Profile Preview" class="h-full w-full object-cover">
                                                <span
                                                    class="material-symbols-outlined text-gray-400 text-[40px] group-hover:text-primary transition-colors"
                                                    style="display:none;">add_a_photo</span>
                                            <?php else: ?>
                                                <span
                                                    class="material-symbols-outlined text-gray-400 text-[40px] group-hover:text-primary transition-colors">add_a_photo</span>
                                                <img alt="Profile Preview" class="hidden h-full w-full object-cover">
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="text-center sm:text-left">
                                        <h4 class="font-bold text-text-main dark:text-white">Profile Photo</h4>
                                        <p class="text-sm text-text-sub mb-3">Professional headshot recommended.</p>
                                        <input type="file" id="profileImageInput" name="profile_image" accept="image/*"
                                            class="hidden" onchange="previewProfileImage(this)">
                                        <button type="button"
                                            onclick="document.getElementById('profileImageInput').click()"
                                            class="text-sm font-medium text-primary hover:text-primary-dark transition-colors">Upload
                                            Image</button>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="md:col-span-2">
                                        <label
                                            class="mb-2 block text-sm font-semibold text-text-main dark:text-gray-300"
                                            for="headline">Professional Title <span
                                                class="text-red-500">*</span></label>
                                        <input
                                            class="block w-full rounded-xl border border-[#e7ebf3] dark:border-[#2a3447] bg-white dark:bg-[#1a2333] px-4 py-3 shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/20 text-base placeholder-gray-400 dark:placeholder-gray-500 transition-all duration-200"
                                            id="headline" name="headline" placeholder="e.g. Senior Full Stack Developer"
                                            type="text" value="<?= htmlspecialchars($existingData['title'] ?? '') ?>"
                                            required>
                                    </div>
                                    <div>
                                        <label
                                            class="mb-2 block text-sm font-semibold text-text-main dark:text-gray-300"
                                            for="rate">Hourly Rate (₹INR) <span class="text-red-500">*</span></label>
                                        <div class="relative">
                                            <span
                                                class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400 text-base">₹</span>
                                            <input
                                                class="block w-full rounded-xl border border-[#e7ebf3] dark:border-[#2a3447] bg-white dark:bg-[#1a2333] pl-9 pr-12 py-3 shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/20 text-base transition-all duration-200"
                                                id="rate" name="rate" placeholder="0.00" type="number"
                                                value="<?= htmlspecialchars($existingData['rate'] ?? '') ?>" required>
                                            <span
                                                class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500 text-sm font-medium">/hr</span>
                                        </div>
                                    </div>
                                    <div>
                                        <label
                                            class="mb-2 block text-sm font-semibold text-text-main dark:text-gray-300"
                                            for="availability">Availability</label>
                                        <select name="availability"
                                            class="block w-full rounded-xl border border-[#e7ebf3] dark:border-[#2a3447] bg-white dark:bg-[#1a2333] px-4 py-3 shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/20 text-base transition-all duration-200">
                                            <option value="more_than_30"
                                                <?= ($existingData['availability'] == 'more_than_30') ? 'selected' : '' ?>>
                                                More than 30 hrs/week</option>
                                            <option value="less_than_30"
                                                <?= ($existingData['availability'] == 'less_than_30') ? 'selected' : '' ?>>
                                                Less than 30 hrs/week</option>
                                            <option value="as_needed" <?= ($existingData['availability'] == 'as_needed') ? 'selected' : '' ?>>As needed - Open to offers</option>
                                        </select>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label
                                            class="mb-2 block text-sm font-semibold text-text-main dark:text-gray-300"
                                            for="location">Location <span class="text-red-500">*</span></label>
                                        <input
                                            class="block w-full rounded-xl border border-[#e7ebf3] dark:border-[#2a3447] bg-white dark:bg-[#1a2333] px-4 py-3 shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/20 text-base placeholder-gray-400 dark:placeholder-gray-500 transition-all duration-200"
                                            id="location" name="location" placeholder="e.g. Bengaluru, India or Remote"
                                            type="text" value="<?= htmlspecialchars($existingData['location'] ?? '') ?>"
                                            required>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label
                                            class="mb-2 block text-sm font-semibold text-text-main dark:text-gray-300"
                                            for="phone">Phone no. <span class="text-red-500">*</span></label>
                                        <input
                                            class="block w-full rounded-xl border border-[#e7ebf3] dark:border-[#2a3447] bg-white dark:bg-[#1a2333] px-4 py-3 shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/20 text-base placeholder-gray-400 dark:placeholder-gray-500 transition-all duration-200"
                                            id="phone" name="phone" placeholder="e.g. 1234567890" type="text"
                                            value="<?= htmlspecialchars($existingData['phone'] ?? '') ?>" required>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label
                                            class="mb-2 block text-sm font-semibold text-text-main dark:text-gray-300"
                                            for="languages">Languages </label>
                                        <input
                                            class="block w-full rounded-xl border border-[#e7ebf3] dark:border-[#2a3447] bg-white dark:bg-[#1a2333] px-4 py-3 shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/20 text-base placeholder-gray-400 dark:placeholder-gray-500 transition-all duration-200"
                                            id="languages" name="languages" placeholder="e.g. English, Hindi"
                                            type="text"
                                            value="<?= htmlspecialchars($existingData['languages'] ?? '') ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Skills & Bio Section -->
                        <div class="rounded-xl border border-[#e7ebf3] dark:border-[#2a3447] bg-white dark:bg-card-dark shadow-sm scroll-mt-24"
                            id="skills-bio">
                            <div
                                class="border-b border-[#e7ebf3] px-6 py-4 dark:border-[#2a3447] flex justify-between items-center">
                                <h3 class="text-lg font-bold text-text-main dark:text-white">Skills &amp; Bio</h3>
                            </div>
                            <div class="p-6 space-y-6">
                                <div>
                                    <label class="mb-2 block text-sm font-semibold text-text-main dark:text-gray-300"
                                        for="bio">Professional Overview <span class="text-red-500">*</span></label>
                                    <textarea
                                        class="block w-full rounded-xl border border-[#e7ebf3] dark:border-[#2a3447] bg-white dark:bg-[#1a2333] px-4 py-3 shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/20 text-base placeholder-gray-400 dark:placeholder-gray-500 transition-all duration-200"
                                        id="bio" name="bio"
                                        placeholder="Highlight your top achievements experience and why should clients hire you"
                                        rows="6" required><?= htmlspecialchars($existingData['bio'] ?? '') ?></textarea>

                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-semibold text-text-main dark:text-gray-300"
                                        for="skills">Skills <span class="text-red-500">*</span></label>
                                    <textarea
                                        class="block w-full rounded-xl border border-[#e7ebf3] dark:border-[#2a3447] bg-white dark:bg-[#1a2333] px-4 py-3 shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/20 text-base placeholder-gray-400 dark:placeholder-gray-500 transition-all duration-200"
                                        id="skills" name="skills" placeholder="Highlight your top skills" rows="3"
                                        required><?= htmlspecialchars($existingData['skills'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Portfolio Section -->
                        <div class="rounded-xl border border-[#e7ebf3] dark:border-[#2a3447] bg-white dark:bg-card-dark shadow-sm scroll-mt-24"
                            id="portfolio">
                            <div
                                class="border-b border-[#e7ebf3] px-6 py-4 dark:border-[#2a3447] flex justify-between items-center">
                                <h3 class="text-lg font-bold text-text-main dark:text-white">Portfolio</h3>
                            </div>
                            <div class="p-6">
                                <input type="file" id="portfolioImageInput" name="portfolio_image" accept="image/*"
                                    class="hidden" onchange="previewPortfolioImage(this)">
                                <?php
                                // Check if portfolio_images has a valid image path (not empty, not '[]', and is a valid file path)
                                $hasValidPortfolioImage = !empty($existingData['portfolio_images'])
                                    && $existingData['portfolio_images'] !== '[]'
                                    && $existingData['portfolio_images'] !== 'null'
                                    && strpos($existingData['portfolio_images'], '/') !== false;
                                ?>
                                <div id="portfolioGrid" class="grid grid-cols-1 gap-4">
                                    <!-- Existing portfolio image (shown if valid image exists) -->
                                    <div id="portfolio-item-existing"
                                        class="relative group <?= $hasValidPortfolioImage ? '' : 'hidden' ?>">
                                        <img id="existingPortfolioImg"
                                            src="<?= $hasValidPortfolioImage ? htmlspecialchars($existingData['portfolio_images']) : '' ?>"
                                            alt="Portfolio Image"
                                            class="h-48 w-full object-cover rounded-xl border border-gray-200 dark:border-gray-700">
                                        <button type="button" onclick="removePortfolioImage()"
                                            class="absolute top-2 right-2 bg-red-500 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <span class="material-symbols-outlined text-[16px]">close</span>
                                        </button>
                                        <div
                                            class="absolute bottom-2 left-2 bg-black/50 text-white text-xs px-2 py-1 rounded">
                                            Portfolio Image
                                        </div>
                                    </div>

                                    <!-- Upload button (shown when no image exists or after removing) -->
                                    <button type="button" id="uploadPortfolioBtn"
                                        onclick="document.getElementById('portfolioImageInput').click()"
                                        class="<?= $hasValidPortfolioImage ? 'hidden' : '' ?> flex h-48 w-full flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-[#1a2333]/50 hover:border-primary hover:bg-primary/5 dark:hover:border-primary transition-all group">
                                        <div
                                            class="rounded-full bg-white dark:bg-[#2a3447] p-3 shadow-sm group-hover:scale-110 transition-transform">
                                            <span
                                                class="material-symbols-outlined text-primary text-[24px]">cloud_upload</span>
                                        </div>
                                        <span
                                            class="mt-3 text-sm font-bold text-text-sub group-hover:text-primary transition-colors">Upload
                                            Portfolio Image</span>
                                        <span class="text-xs text-gray-400 mt-1">Only 1 image allowed</span>
                                    </button>
                                </div>
                                <!-- Hidden input to track if image should be removed -->
                                <input type="hidden" id="removePortfolioFlag" name="remove_portfolio" value="0">

                                <div class="p-6">
                                    <label for="projRepo"
                                        class="block text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                                        GitHub Repository / Live Project URL
                                    </label>

                                    <div class="relative">
                                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                                            <span class="material-symbols-outlined text-[20px]">link</span>
                                        </span>

                                        <input id="projRepo" name="project_link" type="url"
                                            placeholder="https://github.com/username/project or https://yourproject.com"
                                            class="w-full pl-11 pr-4 py-3 rounded-xl border border-[#e7ebf3] dark:border-[#2a3447]
                   bg-white dark:bg-[#1a2333] text-text-main dark:text-white text-base
                   focus:ring-2 focus:ring-primary/20 focus:border-primary placeholder-gray-400 dark:placeholder-gray-500 transition-all duration-200"
                                            pattern="https?://.*"
                                            value="<?= htmlspecialchars($existingData['project_link'] ?? '') ?>"
                                            required />
                                    </div>

                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        Add a GitHub repository or live demo link for this project.
                                    </p>
                                </div>

                            </div>
                        </div>

                        <!-- Experience Section -->
                        <div class="rounded-xl border border-[#e7ebf3] dark:border-[#2a3447] bg-white dark:bg-card-dark shadow-sm scroll-mt-24"
                            id="experience">
                            <div
                                class="border-b border-[#e7ebf3] px-6 py-4 dark:border-[#2a3447] flex justify-between items-center">
                                <h3 class="text-lg font-bold text-text-main dark:text-white">Experience &amp; Education
                                </h3>
                            </div>
                            <div class="p-6 space-y-8">
                                <!-- Hidden file input for work history -->
                                <input type="file" id="workHistoryInput" name="work_history_files[]"
                                    accept="image/*,.pdf,.doc,.docx" class="hidden" onchange="previewWorkHistory(this)"
                                    multiple>

                                <div>
                                    <div class="flex items-center justify-between mb-4">
                                        <h4 class="font-bold text-text-main dark:text-white flex items-center gap-2">
                                            <span class="material-symbols-outlined text-text-sub">work</span>
                                            Work History
                                        </h4>

                                    </div>
                                    <div>
                                        <label
                                            class="mb-1.5 block text-sm font-medium text-text-main dark:text-gray-300"
                                            for="bio"></label>
                                        <textarea
                                            class="w-full px-4 py-3 rounded-xl border border-[#e7ebf3] dark:border-[#2a3447]
                   bg-white dark:bg-[#1a2333] text-text-main dark:text-white text-base
                   focus:ring-2 focus:ring-primary/20 focus:border-primary placeholder-gray-400 dark:placeholder-gray-500 transition-all duration-200"
                                            id="work_history" name="work_history" rows="8"
                                            placeholder="Led the development of a React-based design system used across 5 internal products. Improved application performance by 40% through code splitting and optimized state management."
                                            required><?= htmlspecialchars($existingData['work_history'] ?? '') ?></textarea>

                                    </div>
                                </div>

                                <!-- Education Sub-section -->
                                <div class="pt-6 border-t border-gray-100 dark:border-gray-800" id="education">
                                    <div class="flex items-center justify-between mb-4">
                                        <h4 class="font-bold text-text-main dark:text-white flex items-center gap-2">
                                            <span class="material-symbols-outlined text-text-sub">school</span>
                                            Education
                                        </h4>
                                    </div>
                                    <div>
                                        <label
                                            class="mb-1.5 block text-sm font-medium text-text-main dark:text-gray-300"
                                            for="bio"></label>
                                        <textarea
                                            class="w-full px-4 py-3 rounded-xl border border-[#e7ebf3] dark:border-[#2a3447]
                   bg-white dark:bg-[#1a2333] text-text-main dark:text-white text-base
                   focus:ring-2 focus:ring-primary/20 focus:border-primary placeholder-gray-400 dark:placeholder-gray-500 transition-all duration-200"
                                            id="education_info" name="education_info" rows="8"
                                            placeholder="BS Computer Science University of Tech | 2018-2022"
                                            required><?= htmlspecialchars($existingData['education'] ?? '') ?></textarea>

                                    </div>
                                </div>
                            </div>
                        </div>
                </div>

                <!-- Sticky Footer with Buttons -->
                <div
                    class="sticky bottom-0 z-30 -mx-4 sm:-mx-6 lg:-mx-8 border-t border-[#e7ebf3] bg-white/90 dark:bg-card-dark/95 backdrop-blur px-4 py-4 sm:px-6 lg:px-8 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)] dark:border-[#2a3447] flex justify-end items-center mt-8">
                    <div class="flex gap-4">
                        <button type="button" onclick="showPreviewModal()"
                            class="hidden sm:inline-block rounded-lg px-6 py-2.5 text-sm font-bold text-primary border border-primary/20 hover:bg-primary/5 transition-colors">
                            Preview
                        </button>
                        <button id="publishBtn" type="submit" name="publish_profile"
                            class="rounded-lg bg-primary px-8 py-2.5 text-sm font-bold text-white shadow-lg hover:bg-primary-dark hover:shadow-primary/30 transition-all active:scale-95">
                            Publish Profile
                        </button>
                    </div>
                </div>
                </form>
            </div>
    </div>
    </main>
    </div>

    <!-- Preview Modal -->
    <div id="previewModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closePreviewModal()"></div>
        <div class="relative min-h-screen flex items-center justify-center p-4">
            <div
                class="relative bg-white dark:bg-card-dark rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <!-- Modal Header -->
                <div
                    class="sticky top-0 bg-white dark:bg-card-dark border-b border-gray-200 dark:border-gray-700 px-6 py-4 flex justify-between items-center">
                    <h2 class="text-xl font-bold text-text-main dark:text-white">Profile Preview</h2>
                    <button onclick="closePreviewModal()"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-white transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <!-- Modal Content -->
                <div class="p-6 space-y-6" id="previewContent">
                    <!-- Content will be populated by JavaScript -->
                </div>
                <!-- Modal Footer -->
                <div
                    class="sticky bottom-0 bg-white dark:bg-card-dark border-t border-gray-200 dark:border-gray-700 px-6 py-4 flex justify-end gap-3">
                    <button onclick="closePreviewModal()"
                        class="rounded-lg px-6 py-2.5 text-sm font-bold text-text-sub hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                        Close
                    </button>
                    
                </div>
            </div>
        </div>
    </div>

    <script>
        // Preview Modal Functions
        function showPreviewModal() {
            const modal = document.getElementById('previewModal');
            const content = document.getElementById('previewContent');

            // Get form values
            const headline = document.getElementById('headline')?.value || 'Not provided';
            const rate = document.getElementById('rate')?.value || 'Not provided';
            const availability = document.querySelector('select[name="availability"]')?.selectedOptions[0]?.text || 'Not provided';
            const bio = document.getElementById('bio')?.value || 'Not provided';
            const skills = document.getElementById('skills')?.value || 'Not provided';

            // Get profile image
            const profileImg = document.querySelector('.h-28.w-28.rounded-full img');
            const profileImgSrc = profileImg && !profileImg.classList.contains('hidden') ? profileImg.src : null;

            // Get portfolio items
            const portfolioItems = document.querySelectorAll('#portfolioGrid > div[id^="portfolio-item"]');
            let portfolioHTML = '';
            if (portfolioItems.length > 0) {
                portfolioHTML = '<div class="grid grid-cols-3 gap-2">';
                portfolioItems.forEach(item => {
                    const img = item.querySelector('img');
                    if (img) {
                        portfolioHTML += `<img src="${img.src}" alt="Portfolio" class="h-20 w-full object-cover rounded-lg">`;
                    }
                });
                portfolioHTML += '</div>';
            } else {
                portfolioHTML = '<p class="text-text-sub text-sm">No portfolio items added</p>';
            }

            // Get work history from textarea
            const workHistoryText = document.getElementById('work_history')?.value || '';
            let workHistoryHTML = '';
            if (workHistoryText.trim()) {
                // Split by newlines and display as list
                const workItems = workHistoryText.split('\n').filter(item => item.trim());
                if (workItems.length > 0) {
                    workHistoryHTML = '<ul class="space-y-2">';
                    workItems.forEach(item => {
                        workHistoryHTML += `<li class="flex items-start gap-2 text-sm text-text-sub">
                            <span class="material-symbols-outlined text-primary text-[16px] mt-0.5">check_circle</span>
                            <span>${item.trim()}</span>
                        </li>`;
                    });
                    workHistoryHTML += '</ul>';
                } else {
                    workHistoryHTML = `<p class="text-text-sub text-sm whitespace-pre-wrap">${workHistoryText}</p>`;
                }
            } else {
                workHistoryHTML = '<p class="text-text-sub text-sm">No work history added</p>';
            }

            // Get education from textarea
            const educationText = document.getElementById('education_info')?.value || '';
            let educationHTML = '';
            if (educationText.trim()) {
                // Split by newlines and display as list
                const eduItems = educationText.split('\n').filter(item => item.trim());
                if (eduItems.length > 0) {
                    educationHTML = '<ul class="space-y-2">';
                    eduItems.forEach(item => {
                        educationHTML += `<li class="flex items-start gap-2 text-sm">
                            <span class="material-symbols-outlined text-primary text-[16px] mt-0.5">school</span>
                            <span class="text-text-sub">${item.trim()}</span>
                        </li>`;
                    });
                    educationHTML += '</ul>';
                } else {
                    educationHTML = `<p class="text-text-sub text-sm whitespace-pre-wrap">${educationText}</p>`;
                }
            } else {
                educationHTML = '<p class="text-text-sub text-sm">No education added</p>';
            }

            // Build preview content
            content.innerHTML = `
                <!-- Profile Header -->
                <div class="flex items-center gap-4 pb-4 border-b border-gray-200 dark:border-gray-700">
                    ${profileImgSrc
                    ? `<img src="${profileImgSrc}" alt="Profile" class="h-16 w-16 rounded-full object-cover">`
                    : `<div class="h-16 w-16 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                            <span class="material-symbols-outlined text-gray-400">person</span>
                           </div>`
                }
                    <div>
                        <h3 class="font-bold text-lg text-text-main dark:text-white">${headline}</h3>
                        <p class="text-primary font-medium">₹${rate}/hr • ${availability}</p>
                    </div>
                </div>
                
                <!-- Bio -->
                <div>
                    <h4 class="font-bold text-sm text-text-main dark:text-white mb-2 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-text-sub">person</span>
                        Professional Overview
                    </h4>
                    <p class="text-sm text-text-sub leading-relaxed">${bio}</p>
                </div>
                
                <!-- Skills -->
                <div>
                    <h4 class="font-bold text-sm text-text-main dark:text-white mb-2 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-text-sub">code</span>
                        Skills
                    </h4>
                    <p class="text-sm text-text-sub">${skills}</p>
                </div>
                
                <!-- Portfolio -->
                <div>
                    <h4 class="font-bold text-sm text-text-main dark:text-white mb-2 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-text-sub">folder</span>
                        Portfolio
                    </h4>
                    ${portfolioHTML}
                </div>
                
                <!-- Work History -->
                <div>
                    <h4 class="font-bold text-sm text-text-main dark:text-white mb-2 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-text-sub">work</span>
                        Work History
                    </h4>
                    ${workHistoryHTML}
                </div>
                
                <!-- Education -->
                <div>
                    <h4 class="font-bold text-sm text-text-main dark:text-white mb-2 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-text-sub">school</span>
                        Education
                    </h4>
                    ${educationHTML}
                </div>
            `;

            // Show modal
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closePreviewModal() {
            const modal = document.getElementById('previewModal');
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }

        // Profile Image Preview
        function previewProfileImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    const photoContainer = document.querySelector('.h-28.w-28.rounded-full');
                    const icon = photoContainer.querySelector('.material-symbols-outlined');
                    let img = photoContainer.querySelector('img');

                    if (icon) {
                        icon.style.display = 'none';
                    }

                    if (img) {
                        img.src = e.target.result;
                        img.classList.remove('hidden');
                    } else {
                        img = document.createElement('img');
                        img.src = e.target.result;
                        img.alt = 'Profile Preview';
                        img.className = 'h-full w-full object-cover';
                        photoContainer.appendChild(img);
                    }
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Portfolio project counter
        let projectCount = 0;

        // Portfolio Image Preview (single image only)
        function previewPortfolioImage(input) {
            if (input.files && input.files.length > 0) {
                const file = input.files[0]; // Only take the first file
                const portfolioGrid = document.getElementById('portfolioGrid');
                const uploadButton = document.getElementById('uploadPortfolioBtn');
                const existingItem = document.getElementById('portfolio-item-existing');
                const removeFlag = document.getElementById('removePortfolioFlag');

                const reader = new FileReader();

                reader.onload = function (e) {
                    // Hide existing portfolio image if present
                    if (existingItem) {
                        existingItem.classList.add('hidden');
                    }

                    // Remove any previously uploaded preview
                    const previousPreview = document.getElementById('portfolio-item-new');
                    if (previousPreview) {
                        previousPreview.remove();
                    }

                    // Create new preview item
                    const projectDiv = document.createElement('div');
                    projectDiv.className = 'relative group h-48 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden bg-gray-100 dark:bg-[#1a2333]';
                    projectDiv.id = 'portfolio-item-new';

                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.alt = 'Portfolio Project';
                    img.className = 'h-full w-full object-cover';

                    const overlay = document.createElement('div');
                    overlay.className = 'absolute inset-0 bg-gradient-to-t from-black/80 to-transparent flex flex-col justify-end p-4';
                    overlay.innerHTML = `
                        <h4 class="text-white font-bold">New Portfolio Image</h4>
                        <p class="text-gray-300 text-xs mt-1">${file.name}</p>
                    `;

                    const actionDiv = document.createElement('div');
                    actionDiv.className = 'absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity flex gap-2';
                    actionDiv.innerHTML = `
                        <button type="button" onclick="removeNewPortfolioImage()"
                            class="bg-white dark:bg-[#2a3447] p-1.5 rounded-full text-red-500 hover:text-red-700 shadow-sm">
                            <span class="material-symbols-outlined text-[16px]">delete</span>
                        </button>
                    `;

                    projectDiv.appendChild(img);
                    projectDiv.appendChild(overlay);
                    projectDiv.appendChild(actionDiv);

                    // Insert before upload button
                    portfolioGrid.insertBefore(projectDiv, uploadButton);

                    // Hide upload button (only 1 image allowed)
                    if (uploadButton) {
                        uploadButton.classList.add('hidden');
                    }

                    // Set flag to indicate image replacement
                    if (removeFlag) {
                        removeFlag.value = '1';
                    }
                };

                reader.readAsDataURL(file);
            }
        }

        // Remove newly uploaded portfolio image (reverts to previous state)
        function removeNewPortfolioImage() {
            const newItem = document.getElementById('portfolio-item-new');
            const existingItem = document.getElementById('portfolio-item-existing');
            const uploadBtn = document.getElementById('uploadPortfolioBtn');
            const fileInput = document.getElementById('portfolioImageInput');
            const removeFlag = document.getElementById('removePortfolioFlag');

            // Remove the new preview
            if (newItem) {
                newItem.remove();
            }

            // Clear the file input
            if (fileInput) {
                fileInput.value = '';
            }

            // Check if there was an existing image (by checking if existingItem has a valid src)
            const existingImg = existingItem ? existingItem.querySelector('img') : null;
            const hasExistingImage = existingImg && existingImg.src && existingImg.src !== '' && !existingImg.src.endsWith('/');

            if (hasExistingImage) {
                // Show existing image again
                existingItem.classList.remove('hidden');
                if (uploadBtn) uploadBtn.classList.add('hidden');
                if (removeFlag) removeFlag.value = '0';
            } else {
                // Show upload button
                if (uploadBtn) uploadBtn.classList.remove('hidden');
            }
        }

        // Remove Portfolio Item (for newly uploaded items)
        function removePortfolioItem(itemId) {
            const item = document.getElementById(itemId);
            if (item) {
                item.remove();
            }
        }

        // Remove existing Portfolio Image (shows upload button again)
        function removePortfolioImage() {
            const existingItem = document.getElementById('portfolio-item-existing');
            const uploadBtn = document.getElementById('uploadPortfolioBtn');
            const removeFlag = document.getElementById('removePortfolioFlag');

            if (existingItem) {
                existingItem.classList.add('hidden');
            }
            if (uploadBtn) {
                uploadBtn.classList.remove('hidden');
            }
            if (removeFlag) {
                removeFlag.value = '1'; // Flag to remove existing image on server
            }
        }

        // Work History counter
        let workHistoryCount = 0;

        // Work History Preview
        function previewWorkHistory(input) {
            if (input.files && input.files.length > 0) {
                const workHistoryGrid = document.getElementById('workHistoryGrid');

                // Change grid layout after first upload
                if (workHistoryCount === 0) {
                    workHistoryGrid.className = 'space-y-3';
                    workHistoryGrid.innerHTML = '';
                }

                for (let i = 0; i < input.files.length; i++) {
                    const file = input.files[i];
                    workHistoryCount++;

                    // Create work history item
                    const itemDiv = document.createElement('div');
                    itemDiv.id = 'work-history-item-' + workHistoryCount;
                    itemDiv.className = 'flex justify-between items-center rounded-lg border border-gray-200 dark:border-gray-700 p-4 bg-white dark:bg-[#1a2333]';

                    // Determine icon based on file type
                    let icon = 'description';
                    if (file.type.startsWith('image/')) {
                        icon = 'image';
                    } else if (file.name.endsWith('.pdf')) {
                        icon = 'picture_as_pdf';
                    }

                    itemDiv.innerHTML = `
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-lg bg-primary/10 flex items-center justify-center">
                                <span class="material-symbols-outlined text-primary">${icon}</span>
                            </div>
                            <div>
                                <h5 class="font-bold text-text-main dark:text-white text-sm">${file.name}</h5>
                                <p class="text-xs text-text-sub">${(file.size / 1024).toFixed(1)} KB</p>
                            </div>
                        </div>
                        <button type="button" onclick="removeWorkHistoryItem('work-history-item-${workHistoryCount}')"
                            class="text-red-500 hover:text-red-700 transition-colors">
                            <span class="material-symbols-outlined text-[20px]">delete</span>
                        </button>
                    `;

                    workHistoryGrid.appendChild(itemDiv);
                }

                input.value = '';
            }
        }

        // Remove Work History Item
        function removeWorkHistoryItem(itemId) {
            const item = document.getElementById(itemId);
            if (item) {
                item.remove();
                // Check if grid is empty
                const workHistoryGrid = document.getElementById('workHistoryGrid');
                if (workHistoryGrid.children.length === 0) {
                    workHistoryGrid.className = 'rounded-lg border border-dashed border-gray-300 dark:border-gray-700 p-6 text-center bg-gray-50 dark:bg-[#1a2333]/50';
                    workHistoryGrid.innerHTML = `
                        <p class="text-sm text-text-sub">No work history added yet.</p>
                        <button type="button" onclick="document.getElementById('workHistoryInput').click()"
                            class="mt-2 text-sm font-medium text-primary hover:underline">Add your past roles</button>
                    `;
                }
            }
        }

        // Education entry counter
        let educationEntryCount = 1;

        // Add new Education Entry (duplicate the box)
        function addEducationEntry() {
            educationEntryCount++;
            const educationGrid = document.getElementById('educationGrid');

            const newEntry = document.createElement('div');
            newEntry.className = 'education-entry rounded-lg border border-gray-200 dark:border-gray-700 p-4 bg-white dark:bg-[#1a2333]';
            newEntry.id = 'education-entry-' + educationEntryCount;

            newEntry.innerHTML = `
                <div class="space-y-3">
                    <!-- Degree Field -->
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <label class="text-xs text-text-sub mb-1 block">Degree / Certificate</label>
                            <input type="text" name="education_degree[]" value=""
                                placeholder="Enter degree or certificate"
                                class="education-degree w-full font-bold text-text-main dark:text-white bg-transparent border-0 border-b border-gray-300 focus:border-primary focus:ring-0 p-0 text-sm">
                        </div>
                        <button type="button" onclick="this.previousElementSibling.querySelector('input').focus();"
                            class="text-gray-400 hover:text-primary transition-colors ml-2">
                            <span class="material-symbols-outlined text-[18px]">edit</span>
                        </button>
                    </div>
                    <!-- University Field -->
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <label class="text-xs text-text-sub mb-1 block">Institution</label>
                            <input type="text" name="education_university[]" value=""
                                placeholder="Enter institution name"
                                class="education-university w-full font-bold text-sm text-text-main dark:text-white bg-transparent border-0 border-b border-gray-300 focus:border-primary focus:ring-0 p-0">
                        </div>
                        <button type="button" onclick="this.previousElementSibling.querySelector('input').focus();"
                            class="text-gray-400 hover:text-primary transition-colors ml-2">
                            <span class="material-symbols-outlined text-[18px]">edit</span>
                        </button>
                    </div>
                    <!-- Year Field -->
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <label class="text-xs text-text-sub mb-1 block">Year</label>
                            <input type="text" name="education_year[]" value=""
                                placeholder="e.g. 2015 - 2019"
                                class="education-year w-full font-bold text-sm text-text-main dark:text-white bg-transparent border-0 border-b border-gray-300 focus:border-primary focus:ring-0 p-0">
                        </div>
                        <button type="button" onclick="this.previousElementSibling.querySelector('input').focus();"
                            class="text-gray-400 hover:text-primary transition-colors ml-2">
                            <span class="material-symbols-outlined text-[18px]">edit</span>
                        </button>
                    </div>
                </div>
                <!-- Delete button -->
                <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700 flex justify-end">
                    <button type="button" onclick="removeEducationEntry('education-entry-${educationEntryCount}')"
                        class="text-xs text-red-500 hover:text-red-700 flex items-center gap-1 transition-colors">
                        <span class="material-symbols-outlined text-[14px]">delete</span>
                        Remove
                    </button>
                </div>
            `;

            educationGrid.appendChild(newEntry);

            // Focus on the first input of the new entry
            newEntry.querySelector('input').focus();
        }

        // Remove Education Entry
        function removeEducationEntry(entryId) {
            const entry = document.getElementById(entryId);
            if (entry) {
                entry.remove();
            }
        }

        // Dynamic navigation tracking based on form interaction
        document.addEventListener('DOMContentLoaded', function () {
            const navLinks = document.querySelectorAll('.nav-link');

            // Function to set active navigation based on section
            function setActiveNav(sectionId) {
                navLinks.forEach(link => {
                    const dot = link.querySelector('.nav-dot');
                    const text = link.querySelector('.nav-text');

                    if (link.getAttribute('data-section') === sectionId) {
                        // Active state - blue dot and text
                        link.classList.add('active');
                        dot.classList.remove('bg-gray-300', 'dark:bg-gray-600');
                        dot.classList.add('bg-primary');
                        text.classList.remove('font-medium', 'text-text-sub');
                        text.classList.add('font-bold', 'text-primary');
                    } else {
                        // Inactive state - gray dot
                        link.classList.remove('active');
                        dot.classList.remove('bg-primary');
                        dot.classList.add('bg-gray-300', 'dark:bg-gray-600');
                        text.classList.remove('font-bold', 'text-primary');
                        text.classList.add('font-medium', 'text-text-sub');
                    }
                });
            }

            // Track form input focus for all inputs, textareas, and selects
            const formInputs = document.querySelectorAll('input, textarea, select');
            formInputs.forEach(input => {
                input.addEventListener('focus', function () {
                    const section = this.closest('#basic-info, #skills-bio, #portfolio, #experience, #education');
                    if (section) {
                        setActiveNav(section.id);
                    }
                });
            });

            // Track file upload clicks
            document.getElementById('profileImageInput')?.addEventListener('change', () => setActiveNav('basic-info'));
            document.getElementById('portfolioImageInput')?.addEventListener('change', () => setActiveNav('portfolio'));
            document.getElementById('workHistoryInput')?.addEventListener('change', () => setActiveNav('experience'));

            // Track button clicks in sections
            document.querySelectorAll('#portfolio button, #experience button, #education button').forEach(btn => {
                btn.addEventListener('click', function () {
                    const section = this.closest('#basic-info, #skills-bio, #portfolio, #experience, #education');
                    if (section) {
                        setActiveNav(section.id);
                    }
                });
            });

            // Navigation click to scroll and highlight
            navLinks.forEach(link => {
                link.addEventListener('click', function (e) {
                    e.preventDefault();
                    const sectionId = this.getAttribute('data-section');
                    const section = document.getElementById(sectionId);
                    if (section) {
                        section.scrollIntoView({ behavior: 'smooth' });
                        setActiveNav(sectionId);
                    }
                });
            });
        });
        // Note: The form submits normally via HTML form POST to createdeveloperprofile.php
        // The publish button has type="submit" which triggers the PHP handler at the top of this file
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