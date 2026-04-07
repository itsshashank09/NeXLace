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
        <main class="flex-grow py-8">
            <div class="mx-auto max-w-[1200px] px-4 sm:px-6 lg:px-8">
                <div class="mb-8">
                    <h1 class="text-3xl font-bold tracking-tight text-text-main dark:text-white">Login &amp; Security
                    </h1>
                    <p class="text-text-sub mt-2">Manage your password, security preferences, and active sessions.</p>
                </div>
                <div class="flex flex-col lg:flex-row gap-8">
                    <aside class="w-full lg:w-64 flex-shrink-0">
                        <nav class="sticky top-24 space-y-1">
                            <a class="flex items-center gap-3 px-4 py-2.5 text-sm font-medium rounded-lg text-text-sub hover:bg-gray-50 hover:text-text-main dark:text-gray-400 dark:hover:bg-[#1f2937] dark:hover:text-white transition-colors"
                                href="profilesetting.php">
                                <span class="material-symbols-outlined text-[20px]">person</span>
                                My Profile
                            </a>
                            <a class="flex items-center gap-3 px-4 py-2.5 text-sm font-medium rounded-lg text-text-sub hover:bg-gray-50 hover:text-text-main dark:text-gray-400 dark:hover:bg-[#1f2937] dark:hover:text-white transition-colors"
                                href="profilesetting.php#theme">
                                <span class="material-symbols-outlined text-[20px]">palette</span>
                                Theme
                            </a>
                            <a class="flex items-center gap-3 px-4 py-2.5 text-sm font-bold rounded-lg bg-primary/10 text-primary"
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
                            class="rounded-xl border border-[#e7ebf3] dark:border-[#2a3447] bg-white dark:bg-card-dark shadow-sm">
                            <div class="border-b border-[#e7ebf3] px-6 py-4 dark:border-[#2a3447]">
                                <h3 class="text-lg font-bold text-text-main dark:text-white">Password Management</h3>
                                <p class="text-sm text-text-sub">It's a good idea to use a strong password that you're
                                    not using elsewhere.</p>
                            </div>
                            <div class="p-6">
                                <div class="grid grid-cols-1 gap-6 max-w-xl">
                                    <div>
                                        <label
                                            class="mb-1.5 block text-sm font-medium text-text-main dark:text-gray-300"
                                            for="currentPassword">Current Password</label>
                                        <input class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white
                                        dark:bg-[#1a2333] shadow-sm focus:border-primary focus:ring-primary sm:text-sm"
                                            id="currentPassword" type="password" autocomplete="current-password" />
                                    </div>
                                    <div>
                                        <label
                                            class="mb-1.5 block text-sm font-medium text-text-main dark:text-gray-300"
                                            for="newPassword">New Password</label>
                                        <input class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white
                                        dark:bg-[#1a2333] shadow-sm focus:border-primary focus:ring-primary sm:text-sm"
                                            id="newPassword" type="password" autocomplete="new-password" />
                                    </div>
                                    <div>
                                        <label
                                            class="mb-1.5 block text-sm font-medium text-text-main dark:text-gray-300"
                                            for="confirmPassword">Confirm New Password</label>
                                        <input class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white
                                        dark:bg-[#1a2333] shadow-sm focus:border-primary focus:ring-primary sm:text-sm"
                                            id="confirmPassword" type="password" autocomplete="new-password" />
                                    </div>
                                </div>
                                <div
                                    class="mt-8 flex justify-start border-t border-[#e7ebf3] pt-6 dark:border-[#2a3447]">
                                    <button onclick="updatePassword()" id="updatePasswordBtn"
                                        class="rounded-lg bg-primary px-6 py-2 text-sm font-bold text-white hover:bg-primary-dark transition-colors shadow-sm">Update
                                        Password</button>
                                </div>
                            </div>
                        </div>
                        <div
                            class="rounded-xl border border-[#e7ebf3] dark:border-[#2a3447] bg-white dark:bg-card-dark shadow-sm">
                            <div class="border-b border-[#e7ebf3] px-6 py-4 dark:border-[#2a3447]">
                                <h3 class="text-lg font-bold text-text-main dark:text-white">Connected Devices</h3>
                                <p class="text-sm text-text-sub">You're currently logged in to these devices.</p>
                            </div>
                            <div id="devicesContainer" class="divide-y divide-[#e7ebf3] dark:divide-[#2a3447]">
                                <!-- Devices will be populated here -->
                                <div class="p-6 text-center text-text-sub">
                                    Loading devices...
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </main>

    </div>

    <script>
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

        function updatePassword() {
            const currentPassword = document.getElementById('currentPassword').value.trim();
            const newPassword = document.getElementById('newPassword').value.trim();
            const confirmPassword = document.getElementById('confirmPassword').value.trim();
            const updateBtn = document.getElementById('updatePasswordBtn');

            if (!currentPassword || !newPassword || !confirmPassword) {
                showNotification('Please fill in all fields', 'error');
                return;
            }

            if (newPassword !== confirmPassword) {
                showNotification('New passwords do not match', 'error');
                return;
            }

            if (newPassword.length < 6) {
                showNotification('New password must be at least 6 characters', 'error');
                return;
            }

            updateBtn.disabled = true;
            updateBtn.innerHTML = 'Updating...';

            const formData = new FormData();
            formData.append('currentPassword', currentPassword);
            formData.append('newPassword', newPassword);
            formData.append('confirmPassword', confirmPassword);

            csrfFetch('api/change_password.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                        document.getElementById('currentPassword').value = '';
                        document.getElementById('newPassword').value = '';
                        document.getElementById('confirmPassword').value = '';
                    } else {
                        showNotification(data.message, 'error');
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    showNotification('An error occurred. Please try again.', 'error');
                })
                .finally(() => {
                    updateBtn.disabled = false;
                    updateBtn.innerHTML = 'Update Password';
                });
        }

        // Fetch connected devices on load
        document.addEventListener('DOMContentLoaded', fetchDevices);

        function fetchDevices() {
            fetch('api/get_devices.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderDevices(data.devices);
                    } else {
                        document.getElementById('devicesContainer').innerHTML = `<div class="p-6 text-red-500">${data.message}</div>`;
                    }
                })
                .catch(err => {
                    console.error('Error fetching devices:', err);
                    document.getElementById('devicesContainer').innerHTML = '<div class="p-6 text-red-500">Failed to load devices</div>';
                });
        }

        function renderDevices(devices) {
            const container = document.getElementById('devicesContainer');
            if (devices.length === 0) {
                container.innerHTML = '<div class="p-6 text-gray-500">No active sessions found.</div>';
                return;
            }

            let html = '';
            devices.forEach(device => {
                let icon = 'desktop_windows';
                if (device.device_type === 'Mobile') icon = 'smartphone';
                if (device.device_type === 'Tablet') icon = 'tablet_mac';

                const isCurrent = device.is_current ?
                    `<span class="inline-flex items-center rounded-full bg-green-500/10 px-2 py-0.5 text-[10px] font-medium text-green-600 ring-1 ring-inset ring-green-500/20">Current Session</span>` : '';

                const logoutBtn = !device.is_current ?
                    `<button onclick="revokeSession(${device.id})" class="text-sm font-medium text-text-sub hover:text-red-600 dark:hover:text-red-400 transition-colors">Log out</button>` :
                    `<span class="text-sm font-medium text-gray-400 cursor-default">Current</span>`;

                html += `
                <div class="flex items-center justify-between p-6">
                    <div class="flex items-center gap-4">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gray-100 dark:bg-[#1a2333] text-text-sub">
                            <span class="material-symbols-outlined">${icon}</span>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <p class="font-bold text-text-main dark:text-white">${device.name}</p>
                                ${isCurrent}
                            </div>
                            <p class="text-xs text-text-sub">${device.description}</p>
                        </div>
                    </div>
                    ${logoutBtn}
                </div>`;
            });

            container.innerHTML = html;
        }

        function revokeSession(deviceId) {
            if (!confirm('Are you sure you want to log out this device?')) return;

            const formData = new FormData();
            formData.append('device_id', deviceId);

            csrfFetch('api/revoke_session.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                        fetchDevices(); // Refresh list
                    } else {
                        showNotification(data.message, 'error');
                    }
                })
                .catch(err => {
                    console.error(err);
                    showNotification('Failed to logout device', 'error');
                });
        }
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const buttons = [
                { id: 'nav-help-btn', url: 'help.php' },
                { id: 'nav-notification-btn', url: 'notification.php' }
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