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
$userId = $_SESSION['user_id'] ?? null;
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
?>
<!DOCTYPE html>
<html class="light" lang="en">

<head>
    <link rel="icon" type="image/png" href="assetes/logo.png" />
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>NeXLace - Notifications</title>
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
                    <button onclick="window.location.href='help.php'"
                        class="text-text-sub hover:text-primary dark:text-gray-400 dark:hover:text-white">
                        <span class="material-symbols-outlined">help</span>
                    </button>
                    <button onclick="window.location.href='notification.php'"
                        class="relative text-sm font-bold text-primary dark:text-primary hover:text-primary dark:hover:text-primary transition-colors">
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
            <div class="mx-auto max-w-[800px] px-4 sm:px-6 lg:px-8 space-y-6">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <h1 class="text-2xl font-bold tracking-tight text-text-main dark:text-white">Notifications</h1>
                    <button id="markAllReadBtn"
                        class="text-sm font-medium text-primary hover:text-primary-dark dark:text-blue-400 dark:hover:text-blue-300 transition-colors">
                        Mark all as read
                    </button>
                </div>
                <div class="flex flex-wrap gap-2 pb-2">
                    <button id="filterAll"
                        class="filter-btn active rounded-full bg-text-main text-white px-4 py-1.5 text-sm font-medium hover:bg-opacity-90 transition-colors">
                        All
                    </button>
                    <button id="filterUnread"
                        class="filter-btn rounded-full bg-white dark:bg-card-dark border border-gray-200 dark:border-gray-700 text-text-main dark:text-gray-300 px-4 py-1.5 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                        Unread <span id="unreadBadge"
                            class="ml-1 text-xs font-bold text-white bg-primary rounded-full px-1.5 py-0.5">0</span>
                    </button>
                </div>

                <!-- Loading State -->
                <div id="loadingState" class="flex justify-center py-12">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                </div>

                <!-- Empty State -->
                <div id="emptyState" class="hidden text-center py-12">
                    <span
                        class="material-symbols-outlined text-6xl text-gray-300 dark:text-gray-600">notifications_off</span>
                    <p class="mt-4 text-lg font-medium text-text-main dark:text-gray-300">No notifications</p>
                    <p class="mt-1 text-sm text-text-sub">You're all caught up!</p>
                </div>

                <!-- Notifications Container -->
                <div id="notificationsContainer" class="space-y-3">
                    <!-- Notifications will be dynamically inserted here -->
                </div>
            </div>
        </main>
    </div>

    <script>
        // ──────── Shared state (used by both inline scripts & SSE module) ────────
        let currentFilter = 'all';

        // SSE handles initial load; this is only for manual re-fetches
        document.addEventListener('DOMContentLoaded', () => {
            setupEventListeners();
        });

        function setupEventListeners() {
            document.getElementById('filterAll').addEventListener('click', () => setFilter('all'));
            document.getElementById('filterUnread').addEventListener('click', () => setFilter('unread'));
            document.getElementById('markAllReadBtn').addEventListener('click', markAllAsRead);
        }

        function setFilter(filter) {
            currentFilter = filter;

            // Update button styles
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('bg-text-main', 'text-white');
                btn.classList.add('bg-white', 'dark:bg-card-dark', 'border', 'border-gray-200', 'dark:border-gray-700', 'text-text-main', 'dark:text-gray-300');
            });

            const activeBtn = filter === 'all'
                ? document.getElementById('filterAll')
                : document.getElementById('filterUnread');
            activeBtn.classList.remove('bg-white', 'dark:bg-card-dark', 'border', 'border-gray-200', 'dark:border-gray-700', 'text-text-main', 'dark:text-gray-300');
            activeBtn.classList.add('bg-text-main', 'text-white');

            // Re-render from the SSE cache – no network request needed
            if (typeof nexlaceRefreshList === 'function') {
                nexlaceRefreshList();
            }
        }

        // ──────── Mark All as Read (inline update, NO reload) ────────
        async function markAllAsRead() {
            try {
                const response = await csrfFetch('api/mark_notifications_read.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ mark_all: true })
                });
                const data = await response.json();

                if (data.success) {
                    // Update UI in-place via SSE helper
                    if (typeof nexlaceMarkAllReadLocally === 'function') {
                        nexlaceMarkAllReadLocally();
                    }
                } else {
                    console.error('Failed to mark all as read:', data.error);
                }
            } catch (error) {
                console.error('Error marking all as read:', error);
            }
        }

        // ──────── Mark Single as Read (inline update, NO reload) ────────
        async function markAsRead(notificationId, link) {
            try {
                const response = await csrfFetch('api/mark_notifications_read.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ notification_id: notificationId })
                });
                const data = await response.json();

                if (data.success) {
                    // Update UI in-place
                    if (typeof nexlaceMarkReadLocally === 'function') {
                        nexlaceMarkReadLocally(notificationId);
                    }
                    // Navigate if there's a target link
                    if (link) {
                        window.location.href = link;
                    }
                }
            } catch (error) {
                console.error('Error marking notification as read:', error);
                if (link) window.location.href = link;
            }
        }

        // ──────── Delete Notification (animated removal, NO reload) ────────
        async function deleteNotification(notificationId) {
            if (!confirm('Are you sure you want to delete this notification?')) return;

            try {
                const response = await csrfFetch('api/delete_notification.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ notification_id: notificationId })
                });
                const data = await response.json();

                if (data.success) {
                    // Animate out & remove from cache
                    if (typeof nexlaceRemoveNotification === 'function') {
                        nexlaceRemoveNotification(notificationId);
                    }
                } else {
                    console.error('Failed to delete notification:', data.error);
                    alert('Failed to delete notification');
                }
            } catch (error) {
                console.error('Error deleting notification:', error);
                alert('An error occurred while deleting the notification');
            }
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