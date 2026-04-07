<?php
/**
 * User Details Page
 * Detailed view of individual user information
 */

require_once 'auth.php';
requireAdminAuth();

require_once __DIR__ . '/../includes/db_helper.php';

$admin = getCurrentAdmin();
$db = getDB();

$userId = $_GET['id'] ?? null;

if (!$userId || !is_numeric($userId)) {
    header('Location: dashboard.php');
    exit();
}

// Get user details
$user = null;
$developerProfile = null;
$sessions = [];
$jobPostings = [];

if ($db) {
    try {
        // Get user basic info
        $stmt = $db->prepare("SELECT * FROM register WHERE Id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            header('Location: dashboard.php');
            exit();
        }

        // Get developer profile
        $stmt = $db->prepare("SELECT * FROM developers WHERE user_id = ?");
        $stmt->execute([$userId]);
        $developerProfile = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get user sessions
        $stmt = $db->prepare("SELECT * FROM user_sessions WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
        $stmt->execute([$userId]);
        $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get job postings (if any - for future feature)
        // $stmt = $db->prepare("SELECT * FROM post_jobs WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
        // $stmt->execute([$userId]);
        // $jobPostings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("User Details Error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="../assetes/logo.png" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details -
        <?= htmlspecialchars($user['Name'] ?? 'User') ?> - NeXLace Admin
    </title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        .filled {
            font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#1d4ed8',
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-gray-50 dark:bg-gray-900 min-h-screen">

    <!-- Top Navigation -->
    <nav class="bg-white dark:bg-gray-800 shadow-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Back Button & Title -->
                <div class="flex items-center gap-3">
                    <a href="dashboard.php" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
                        <span class="material-symbols-outlined text-gray-700 dark:text-gray-200">arrow_back</span>
                    </a>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900 dark:text-white">User Details</h1>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Detailed user information</p>
                    </div>
                </div>

                <!-- Right Side Actions -->
                <div class="flex items-center gap-3">
                    <button id="darkModeToggle"
                        class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                        <span class="material-symbols-outlined text-gray-700 dark:text-gray-200">dark_mode</span>
                    </button>
                    <div class="flex items-center gap-2 px-3 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg">
                        <span class="material-symbols-outlined text-gray-600 dark:text-gray-300">account_circle</span>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
                            <?= htmlspecialchars($admin['name']) ?>
                        </span>
                    </div>
                    <a href="logout.php"
                        class="flex items-center gap-2 px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg transition-colors">
                        <span class="material-symbols-outlined text-sm">logout</span>
                        <span class="text-sm font-medium">Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- User Profile Card -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-8 mb-6">
            <div class="flex items-start gap-6">
                <!-- Profile Image -->
                <div class="flex-shrink-0">
                    <?php if (!empty($user['image'])): ?>
                        <img src="../<?= htmlspecialchars($user['image']) ?>" alt="<?= htmlspecialchars($user['Name']) ?>"
                            class="w-24 h-24 rounded-full object-cover border-4 border-primary">
                    <?php else: ?>
                        <div
                            class="w-24 h-24 rounded-full bg-primary flex items-center justify-center text-white text-3xl font-bold border-4 border-primary">
                            <?= strtoupper(substr($user['Name'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- User Info -->
                <div class="flex-1">
                    <div class="flex items-start justify-between">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                                <?= htmlspecialchars($user['Name']) ?>
                            </h2>
                            <p class="text-lg text-gray-600 dark:text-gray-300 mt-1">
                                <?= htmlspecialchars($user['Email']) ?>
                            </p>
                            <?php if (!empty($user['Professional Headline'])): ?>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                                    <?= htmlspecialchars($user['Professional Headline']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <?php
                        $isActive = isset($user['is_active']) ? $user['is_active'] : 1;
                        ?>
                        <button id="toggleStatusBtn"
                            onclick="toggleUserStatus(<?= htmlspecialchars($user['id'] ?? $user['Id'] ?? 0) ?>, <?= $isActive ? 0 : 1 ?>)"
                            class="px-5 py-2 <?= $isActive ? 'bg-red-500 hover:bg-red-600 text-white dark:bg-red-600 dark:hover:bg-red-700' : 'bg-green-500 hover:bg-green-600 text-white dark:bg-green-600 dark:hover:bg-green-700' ?> rounded-lg font-medium text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 <?= $isActive ? 'focus:ring-red-500' : 'focus:ring-green-500' ?> shadow flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm">
                                <?= $isActive ? 'block' : 'check_circle' ?>
                            </span>
                            <span id="toggleStatusText"><?= $isActive ? 'Deactivate' : 'Activate' ?></span>
                        </button>
                    </div>

                    <!-- Bio -->
                    <?php if (!empty($user['Bio'])): ?>
                        <div class="mt-4 p-4 bg-gray-50 dark:bg-gray-900 rounded-lg">
                            <p class="text-sm text-gray-700 dark:text-gray-300">
                                <?= nl2br(htmlspecialchars($user['Bio'])) ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <!-- Quick Stats -->
                    <div class="grid grid-cols-3 gap-4 mt-6">
                        <div class="text-center p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                            <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                                <?= count($sessions) ?>
                            </p>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">Total Sessions</p>
                        </div>
                        <div class="text-center p-3 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                            <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                                <?= $developerProfile ? 'Yes' : 'No' ?>
                            </p>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">Developer</p>
                        </div>
                        <div class="text-center p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                            <p class="text-2xl font-bold text-green-600 dark:text-green-400">
                                <?= date('M d, Y', strtotime($user['created_at'])) ?>
                            </p>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">Joined</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Developer Profile Section -->
        <?php if ($developerProfile): ?>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6 mb-6">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-purple-600 filled">code</span>
                    Developer Profile
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Title</label>
                        <p class="text-base text-gray-900 dark:text-white mt-1">
                            <?= htmlspecialchars($developerProfile['title'] ?? 'N/A') ?>
                        </p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Hourly Rate</label>
                        <p class="text-base text-gray-900 dark:text-white mt-1">₹
                            <?= number_format($developerProfile['rate'] ?? 0) ?>/hr
                        </p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Location</label>
                        <p class="text-base text-gray-900 dark:text-white mt-1">
                            <?= htmlspecialchars($developerProfile['location'] ?? 'N/A') ?>
                        </p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Availability</label>
                        <p class="text-base text-gray-900 dark:text-white mt-1">
                            <?= htmlspecialchars($developerProfile['availability'] ?? 'N/A') ?>
                        </p>
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Skills</label>
                        <div class="flex flex-wrap gap-2 mt-2">
                            <?php
                            $skills = explode(',', $developerProfile['skills'] ?? '');
                            foreach (array_filter($skills) as $skill):
                                ?>
                                <span
                                    class="px-3 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-full text-sm">
                                    <?= htmlspecialchars(trim($skill)) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php if (!empty($developerProfile['bio'])): ?>
                        <div class="md:col-span-2">
                            <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Bio</label>
                            <p class="text-sm text-gray-700 dark:text-gray-300 mt-2">
                                <?= nl2br(htmlspecialchars($developerProfile['bio'])) ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Session History -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <span class="material-symbols-outlined text-green-600 filled">devices</span>
                    Session History
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Recent login sessions (last 10)</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                Device</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                IP Address</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                User Agent</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                Last Activity</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($sessions as $session): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <span class="material-symbols-outlined text-gray-600 dark:text-gray-400">
                                            <?= $session['device_type'] === 'Mobile' ? 'smartphone' : ($session['device_type'] === 'Tablet' ? 'tablet' : 'computer') ?>
                                        </span>
                                        <span class="text-sm text-gray-900 dark:text-white">
                                            <?= htmlspecialchars($session['device_type']) ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-sm text-gray-600 dark:text-gray-300">
                                        <?= htmlspecialchars($session['ip_address']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-xs block">
                                        <?= htmlspecialchars(substr($session['user_agent'], 0, 50)) ?>...
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-sm text-gray-600 dark:text-gray-300">
                                        <?= date('M d, Y H:i', strtotime($session['created_at'])) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($sessions)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center">
                                    <span class="material-symbols-outlined text-gray-400 text-4xl mb-2">devices_off</span>
                                    <p class="text-gray-500 dark:text-gray-400">No session history</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Scripts -->
    <script>
        // Dark Mode Toggle
        const darkModeToggle = document.getElementById('darkModeToggle');
        const html = document.documentElement;

        const currentTheme = localStorage.getItem('theme') || 'light';
        if (currentTheme === 'dark') {
            html.classList.add('dark');
        }

        darkModeToggle.addEventListener('click', () => {
            html.classList.toggle('dark');
            const newTheme = html.classList.contains('dark') ? 'dark' : 'light';
            localStorage.setItem('theme', newTheme);
        });

        // Toggle User Status Functionality
        async function toggleUserStatus(userId, targetStatus) {
            const btn = document.getElementById('toggleStatusBtn');
            const actionText = targetStatus === 1 ? "Activate" : "Deactivate";

            if (!confirm(`Are you sure you want to ${actionText} this user's account?`)) {
                return;
            }

            try {
                btn.disabled = true;
                const originalContent = btn.innerHTML;
                btn.innerHTML = `<span class="material-symbols-outlined text-sm animate-spin">sync</span><span>Processing...</span>`;

                const response = await fetch('toggle_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: userId, status: targetStatus })
                });

                const data = await response.json();

                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Something went wrong');
                    btn.innerHTML = originalContent;
                    btn.disabled = false;
                }
            } catch (error) {
                console.error("Error:", error);
                alert('Connection error');
                location.reload();
            }
        }
    </script>

</body>

</html>