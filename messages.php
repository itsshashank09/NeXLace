<?php
session_start();
require_once 'config/security_headers.php';
require_once 'config/csrf.php';
$csrfToken = generateCsrfToken();

// Check if user is logged in
if (!isset($_SESSION['name']) || empty($_SESSION['name'])) {
    header('Location: login.html');
    exit();
}

require_once 'config/database.php';

$name = $_SESSION['name'];
$email = $_SESSION['email'] ?? '';
$userId = $_SESSION['user_id'] ?? null;
$profileImage = '';

// Fetch profile data
$unreadNotifications = 0;
try {
    require_once 'includes/db_helper.php';
    $conn = getDB();

    if ($conn && !empty($email)) {
        $stmt = $conn->prepare("SELECT `Professional Headline`, `Bio`, `image` FROM register WHERE Email = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
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

// Check for invitation parameters (from developer profile page)
$inviteAction = isset($_GET['action']) && $_GET['action'] === 'invite';
$inviteDevId = $_GET['dev_id'] ?? null;
$inviteDevName = $_GET['dev_name'] ?? '';
$inviteDevImage = $_GET['dev_image'] ?? '';
$inviteWorkType = $_GET['work_type'] ?? '';
$inviteWorkEmail = $_GET['work_email'] ?? '';
$inviteWorkDetails = $_GET['work_details'] ?? '';
?>
<!DOCTYPE html>
<html class="light" lang="en">

<head>
    <link rel="icon" type="image/png" href="assetes/logo.png" />
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>NeXLace - Messages</title>
    <meta name="description" content="Manage your conversations and job invitations on NeXLace" />
    <link href="https://fonts.googleapis.com" rel="preconnect" />
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap"
        rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
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
                        "success": "#10b981",
                        "danger": "#ef4444",
                        "warning": "#f59e0b",
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
    <style>
        /* Custom scrollbar for chat areas */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }

        .dark .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #475569;
        }

        /* Message bubble animations */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message-bubble {
            animation: slideIn 0.3s ease-out;
        }

        /* Pulse animation for unread indicator */
        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }

        .pulse-animation {
            animation: pulse 2s ease-in-out infinite;
        }

        /* Conversation item hover effect */
        .conversation-item {
            transition: all 0.2s ease;
        }

        .conversation-item:hover {
            transform: translateX(4px);
        }

        .conversation-item.active {
            background: linear-gradient(135deg, rgba(19, 91, 236, 0.1) 0%, rgba(19, 91, 236, 0.05) 100%);
            border-left: 3px solid #135bec;
        }

        /* Invitation card styles */
        .invitation-card {
            transition: all 0.2s ease;
        }

        .invitation-card:hover {
            box-shadow: 0 4px 20px -4px rgba(0, 0, 0, 0.1);
        }

        .dark .invitation-card:hover {
            box-shadow: 0 4px 20px -4px rgba(0, 0, 0, 0.3);
        }

        /* Fade in animation for invitation card */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .invitation-card {
            animation: fadeIn 0.3s ease-out;
        }

        /* Button hover animations */
        @keyframes buttonPress {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(0.95);
            }

            100% {
                transform: scale(1);
            }
        }

        /* Smooth spin animation for loading */
        @keyframes smoothSpin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        .animate-spin {
            animation: smoothSpin 1.5s linear infinite;
        }

        /* Action required glow effect */
        @keyframes actionGlow {

            0%,
            100% {
                box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.4);
            }

            50% {
                box-shadow: 0 0 0 8px rgba(245, 158, 11, 0);
            }
        }

        .action-required-glow {
            animation: actionGlow 2s ease-in-out infinite;
        }
    </style>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken); ?>">
    <script src="js/csrf.js"></script>
</head>

