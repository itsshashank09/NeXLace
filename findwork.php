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

// Fetch profile data from database
try {
    require_once 'includes/db_helper.php';
    $conn = getDB();

    if ($conn && !empty($email)) {
        $stmt = $conn->prepare("SELECT id, `Professional Headline`, `Bio`, `image` FROM register WHERE Email = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            $headline = $user['Professional Headline'] ?? '';
            $bio = $user['Bio'] ?? '';
            $profileImage = $user['image'] ?? '';
            // Ensure session has user_id
            if (!isset($_SESSION['user_id'])) {
                $_SESSION['user_id'] = $user['id'];
            }
        }
    }
} catch (Exception $e) {
    error_log("Error fetching profile data: " . $e->getMessage());
}

// Database connection and fetch jobs
require_once 'config/database.php';

$jobs = [];
$searchQuery = isset($_GET['q']) ? trim($_GET['q']) : '';

// Get filter values
$filterCategories = isset($_GET['category']) ? (array) $_GET['category'] : [];
$filterProjectTypes = isset($_GET['project_type']) ? (array) $_GET['project_type'] : [];
$filterExperienceLevel = isset($_GET['experience_level']) ? trim($_GET['experience_level']) : '';

require_once 'includes/db_helper.php';
$conn = getDB();

if ($conn) {
    try {
        // --- AUTO-FIX: Ensure columns exist and have data ---
        $columnsToCheck = [
            'user_id' => "INT NOT NULL DEFAULT 0",
            'job_title' => "VARCHAR(255) NOT NULL",
            'job_details' => "TEXT NOT NULL",
            'skills_required' => "TEXT",
            'estimated_budget' => "INT",
            'project_timeline' => "VARCHAR(100)",
            'category' => "VARCHAR(100) DEFAULT 'Web Development'",
            'project_type' => "VARCHAR(50) DEFAULT 'Fixed Price'",
            'experience_level' => "VARCHAR(50) DEFAULT 'Intermediate'",
            'created_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
        ];
        $defaults = [
            'category' => 'Web Development',
            'project_type' => 'Fixed Price',
            'experience_level' => 'Intermediate'
        ];

        // Ensure table exists
        $createTableSql = "CREATE TABLE IF NOT EXISTS post_jobs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL DEFAULT 0,
            job_title VARCHAR(255) NOT NULL,
            job_details TEXT NOT NULL,
            skills_required TEXT,
            estimated_budget INT,
            project_timeline VARCHAR(100),
            category VARCHAR(100) DEFAULT 'Web Development',
            project_type VARCHAR(50) DEFAULT 'Fixed Price',
            experience_level VARCHAR(50) DEFAULT 'Intermediate',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $conn->exec($createTableSql);

        foreach ($columnsToCheck as $colName => $colDef) {
            // Skip check for columns that don't have defaults or handle differently?
            // Actually, for NOT NULL columns without default, updating existing rows is tricky if empty.
            // But valid for fresh columns.

            $check = $conn->query("SHOW COLUMNS FROM post_jobs LIKE '$colName'");
            if ($check->rowCount() == 0) {
                // Column missing, add it
                $conn->exec("ALTER TABLE post_jobs ADD COLUMN $colName $colDef");
            }

            // Only update defaults for specific columns
            if (isset($defaults[$colName])) {
                $conn->query("UPDATE post_jobs SET $colName = '{$defaults[$colName]}' WHERE $colName IS NULL OR $colName = ''");
            }
        }
        // ----------------------------------------------------

        // Get current user ID for applied jobs lookup
        $currentUserId = $_SESSION['user_id'] ?? 0;
        $appliedJobIds = [];      // Jobs to EXCLUDE from listing (pending or accepted)
        $allAppliedJobs = [];     // All applied jobs for modal display
        $acceptedJobIds = [];     // Jobs that are accepted (permanently hidden)

        // Fetch applied job IDs for current user
        if ($currentUserId > 0) {
            try {
                // Get ALL applied jobs for modal display
                $appliedStmt = $conn->prepare("
                    SELECT ja.job_id, ja.proposed_rate, ja.cover_letter, ja.status, ja.created_at as applied_at,
                           pj.job_title, pj.job_details, pj.skills_required, pj.estimated_budget, 
                           pj.project_timeline, pj.category, pj.project_type, pj.experience_level
                    FROM job_applications ja
                    JOIN post_jobs pj ON ja.job_id = pj.id
                    WHERE ja.developer_id = ?
                    ORDER BY ja.created_at DESC
                ");
                $appliedStmt->execute([$currentUserId]);
                $appliedJobsData = $appliedStmt->fetchAll();

                foreach ($appliedJobsData as $appliedJob) {
                    $allAppliedJobs[] = $appliedJob;

                    // Only exclude from listing if status is 'pending' or 'accepted'
                    // Declined/rejected jobs should appear again so user can re-apply
                    if ($appliedJob['status'] === 'pending' || $appliedJob['status'] === 'accepted') {
                        $appliedJobIds[] = $appliedJob['job_id'];
                    }

                    // Track accepted jobs separately (these are permanently hidden)
                    if ($appliedJob['status'] === 'accepted') {
                        $acceptedJobIds[] = $appliedJob['job_id'];
                    }
                }
            } catch (PDOException $e) {
                error_log("Error fetching applied jobs: " . $e->getMessage());
            }
        }

        // For display in the Applied Jobs modal, use all applied jobs (not just non-accepted)
        $appliedJobs = $allAppliedJobs;
        $appliedJobsCount = count($allAppliedJobs);

        // Fetch liked job IDs for current user
        $likedJobIds = [];
        $likedJobs = [];

        if ($currentUserId > 0) {
            try {
                // Ensure liked_jobs table exists
                $conn->exec("CREATE TABLE IF NOT EXISTS liked_jobs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    job_id INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_like (user_id, job_id),
                    INDEX idx_user (user_id),
                    INDEX idx_job (job_id)
                )");

                $likedStmt = $conn->prepare("
                    SELECT lj.job_id, lj.created_at as liked_at,
                           pj.job_title, pj.job_details, pj.skills_required, pj.estimated_budget,
                           pj.project_timeline, pj.category, pj.project_type, pj.experience_level
                    FROM liked_jobs lj
                    JOIN post_jobs pj ON lj.job_id = pj.id
                    WHERE lj.user_id = ?
                    ORDER BY lj.created_at DESC
                ");
                $likedStmt->execute([$currentUserId]);
                $likedJobsData = $likedStmt->fetchAll();

                foreach ($likedJobsData as $likedJob) {
                    $likedJobIds[] = $likedJob['job_id'];
                    $likedJobs[] = $likedJob;
                }
            } catch (PDOException $e) {
                error_log("Error fetching liked jobs: " . $e->getMessage());
            }
        }

        $likedJobsCount = count($likedJobIds);

        $sql = "SELECT * FROM post_jobs WHERE 1=1";
        $params = [];

        // Exclude jobs that have ANY accepted application (job is filled/taken)
        $sql .= " AND id NOT IN (SELECT DISTINCT job_id FROM job_applications WHERE status = 'accepted')";

        // Exclude jobs user already applied for (pending status only - declined can re-apply)
        if (!empty($appliedJobIds)) {
            $excludePlaceholders = [];
            foreach ($appliedJobIds as $i => $jobId) {
                $key = ':exclude' . $i;
                $excludePlaceholders[] = $key;
                $params[$key] = $jobId;
            }
            $sql .= " AND id NOT IN (" . implode(',', $excludePlaceholders) . ")";
        }

        // Search filter
        if (!empty($searchQuery)) {
            $sql .= " AND (job_title LIKE :search1 OR job_details LIKE :search2 OR skills_required LIKE :search3)";
            $searchParam = '%' . $searchQuery . '%';
            $params[':search1'] = $searchParam;
            $params[':search2'] = $searchParam;
            $params[':search3'] = $searchParam;
        }

        // Category filter
        if (!empty($filterCategories)) {
            $categoryPlaceholders = [];
            foreach ($filterCategories as $i => $cat) {
                $key = ':cat' . $i;
                $categoryPlaceholders[] = $key;
                $params[$key] = $cat;
            }
            $sql .= " AND category IN (" . implode(',', $categoryPlaceholders) . ")";
        }

        // Project type filter
        if (!empty($filterProjectTypes)) {
            $typePlaceholders = [];
            foreach ($filterProjectTypes as $i => $type) {
                $key = ':type' . $i;
                $typePlaceholders[] = $key;
                $params[$key] = $type;
            }
            $sql .= " AND project_type IN (" . implode(',', $typePlaceholders) . ")";
        }

        // Experience level filter
        if (!empty($filterExperienceLevel)) {
            $sql .= " AND experience_level = :exp_level";
            $params[':exp_level'] = $filterExperienceLevel;
        }

        $sql .= " ORDER BY id DESC";

        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $jobs = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching jobs: " . $e->getMessage());
    }
}

// Check for success message
$showSuccess = isset($_GET['success']) && $_GET['success'] == '1';
?>
<!DOCTYPE html>
<html class="light" lang="en">

<head>
    <link rel="icon" type="image/png" href="assetes/logo.png" />
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>NeXLace - Find Work</title>
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
                        "card-light": "#ffffff",
                        "card-dark": "#151c2b",
                        "text-main": "#0d121b",
                        "text-sub": "#4c669a",
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

                // Check for toast on load
                document.addEventListener('DOMContentLoaded', () => {
                    if (localStorage.getItem('applicationSent') === 'true') {
                        showToast('success', 'Application sent successfully!');
                        localStorage.removeItem('applicationSent');
                    }
                });
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
                        <a class="text-sm font-bold text-primary dark:text-primary hover:text-primary dark:hover:text-primary transition-colors"
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
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                    <div>
                        <h1 class="text-3xl font-bold tracking-tight text-text-main dark:text-white">Find Work</h1>
                        <p class="text-text-sub mt-1">Explore the latest web development projects.</p>
                    </div>
                    <div class="flex gap-3">
                        <button onclick="openAppliedJobsModal()"
                            class="relative flex h-10 items-center justify-center gap-2 rounded-full border-2 border-primary bg-white dark:bg-card-dark px-5 text-sm font-bold text-primary transition-transform hover:bg-primary/5 active:scale-95">
                            <span class="material-symbols-outlined text-[20px]">work_history</span>
                            Applied Jobs
                            <?php if ($appliedJobsCount > 0): ?>
                                <span
                                    class="absolute -top-2 -right-2 min-w-[20px] h-5 flex items-center justify-center rounded-full bg-primary text-white text-xs font-bold px-1.5">
                                    <?= $appliedJobsCount ?>
                                </span>
                            <?php endif; ?>
                        </button>
                        <button onclick="openLikedJobsModal()"
                            class="relative flex h-10 items-center justify-center gap-2 rounded-full border-2 border-primary bg-white dark:bg-card-dark px-5 text-sm font-bold text-primary transition-transform hover:bg-primary/5 dark:hover:bg-primary/10 active:scale-95">
                            <span class="material-symbols-outlined text-[20px] text-red-500"
                                style="font-variation-settings: 'FILL' 1;">favorite</span>
                            Liked Jobs
                            <?php if ($likedJobsCount > 0): ?>
                                <span id="likedJobsBadge"
                                    class="absolute -top-2 -right-2 min-w-[20px] h-5 flex items-center justify-center rounded-full bg-primary text-white text-xs font-bold px-1.5">
                                    <?= $likedJobsCount ?>
                                </span>
                            <?php endif; ?>
                        </button>
                        <button onclick="window.location.href='postjob.php'"
                            class="flex h-10 items-center justify-center gap-2 rounded-full bg-primary px-6 text-sm font-bold text-white transition-transform hover:bg-primary-dark active:scale-95 shadow-md shadow-blue-500/20">
                            <span class="material-symbols-outlined text-[20px]">add</span>
                            Post a Service
                        </button>
                    </div>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                    <div class="lg:col-span-3 space-y-6">
                        <form id="filterForm" method="GET" action="findwork.php"
                            class="rounded-xl border border-[#e7ebf3] dark:border-[#2a3447] bg-card-light dark:bg-card-dark p-6 sticky top-24">
                            <!-- Preserve search query -->
                            <input type="hidden" name="q" value="<?= htmlspecialchars($searchQuery) ?>">

                            <div class="flex items-center justify-between mb-4">
                                <h3 class="font-bold text-text-main dark:text-white">Filter By</h3>
                                <a href="findwork.php" class="text-xs text-primary font-bold hover:underline">Clear
                                    all</a>
                            </div>
                            <div class="space-y-6">
                                <div>
                                    <h4 class="text-sm font-bold text-text-main dark:text-white mb-3">Category</h4>
                                    <div class="space-y-2">
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input name="category[]" value="Web Development"
                                                class="rounded border-gray-300 text-primary focus:ring-primary h-4 w-4"
                                                type="checkbox" <?= in_array('Web Development', $filterCategories) ? 'checked' : '' ?> onchange="this.form.submit()" />
                                            <span class="text-sm text-text-sub">Web Development</span>
                                        </label>
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input name="category[]" value="Mobile Apps"
                                                class="rounded border-gray-300 text-primary focus:ring-primary h-4 w-4"
                                                type="checkbox" <?= in_array('Mobile Apps', $filterCategories) ? 'checked' : '' ?> onchange="this.form.submit()" />
                                            <span class="text-sm text-text-sub">Mobile Apps</span>
                                        </label>
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input name="category[]" value="UI/UX Design"
                                                class="rounded border-gray-300 text-primary focus:ring-primary h-4 w-4"
                                                type="checkbox" <?= in_array('UI/UX Design', $filterCategories) ? 'checked' : '' ?> onchange="this.form.submit()" />
                                            <span class="text-sm text-text-sub">UI/UX Design</span>
                                        </label>
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input name="category[]" value="Scripts & Utilities"
                                                class="rounded border-gray-300 text-primary focus:ring-primary h-4 w-4"
                                                type="checkbox" <?= in_array('Scripts & Utilities', $filterCategories) ? 'checked' : '' ?> onchange="this.form.submit()" />
                                            <span class="text-sm text-text-sub">Scripts &amp; Utilities</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="h-px bg-gray-100 dark:bg-gray-800"></div>
                                <div>
                                    <h4 class="text-sm font-bold text-text-main dark:text-white mb-3">Project Type</h4>
                                    <div class="space-y-2">
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input name="project_type[]" value="Fixed Price"
                                                class="rounded border-gray-300 text-primary focus:ring-primary h-4 w-4"
                                                type="checkbox" <?= in_array('Fixed Price', $filterProjectTypes) ? 'checked' : '' ?> onchange="this.form.submit()" />
                                            <span class="text-sm text-text-sub">Fixed Price</span>
                                        </label>
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input name="project_type[]" value="Hourly"
                                                class="rounded border-gray-300 text-primary focus:ring-primary h-4 w-4"
                                                type="checkbox" <?= in_array('Hourly', $filterProjectTypes) ? 'checked' : '' ?> onchange="this.form.submit()" />
                                            <span class="text-sm text-text-sub">Hourly</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="h-px bg-gray-100 dark:bg-gray-800"></div>
                                <div>
                                    <h4 class="text-sm font-bold text-text-main dark:text-white mb-3">Experience Level
                                    </h4>
                                    <div class="space-y-2">
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input class="border-gray-300 text-primary focus:ring-primary h-4 w-4"
                                                name="experience_level" value="Entry Level" type="radio"
                                                <?= $filterExperienceLevel === 'Entry Level' ? 'checked' : '' ?>
                                                onchange="this.form.submit()" />
                                            <span class="text-sm text-text-sub">Entry Level</span>
                                        </label>
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input class="border-gray-300 text-primary focus:ring-primary h-4 w-4"
                                                name="experience_level" value="Intermediate" type="radio"
                                                <?= $filterExperienceLevel === 'Intermediate' ? 'checked' : '' ?>
                                                onchange="this.form.submit()" />
                                            <span class="text-sm text-text-sub">Intermediate</span>
                                        </label>
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input class="border-gray-300 text-primary focus:ring-primary h-4 w-4"
                                                name="experience_level" value="Expert" type="radio"
                                                <?= $filterExperienceLevel === 'Expert' ? 'checked' : '' ?>
                                                onchange="this.form.submit()" />
                                            <span class="text-sm text-text-sub">Expert</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="lg:col-span-9 space-y-6">
                        <form method="GET" action="findwork.php" class="relative">
                            <!-- Preserve filter values when searching -->
                            <?php foreach ($filterCategories as $cat): ?>
                                <input type="hidden" name="category[]" value="<?= htmlspecialchars($cat) ?>">
                            <?php endforeach; ?>
                            <?php foreach ($filterProjectTypes as $type): ?>
                                <input type="hidden" name="project_type[]" value="<?= htmlspecialchars($type) ?>">
                            <?php endforeach; ?>
                            <?php if (!empty($filterExperienceLevel)): ?>
                                <input type="hidden" name="experience_level"
                                    value="<?= htmlspecialchars($filterExperienceLevel) ?>">
                            <?php endif; ?>

                            <input name="q" value="<?= htmlspecialchars($searchQuery) ?>"
                                class="w-full h-12 rounded-xl border border-[#e7ebf3] dark:border-[#2a3447] bg-white dark:bg-card-dark pl-12 pr-24 text-base focus:border-primary focus:ring-primary shadow-sm"
                                placeholder="Search for jobs..." type="text" />
                            <span
                                class="absolute left-4 top-1/2 -translate-y-1/2 material-symbols-outlined text-gray-400">search</span>
                            <button type="submit"
                                class="absolute right-2 top-1/2 -translate-y-1/2 bg-primary hover:bg-primary-dark text-white rounded-lg px-4 py-1.5 text-sm font-bold transition-colors">Search</button>
                        </form>
                        <div class="flex items-center justify-between pb-2">
                            <h2 class="text-xl font-bold text-text-main dark:text-white">Jobs matching your skills</h2>

                        </div>
                        <div class="space-y-4">
                            <?php if ($showSuccess): ?>
                                <div
                                    class="rounded-xl border border-green-200 bg-green-50 dark:bg-green-900/20 dark:border-green-800 p-4 mb-4">
                                    <p class="text-green-700 dark:text-green-400 font-medium flex items-center gap-2">
                                        <span class="material-symbols-outlined">check_circle</span>
                                        Job posted successfully!
                                    </p>
                                </div>
                            <?php endif; ?>

                            <?php if (empty($jobs)): ?>
                                <div
                                    class="rounded-xl border border-[#e7ebf3] dark:border-[#2a3447] bg-card-light dark:bg-card-dark p-12 text-center">
                                    <span
                                        class="material-symbols-outlined text-6xl text-gray-300 dark:text-gray-600 mb-4">work_off</span>
                                    <h3 class="text-lg font-bold text-text-main dark:text-white mb-2">No jobs posted yet
                                    </h3>
                                    <p class="text-text-sub mb-4">Be the first to post a job!</p>
                                    <a href="postjob.php"
                                        class="inline-flex items-center gap-2 px-6 py-2.5 bg-primary hover:bg-primary-dark text-white rounded-full text-sm font-bold transition-colors">
                                        <span class="material-symbols-outlined text-[18px]">add</span>
                                        Post a Job
                                    </a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($jobs as $job): ?>
                                    <div
                                        class="group rounded-xl border border-[#e7ebf3] dark:border-[#2a3447] bg-card-light dark:bg-card-dark p-6 hover:border-primary/50 hover:shadow-md transition-all cursor-pointer relative">
                                        <?php $isLiked = in_array($job['id'], $likedJobIds); ?>
                                        <button onclick="toggleLike(<?= $job['id'] ?>, this); event.stopPropagation();"
                                            class="like-btn absolute top-6 right-6 transition-all duration-200 hover:scale-110 <?= $isLiked ? 'text-red-500' : 'text-gray-400 hover:text-red-400' ?>">
                                            <span class="material-symbols-outlined"
                                                style="font-variation-settings: 'FILL' <?= $isLiked ? '1' : '0' ?>;">favorite</span>
                                        </button>
                                        <div class="flex items-start justify-between mb-2 pr-10">
                                            <h3
                                                class="text-lg font-bold text-text-main dark:text-white group-hover:text-primary transition-colors">
                                                <?= htmlspecialchars($job['job_title']) ?>
                                            </h3>
                                        </div>
                                        <div class="flex flex-wrap gap-y-2 gap-x-4 text-xs font-medium text-text-sub mb-4">
                                            <span class="flex items-center"><span
                                                    class="material-symbols-outlined text-[16px] mr-1">schedule</span> Posted
                                                recently</span>
                                            <span class="flex items-center"><span
                                                    class="material-symbols-outlined text-[16px] mr-1">payments</span>
                                                ₹<?= number_format($job['estimated_budget']) ?>
                                                Fixed Price</span>
                                            <span class="flex items-center"><span
                                                    class="material-symbols-outlined text-[16px] mr-1">timer</span>
                                                <?= htmlspecialchars($job['project_timeline']) ?></span>

                                            <?php if (!empty($job['category'])): ?>
                                                <span class="flex items-center text-primary bg-primary/10 px-2 py-0.5 rounded">
                                                    <span class="material-symbols-outlined text-[16px] mr-1">folder</span>
                                                    <?= htmlspecialchars($job['category']) ?>
                                                </span>
                                            <?php endif; ?>

                                            <?php if (!empty($job['project_type'])): ?>
                                                <span
                                                    class="flex items-center text-orange-600 bg-orange-100 dark:bg-orange-900/30 px-2 py-0.5 rounded">
                                                    <span class="material-symbols-outlined text-[16px] mr-1">work</span>
                                                    <?= htmlspecialchars($job['project_type']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-text-sub text-sm line-clamp-2 mb-4">
                                            <?= htmlspecialchars($job['job_details']) ?>
                                        </p>
                                        <div class="flex flex-wrap gap-2 mb-5">
                                            <?php
                                            $skills = array_filter(array_map('trim', explode(',', $job['skills_required'])));
                                            foreach ($skills as $skill):
                                                ?>
                                                <span
                                                    class="px-3 py-1 bg-gray-100 dark:bg-[#1f2937] text-text-sub text-xs rounded-full"><?= htmlspecialchars($skill) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                        <div
                                            class="flex items-center gap-2 pt-4 border-t border-[#e7ebf3] dark:border-[#2a3447]">
                                            <div class="flex items-center gap-1 text-xs font-bold text-green-600">
                                                <span class="material-symbols-outlined text-[16px] fill-current">verified</span>
                                                New Posting
                                            </div>
                                            <button
                                                onclick="openApplyModal(<?= $job['id'] ?>, '<?= htmlspecialchars(addslashes($job['job_title']), ENT_QUOTES) ?>')"
                                                data-job-id="<?= $job['id'] ?>"
                                                class="ml-auto px-4 py-2 bg-primary hover:bg-primary-dark text-white rounded-lg text-sm font-bold transition-colors flex items-center gap-2">
                                                <span class="material-symbols-outlined text-[16px]">send</span>
                                                Apply Now
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <div class="flex justify-center pt-6">
                                <button
                                    class="px-6 py-2 rounded-full border border-gray-300 dark:border-gray-600 text-sm font-bold text-text-main dark:text-white hover:bg-gray-50 dark:hover:bg-[#1f2937]">Load
                                    more jobs</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <!-- Apply Now Modal -->
    <div id="applyModal" class="fixed inset-0 z-[100] hidden">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity" onclick="closeApplyModal()"></div>

        <!-- Modal Content -->
        <div class="fixed inset-0 flex items-center justify-center p-4">
            <div
                class="relative w-full max-w-lg bg-white dark:bg-card-dark rounded-2xl shadow-2xl transform transition-all animate-modal-in overflow-hidden">
                <!-- Header -->
                <div class="relative bg-gradient-to-r from-primary to-blue-600 px-6 py-5">
                    <button onclick="closeApplyModal()"
                        class="absolute right-4 top-4 text-white/80 hover:text-white transition-colors">
                        <span class="material-symbols-outlined text-[24px]">close</span>
                    </button>
                    <h3 class="text-xl font-bold text-white">Apply for this Job</h3>
                    <p id="modalJobTitle" class="text-white/80 text-sm mt-1"></p>
                </div>

                <!-- Developer Profile Section -->
                <div class="px-6 py-5 border-b border-[#e7ebf3] dark:border-[#2a3447]">
                    <div class="flex items-start gap-4">
                        <div id="developerAvatar"
                            class="h-16 w-16 rounded-full bg-primary flex items-center justify-center text-white font-bold text-xl overflow-hidden flex-shrink-0">
                            <span id="avatarInitial"><?= strtoupper($name[0]); ?></span>
                            <img id="avatarImage" src="" alt="" class="hidden h-full w-full object-cover">
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 id="developerName" class="text-lg font-bold text-text-main dark:text-white">
                                <?= htmlspecialchars($name); ?>
                            </h4>
                            <p id="developerTitle" class="text-primary font-medium text-sm"></p>
                            <div class="flex flex-wrap gap-3 mt-2">
                                <span id="developerRate" class="flex items-center gap-1 text-xs text-text-sub">
                                    <span class="material-symbols-outlined text-[16px]">payments</span>
                                    <span class="rate-value"></span>
                                </span>
                                <span id="developerExp" class="flex items-center gap-1 text-xs text-text-sub">
                                    <span class="material-symbols-outlined text-[16px]">work_history</span>
                                    <span class="exp-value"></span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Skills -->
                    <div id="developerSkillsContainer" class="mt-4">
                        <p class="text-xs font-bold text-text-sub mb-2">YOUR SKILLS</p>
                        <div id="developerSkills" class="flex flex-wrap gap-2"></div>
                    </div>
                </div>

                <!-- Application Form -->
                <div class="px-6 py-5 space-y-4">
                    <!-- No Profile Warning -->
                    <div id="noProfileWarning"
                        class="hidden rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 p-4">
                        <div class="flex items-start gap-3">
                            <span class="material-symbols-outlined text-amber-600 dark:text-amber-400">warning</span>
                            <div>
                                <p class="text-sm font-medium text-amber-800 dark:text-amber-200">Complete Your Profile
                                </p>
                                <p class="text-xs text-amber-600 dark:text-amber-400 mt-1">Create a developer profile to
                                    increase your chances of getting hired.</p>
                                <a href="createdeveloperprofile.php"
                                    class="inline-flex items-center gap-1 text-xs font-bold text-primary mt-2 hover:underline">
                                    <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
                                    Create Profile
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Proposed Rate -->
                    <div>
                        <label class="block text-sm font-bold text-text-main dark:text-white mb-2">Proposed Rate
                            (₹)</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-text-sub">₹</span>
                            <input type="number" id="proposedRate" placeholder="Enter your rate"
                                class="w-full h-11 rounded-lg border border-[#e7ebf3] dark:border-[#2a3447] bg-white dark:bg-[#1a2333] pl-10 pr-4 text-sm focus:border-primary focus:ring-primary">
                        </div>
                    </div>

                    <!-- Cover Letter -->
                    <div>
                        <label class="block text-sm font-bold text-text-main dark:text-white mb-2">Cover Letter</label>
                        <textarea id="coverLetter" rows="4"
                            placeholder="Introduce yourself and explain why you're a great fit for this job..."
                            class="w-full rounded-lg border border-[#e7ebf3] dark:border-[#2a3447] bg-white dark:bg-[#1a2333] px-4 py-3 text-sm focus:border-primary focus:ring-primary resize-none"></textarea>
                        <p class="text-xs text-text-sub mt-1">Tip: Mention relevant experience and how you can help with
                            this project.</p>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 bg-gray-50 dark:bg-[#1a2333] flex items-center justify-end gap-3">
                    <button onclick="closeApplyModal()"
                        class="px-5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-bold text-text-main dark:text-white hover:bg-gray-100 dark:hover:bg-[#232d3f] transition-colors">
                        Cancel
                    </button>
                    <button id="sendApplicationBtn" onclick="submitApplication()"
                        class="px-6 py-2.5 rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-bold transition-colors flex items-center gap-2 shadow-md shadow-blue-500/20">
                        <span class="material-symbols-outlined text-[18px]">send</span>
                        Send Application
                    </button>
                </div>

                <!-- Loading Overlay -->
                <div id="modalLoading"
                    class="hidden absolute inset-0 bg-white/80 dark:bg-card-dark/80 flex items-center justify-center">
                    <div class="flex flex-col items-center gap-3">
                        <div class="w-10 h-10 border-4 border-primary border-t-transparent rounded-full animate-spin">
                        </div>
                        <p class="text-sm font-medium text-text-sub">Submitting...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Applied Jobs Modal -->
    <div id="appliedJobsModal" class="fixed inset-0 z-[100] hidden">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity" onclick="closeAppliedJobsModal()">
        </div>

        <!-- Modal Content -->
        <div class="fixed inset-0 flex items-center justify-center p-4">
            <div
                class="relative w-full max-w-2xl max-h-[85vh] bg-white dark:bg-card-dark rounded-2xl shadow-2xl transform transition-all animate-modal-in overflow-hidden flex flex-col">
                <!-- Header -->
                <div class="relative bg-gradient-to-r from-primary to-blue-600 px-6 py-5 flex-shrink-0">
                    <button onclick="closeAppliedJobsModal()"
                        class="absolute right-4 top-4 text-white/80 hover:text-white transition-colors">
                        <span class="material-symbols-outlined text-[24px]">close</span>
                    </button>
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-white text-[28px]">work_history</span>
                        <div>
                            <h3 class="text-xl font-bold text-white">Applied Jobs</h3>
                            <p class="text-white/80 text-sm"><?= $appliedJobsCount ?>
                                job<?= $appliedJobsCount !== 1 ? 's' : '' ?> applied</p>
                        </div>
                    </div>
                </div>

                <!-- Jobs List -->
                <div class="flex-1 overflow-y-auto p-4 space-y-4 custom-scrollbar">
                    <?php if (empty($appliedJobs)): ?>
                        <div class="text-center py-12">
                            <span
                                class="material-symbols-outlined text-6xl text-gray-300 dark:text-gray-600 mb-4">work_off</span>
                            <h3 class="text-lg font-bold text-text-main dark:text-white mb-2">No applications yet</h3>
                            <p class="text-text-sub mb-4">Start applying to jobs to see them here!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($appliedJobs as $appliedJob): ?>
                            <div
                                class="rounded-xl border border-[#e7ebf3] dark:border-[#2a3447] bg-white dark:bg-[#1a2333] p-5 hover:border-primary/50 transition-all">
                                <div class="flex items-start justify-between mb-3">
                                    <h4 class="text-base font-bold text-text-main dark:text-white pr-4">
                                        <?= htmlspecialchars($appliedJob['job_title']) ?>
                                    </h4>
                                    <?php
                                    $statusClass = 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400';
                                    $statusIcon = 'hourglass_top';
                                    $statusText = 'Pending';
                                    if ($appliedJob['status'] === 'accepted') {
                                        $statusClass = 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
                                        $statusIcon = 'check_circle';
                                        $statusText = 'Accepted';
                                    } elseif ($appliedJob['status'] === 'rejected') {
                                        $statusClass = 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
                                        $statusIcon = 'cancel';
                                        $statusText = 'Declined';
                                    }
                                    ?>
                                    <span
                                        class="flex-shrink-0 inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold <?= $statusClass ?>">
                                        <span class="material-symbols-outlined text-[14px]"><?= $statusIcon ?></span>
                                        <?= $statusText ?>
                                    </span>
                                </div>
                                <div class="flex flex-wrap gap-3 text-xs text-text-sub mb-3">
                                    <span class="flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[14px]">payments</span>
                                        ₹<?= number_format($appliedJob['estimated_budget']) ?>
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[14px]">schedule</span>
                                        <?= htmlspecialchars($appliedJob['project_timeline']) ?>
                                    </span>
                                    <?php if (!empty($appliedJob['category'])): ?>
                                        <span class="flex items-center gap-1 text-primary bg-primary/10 px-2 py-0.5 rounded">
                                            <?= htmlspecialchars($appliedJob['category']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div
                                    class="flex items-center justify-between pt-3 border-t border-[#e7ebf3] dark:border-[#2a3447]">
                                    <div class="flex items-center gap-4 text-xs text-text-sub">
                                        <span class="flex items-center gap-1">
                                            <span
                                                class="material-symbols-outlined text-[14px] text-green-600">currency_rupee</span>
                                            Your Rate: ₹<?= number_format($appliedJob['proposed_rate']) ?>
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[14px]">calendar_today</span>
                                            Applied <?= date('M d, Y', strtotime($appliedJob['applied_at'])) ?>
                                        </span>
                                    </div>
                                    <?php if ($appliedJob['status'] === 'rejected'): ?>
                                        <button
                                            onclick="closeAppliedJobsModal(); openApplyModal(<?= $appliedJob['job_id'] ?>, '<?= htmlspecialchars(addslashes($appliedJob['job_title']), ENT_QUOTES) ?>')"
                                            class="flex items-center gap-1 px-3 py-1.5 bg-primary hover:bg-primary-dark text-white rounded-lg text-xs font-bold transition-colors">
                                            <span class="material-symbols-outlined text-[14px]">refresh</span>
                                            Re-apply
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Footer -->
                <div
                    class="px-6 py-4 bg-gray-50 dark:bg-[#1a2333] border-t border-[#e7ebf3] dark:border-[#2a3447] flex-shrink-0">
                    <button onclick="closeAppliedJobsModal()"
                        class="w-full px-5 py-2.5 rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-bold transition-colors">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Liked Jobs Modal -->
    <div id="likedJobsModal" class="fixed inset-0 z-[100] hidden">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity" onclick="closeLikedJobsModal()">
        </div>

        <!-- Modal Content -->
        <div class="fixed inset-0 flex items-center justify-center p-4">
            <div
                class="relative w-full max-w-2xl max-h-[85vh] bg-white dark:bg-card-dark rounded-2xl shadow-2xl transform transition-all animate-modal-in overflow-hidden flex flex-col">
                <!-- Header -->
                <div class="relative bg-gradient-to-r from-primary to-blue-600 px-6 py-5 flex-shrink-0">
                    <button onclick="closeLikedJobsModal()"
                        class="absolute right-4 top-4 text-white/80 hover:text-white transition-colors">
                        <span class="material-symbols-outlined text-[24px]">close</span>
                    </button>
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-white text-[28px]"
                            style="font-variation-settings: 'FILL' 1;">favorite</span>
                        <div>
                            <h3 class="text-xl font-bold text-white">Liked Jobs</h3>
                            <p id="likedJobsModalCount" class="text-white/80 text-sm">Loading...</p>
                        </div>
                    </div>
                </div>

                <!-- Jobs List -->
                <div id="likedJobsList" class="flex-1 overflow-y-auto p-4 space-y-4 custom-scrollbar">
                    <div class="flex flex-col items-center justify-center h-full py-12">
                        <div
                            class="w-10 h-10 border-4 border-primary border-t-transparent rounded-full animate-spin mb-4">
                        </div>
                        <p class="text-text-sub">Loading your liked jobs...</p>
                    </div>
                </div>

                <!-- Footer -->
                <div
                    class="px-6 py-4 bg-gray-50 dark:bg-[#1a2333] border-t border-[#e7ebf3] dark:border-[#2a3447] flex-shrink-0">
                    <button onclick="closeLikedJobsModal()"
                        class="w-full px-5 py-2.5 rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-bold transition-colors">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="successToast"
        class="fixed bottom-6 right-6 z-[150] hidden transform translate-y-4 opacity-0 transition-all duration-300">
        <div class="flex items-center gap-3 bg-green-600 text-white px-5 py-4 rounded-xl shadow-lg">
            <span class="material-symbols-outlined">check_circle</span>
            <span id="toastMessage" class="font-medium">Application sent successfully!</span>
        </div>
    </div>

    <!-- Error Toast -->
    <div id="errorToast"
        class="fixed bottom-6 right-6 z-[150] hidden transform translate-y-4 opacity-0 transition-all duration-300">
        <div class="flex items-center gap-3 bg-red-600 text-white px-5 py-4 rounded-xl shadow-lg">
            <span class="material-symbols-outlined">error</span>
            <span id="errorMessage" class="font-medium">Something went wrong</span>
        </div>
    </div>

    <style>
        @keyframes modalIn {
            from {
                opacity: 0;
                transform: scale(0.95) translateY(10px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .animate-modal-in {
            animation: modalIn 0.2s ease-out forwards;
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background-color: rgba(156, 163, 175, 0.5);
            border-radius: 3px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background-color: rgba(156, 163, 175, 0.7);
        }
    </style>

    <script>
        let currentJobId = null;
        let developerData = null;

        // Open Applied Jobs Modal
        function openAppliedJobsModal() {
            document.getElementById('appliedJobsModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        // Close Applied Jobs Modal
        function closeAppliedJobsModal() {
            document.getElementById('appliedJobsModal').classList.add('hidden');
            document.body.style.overflow = '';
        }

        // Open Apply Modal
        function openApplyModal(jobId, jobTitle) {
            currentJobId = jobId;
            document.getElementById('modalJobTitle').textContent = jobTitle;
            document.getElementById('applyModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';

            // Fetch developer profile
            fetchDeveloperProfile();
        }

        // Close Apply Modal
        function closeApplyModal() {
            document.getElementById('applyModal').classList.add('hidden');
            document.body.style.overflow = '';
            currentJobId = null;

            // Reset form
            document.getElementById('proposedRate').value = '';
            document.getElementById('coverLetter').value = '';
        }

        // Fetch developer profile data
        async function fetchDeveloperProfile() {
            try {
                const response = await fetch('api/apply_job.php');
                const data = await response.json();

                if (data.success) {
                    developerData = data.developer;
                    updateModalWithProfile(data);
                }
            } catch (error) {
                console.error('Error fetching profile:', error);
            }
        }

        // Update modal with developer profile
        function updateModalWithProfile(data) {
            const dev = data.developer;

            // Update avatar
            if (dev.profile_image) {
                document.getElementById('avatarImage').src = dev.profile_image;
                document.getElementById('avatarImage').classList.remove('hidden');
                document.getElementById('avatarInitial').classList.add('hidden');
            } else {
                document.getElementById('avatarImage').classList.add('hidden');
                document.getElementById('avatarInitial').classList.remove('hidden');
                document.getElementById('avatarInitial').textContent = dev.name ? dev.name[0].toUpperCase() : '?';
            }

            // Update name and title
            document.getElementById('developerName').textContent = dev.name || 'Developer';
            document.getElementById('developerTitle').textContent = dev.title || 'Freelancer';

            // Update rate and experience
            if (dev.rate) {
                document.querySelector('#developerRate .rate-value').textContent = '₹' + dev.rate + '/hr';
                document.getElementById('developerRate').classList.remove('hidden');
                document.getElementById('proposedRate').value = dev.rate;
            } else {
                document.getElementById('developerRate').classList.add('hidden');
            }

            if (dev.experience) {
                document.querySelector('#developerExp .exp-value').textContent = dev.experience + ' years exp';
                document.getElementById('developerExp').classList.remove('hidden');
            } else {
                document.getElementById('developerExp').classList.add('hidden');
            }

            // Update skills
            const skillsContainer = document.getElementById('developerSkills');
            skillsContainer.innerHTML = '';

            if (dev.skills) {
                const skills = dev.skills.split(',').map(s => s.trim()).filter(s => s);
                skills.slice(0, 5).forEach(skill => {
                    const badge = document.createElement('span');
                    badge.className = 'px-3 py-1 bg-primary/10 text-primary text-xs font-medium rounded-full';
                    badge.textContent = skill;
                    skillsContainer.appendChild(badge);
                });
                document.getElementById('developerSkillsContainer').classList.remove('hidden');
            } else {
                document.getElementById('developerSkillsContainer').classList.add('hidden');
            }

            // Show warning if no profile
            if (!data.has_profile) {
                document.getElementById('noProfileWarning').classList.remove('hidden');
            } else {
                document.getElementById('noProfileWarning').classList.add('hidden');
            }
        }

        // Submit application
        async function submitApplication() {
            if (!currentJobId) return;

            const coverLetter = document.getElementById('coverLetter').value.trim();
            const proposedRate = parseFloat(document.getElementById('proposedRate').value) || 0;

            // Show loading
            document.getElementById('modalLoading').classList.remove('hidden');
            document.getElementById('sendApplicationBtn').disabled = true;

            try {
                const response = await csrfFetch('api/apply_job.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        job_id: currentJobId,
                        cover_letter: coverLetter,
                        proposed_rate: proposedRate
                    })
                });

                const data = await response.json();

                // Hide loading
                document.getElementById('modalLoading').classList.add('hidden');
                document.getElementById('sendApplicationBtn').disabled = false;

                if (data.success) {
                    localStorage.setItem('applicationSent', 'true');
                    window.location.reload();
                } else {
                    showToast('error', data.message || 'Failed to submit application');
                }
            } catch (error) {
                document.getElementById('modalLoading').classList.add('hidden');
                document.getElementById('sendApplicationBtn').disabled = false;
                showToast('error', 'An error occurred. Please try again.');
                console.error('Error:', error);
            }
        }

        // Show toast notification
        function showToast(type, message) {
            const toast = document.getElementById(type === 'success' ? 'successToast' : 'errorToast');
            const messageEl = document.getElementById(type === 'success' ? 'toastMessage' : 'errorMessage');

            messageEl.textContent = message;
            toast.classList.remove('hidden');

            setTimeout(() => {
                toast.classList.remove('translate-y-4', 'opacity-0');
            }, 10);

            setTimeout(() => {
                toast.classList.add('translate-y-4', 'opacity-0');
                setTimeout(() => {
                    toast.classList.add('hidden');
                }, 300);
            }, 4000);
        }

        // Close modal on Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeApplyModal();
                closeAppliedJobsModal();
                closeLikedJobsModal();
            }
        });

        // Toggle Like functionality
        async function toggleLike(jobId, btnElement) {
            // Prevent multiple clicks
            if (btnElement.disabled) return;
            btnElement.disabled = true;

            try {
                const response = await csrfFetch('api/like_job.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        job_id: jobId
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // Update button appearance
                    const icon = btnElement.querySelector('.material-symbols-outlined');
                    if (data.liked) {
                        btnElement.classList.remove('text-gray-400', 'hover:text-red-400');
                        btnElement.classList.add('text-red-500');
                        icon.style.fontVariationSettings = "'FILL' 1";
                    } else {
                        btnElement.classList.remove('text-red-500');
                        btnElement.classList.add('text-gray-400', 'hover:text-red-400');
                        icon.style.fontVariationSettings = "'FILL' 0";
                    }

                    // Show toast
                    showToast('success', data.message);

                    // Update global liked count badge if it exists or reload/fetch update
                    updateLikedJobsBadge(data.liked);
                } else {
                    showToast('error', data.message || 'Failed to update like status');
                }
            } catch (error) {
                console.error('Error toggling like:', error);
                showToast('error', 'An error occurred');
            } finally {
                btnElement.disabled = false;
            }
        }

        // Update Liked Jobs Badge
        function updateLikedJobsBadge(isLiked) {
            const badge = document.getElementById('likedJobsBadge');
            if (badge) {
                let count = parseInt(badge.textContent) || 0;
                count = isLiked ? count + 1 : Math.max(0, count - 1);

                if (count > 0) {
                    badge.textContent = count;
                    badge.classList.remove('hidden');
                } else {
                    badge.remove(); // Remove badge if 0
                    // But if we want to add it back later, we might need to recreate it. 
                    // Simpler: reload page or use a container. For now, fine.
                }
            } else if (isLiked) {
                // Determine where to add badge if it doesn't exist
                // This is a bit complex without a dedicated container, reload is simpler for inconsistent state
                // But let's try to reload page silently or just leave it for now.
                // Or better: Just reload the page to be consistent is safest easiest way to sync state? No, bad UX.
                // We'll leave it as is. If button exists, update it.
                // If it doesn't exist (count was 0), we can't easily add it back without selecting the parent button properly.
                location.reload(); // Simplest way to ensure everything stays in sync for first implementation of this specific edge case
            }
        }

        // Open Liked Jobs Modal
        async function openLikedJobsModal() {
            document.getElementById('likedJobsModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';

            // Fetch liked jobs
            const listContainer = document.getElementById('likedJobsList');
            const countElement = document.getElementById('likedJobsModalCount');

            try {
                const response = await fetch('api/like_job.php', { method: 'GET' });
                const data = await response.json();

                if (data.success) {
                    countElement.textContent = `${data.count} job${data.count !== 1 ? 's' : ''} liked`;

                    if (data.count === 0) {
                        listContainer.innerHTML = `
                            <div class="text-center py-12">
                                <span class="material-symbols-outlined text-6xl text-gray-300 dark:text-gray-600 mb-4">favorite_border</span>
                                <h3 class="text-lg font-bold text-text-main dark:text-white mb-2">No liked jobs yet</h3>
                                <p class="text-text-sub mb-4">Like jobs to save them for later!</p>
                            </div>
                        `;
                    } else {
                        listContainer.innerHTML = data.jobs.map(job => `
                            <div class="rounded-xl border border-[#e7ebf3] dark:border-[#2a3447] bg-white dark:bg-[#1a2333] p-5 hover:border-primary/30 transition-all relative group">
                                <button onclick="toggleLike(${job.job_id}, this); this.closest('div').remove();" 
                                    class="absolute top-5 right-5 text-red-500 hover:text-red-700 transition-colors" title="Remove from liked">
                                    <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">favorite</span>
                                </button>
                                <div class="flex items-start justify-between mb-3 pr-10">
                                    <h4 class="text-base font-bold text-text-main dark:text-white">
                                        ${escapeHtml(job.job_title)}
                                    </h4>
                                </div>
                                <div class="flex flex-wrap gap-3 text-xs text-text-sub mb-3">
                                    <span class="flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[14px]">payments</span>
                                        ₹${parseInt(job.estimated_budget).toLocaleString('en-IN')}
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[14px]">schedule</span>
                                        ${escapeHtml(job.project_timeline)}
                                    </span>
                                    ${job.category ? `
                                    <span class="flex items-center gap-1 text-primary bg-primary/10 px-2 py-0.5 rounded">
                                        ${escapeHtml(job.category)}
                                    </span>` : ''}
                                </div>
                                <div class="flex items-center justify-between pt-3 border-t border-[#e7ebf3] dark:border-[#2a3447]">
                                    <div class="flex items-center gap-4 text-xs text-text-sub">
                                        <span class="flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[14px]">calendar_today</span>
                                            Liked on ${new Date(job.liked_at).toLocaleDateString()}
                                        </span>
                                    </div>
                                    <button onclick="openApplyModal(${job.job_id}, '${escapeHtml(job.job_title).replace(/'/g, "\\'")}')"
                                        class="px-3 py-1.5 bg-primary hover:bg-primary-dark text-white rounded-lg text-xs font-bold transition-colors">
                                        Apply Now
                                    </button>
                                </div>
                            </div>
                        `).join('');
                    }
                }
            } catch (error) {
                console.error('Error fetching liked jobs:', error);
                listContainer.innerHTML = '<p class="text-red-500 text-center py-8">Failed to load liked jobs.</p>';
            }
        }

        // Close Liked Jobs Modal
        function closeLikedJobsModal() {
            document.getElementById('likedJobsModal').classList.add('hidden');
            document.body.style.overflow = '';
            // Reload to update main page state if changes occurred
            // location.reload(); 
            // Better behavior: update badge on close if needed, but for now reload is safest to sync perfectly
            if (document.getElementById('likedJobsList').innerHTML.indexOf('remove()') > -1) {
                location.reload();
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    </script>
    <script src="js/search_engine.js"></script>
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
    <?php include 'includes/chatbot_widget.php'; ?>
    <script src="js/chatbot.js"></script>
    <!-- Use SSE instead of polling for better performance -->
    <script src="js/notifications_sse.js"></script>
</body>

</html>