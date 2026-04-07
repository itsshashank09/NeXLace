<?php
require_once 'auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $result = authenticateAdmin($username, $password);

    if ($result['success']) {
        header('Location: dashboard.php');
        exit();
    } else {
        $error = $result['message'];
    }
}

// Redirect if already logged in
if (isAdminLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="../assetes/logo.png" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - NeXLace</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
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

<body
    class="bg-gradient-to-br from-blue-50 to-indigo-100 dark:from-gray-900 dark:to-gray-800 min-h-screen flex items-center justify-center p-4">

    <!-- Dark Mode Toggle -->
    <button id="darkModeToggle"
        class="fixed top-4 right-4 p-2 rounded-lg bg-white dark:bg-gray-800 shadow-lg hover:shadow-xl transition-all">
        <span class="material-symbols-outlined text-gray-700 dark:text-gray-200">dark_mode</span>
    </button>

    <!-- Login Card -->
    <div class="w-full max-w-md">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl p-8 space-y-6">

            <!-- Logo & Title -->
            <div class="text-center space-y-2">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-primary rounded-2xl mb-4">
                    <span class="material-symbols-outlined text-white text-3xl">admin_panel_settings</span>
                </div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">NeXLace Admin</h1>
                <p class="text-gray-500 dark:text-gray-400">Sign in to access admin panel</p>
            </div>

            <!-- Login Form -->
            <form id="loginForm" method="POST" action="" class="space-y-4">

                <!-- Error Message -->


                <?php if (!empty($error)): ?>
                    <div
                        class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 px-4 py-3 rounded-lg flex items-center gap-2">
                        <span class="material-symbols-outlined text-xl">error</span>
                        <span>
                            <?= htmlspecialchars($error) ?>
                        </span>
                    </div>
                <?php endif; ?>

                <!-- Username Field -->
                <div class="space-y-2">
                    <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Username
                    </label>
                    <div class="relative">
                        <span
                            class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">person</span>
                        <input type="text" id="username" name="username" required
                            class="w-full pl-11 pr-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary focus:border-primary transition-all"
                            placeholder="Enter your username" autocomplete="username">
                    </div>
                </div>

                <!-- Password Field -->
                <div class="space-y-2">
                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Password
                    </label>
                    <div class="relative">
                        <span
                            class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">lock</span>
                        <input type="password" id="password" name="password" required
                            class="w-full pl-11 pr-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary focus:border-primary transition-all"
                            placeholder="Enter your password" autocomplete="current-password">
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit"
                    class="w-full bg-primary hover:bg-blue-700 text-white font-semibold py-3 rounded-lg shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200 flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined">login</span>
                    <span>Sign In</span>
                </button>
            </form>

            <!-- Footer -->
            <div
                class="text-center text-sm text-gray-500 dark:text-gray-400 pt-4 border-t border-gray-200 dark:border-gray-700">
                <p>NeXLace Admin Panel v1.0</p>
                <p class="mt-1">©
                    <?= date('Y') ?> NeXLace. All rights reserved.
                </p>
            </div>
        </div>
    </div>

    <!-- Dark Mode Script -->
    <script>
        const darkModeToggle = document.getElementById('darkModeToggle');
        const html = document.documentElement;

        // Check for saved theme preference
        const currentTheme = localStorage.getItem('theme') || 'light';
        if (currentTheme === 'dark') {
            html.classList.add('dark');
        }

        darkModeToggle.addEventListener('click', () => {
            html.classList.toggle('dark');
            const newTheme = html.classList.contains('dark') ? 'dark' : 'light';
            localStorage.setItem('theme', newTheme);
        });
    </script>

</body>

</html>