<body class="bg-background-light dark:bg-background-dark text-text-main dark:text-[#f8f9fc] font-display antialiased">
    <div class="relative flex h-screen w-full flex-col overflow-hidden">
        <!-- Header -->
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

        <!-- Main Content -->
        <main class="flex-1 flex overflow-hidden">
            <div class="w-full max-w-[1400px] mx-auto flex">
                <!-- Conversations Sidebar -->
                <aside id="conversations-sidebar"
                    class="w-full sm:w-80 lg:w-96 border-r border-[#e7ebf3] dark:border-[#2a3447] bg-white dark:bg-card-dark flex flex-col">
                    <!-- Sidebar Header -->
                    <div class="p-4 border-b border-[#e7ebf3] dark:border-[#2a3447]">
                        <div class="flex items-center justify-between mb-4">
                            <h1 class="text-xl font-bold text-text-main dark:text-white">Messages</h1>
                        </div>
                        <!-- Search -->
                        <div class="relative">
                            <span
                                class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-gray-400 text-[20px]">search</span>
                            <input id="conversation-search" type="text" placeholder="Search conversations..."
                                class="w-full h-10 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-[#1a2333] pl-10 pr-4 text-sm focus:border-primary focus:ring-1 focus:ring-primary" />
                        </div>
                    </div>

                    <!-- Filter Tabs -->
                    <div class="px-4 py-2 flex gap-2 border-b border-[#e7ebf3] dark:border-[#2a3447]">
                        <button data-filter="all"
                            class="filter-btn px-3 py-1.5 rounded-full text-xs font-semibold bg-primary text-white">
                            All
                        </button>
                        <button data-filter="invitations"
                            class="filter-btn px-3 py-1.5 rounded-full text-xs font-semibold bg-gray-100 dark:bg-[#1f2937] text-text-sub dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-[#2a3447]">
                            Invitations
                        </button>
                        <button data-filter="job_requests"
                            class="filter-btn px-3 py-1.5 rounded-full text-xs font-semibold bg-gray-100 dark:bg-[#1f2937] text-text-sub dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-[#2a3447]">
                            Job Requests
                        </button>
                        <button data-filter="messages"
                            class="filter-btn px-3 py-1.5 rounded-full text-xs font-semibold bg-gray-100 dark:bg-[#1f2937] text-text-sub dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-[#2a3447]">
                            Request Messages
                        </button>
                    </div>

                    <!-- Conversations List -->
                    <div id="conversations-list" class="flex-1 overflow-y-auto custom-scrollbar">
                        <!-- Loading state -->
                        <div id="conversations-loading" class="flex items-center justify-center py-12">
                            <div class="animate-spin rounded-full h-8 w-8 border-2 border-primary border-t-transparent">
                            </div>
                        </div>
                        <!-- Empty state -->
                        <div id="conversations-empty"
                            class="hidden flex-col items-center justify-center py-12 px-6 text-center">
                            <div
                                class="w-16 h-16 rounded-full bg-gray-100 dark:bg-[#1f2937] flex items-center justify-center mb-4">
                                <span class="material-symbols-outlined text-[32px] text-gray-400">chat_bubble</span>
                            </div>
                            <h3 class="text-base font-semibold text-text-main dark:text-white mb-1">No conversations yet
                            </h3>
                            <p class="text-sm text-text-sub">Start by inviting talent or applying to jobs</p>
                        </div>
                        <!-- Conversations will be loaded here -->
                    </div>
                </aside>

                <!-- Chat Area -->
                <section id="chat-area"
                    class="hidden sm:flex flex-1 flex-col bg-background-light dark:bg-background-dark">
                    <!-- Empty Chat State -->
                    <div id="chat-empty-state"
                        class="flex-1 flex flex-col items-center justify-center text-center px-8">
                        <div
                            class="w-24 h-24 rounded-full bg-gradient-to-br from-primary/20 to-blue-400/20 flex items-center justify-center mb-6">
                            <span class="material-symbols-outlined text-[48px] text-primary">forum</span>
                        </div>
                        <h2 class="text-2xl font-bold text-text-main dark:text-white mb-2">Your Messages</h2>
                        <p class="text-text-sub max-w-md mb-6">Select a conversation to start chatting or review job
                            invitations</p>
                        <a href="developer.php"
                            class="inline-flex items-center gap-2 px-6 py-3 rounded-full bg-primary text-white font-semibold hover:bg-primary-dark transition-colors shadow-lg shadow-primary/25">
                            <span class="material-symbols-outlined text-[20px]">person_search</span>
                            Find Talent
                        </a>
                    </div>

                    <!-- Active Chat Container (hidden by default) -->
                    <div id="chat-container" class="hidden flex-1 flex flex-col h-full">
                        <!-- Chat Header -->
                        <div id="chat-header"
                            class="px-6 py-4 border-b border-[#e7ebf3] dark:border-[#2a3447] bg-white dark:bg-card-dark flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <button id="back-to-list"
                                    class="sm:hidden p-2 -ml-2 rounded-lg hover:bg-gray-100 dark:hover:bg-[#1f2937]">
                                    <span class="material-symbols-outlined">arrow_back</span>
                                </button>
                                <div id="chat-user-avatar"
                                    class="w-12 h-12 rounded-full bg-primary flex items-center justify-center text-white font-bold text-lg overflow-hidden">
                                    <!-- Avatar will be set dynamically -->
                                </div>
                                <div>
                                    <h3 id="chat-user-name" class="font-bold text-text-main dark:text-white">User Name
                                    </h3>

                                </div>
                            </div>
                            <div class="relative flex items-center gap-2">
                                <button id="chat-options-btn"
                                    class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-[#1f2937] text-text-sub transition-colors">
                                    <span class="material-symbols-outlined">more_vert</span>
                                </button>
                                <!-- Dropdown Menu -->
                                <div id="chat-options-dropdown"
                                    class="hidden absolute right-0 top-full mt-2 w-48 rounded-xl border border-[#e7ebf3] bg-white shadow-xl dark:border-[#2a3447] dark:bg-card-dark z-50 overflow-hidden transform origin-top-right transition-all">
                                    <div class="p-1">
                                        <button id="view-reviews-btn"
                                            class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-text-main hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-[#1f2937] transition-colors text-left">
                                            <span
                                                class="material-symbols-outlined text-[20px] text-text-sub">reviews</span>
                                            Reviews
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Messages Area -->
                        <div id="messages-area" class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-4">
                            <!-- Messages will be loaded here -->
                        </div>

                        <!-- Invitation Actions (for pending invitations) -->
                        <div id="invitation-actions"
                            class="hidden px-6 py-5 bg-gradient-to-r from-blue-50 via-indigo-50 to-purple-50 dark:from-[#1e293b] dark:via-[#162033] dark:to-[#0f172a] border-t border-[#e7ebf3] dark:border-[#2a3447]">
                            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 max-w-2xl mx-auto">
                                <div class="text-center sm:text-left">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="material-symbols-outlined text-primary text-lg">task_alt</span>
                                        <p class="text-base font-semibold text-text-main dark:text-white">Take Action
                                        </p>
                                    </div>
                                    <p class="text-sm text-text-sub">Accept to start a conversation with the client, or
                                        decline if not interested</p>
                                </div>
                                <div class="flex gap-3">
                                    <button id="reject-invitation-btn"
                                        class="group px-6 py-3 rounded-xl border-2 border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 font-semibold hover:bg-red-100 dark:hover:bg-red-900/30 hover:border-red-300 dark:hover:border-red-700 transition-all duration-200 flex items-center gap-2 min-w-[120px] justify-center">
                                        <span
                                            class="material-symbols-outlined text-[20px] group-hover:scale-110 transition-transform">close</span>
                                        Decline
                                    </button>
                                    <button id="accept-invitation-btn"
                                        class="group px-6 py-3 rounded-xl bg-gradient-to-r from-green-500 to-emerald-600 text-white font-semibold hover:from-green-600 hover:to-emerald-700 transition-all duration-200 shadow-lg shadow-green-500/30 hover:shadow-green-500/40 flex items-center gap-2 min-w-[120px] justify-center">
                                        <span
                                            class="material-symbols-outlined text-[20px] group-hover:scale-110 transition-transform">check</span>
                                        Accept
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Invitation Status (for already responded invitations) -->
                        <div id="invitation-status"
                            class="hidden px-6 py-4 border-t border-[#e7ebf3] dark:border-[#2a3447]">
                            <!-- Status message will be set dynamically -->
                        </div>

                        <!-- Message Input (hidden for pending invitations) -->
                        <div id="message-input-area"
                            class="px-6 py-4 border-t border-[#e7ebf3] dark:border-[#2a3447] bg-white dark:bg-card-dark">
                            <!-- File Preview Area -->
                            <div id="file-preview-area" class="hidden mb-3">
                                <div
                                    class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-[#1a2333] rounded-xl border border-gray-200 dark:border-gray-700">
                                    <div
                                        class="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center flex-shrink-0">
                                        <span id="file-icon"
                                            class="material-symbols-outlined text-primary">description</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p id="file-name"
                                            class="text-sm font-medium text-text-main dark:text-white truncate">
                                            filename.pdf</p>
                                        <p id="file-size" class="text-xs text-text-sub">1.2 MB</p>
                                    </div>
                                    <button id="remove-file-btn"
                                        class="p-1.5 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 text-text-sub hover:text-danger transition-colors"
                                        title="Remove file">
                                        <span class="material-symbols-outlined text-[20px]">close</span>
                                    </button>
                                </div>
                            </div>
                            <div class="flex items-end gap-3">
                                <input type="file" id="file-input" class="hidden"
                                    accept="image/*,.pdf,.doc,.docx,.txt,.zip,.rar" />
                                <button id="attach-file-btn"
                                    class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-[#1f2937] text-text-sub"
                                    title="Attach file (max 2 MB)">
                                    <span class="material-symbols-outlined">attach_file</span>
                                </button>
                                <div class="flex-1 relative">
                                    <textarea id="message-input" rows="1" placeholder="Type a message..."
                                        class="w-full resize-none rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-[#1a2333] px-4 py-3 text-sm focus:border-primary focus:ring-1 focus:ring-primary min-h-[48px] max-h-[120px]"></textarea>
                                </div>
                                <button id="send-message-btn"
                                    class="p-3 rounded-xl bg-primary text-white hover:bg-primary-dark transition-colors shadow-md shadow-primary/25 disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span class="material-symbols-outlined">send</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <!-- Toast Notification -->
    <div id="toast"
        class="fixed bottom-4 right-4 z-50 transform translate-y-full opacity-0 transition-all duration-300">
        <div
            class="px-6 py-4 rounded-xl shadow-xl bg-white dark:bg-card-dark border border-[#e7ebf3] dark:border-[#2a3447] flex items-center gap-3">
            <span id="toast-icon" class="material-symbols-outlined text-success">check_circle</span>
            <p id="toast-message" class="text-sm font-medium text-text-main dark:text-white">Message</p>
        </div>
    </div>

    <!-- Review Modal -->
    <div id="review-modal"
        class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm transition-opacity">
        <div
            class="bg-white dark:bg-card-dark rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all scale-100">
            <!-- Header -->
            <div
                class="px-6 py-4 border-b border-[#e7ebf3] dark:border-[#2a3447] flex items-center justify-between bg-gray-50 dark:bg-[#1a2333]/50">
                <h3 class="text-lg font-bold text-text-main dark:text-white">Rate Experience</h3>
                <button id="close-review-modal" class="text-text-sub hover:text-danger transition-colors">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <!-- Content -->
            <div class="p-6">
                <div class="text-center mb-6">
                    <p class="text-sm text-text-sub mb-2">How was your experience working with <span
                            id="review-user-name" class="font-semibold text-text-main dark:text-white">User</span>?</p>

                    <!-- Star Rating -->
                    <div class="flex justify-center gap-2 mb-2">
                        <button class="star-rating-btn p-1 transition-transform hover:scale-110 focus:outline-none"
                            data-value="1" title="Poor">
                            <span
                                class="material-symbols-outlined text-[32px] text-gray-300 transition-colors">star</span>
                        </button>
                        <button class="star-rating-btn p-1 transition-transform hover:scale-110 focus:outline-none"
                            data-value="2" title="Fair">
                            <span
                                class="material-symbols-outlined text-[32px] text-gray-300 transition-colors">star</span>
                        </button>
                        <button class="star-rating-btn p-1 transition-transform hover:scale-110 focus:outline-none"
                            data-value="3" title="Good">
                            <span
                                class="material-symbols-outlined text-[32px] text-gray-300 transition-colors">star</span>
                        </button>
                        <button class="star-rating-btn p-1 transition-transform hover:scale-110 focus:outline-none"
                            data-value="4" title="Very Good">
                            <span
                                class="material-symbols-outlined text-[32px] text-gray-300 transition-colors">star</span>
                        </button>
                        <button class="star-rating-btn p-1 transition-transform hover:scale-110 focus:outline-none"
                            data-value="5" title="Excellent">
                            <span
                                class="material-symbols-outlined text-[32px] text-gray-300 transition-colors">star</span>
                        </button>
                    </div>
                    <div class="flex justify-between text-xs text-text-sub px-4">
                        <span>Poor</span>
                        <span>Excellent</span>
                    </div>
                </div>

                <!-- Comment Input -->
                <div class="mb-2">
                    <label for="review-comment"
                        class="block text-sm font-medium text-text-main dark:text-gray-300 mb-2">Additional Comments
                        (Optional)</label>
                    <textarea id="review-comment" rows="3"
                        class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-[#1a2333] px-4 py-3 text-sm focus:border-primary focus:ring-1 focus:ring-primary resize-none"
                        placeholder="Share details about your experience..."></textarea>
                </div>
            </div>

            <!-- Footer -->
            <div
                class="px-6 py-4 border-t border-[#e7ebf3] dark:border-[#2a3447] bg-gray-50 dark:bg-[#1a2333]/50 flex justify-end gap-3">
                <button id="cancel-review-btn"
                    class="px-4 py-2 rounded-lg border border-gray-200 dark:border-gray-600 text-text-sub hover:bg-gray-100 dark:hover:bg-[#1f2937] font-medium transition-colors">
                    Cancel
                </button>
                <button id="submit-review-btn"
                    class="px-6 py-2 rounded-lg bg-primary text-white font-medium hover:bg-primary-dark shadow-lg shadow-primary/20 transition-all transform hover:scale-[1.02]">
                    Submit Review
                </button>
            </div>
        </div>
    </div>

    <script>
        // Global state
        const state = {
            currentUserId: <?= json_encode($userId) ?>,
            currentUserName: <?= json_encode($name) ?>,
            currentUserImage: <?= json_encode($profileImage) ?>,
            conversations: [],
            activeConversation: null,
            activeFilter: 'all',
            attachedFile: null, // Track attached file
            pendingInvite: {
                action: <?= json_encode($inviteAction) ?>,
                devId: <?= json_encode($inviteDevId) ?>,
                devName: <?= json_encode($inviteDevName) ?>,
                devImage: <?= json_encode($inviteDevImage) ?>,
                workType: <?= json_encode($inviteWorkType) ?>,
                workEmail: <?= json_encode($inviteWorkEmail) ?>,
                workDetails: <?= json_encode($inviteWorkDetails) ?>
            },
            lastMessageData: null // Track last message data to prevent unnecessary re-renders
        };

        // DOM Elements
        const elements = {
            conversationsList: document.getElementById('conversations-list'),
            conversationsLoading: document.getElementById('conversations-loading'),
            conversationsEmpty: document.getElementById('conversations-empty'),
            chatEmptyState: document.getElementById('chat-empty-state'),
            chatContainer: document.getElementById('chat-container'),
            chatHeader: document.getElementById('chat-header'),
            chatUserAvatar: document.getElementById('chat-user-avatar'),
            chatUserName: document.getElementById('chat-user-name'),
            chatUserStatus: document.getElementById('chat-user-status'),
            messagesArea: document.getElementById('messages-area'),
            messageInput: document.getElementById('message-input'),
            sendMessageBtn: document.getElementById('send-message-btn'),
            invitationActions: document.getElementById('invitation-actions'),
            invitationStatus: document.getElementById('invitation-status'),
            messageInputArea: document.getElementById('message-input-area'),
            acceptInvitationBtn: document.getElementById('accept-invitation-btn'),
            rejectInvitationBtn: document.getElementById('reject-invitation-btn'),
            conversationSearch: document.getElementById('conversation-search'),
            filterBtns: document.querySelectorAll('.filter-btn'),
            toast: document.getElementById('toast'),
            toastIcon: document.getElementById('toast-icon'),
            toastMessage: document.getElementById('toast-message'),
            attachFileBtn: document.getElementById('attach-file-btn'),
            fileInput: document.getElementById('file-input'),
            filePreviewArea: document.getElementById('file-preview-area'),
            fileName: document.getElementById('file-name'),
            fileSize: document.getElementById('file-size'),
            fileIcon: document.getElementById('file-icon'),
            removeFileBtn: document.getElementById('remove-file-btn')
        };

        // Utility Functions
        function showToast(message, type = 'success') {
            const iconMap = {
                success: 'check_circle',
                error: 'error',
                warning: 'warning',
                info: 'info'
            };
            const colorMap = {
                success: 'text-success',
                error: 'text-danger',
                warning: 'text-warning',
                info: 'text-primary'
            };

            elements.toastIcon.textContent = iconMap[type];
            elements.toastIcon.className = `material-symbols-outlined ${colorMap[type]}`;
            elements.toastMessage.textContent = message;

            elements.toast.classList.remove('translate-y-full', 'opacity-0');

            setTimeout(() => {
                elements.toast.classList.add('translate-y-full', 'opacity-0');
            }, 3000);
        }

        function formatTime(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diff = now - date;

            if (diff < 60000) return 'Just now';
            if (diff < 3600000) return Math.floor(diff / 60000) + 'm ago';
            if (diff < 86400000) return Math.floor(diff / 3600000) + 'h ago';
            if (diff < 604800000) return Math.floor(diff / 86400000) + 'd ago';

            return date.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric'
            });
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function getFileIcon(fileName) {
            const ext = fileName.split('.').pop().toLowerCase();
            const iconMap = {
                // Images
                'jpg': 'image',
                'jpeg': 'image',
                'png': 'image',
                'gif': 'image',
                'webp': 'image',
                'svg': 'image',
                // Documents
                'pdf': 'picture_as_pdf',
                'doc': 'description',
                'docx': 'description',
                'txt': 'article',
                // Spreadsheets
                'xls': 'table_chart',
                'xlsx': 'table_chart',
                'csv': 'table_chart',
                // Code
                'js': 'code',
                'html': 'code',
                'css': 'code',
                'php': 'code',
                'py': 'code',
                'java': 'code',
                // Archives
                'zip': 'folder_zip',
                'rar': 'folder_zip',
                '7z': 'folder_zip',
                // Audio/Video
                'mp3': 'audio_file',
                'wav': 'audio_file',
                'mp4': 'video_file',
                'avi': 'video_file',
                'mov': 'video_file'
            };
            return iconMap[ext] || 'description';
        }

        function showFilePreview(file) {
            elements.fileName.textContent = file.name;
            elements.fileSize.textContent = formatFileSize(file.size);
            elements.fileIcon.textContent = getFileIcon(file.name);
            elements.filePreviewArea.classList.remove('hidden');
        }

        function clearAttachedFile() {
            state.attachedFile = null;
            elements.fileInput.value = '';
            elements.filePreviewArea.classList.add('hidden');
        }

        function getInitials(name) {
            return name ? name.charAt(0).toUpperCase() : '?';
        }

        function renderAvatar(image, name, size = 'w-12 h-12', textSize = 'text-lg') {
            if (image && image !== 'null' && image.trim() !== '') {
                return `<img src="${image}" alt="${name}" class="${size} rounded-full object-cover border border-slate-200 dark:border-slate-700" />`;
            }
            return `<div class="${size} rounded-full bg-[#135bec] flex items-center justify-center text-white font-bold ${textSize} border border-slate-200 dark:border-slate-700 shadow-sm custom-avatar">${getInitials(name)}</div>`;
        }

        // API Functions
        async function fetchConversations() {
            try {
                const response = await fetch('api/get_conversations.php');
                const data = await response.json();

                if (data.success) {
                    state.conversations = data.conversations;
                    renderConversations();
                } else {
                    showToast(data.error || 'Failed to load conversations', 'error');
                }
            } catch (error) {
                console.error('Error fetching conversations:', error);
                showToast('Failed to load conversations', 'error');
            }
        }

        async function fetchMessages(userId, silent = false) {
            try {
                const response = await fetch(`api/get_messages.php?user_id=${userId}`);
                const data = await response.json();

                if (data.success) {
                    // Check if data has changed to avoid unnecessary re-renders
                    const currentData = JSON.stringify(data.messages);
                    if (state.lastMessageData === currentData) {
                        return; // No changes, skip render
                    }
                    state.lastMessageData = currentData;

                    renderMessages(data.messages, data.other_user);
                } else {
                    if (!silent) showToast(data.error || 'Failed to load messages', 'error');
                }
            } catch (error) {
                console.error('Error fetching messages:', error);
                if (!silent) showToast('Failed to load messages', 'error');
            }
        }

        async function sendMessage(receiverId, message, file = null) {
            try {
                const formData = new FormData();
                formData.append('receiver_id', receiverId);
                formData.append('message', message);

                if (file) {
                    formData.append('attachment', file);
                }

                const response = await csrfFetch('api/send_message.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    return true;
                } else {
                    showToast(data.error || 'Failed to send message', 'error');
                    return false;
                }
            } catch (error) {
                console.error('Error sending message:', error);
                showToast('Failed to send message', 'error');
                return false;
            }
        }

        async function sendInvitation(receiverId, workType, workEmail, workDetails) {
            try {
                const response = await csrfFetch('api/send_invitation.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        receiver_id: receiverId,
                        work_type: workType,
                        work_email: workEmail,
                        work_details: workDetails
                    })
                });
                const data = await response.json();

                if (data.success) {
                    showToast('Invitation sent successfully!', 'success');
                    fetchConversations();
                    return true;
                } else {
                    showToast(data.error || 'Failed to send invitation', 'error');
                    return false;
                }
            } catch (error) {
                console.error('Error sending invitation:', error);
                showToast('Failed to send invitation', 'error');
                return false;
            }
        }

        async function respondToInvitation(invitationId, response) {
            try {
                const res = await csrfFetch('api/respond_invitation.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        invitation_id: invitationId,
                        response: response
                    })
                });
                const data = await res.json();

                if (data.success) {
                    showToast(data.message, 'success');

                    // Update state immediately
                    if (state.activeConversation) {
                        state.activeConversation.invitationStatus = data.status;
                    }

                    // If accepted, update UI and show chat
                    if (response === 'accept' && data.sender_id) {
                        // Hide action buttons, show status and message input
                        elements.invitationActions.classList.add('hidden');
                        elements.invitationStatus.classList.remove('hidden');
                        elements.invitationStatus.innerHTML = `
                            <div class="flex items-center justify-center gap-2 py-2">
                                <div class="flex items-center gap-2 px-4 py-2 rounded-full bg-green-50 dark:bg-green-900/20 text-success">
                                    <span class="material-symbols-outlined">check_circle</span>
                                    <p class="text-sm font-medium">Invitation accepted! You can now chat with ${data.sender_name}.</p>
                                </div>
                            </div>
                        `;
                        elements.messageInputArea.classList.remove('hidden');

                        // Fetch conversations and messages
                        await fetchConversations();
                        fetchMessages(data.sender_id);
                    } else if (response === 'reject') {
                        // Show rejection status
                        elements.invitationActions.classList.add('hidden');
                        elements.invitationStatus.classList.remove('hidden');
                        elements.invitationStatus.innerHTML = `
                            <div class="flex items-center justify-center gap-2 py-2">
                                <div class="flex items-center gap-2 px-4 py-2 rounded-full bg-red-50 dark:bg-red-900/20 text-danger">
                                    <span class="material-symbols-outlined">cancel</span>
                                    <p class="text-sm font-medium">You declined this invitation.</p>
                                </div>
                            </div>
                        `;
                        elements.messageInputArea.classList.add('hidden');

                        // Refresh conversations list
                        await fetchConversations();
                    }

                    return true;
                } else {
                    showToast(data.error || 'Failed to respond', 'error');
                    return false;
                }
            } catch (error) {
                console.error('Error responding to invitation:', error);
                showToast('Failed to respond to invitation', 'error');
                return false;
            }
        }

        // Render Functions
        function renderConversations() {
            elements.conversationsLoading.classList.add('hidden');

            let filtered = state.conversations;

            // Apply filter
            if (state.activeFilter === 'invitations') {
                // Show only job invitations (client -> dev)
                filtered = filtered.filter(c => c.type.includes('invitation') && !c.is_message_request && !c.is_job_application);
            } else if (state.activeFilter === 'job_requests') {
                // Show only job applications (dev -> client)
                filtered = filtered.filter(c => c.type.includes('invitation') && c.is_job_application);
            } else if (state.activeFilter === 'messages') {
                // Show only message requests (and maybe normal messages if they started as requests?)
                // For now, let's keep "Request Messages" focused on Message Requests and general chats
                filtered = filtered.filter(c => c.type === 'message' || c.type.includes('message_request'));
            }

            // Apply search
            const searchTerm = elements.conversationSearch.value.toLowerCase();
            if (searchTerm) {
                filtered = filtered.filter(c =>
                    c.user_name.toLowerCase().includes(searchTerm) ||
                    c.last_message.toLowerCase().includes(searchTerm)
                );
            }

            if (filtered.length === 0) {
                elements.conversationsEmpty.classList.remove('hidden');
                elements.conversationsEmpty.classList.add('flex');
                // Clear existing conversations
                const existingItems = elements.conversationsList.querySelectorAll('.conversation-item');
                existingItems.forEach(item => item.remove());
                return;
            }

            elements.conversationsEmpty.classList.add('hidden');
            elements.conversationsEmpty.classList.remove('flex');

            // Clear existing items (except loading and empty states)
            const existingItems = elements.conversationsList.querySelectorAll('.conversation-item');
            existingItems.forEach(item => item.remove());

            filtered.forEach(conv => {
                const isActive = state.activeConversation && state.activeConversation.id === conv.id;
                const isInvitation = conv.type.includes('invitation') && !conv.is_message_request;
                const isJobApplication = conv.is_job_application;
                const isMessageRequest = conv.is_message_request || conv.type.includes('message_request');
                const isPending = conv.invitation_status === 'pending';
                const isRejected = conv.invitation_status === 'rejected';
                const isAccepted = conv.invitation_status === 'accepted';
                const isSent = conv.type === 'invitation_sent' || conv.type === 'message_request_sent';
                const isReceived = conv.type === 'invitation_received' || conv.type === 'message_request_received';
                const needsAction = isReceived && isPending; // Developer needs to respond


                let statusBadge = '';
                let actionIndicator = '';

                if (isInvitation) {
                    if (isPending) {
                        if (isSent) {
                            statusBadge = `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400"><span class="material-symbols-outlined text-[10px]">schedule</span>${isJobApplication ? 'Application Sent' : 'Invitation Sent'}</span>`;
                        } else {
                            statusBadge = `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 animate-pulse"><span class="material-symbols-outlined text-[10px]">priority_high</span>Action Required</span>`;
                            actionIndicator = `<div class="absolute -left-1 top-1/2 -translate-y-1/2 w-1 h-8 bg-gradient-to-b from-amber-400 to-orange-500 rounded-r-full"></div>`;
                        }
                    } else if (isAccepted) {
                        statusBadge = `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400"><span class="material-symbols-outlined text-[10px]">check_circle</span>Accepted</span>`;
                    } else if (isRejected) {
                        statusBadge = `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400"><span class="material-symbols-outlined text-[10px]">cancel</span>Declined</span>`;
                    }
                } else if (isMessageRequest) {
                    // Message Request badges
                    if (isPending) {
                        if (isSent) {
                            statusBadge = `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400"><span class="material-symbols-outlined text-[10px]">schedule</span>Request Pending</span>`;
                        } else {
                            statusBadge = `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-primary/10 text-primary dark:bg-primary/20 animate-pulse"><span class="material-symbols-outlined text-[10px]">chat</span>New Request</span>`;
                            actionIndicator = `<div class="absolute -left-1 top-1/2 -translate-y-1/2 w-1 h-8 bg-gradient-to-b from-primary to-blue-600 rounded-r-full"></div>`;
                        }
                    } else if (isAccepted) {
                        statusBadge = `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400"><span class="material-symbols-outlined text-[10px]">check_circle</span>Connected</span>`;
                    } else if (isRejected) {
                        statusBadge = `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400"><span class="material-symbols-outlined text-[10px]">cancel</span>Declined</span>`;
                    }
                }

                // Determine the icon for the conversation
                let typeIcon = '';
                if (isInvitation) {
                    if (isJobApplication) {
                        typeIcon = '<span class="absolute -bottom-1 -right-1 w-5 h-5 rounded-full bg-white dark:bg-card-dark border border-gray-200 dark:border-gray-700 flex items-center justify-center"><span class="material-symbols-outlined text-[12px] text-green-600">person_add</span></span>';
                    } else {
                        typeIcon = '<span class="absolute -bottom-1 -right-1 w-5 h-5 rounded-full bg-white dark:bg-card-dark border border-gray-200 dark:border-gray-700 flex items-center justify-center"><span class="material-symbols-outlined text-[12px] text-primary">work</span></span>';
                    }
                } else if (isMessageRequest) {
                    typeIcon = '<span class="absolute -bottom-1 -right-1 w-5 h-5 rounded-full bg-white dark:bg-card-dark border border-gray-200 dark:border-gray-700 flex items-center justify-center"><span class="material-symbols-outlined text-[12px] text-primary">chat</span></span>';
                }

                const html = `
                    <div class="conversation-item cursor-pointer p-4 border-b border-[#e7ebf3] dark:border-[#2a3447] hover:bg-gray-50 dark:hover:bg-[#1f2937] ${isActive ? 'active' : ''} ${needsAction ? 'bg-amber-50/50 dark:bg-amber-900/10' : ''} ${isMessageRequest && isPending && isReceived ? 'bg-blue-50/50 dark:bg-blue-900/10' : ''} relative"
                         data-conversation-id="${conv.id}"
                         data-user-id="${conv.user_id}"
                         data-user-name="${conv.user_name}"
                         data-user-image="${conv.user_image || ''}"
                         data-type="${conv.type}"
                         data-invitation-id="${conv.invitation_id || ''}"
                         data-invitation-status="${conv.invitation_status || ''}"
                         data-work-type="${conv.work_type || ''}"
                         data-work-details="${conv.work_details || ''}"
                         data-is-message-request="${isMessageRequest}"
                         data-is-job-application="${isJobApplication}"
                         data-proposed-rate="${conv.proposed_rate || ''}"
                         data-cover-letter="${(conv.cover_letter || '').replace(/"/g, '&quot;')}">
                        ${actionIndicator}
                        <div class="flex items-start gap-3">
                            <div class="relative flex-shrink-0">
                                ${renderAvatar(conv.user_image, conv.user_name, 'w-12 h-12', 'text-lg')}
                                ${conv.unread_count > 0 ? '<span class="absolute -top-1 -right-1 w-5 h-5 rounded-full bg-primary text-white text-[10px] font-bold flex items-center justify-center pulse-animation">' + conv.unread_count + '</span>' : ''}
                                ${typeIcon}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-2 mb-1">
                                    <h4 class="font-semibold text-text-main dark:text-white truncate">${conv.user_name}</h4>
                                    <span class="text-[11px] text-text-sub flex-shrink-0">${formatTime(conv.last_message_time)}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <p class="text-sm text-text-sub truncate flex-1">${conv.last_message}</p>
                                </div>
                                ${statusBadge ? '<div class="mt-2">' + statusBadge + '</div>' : ''}
                            </div>
                        </div>
                    </div>
                `;

                elements.conversationsList.insertAdjacentHTML('beforeend', html);
            });

            // Add click handlers
            document.querySelectorAll('.conversation-item').forEach(item => {
                item.addEventListener('click', () => handleConversationClick(item));
            });
        }

        function handleConversationClick(item) {
            const data = item.dataset;

            state.activeConversation = {
                id: data.conversationId,
                userId: parseInt(data.userId),
                userName: data.userName,
                userImage: data.userImage,
                type: data.type,
                invitationId: data.invitationId ? parseInt(data.invitationId) : null,
                invitationStatus: data.invitationStatus,
                workType: data.workType,
                workDetails: data.workDetails,
                isMessageRequest: data.isMessageRequest === 'true',
                isJobApplication: data.isJobApplication === 'true',
                proposedRate: data.proposedRate || null,
                coverLetter: data.coverLetter ? data.coverLetter.replace(/&quot;/g, '"') : null
            };

            // Reset last message data to force a fresh render for the new conversation
            state.lastMessageData = null;

            // Update active state in list
            document.querySelectorAll('.conversation-item').forEach(i => i.classList.remove('active'));
            item.classList.add('active');

            // Show chat container
            elements.chatEmptyState.classList.add('hidden');
            elements.chatContainer.classList.remove('hidden');
            elements.chatContainer.classList.add('flex');

            // Update header
            elements.chatUserName.textContent = data.userName;
            elements.chatUserAvatar.innerHTML = renderAvatar(data.userImage, data.userName, 'w-full h-full', 'text-xl');

            // Handle based on type
            if (data.type.includes('message_request')) {
                openMessageRequest();
            } else if (data.type.includes('invitation')) {
                openInvitation();
            } else {
                openChat(data.userId, data.userName);
            }
        }

        function openInvitation() {
            const conv = state.activeConversation;
            const isReceived = conv.type === 'invitation_received';
            const isPending = conv.invitationStatus === 'pending';
            const isAccepted = conv.invitationStatus === 'accepted';
            const isRejected = conv.invitationStatus === 'rejected';
            const isJobApplication = conv.workType === 'Job Application';

            // Build different invitation views for sender vs receiver
            let invitationHtml = '';

            // Determine header texts based on whether it's a job application or regular invitation
            const headerTitle = isJobApplication ? 'Job Application' : 'Job Invitation';
            const headerSubtitle = isJobApplication ?
                (isReceived ? 'Someone applied to your job posting' : 'Your application to a job posting') :
                (isReceived ? 'New opportunity received' : 'Invitation you sent');
            const headerIcon = isJobApplication ? 'person_add' : 'work';
            const roleLabel = isJobApplication ?
                (isReceived ? 'Applicant / Developer' : 'Employer / Client') :
                (isReceived ? 'Client / Employer' : 'Developer / Freelancer');
            const acceptButtonText = isJobApplication ? 'Accept Application' : 'Accept Invitation';

            if (isReceived) {
                // Receiver view - they received the invitation/application
                invitationHtml = `
                    <div class="flex justify-center items-start py-6 px-4">
                        <div class="invitation-card max-w-xl w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-card-dark shadow-sm overflow-hidden">
                            
                            <!-- Compact Header with Status -->
                            <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        <div class="w-12 h-12 rounded-xl ${isJobApplication ? 'bg-green-100 dark:bg-green-900/30' : 'bg-primary/10'} flex items-center justify-center">
                                            <span class="material-symbols-outlined ${isJobApplication ? 'text-green-600 dark:text-green-400' : 'text-primary'} text-2xl">${headerIcon}</span>
                                        </div>
                                        <div>
                                            <h3 class="text-lg font-bold text-slate-900 dark:text-white">${headerTitle}</h3>
                                            <p class="text-sm text-slate-500 dark:text-slate-400">${headerSubtitle}</p>
                                        </div>
                                    </div>
                                    ${isPending ? '<span class="px-3 py-1.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 flex items-center gap-1"><span class="material-symbols-outlined text-sm">schedule</span>Pending</span>' : ''}
                                </div>
                            </div>

                            <!-- Client Info Section -->
                            <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50">
                                <div class="flex items-center gap-4">
                                    <div class="relative">
                                        ${renderAvatar(conv.userImage, conv.userName, 'w-14 h-14', 'text-xl')}
                                        <div class="absolute bottom-0 right-0 w-4 h-4 rounded-full bg-green-500 border-2 border-white dark:border-slate-800"></div>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <p class="font-bold text-slate-900 dark:text-white">${conv.userName}</p>
                                            <span class="material-symbols-outlined text-primary text-[18px]" style="font-variation-settings: 'FILL' 1;">verified</span>
                                        </div>
                                        <p class="text-sm text-slate-500 dark:text-slate-400">${roleLabel}</p>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">${isJobApplication ? 'Applied by' : 'Invited by'}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Project Details -->
                            <div class="px-6 py-5 space-y-4">
                                <div class="grid grid-cols-1 gap-4">
                                    ${isJobApplication ? `
                                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="material-symbols-outlined text-green-600 text-lg">currency_rupee</span>
                                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Proposed Rate</span>
                                        </div>
                                        <p class="text-base font-semibold text-slate-900 dark:text-white">₹${conv.proposedRate ? Number(conv.proposedRate).toLocaleString('en-IN') : 'Not specified'}</p>
                                    </div>
                                    
                                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="material-symbols-outlined text-primary text-lg">mail</span>
                                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Cover Letter</span>
                                        </div>
                                        <p class="text-sm text-slate-700 dark:text-slate-300 leading-relaxed whitespace-pre-wrap">${conv.coverLetter || 'No cover letter provided'}</p>
                                    </div>
                                    ` : `
                                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="material-symbols-outlined text-primary text-lg">category</span>
                                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Project Type</span>
                                        </div>
                                        <p class="text-base font-semibold text-slate-900 dark:text-white">${conv.workType || 'Not specified'}</p>
                                    </div>
                                    
                                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="material-symbols-outlined text-primary text-lg">description</span>
                                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Project Description</span>
                                        </div>
                                        <p class="text-sm text-slate-700 dark:text-slate-300 leading-relaxed">${conv.workDetails || 'No details provided'}</p>
                                    </div>
                                    `}
                                </div>
                            </div>

                            ${isPending ? `
                                <!-- Action Buttons -->
                                <div class="px-6 py-5 bg-gradient-to-r from-slate-50 to-slate-100 dark:from-slate-800/80 dark:to-slate-800/50 border-t border-slate-100 dark:border-slate-700">
                                    <div class="flex items-center gap-2 mb-4">
                                        <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center">
                                            <span class="material-symbols-outlined text-primary text-lg">touch_app</span>
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900 dark:text-white">Respond to ${isJobApplication ? 'Application' : 'Invitation'}</p>
                                            <p class="text-xs text-slate-500 dark:text-slate-400">Your response will be sent to the ${isJobApplication ? 'applicant' : 'client'}</p>
                                        </div>
                                    </div>
                                    <div class="flex gap-3">
                                        <button id="card-reject-btn" class="group flex-1 px-5 py-3 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold hover:bg-red-50 hover:border-red-200 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:border-red-800 dark:hover:text-red-400 transition-all duration-200 flex items-center justify-center gap-2 text-sm">
                                            <span class="material-symbols-outlined text-[18px]">close</span>
                                            Decline
                                        </button>
                                        <button id="card-accept-btn" class="group flex-1 px-5 py-3 rounded-lg bg-primary text-white font-semibold hover:bg-primary-dark transition-all duration-200 shadow-md shadow-primary/25 hover:shadow-lg hover:shadow-primary/30 flex items-center justify-center gap-2 text-sm">
                                            <span class="material-symbols-outlined text-[18px]">check</span>
                                            ${acceptButtonText}
                                        </button>
                                    </div>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
            } else {
                // Client (sender) view - they sent the invitation
                let statusBadge = '';
                let statusIcon = '';
                let statusBgClass = '';

                if (isPending) {
                    statusBadge = `<span class="px-3 py-1.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 flex items-center gap-1"><span class="material-symbols-outlined text-sm">schedule</span>Awaiting Response</span>`;
                    statusIcon = 'hourglass_top';
                    statusBgClass = 'bg-blue-50 dark:bg-blue-900/20 border-blue-100 dark:border-blue-800';
                } else if (isAccepted) {
                    statusBadge = `<span class="px-3 py-1.5 rounded-full text-xs font-semibold bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 flex items-center gap-1"><span class="material-symbols-outlined text-sm">check_circle</span>Accepted</span>`;
                    statusIcon = 'celebration';
                    statusBgClass = 'bg-green-50 dark:bg-green-900/20 border-green-100 dark:border-green-800';
                } else if (isRejected) {
                    statusBadge = `<span class="px-3 py-1.5 rounded-full text-xs font-semibold bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 flex items-center gap-1"><span class="material-symbols-outlined text-sm">cancel</span>Declined</span>`;
                    statusIcon = 'sentiment_dissatisfied';
                    statusBgClass = 'bg-red-50 dark:bg-red-900/20 border-red-100 dark:border-red-800';
                }

                let statusMessage = '';
                if (isPending) {
                    statusMessage = `
                        <div class="p-4 rounded-xl ${statusBgClass} border flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center flex-shrink-0">
                                <span class="material-symbols-outlined text-blue-600 dark:text-blue-400">${statusIcon}</span>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-slate-900 dark:text-white">Waiting for response</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">${conv.userName} hasn't responded yet. We'll notify you when they do.</p>
                            </div>
                        </div>
                    `;
                } else if (isAccepted) {
                    statusMessage = `
                        <div class="p-4 rounded-xl ${statusBgClass} border flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center flex-shrink-0">
                                <span class="material-symbols-outlined text-green-600 dark:text-green-400">${statusIcon}</span>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-slate-900 dark:text-white">${isJobApplication ? 'Application' : 'Invitation'} Accepted!</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">${conv.userName} accepted. You can now start chatting below.</p>
                            </div>
                        </div>
                    `;
                } else if (isRejected) {
                    statusMessage = `
                        <div class="p-4 rounded-xl ${statusBgClass} border flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center flex-shrink-0">
                                <span class="material-symbols-outlined text-red-600 dark:text-red-400">${statusIcon}</span>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-slate-900 dark:text-white">${isJobApplication ? 'Application' : 'Invitation'} Declined</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">${conv.userName} has declined this ${isJobApplication ? 'application' : 'invitation'}.</p>
                            </div>
                        </div>
                    `;
                }

                invitationHtml = `
                    <div class="flex justify-center items-start py-6 px-4">
                        <div class="invitation-card max-w-xl w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-card-dark shadow-sm overflow-hidden">
                            
                            <!-- Compact Header with Status -->
                            <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center">
                                            <span class="material-symbols-outlined text-primary text-2xl">send</span>
                                        </div>
                                        <div>
                                            <h3 class="text-lg font-bold text-slate-900 dark:text-white">${isJobApplication ? 'Application Sent' : 'Invitation Sent'}</h3>
                                            <p class="text-sm text-slate-500 dark:text-slate-400">${isJobApplication ? 'Your job application details' : 'Your job invitation details'}</p>
                                        </div>
                                    </div>
                                    ${statusBadge}
                                </div>
                            </div>

                            <!-- Developer Info Section -->
                            <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50">
                                <div class="flex items-center gap-4">
                                    <div class="relative">
                                        ${renderAvatar(conv.userImage, conv.userName, 'w-14 h-14', 'text-xl')}
                                        <div class="absolute bottom-0 right-0 w-4 h-4 rounded-full bg-green-500 border-2 border-white dark:border-slate-800"></div>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <p class="font-bold text-slate-900 dark:text-white">${conv.userName}</p>
                                            <span class="material-symbols-outlined text-primary text-[18px]" style="font-variation-settings: 'FILL' 1;">verified</span>
                                        </div>
                                        <p class="text-sm text-slate-500 dark:text-slate-400">${roleLabel}</p>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Sent to</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Project Details -->
                            <div class="px-6 py-5 space-y-4">
                                <div class="grid grid-cols-1 gap-4">
                                    ${isJobApplication ? `
                                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="material-symbols-outlined text-green-600 text-lg">currency_rupee</span>
                                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Proposed Rate</span>
                                        </div>
                                        <p class="text-base font-semibold text-slate-900 dark:text-white">₹${conv.proposedRate ? Number(conv.proposedRate).toLocaleString('en-IN') : 'Not specified'}</p>
                                    </div>
                                    
                                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="material-symbols-outlined text-primary text-lg">mail</span>
                                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Cover Letter</span>
                                        </div>
                                        <p class="text-sm text-slate-700 dark:text-slate-300 leading-relaxed whitespace-pre-wrap">${conv.coverLetter || 'No cover letter provided'}</p>
                                    </div>
                                    ` : `
                                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="material-symbols-outlined text-primary text-lg">category</span>
                                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Project Type</span>
                                        </div>
                                        <p class="text-base font-semibold text-slate-900 dark:text-white">${conv.workType || 'Not specified'}</p>
                                    </div>
                                    
                                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="material-symbols-outlined text-primary text-lg">description</span>
                                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Project Description</span>
                                        </div>
                                        <p class="text-sm text-slate-700 dark:text-slate-300 leading-relaxed">${conv.workDetails || 'No details provided'}</p>
                                    </div>
                                    `}
                                </div>

                                <!-- Status Section -->
                                <div class="pt-4">
                                    ${statusMessage}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }

            elements.messagesArea.innerHTML = invitationHtml;

            // Handle action buttons based on status and type
            // Always hide the bottom action bar - buttons are now inside the card
            elements.invitationActions.classList.add('hidden');

            if (isReceived && isPending) {
                // Pending invitation - hide message input and status bar
                elements.invitationStatus.classList.add('hidden');
                elements.messageInputArea.classList.add('hidden');

                // Add event listeners for the card buttons
                setTimeout(() => {
                    const cardAcceptBtn = document.getElementById('card-accept-btn');
                    const cardRejectBtn = document.getElementById('card-reject-btn');

                    if (cardAcceptBtn) {
                        cardAcceptBtn.addEventListener('click', async () => {
                            if (!state.activeConversation?.invitationId) return;

                            cardAcceptBtn.disabled = true;
                            cardRejectBtn.disabled = true;
                            cardAcceptBtn.innerHTML = '<span class="material-symbols-outlined text-[20px] animate-spin">autorenew</span> Accepting...';

                            await respondToInvitation(state.activeConversation.invitationId, 'accept');

                            cardAcceptBtn.disabled = false;
                            cardRejectBtn.disabled = false;
                        });
                    }

                    if (cardRejectBtn) {
                        cardRejectBtn.addEventListener('click', async () => {
                            if (!state.activeConversation?.invitationId) return;

                            cardAcceptBtn.disabled = true;
                            cardRejectBtn.disabled = true;
                            cardRejectBtn.innerHTML = '<span class="material-symbols-outlined text-[20px] animate-spin">autorenew</span> Declining...';

                            await respondToInvitation(state.activeConversation.invitationId, 'reject');

                            cardAcceptBtn.disabled = false;
                            cardRejectBtn.disabled = false;
                        });
                    }
                }, 0);
            } else if (isAccepted) {
                // Accepted - go directly to chat without status bar
                elements.invitationStatus.classList.add('hidden');
                elements.messageInputArea.classList.remove('hidden');

                // Load messages
                fetchMessages(conv.userId);
            } else if (isRejected) {
                // Show rejected status
                elements.invitationStatus.classList.remove('hidden');
                elements.invitationStatus.innerHTML = `
                    <div class="flex items-center justify-center gap-2 py-2">
                        <div class="flex items-center gap-2 px-4 py-2 rounded-full bg-red-50 dark:bg-red-900/20 text-danger">
                            <span class="material-symbols-outlined">cancel</span>
                            <p class="text-sm font-medium">${isReceived ? 'You declined this invitation' : 'This invitation was declined by ' + conv.userName}</p>
                        </div>
                    </div>
                `;
                elements.messageInputArea.classList.add('hidden');
            } else {
                // Sent invitation - pending (for client)
                elements.invitationStatus.classList.remove('hidden');
                elements.invitationStatus.innerHTML = `
                    <div class="flex items-center justify-center gap-2 py-2">
                        <div class="flex items-center gap-2 px-4 py-2 rounded-full bg-blue-50 dark:bg-blue-900/20 text-primary">
                            <span class="material-symbols-outlined animate-spin">hourglass_top</span>
                            <p class="text-sm font-medium">Waiting for ${conv.userName} to respond...</p>
                        </div>
                    </div>
                `;
                elements.messageInputArea.classList.add('hidden');
            }
        }

        // NEW: Handle Message Request display
        function openMessageRequest() {
            const conv = state.activeConversation;
            const isReceived = conv.type === 'message_request_received';
            const isPending = conv.invitationStatus === 'pending';
            const isAccepted = conv.invitationStatus === 'accepted';
            const isRejected = conv.invitationStatus === 'rejected';

            // Build message request view
            let messageRequestHtml = '';

            if (isReceived) {
                // Developer (receiver) view - they received the message request
                messageRequestHtml = `
                    <div class="flex justify-center items-start py-6 px-4">
                        <div class="invitation-card max-w-xl w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-card-dark shadow-sm overflow-hidden">
                            
                            <!-- Compact Header with Status -->
                            <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center">
                                            <span class="material-symbols-outlined text-primary text-2xl">chat</span>
                                        </div>
                                        <div>
                                            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Message Request</h3>
                                            <p class="text-sm text-slate-500 dark:text-slate-400">Someone wants to connect with you</p>
                                        </div>
                                    </div>
                                    ${isPending ? '<span class="px-3 py-1.5 rounded-full text-xs font-semibold bg-primary/10 text-primary dark:bg-primary/20 flex items-center gap-1"><span class="material-symbols-outlined text-sm">schedule</span>Pending</span>' : ''}
                                    ${isAccepted ? '<span class="px-3 py-1.5 rounded-full text-xs font-semibold bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 flex items-center gap-1"><span class="material-symbols-outlined text-sm">check_circle</span>Connected</span>' : ''}
                                    ${isRejected ? '<span class="px-3 py-1.5 rounded-full text-xs font-semibold bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 flex items-center gap-1"><span class="material-symbols-outlined text-sm">cancel</span>Declined</span>' : ''}
                                </div>
                            </div>

                            <!-- Sender Info Section -->
                            <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50">
                                <div class="flex items-center gap-4">
                                    <div class="relative">
                                        ${renderAvatar(conv.userImage, conv.userName, 'w-14 h-14', 'text-xl')}
                                        <div class="absolute bottom-0 right-0 w-4 h-4 rounded-full bg-green-500 border-2 border-white dark:border-slate-800"></div>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <p class="font-bold text-slate-900 dark:text-white">${conv.userName}</p>
                                            <span class="material-symbols-outlined text-primary text-[18px]" style="font-variation-settings: 'FILL' 1;">verified</span>
                                        </div>
                                        <p class="text-sm text-slate-500 dark:text-slate-400">Wants to connect with you</p>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">From</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Message Content -->
                            <div class="px-6 py-5 space-y-4">
                                <div class="grid grid-cols-1 gap-4">
                                    <div class="p-4 rounded-xl bg-primary/5 dark:bg-primary/10 border border-primary/20">
                                        <div class="flex items-center gap-2 mb-3">
                                            <span class="material-symbols-outlined text-primary text-lg">message</span>
                                            <span class="text-xs font-semibold text-primary uppercase tracking-wider">Their Message</span>
                                        </div>
                                        <p class="text-sm text-slate-700 dark:text-slate-300 leading-relaxed whitespace-pre-wrap">${conv.workDetails || 'No message provided'}</p>
                                    </div>
                                </div>
                            </div>

                            ${isPending ? `
                                <!-- Action Buttons -->
                                <div class="px-6 py-5 bg-gradient-to-r from-slate-50 to-slate-100 dark:from-slate-800/80 dark:to-slate-800/50 border-t border-slate-100 dark:border-slate-700">
                                    <div class="flex items-center gap-2 mb-4">
                                        <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center">
                                            <span class="material-symbols-outlined text-primary text-lg">touch_app</span>
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900 dark:text-white">Respond to Request</p>
                                            <p class="text-xs text-slate-500 dark:text-slate-400">Accept to start a conversation</p>
                                        </div>
                                    </div>
                                    <div class="flex gap-3">
                                        <button id="card-reject-btn" class="group flex-1 px-5 py-3 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold hover:bg-red-50 hover:border-red-200 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:border-red-800 dark:hover:text-red-400 transition-all duration-200 flex items-center justify-center gap-2 text-sm">
                                            <span class="material-symbols-outlined text-[18px]">close</span>
                                            Decline
                                        </button>
                                        <button id="card-accept-btn" class="group flex-1 px-5 py-3 rounded-lg bg-primary text-white font-semibold hover:bg-primary-dark transition-all duration-200 shadow-md shadow-primary/25 hover:shadow-lg hover:shadow-primary/30 flex items-center justify-center gap-2 text-sm">
                                            <span class="material-symbols-outlined text-[18px]">check</span>
                                            Accept & Connect
                                        </button>
                                    </div>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
            } else {
                // Sender view - they sent the message request
                let statusBadge = '';
                let statusIcon = '';
                let statusBgClass = '';

                if (isPending) {
                    statusBadge = `<span class="px-3 py-1.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 flex items-center gap-1"><span class="material-symbols-outlined text-sm">schedule</span>Awaiting Response</span>`;
                    statusIcon = 'hourglass_top';
                    statusBgClass = 'bg-blue-50 dark:bg-blue-900/20 border-blue-100 dark:border-blue-800';
                } else if (isAccepted) {
                    statusBadge = `<span class="px-3 py-1.5 rounded-full text-xs font-semibold bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 flex items-center gap-1"><span class="material-symbols-outlined text-sm">check_circle</span>Connected</span>`;
                    statusIcon = 'celebration';
                    statusBgClass = 'bg-green-50 dark:bg-green-900/20 border-green-100 dark:border-green-800';
                } else if (isRejected) {
                    statusBadge = `<span class="px-3 py-1.5 rounded-full text-xs font-semibold bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 flex items-center gap-1"><span class="material-symbols-outlined text-sm">cancel</span>Declined</span>`;
                    statusIcon = 'sentiment_dissatisfied';
                    statusBgClass = 'bg-red-50 dark:bg-red-900/20 border-red-100 dark:border-red-800';
                }

                let statusMessage = '';
                if (isPending) {
                    statusMessage = `
                        <div class="p-4 rounded-xl ${statusBgClass} border flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center flex-shrink-0">
                                <span class="material-symbols-outlined text-blue-600 dark:text-blue-400">${statusIcon}</span>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-slate-900 dark:text-white">Waiting for response</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">${conv.userName} hasn't responded yet. We'll notify you when they do.</p>
                            </div>
                        </div>
                    `;
                } else if (isAccepted) {
                    statusMessage = `
                        <div class="p-4 rounded-xl ${statusBgClass} border flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center flex-shrink-0">
                                <span class="material-symbols-outlined text-green-600 dark:text-green-400">${statusIcon}</span>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-slate-900 dark:text-white">Request Accepted!</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">${conv.userName} accepted your request. You can now start chatting below.</p>
                            </div>
                        </div>
                    `;
                } else if (isRejected) {
                    statusMessage = `
                        <div class="p-4 rounded-xl ${statusBgClass} border flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center flex-shrink-0">
                                <span class="material-symbols-outlined text-red-600 dark:text-red-400">${statusIcon}</span>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-slate-900 dark:text-white">Request Declined</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">${conv.userName} has declined your message request.</p>
                            </div>
                        </div>
                    `;
                }

                messageRequestHtml = `
                    <div class="flex justify-center items-start py-6 px-4">
                        <div class="invitation-card max-w-xl w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-card-dark shadow-sm overflow-hidden">
                            
                            <!-- Compact Header with Status -->
                            <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center">
                                            <span class="material-symbols-outlined text-primary text-2xl">send</span>
                                        </div>
                                        <div>
                                            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Request Sent</h3>
                                            <p class="text-sm text-slate-500 dark:text-slate-400">Your message request details</p>
                                        </div>
                                    </div>
                                    ${statusBadge}
                                </div>
                            </div>

                            <!-- Receiver Info Section -->
                            <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50">
                                <div class="flex items-center gap-4">
                                    <div class="relative">
                                        ${renderAvatar(conv.userImage, conv.userName, 'w-14 h-14', 'text-xl')}
                                        <div class="absolute bottom-0 right-0 w-4 h-4 rounded-full bg-green-500 border-2 border-white dark:border-slate-800"></div>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <p class="font-bold text-slate-900 dark:text-white">${conv.userName}</p>
                                            <span class="material-symbols-outlined text-primary text-[18px]" style="font-variation-settings: 'FILL' 1;">verified</span>
                                        </div>
                                        <p class="text-sm text-slate-500 dark:text-slate-400">Developer / Freelancer</p>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Sent to</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Message Content -->
                            <div class="px-6 py-5 space-y-4">
                                <div class="grid grid-cols-1 gap-4">
                                    <div class="p-4 rounded-xl bg-primary/5 dark:bg-primary/10 border border-primary/20">
                                        <div class="flex items-center gap-2 mb-3">
                                            <span class="material-symbols-outlined text-primary text-lg">message</span>
                                            <span class="text-xs font-semibold text-primary uppercase tracking-wider">Your Message</span>
                                        </div>
                                        <p class="text-sm text-slate-700 dark:text-slate-300 leading-relaxed whitespace-pre-wrap">${conv.workDetails || 'No message provided'}</p>
                                    </div>
                                </div>

                                <!-- Status Section -->
                                <div class="pt-4">
                                    ${statusMessage}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }

            elements.messagesArea.innerHTML = messageRequestHtml;

            // Handle action buttons based on status and type
            elements.invitationActions.classList.add('hidden');

            if (isReceived && isPending) {
                elements.invitationStatus.classList.add('hidden');
                elements.messageInputArea.classList.add('hidden');

                // Add event listeners for the card buttons
                setTimeout(() => {
                    const cardAcceptBtn = document.getElementById('card-accept-btn');
                    const cardRejectBtn = document.getElementById('card-reject-btn');

                    if (cardAcceptBtn) {
                        cardAcceptBtn.addEventListener('click', async () => {
                            if (!state.activeConversation?.invitationId) return;

                            cardAcceptBtn.disabled = true;
                            cardRejectBtn.disabled = true;
                            cardAcceptBtn.innerHTML = '<span class="material-symbols-outlined text-[20px] animate-spin">autorenew</span> Connecting...';

                            await respondToInvitation(state.activeConversation.invitationId, 'accept');

                            cardAcceptBtn.disabled = false;
                            cardRejectBtn.disabled = false;
                        });
                    }

                    if (cardRejectBtn) {
                        cardRejectBtn.addEventListener('click', async () => {
                            if (!state.activeConversation?.invitationId) return;

                            cardAcceptBtn.disabled = true;
                            cardRejectBtn.disabled = true;
                            cardRejectBtn.innerHTML = '<span class="material-symbols-outlined text-[20px] animate-spin">autorenew</span> Declining...';

                            await respondToInvitation(state.activeConversation.invitationId, 'reject');

                            cardAcceptBtn.disabled = false;
                            cardRejectBtn.disabled = false;
                        });
                    }
                }, 0);
            } else if (isAccepted) {
                // Accepted - go directly to chat without status bar
                elements.invitationStatus.classList.add('hidden');
                elements.messageInputArea.classList.remove('hidden');
                fetchMessages(conv.userId);
            } else if (isRejected) {
                elements.invitationStatus.classList.remove('hidden');
                elements.invitationStatus.innerHTML = `
                    <div class="flex items-center justify-center gap-2 py-2">
                        <div class="flex items-center gap-2 px-4 py-2 rounded-full bg-red-50 dark:bg-red-900/20 text-danger">
                            <span class="material-symbols-outlined">cancel</span>
                            <p class="text-sm font-medium">${isReceived ? 'You declined this request' : 'This request was declined by ' + conv.userName}</p>
                        </div>
                    </div>
                `;
                elements.messageInputArea.classList.add('hidden');
            } else {
                // Sent request - pending (for sender)
                elements.invitationStatus.classList.remove('hidden');
                elements.invitationStatus.innerHTML = `
                    <div class="flex items-center justify-center gap-2 py-2">
                        <div class="flex items-center gap-2 px-4 py-2 rounded-full bg-blue-50 dark:bg-blue-900/20 text-primary">
                            <span class="material-symbols-outlined animate-spin">hourglass_top</span>
                            <p class="text-sm font-medium">Waiting for ${conv.userName} to respond...</p>
                        </div>
                    </div>
                `;
                elements.messageInputArea.classList.add('hidden');
            }
        }

        function openChat(userId, userName) {
            elements.invitationActions.classList.add('hidden');
            elements.invitationStatus.classList.add('hidden');
            elements.messageInputArea.classList.remove('hidden');

            fetchMessages(userId);
        }

        function renderMessages(messages, otherUser) {
            if (messages.length === 0) {
                elements.messagesArea.innerHTML = `
                    <div class="flex flex-col items-center justify-center h-full text-center">
                        <div class="w-16 h-16 rounded-full bg-gray-100 dark:bg-[#1f2937] flex items-center justify-center mb-4">
                            <span class="material-symbols-outlined text-[32px] text-gray-400">chat</span>
                        </div>
                        <p class="text-text-sub">No messages yet. Start the conversation!</p>
                    </div>
                `;
                return;
            }

            let html = '';
            let lastDate = '';

            messages.forEach(msg => {
                const msgDate = new Date(msg.created_at).toLocaleDateString();

                // Date separator
                if (msgDate !== lastDate) {
                    html += `
                        <div class="flex justify-center my-4">
                            <span class="px-3 py-1 rounded-full bg-gray-100 dark:bg-[#1f2937] text-xs text-text-sub">${msgDate}</span>
                        </div>
                    `;
                    lastDate = msgDate;
                }

                const time = new Date(msg.created_at).toLocaleTimeString('en-US', {
                    hour: '2-digit',
                    minute: '2-digit'
                });

                // Generate attachment HTML if present
                let attachmentHtml = '';
                if (msg.attachment_path && msg.attachment_name) {
                    const fileIcon = getFileIcon(msg.attachment_name);
                    const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(
                        msg.attachment_name.split('.').pop().toLowerCase()
                    );

                    if (isImage) {
                        attachmentHtml = `
                            <a href="${msg.attachment_path}" target="_blank" class="block mt-2">
                                <img src="${msg.attachment_path}" alt="${msg.attachment_name}" class="max-w-full rounded-lg max-h-48 object-cover hover:opacity-90 transition-opacity" />
                            </a>
                        `;
                    } else {
                        attachmentHtml = `
                            <a href="${msg.attachment_path}" download="${msg.attachment_name}" class="flex items-center gap-2 mt-2 p-2 rounded-lg ${msg.is_mine ? 'bg-white/10 hover:bg-white/20' : 'bg-gray-100 dark:bg-[#1a2333] hover:bg-gray-200 dark:hover:bg-[#252f42]'} transition-colors">
                                <span class="material-symbols-outlined text-[20px]">${fileIcon}</span>
                                <span class="text-xs truncate max-w-[150px]">${msg.attachment_name}</span>
                                <span class="material-symbols-outlined text-[16px] ml-auto">download</span>
                            </a>
                        `;
                    }
                }

                // Message content (may be empty if only attachment)
                const messageContent = msg.message ? `<p class="text-sm">${msg.message}</p>` : '';

                if (msg.is_mine) {
                    let ticks = '';
                    if (msg.is_read) {
                        // Double tick (Seen) - Blue
                        ticks = '<span class="material-symbols-outlined text-[16px] text-primary ml-1" title="Seen" style="font-size: 16px;">done_all</span>';
                    } else {
                        // Single tick (Sent) - Grey
                        ticks = '<span class="material-symbols-outlined text-[16px] text-gray-400 ml-1" title="Sent" style="font-size: 16px;">check</span>';
                    }

                    html += `
                        <div class="flex justify-end message-bubble">
                            <div class="max-w-[70%]">
                                <div class="bg-primary text-white rounded-2xl rounded-tr-sm px-4 py-3 shadow-sm">
                                    ${messageContent}
                                    ${attachmentHtml}
                                </div>
                                <div class="flex items-center justify-end mt-1 gap-1">
                                    <p class="text-[10px] text-text-sub">${time}</p>
                                    ${ticks}
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    html += `
                        <div class="flex gap-3 message-bubble">
                            <div class="flex-shrink-0 mt-auto">
                                ${renderAvatar(msg.sender_image, msg.sender_name, 'w-8 h-8', 'text-sm')}
                            </div>
                            <div class="max-w-[70%]">
                                <div class="bg-white dark:bg-card-dark border border-[#e7ebf3] dark:border-[#2a3447] rounded-2xl rounded-tl-sm px-4 py-3 shadow-sm">
                                    ${messageContent ? `<p class="text-sm text-text-main dark:text-white">${msg.message}</p>` : ''}
                                    ${attachmentHtml}
                                </div>
                                <p class="text-[10px] text-text-sub mt-1">${time}</p>
                            </div>
                        </div>
                    `;
                }
            });

            // Capture scroll state before updating
            const previousScrollHeight = elements.messagesArea.scrollHeight;
            const previousScrollTop = elements.messagesArea.scrollTop;
            const clientHeight = elements.messagesArea.clientHeight;
            // Check if user is near bottom (within 50px) or if content was shorter than container
            const isNearBottom = (previousScrollHeight - previousScrollTop - clientHeight < 50) || (previousScrollHeight <= clientHeight);

            elements.messagesArea.innerHTML = html;

            // Restore scroll position or scroll to bottom
            if (isNearBottom) {
                elements.messagesArea.scrollTop = elements.messagesArea.scrollHeight;
            } else {
                elements.messagesArea.scrollTop = previousScrollTop;
            }
        }

        // Event Handlers
        function setupEventHandlers() {
            // Filter buttons
            elements.filterBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    elements.filterBtns.forEach(b => {
                        b.classList.remove('bg-primary', 'text-white');
                        b.classList.add('bg-gray-100', 'dark:bg-[#1f2937]', 'text-text-sub', 'dark:text-gray-400');
                    });
                    btn.classList.add('bg-primary', 'text-white');
                    btn.classList.remove('bg-gray-100', 'dark:bg-[#1f2937]', 'text-text-sub', 'dark:text-gray-400');

                    state.activeFilter = btn.dataset.filter;
                    renderConversations();
                });
            });

            // Search
            elements.conversationSearch.addEventListener('input', () => {
                renderConversations();
            });

            // Send message
            elements.sendMessageBtn.addEventListener('click', async () => {
                const message = elements.messageInput.value.trim();
                const file = state.attachedFile;

                // Allow sending if there's a message OR a file
                if ((!message && !file) || !state.activeConversation) return;

                elements.sendMessageBtn.disabled = true;

                const success = await sendMessage(state.activeConversation.userId, message || '', file);

                if (success) {
                    elements.messageInput.value = '';
                    elements.messageInput.style.height = 'auto';
                    clearAttachedFile(); // Clear the attached file
                    fetchMessages(state.activeConversation.userId);
                    fetchConversations();
                }

                elements.sendMessageBtn.disabled = false;
            });

            // Enter to send
            elements.messageInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    elements.sendMessageBtn.click();
                }
            });

            // Auto-resize textarea
            elements.messageInput.addEventListener('input', () => {
                elements.messageInput.style.height = 'auto';
                elements.messageInput.style.height = Math.min(elements.messageInput.scrollHeight, 120) + 'px';
            });

            // Accept invitation
            elements.acceptInvitationBtn.addEventListener('click', async () => {
                if (!state.activeConversation?.invitationId) return;

                elements.acceptInvitationBtn.disabled = true;
                elements.rejectInvitationBtn.disabled = true;

                await respondToInvitation(state.activeConversation.invitationId, 'accept');

                elements.acceptInvitationBtn.disabled = false;
                elements.rejectInvitationBtn.disabled = false;
            });

            // Reject invitation
            elements.rejectInvitationBtn.addEventListener('click', async () => {
                if (!state.activeConversation?.invitationId) return;

                elements.acceptInvitationBtn.disabled = true;
                elements.rejectInvitationBtn.disabled = true;

                await respondToInvitation(state.activeConversation.invitationId, 'reject');

                elements.acceptInvitationBtn.disabled = false;
                elements.rejectInvitationBtn.disabled = false;
            });

            // Close dropdown when clicking outside - REMOVED TO CONSOLIDATE ABOVE
            /* 
            document.addEventListener('click', (e) => {
                const dropdown = document.getElementById('profile-dropdown');
                const container = dropdown?.parentElement;
                if (dropdown && !dropdown.classList.contains('hidden') && !container?.contains(e.target)) {
                    dropdown.classList.add('hidden');
                }
            });
            */

            // File attachment button click
            elements.attachFileBtn?.addEventListener('click', () => {
                elements.fileInput?.click();
            });

            // Chat Options Dropdown Toggle
            const chatOptionsBtn = document.getElementById('chat-options-btn');
            const chatOptionsDropdown = document.getElementById('chat-options-dropdown');
            const viewReviewsBtn = document.getElementById('view-reviews-btn');

            if (chatOptionsBtn && chatOptionsDropdown) {
                chatOptionsBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    chatOptionsDropdown.classList.toggle('hidden');
                });

                viewReviewsBtn?.addEventListener('click', () => {
                    chatOptionsDropdown.classList.add('hidden');
                    if (state.activeConversation) {
                        // Open review modal
                        const modal = document.getElementById('review-modal');
                        const reviewUser = document.getElementById('review-user-name');
                        if (modal && reviewUser) {
                            reviewUser.textContent = state.activeConversation.userName;
                            modal.classList.remove('hidden');
                        }
                    } else {
                        showToast('No active conversation', 'warning');
                    }
                });
            }

            // Review Modal Logic
            const reviewModal = document.getElementById('review-modal');
            const closeReviewBtn = document.getElementById('close-review-modal');
            const cancelReviewBtn = document.getElementById('cancel-review-btn');
            const submitReviewBtn = document.getElementById('submit-review-btn');
            const stars = document.querySelectorAll('.star-rating-btn');
            let currentRating = 0;

            if (reviewModal) {
                // Close modal handlers
                const closeModal = () => {
                    reviewModal.classList.add('hidden');
                    // Reset form
                    currentRating = 0;
                    document.getElementById('review-comment').value = '';
                    updateStars(0);
                };

                closeReviewBtn?.addEventListener('click', closeModal);
                cancelReviewBtn?.addEventListener('click', closeModal);

                // Star rating logic
                function updateStars(rating) {
                    stars.forEach(star => {
                        const starValue = parseInt(star.dataset.value);
                        const icon = star.querySelector('span');
                        if (starValue <= rating) {
                            icon.classList.add('text-yellow-400', 'fill-current');
                            icon.classList.remove('text-gray-300');
                            icon.style.fontVariationSettings = "'FILL' 1";
                        } else {
                            icon.classList.remove('text-yellow-400', 'fill-current');
                            icon.classList.add('text-gray-300');
                            icon.style.fontVariationSettings = "'FILL' 0";
                        }
                    });
                }

                stars.forEach(star => {
                    star.addEventListener('mouseenter', () => {
                        updateStars(parseInt(star.dataset.value));
                    });

                    star.addEventListener('mouseleave', () => {
                        updateStars(currentRating);
                    });

                    star.addEventListener('click', () => {
                        currentRating = parseInt(star.dataset.value);
                        updateStars(currentRating);
                    });
                });

                // Submit handler
                submitReviewBtn?.addEventListener('click', async () => {
                    if (currentRating === 0) {
                        showToast('Please select a rating', 'warning');
                        return;
                    }

                    const comment = document.getElementById('review-comment').value;

                    try {
                        const response = await csrfFetch('api/submit_review.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                developer_id: state.activeConversation.userId,
                                rating: currentRating,
                                comment: comment
                            })
                        });
                        
                        const data = await response.json();
                        if (data.success) {
                            showToast(`Review submitted: ${currentRating} stars`, 'success');
                            closeModal();
                        } else {
                            showToast(data.error || 'Failed to submit review', 'error');
                        }
                    } catch (error) {
                        console.error('Error submitting review:', error);
                        showToast('Failed to submit review', 'error');
                    }
                });
            }

            // Close dropdowns when clicking outside
            document.addEventListener('click', (e) => {
                const profileDropdown = document.getElementById('profile-dropdown');
                const profileContainer = profileDropdown?.parentElement;

                if (profileDropdown && !profileDropdown.classList.contains('hidden') && !profileContainer?.contains(e.target)) {
                    profileDropdown.classList.add('hidden');
                }

                if (chatOptionsDropdown && !chatOptionsDropdown.classList.contains('hidden') && !chatOptionsBtn?.contains(e.target)) {
                    chatOptionsDropdown.classList.add('hidden');
                }
            });

            // File input change handler with 2 MB size validation
            elements.fileInput?.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (!file) return;

                const maxSize = 2 * 1024 * 1024; // 2 MB in bytes
                if (file.size > maxSize) {
                    showToast('File size exceeds 2 MB limit. Please select a smaller file.', 'error');
                    elements.fileInput.value = ''; // Clear the file input
                    return;
                }

                // File is valid (less than 2 MB) - store it and show preview
                state.attachedFile = file;
                showFilePreview(file);
                showToast(`File "${file.name}" attached successfully!`, 'success');
            });

            // Remove file button click
            elements.removeFileBtn?.addEventListener('click', () => {
                clearAttachedFile();
                showToast('File removed', 'info');
            });
        }

        // Handle pending invite from URL parameters
        async function handlePendingInvite() {
            if (state.pendingInvite.action && state.pendingInvite.devId) {
                const success = await sendInvitation(
                    state.pendingInvite.devId,
                    state.pendingInvite.workType,
                    state.pendingInvite.workEmail,
                    state.pendingInvite.workDetails
                );

                // Clear URL parameters
                if (success) {
                    window.history.replaceState({}, document.title, 'messages.php');
                }
            }
        }

        // Handle URL filter parameter
        function handleUrlFilterParam() {
            const urlParams = new URLSearchParams(window.location.search);
            const filterParam = urlParams.get('filter');

            if (filterParam && ['all', 'invitations', 'messages'].includes(filterParam)) {
                state.activeFilter = filterParam;

                // Update filter button states
                elements.filterBtns.forEach(btn => {
                    const filter = btn.dataset.filter;
                    if (filter === filterParam) {
                        btn.classList.remove('bg-gray-100', 'dark:bg-[#1f2937]', 'text-text-sub', 'dark:text-gray-400');
                        btn.classList.add('bg-primary', 'text-white');
                    } else {
                        btn.classList.remove('bg-primary', 'text-white');
                        btn.classList.add('bg-gray-100', 'dark:bg-[#1f2937]', 'text-text-sub', 'dark:text-gray-400');
                    }
                });
            }
        }

        // Initialize
        async function init() {
            setupEventHandlers();
            handleUrlFilterParam(); // Handle URL filter parameter first
            await handlePendingInvite();
            await fetchConversations();

            // Auto-open conversation if user_id in URL
            const urlParams = new URLSearchParams(window.location.search);
            const openUserId = urlParams.get('user_id');
            if (openUserId && !state.activeConversation) {
                const convItem = document.querySelector(`.conversation-item[data-user-id="${openUserId}"]`);
                if (convItem) {
                    convItem.click();
                    // Optional: remove user_id from URL to prevent reopening on generic refresh
                    // window.history.replaceState({}, document.title, window.location.pathname);
                }
            }

            // Refresh conversations every 30 seconds
            setInterval(fetchConversations, 30000);

            // Poll for new messages if chat is active (every 3 seconds)
            setInterval(() => {
                if (state.activeConversation && !document.hidden) {
                    // Only poll if it's a regular chat or accepted invitation/request
                    const status = state.activeConversation.invitationStatus;
                    const type = state.activeConversation.type;

                    if (
                        (!type.includes('invitation') && !type.includes('message_request')) || // Regular chat
                        status === 'accepted' // Accepted invite/request
                    ) {
                        // We use a silent fetch that doesn't show loading indicators if desired, 
                        // but reusing fetchMessages is fine for now as long as it doesn't flicker too much.
                        // To avoid replacing the whole DOM every time if nothing changed, we could optimize,
                        // but for this task, simply calling fetchMessages is the first step.
                        // However, fetchMessages calls renderMessages which replaces innerHTML.
                        // This might cause scroll jumping or input focus loss if not careful.
                        // But since input is outside message area, it should be okay.
                        // Ideally we should check for changes.
                        fetchMessages(state.activeConversation.userId, true);
                    }
                }
            }, 3000);
        }

        // Start the app
        document.addEventListener('DOMContentLoaded', init);
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