<?php
/**
 * Admin Dashboard
 * Main admin panel showing all users and statistics
 */

require_once 'auth.php';
requireAdminAuth();

require_once __DIR__ . '/../includes/db_helper.php';

$admin = getCurrentAdmin();
$db = getDB();

// Get user statistics
$totalUsers = 0;
$activeSessions = 0;
$totalDevelopers = 0;
$totalJobs = 0;

if ($db) {
    try {
        // Total users
        $stmt = $db->query("SELECT COUNT(*) as count FROM register");
        $totalUsers = $stmt->fetch()['count'] ?? 0;

        // Active sessions (last 7 days - fixed column name)
        $stmt = $db->query("SELECT COUNT(*) as count FROM user_sessions WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $activeSessions = $stmt->fetch()['count'] ?? 0;

        // Total developer profiles
        $stmt = $db->query("SELECT COUNT(*) as count FROM developers");
        $totalDevelopers = $stmt->fetch()['count'] ?? 0;

        // Total job postings
        $stmt = $db->query("SELECT COUNT(*) as count FROM post_jobs");
        $totalJobs = $stmt->fetch()['count'] ?? 0;

    } catch (PDOException $e) {
        error_log("Dashboard Stats Error: " . $e->getMessage());
    }
}

// Get all users with their details
$users = [];
if ($db) {
    try {
        $query = "SELECT 
                    r.Id,
                    r.Name,
                    r.Email,
                    r.created_at,
                    d.title as developer_title,
                    d.rate as hourly_rate,
                    COUNT(DISTINCT us.id) as session_count
                  FROM register r
                  LEFT JOIN developers d ON r.Id = d.user_id
                  LEFT JOIN user_sessions us ON r.Id = us.user_id
                  GROUP BY r.Id, r.Name, r.Email, r.created_at, d.title, d.rate
                  ORDER BY r.created_at DESC";

        $stmt = $db->query($query);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Dashboard Users Error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="../assetes/logo.png" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - NeXLace</title>
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
                <!-- Logo & Title -->
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-primary rounded-lg flex items-center justify-center">
                        <span class="material-symbols-outlined text-white">admin_panel_settings</span>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900 dark:text-white">NeXLace Admin</h1>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Management Dashboard</p>
                    </div>
                </div>

                <!-- Right Side Actions -->
                <div class="flex items-center gap-3">
                    <!-- Dark Mode Toggle -->
                    <button id="darkModeToggle"
                        class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                        <span class="material-symbols-outlined text-gray-700 dark:text-gray-200">dark_mode</span>
                    </button>

                    <!-- Admin Profile -->
                    <div class="flex items-center gap-2 px-3 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg">
                        <span class="material-symbols-outlined text-gray-600 dark:text-gray-300">account_circle</span>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
                            <?= htmlspecialchars($admin['name']) ?>
                        </span>
                    </div>

                    <!-- Logout Button -->
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

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">

            <!-- Total Users -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Users</p>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">
                            <?= number_format($totalUsers) ?>
                        </p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                        <span class="material-symbols-outlined text-blue-600 dark:text-blue-400 filled">group</span>
                    </div>
                </div>
            </div>

            <!-- Active Sessions -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Active Sessions</p>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">
                            <?= number_format($activeSessions) ?>
                        </p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center">
                        <span
                            class="material-symbols-outlined text-green-600 dark:text-green-400 filled">check_circle</span>
                    </div>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Last 7 days</p>
            </div>

            <!-- Developer Profiles -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6 border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Developers</p>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">
                            <?= number_format($totalDevelopers) ?>
                        </p>
                    </div>
                    <div
                        class="w-12 h-12 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center">
                        <span class="material-symbols-outlined text-purple-600 dark:text-purple-400 filled">code</span>
                    </div>
                </div>
            </div>

            <!-- Total Jobs -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6 border-l-4 border-orange-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Job Postings</p>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">
                            <?= number_format($totalJobs) ?>
                        </p>
                    </div>
                    <div
                        class="w-12 h-12 bg-orange-100 dark:bg-orange-900 rounded-full flex items-center justify-center">
                        <span class="material-symbols-outlined text-orange-600 dark:text-orange-400 filled">work</span>
                    </div>
                </div>
            </div>

        </div>

        <!-- Users Table -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md overflow-hidden">

            <!-- Table Header -->
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">All Users</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Manage and view all registered users</p>
                </div>

                <!-- Search Box -->
                <div class="relative">
                    <span
                        class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">search</span>
                    <input type="text" id="searchInput" placeholder="Search users..."
                        class="pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm focus:ring-2 focus:ring-primary focus:border-primary">
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                ID</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                User</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Email</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Role</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Sessions</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Joined</th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700"
                        id="userTableBody">
                        <?php foreach ($users as $user): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors user-row">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">#
                                        <?= $user['Id'] ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="w-10 h-10 bg-primary rounded-full flex items-center justify-center text-white font-bold">
                                            <?= strtoupper(substr($user['Name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                <?= htmlspecialchars($user['Name']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-600 dark:text-gray-300">
                                        <?= htmlspecialchars($user['Email']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if (!empty($user['developer_title'])): ?>
                                        <span
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200">
                                            <span class="material-symbols-outlined text-sm">code</span>
                                            Developer
                                        </span>
                                    <?php else: ?>
                                        <span
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200">
                                            <span class="material-symbols-outlined text-sm">person</span>
                                            User
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-600 dark:text-gray-300">
                                        <?= $user['session_count'] ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-600 dark:text-gray-300">
                                        <?= date('M d, Y', strtotime($user['created_at'])) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <a href="user_details.php?id=<?= $user['Id'] ?>"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 bg-primary hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                                        <span class="material-symbols-outlined text-sm">visibility</span>
                                        View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <span class="material-symbols-outlined text-gray-400 text-5xl mb-2">person_off</span>
                                    <p class="text-gray-500 dark:text-gray-400">No users found</p>
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

        // Search Functionality
        const searchInput = document.getElementById('searchInput');
        const userRows = document.querySelectorAll('.user-row');

        searchInput.addEventListener('input', (e) => {
            const searchTerm = e.target.value.toLowerCase();

            userRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>

</body>

</html>