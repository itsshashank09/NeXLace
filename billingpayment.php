<?php
require_once 'includes/auth_helper.php';
requireAuth();

// Include database connection
require_once 'config/database.php';
require_once 'config/security_headers.php';

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
                    <h1 class="text-3xl font-bold tracking-tight text-text-main dark:text-white">Billing &amp; Payments
                    </h1>
                    <p class="text-text-sub mt-2">Manage your earnings, payment methods, and billing history.</p>
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
                            <a class="flex items-center gap-3 px-4 py-2.5 text-sm font-bold rounded-lg bg-primary/10 text-primary"
                                href="billingpay.php">
                                <span class="material-symbols-outlined text-[20px]">credit_card</span>
                                Billing Methods
                            </a>
                        </nav>
                    </aside>
                    <div class="flex-1 space-y-8">
                        <div
                            class="rounded-xl border border-[#e7ebf3] dark:border-[#2a3447] bg-white dark:bg-card-dark shadow-sm overflow-hidden">
                            <div class="p-6 sm:p-8 flex flex-col sm:flex-row items-center justify-between gap-6">
                                <div class="flex items-center gap-6">
                                    <div
                                        class="h-16 w-16 rounded-2xl bg-primary/10 flex items-center justify-center text-primary">
                                        <span
                                            class="material-symbols-outlined text-[32px]">account_balance_wallet</span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-text-sub">Available Balance</p>
                                        <h2 class="text-4xl font-black text-text-main dark:text-white mt-1">$0.00</h2>
                                    </div>
                                </div>
                                <button
                                    class="w-full sm:w-auto px-8 py-3 bg-primary hover:bg-primary-dark text-white font-bold rounded-xl shadow-lg shadow-primary/20 transition-all active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
                                    disabled="">
                                    Withdraw Funds
                                </button>
                            </div>
                        </div>
                        <div
                            class="rounded-xl border border-[#e7ebf3] dark:border-[#2a3447] bg-white dark:bg-card-dark shadow-sm">
                            <div
                                class="border-b border-[#e7ebf3] px-6 py-4 dark:border-[#2a3447] flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-bold text-text-main dark:text-white">Payment Methods</h3>
                                    <p class="text-sm text-text-sub">Add and manage your payment accounts.</p>
                                </div>
                                <button
                                    class="flex items-center gap-2 text-sm font-bold text-primary hover:text-primary-dark transition-colors">
                                    <span class="material-symbols-outlined text-[20px]">add_circle</span>
                                    Add New Method
                                </button>
                            </div>
                            <div class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div
                                        class="group relative rounded-xl border border-[#e7ebf3] dark:border-[#2a3447] p-4 flex items-center gap-4 hover:border-primary/50 transition-all cursor-pointer">
                                        <div
                                            class="h-12 w-12 rounded-lg bg-gray-50 dark:bg-[#1a2333] flex items-center justify-center border border-[#e7ebf3] dark:border-[#2a3447]">
                                            <span class="material-symbols-outlined text-text-sub">payments</span>
                                        </div>
                                        <div class="flex-grow">
                                            <p class="font-bold text-text-main dark:text-white">Visa ending in 4242</p>
                                            <p class="text-xs text-text-sub">Expires 12/26 • Primary</p>
                                        </div>
                                        <button class="text-text-sub hover:text-red-500 transition-colors">
                                            <span class="material-symbols-outlined text-[20px]">delete</span>
                                        </button>
                                    </div>
                                    <div
                                        class="group relative rounded-xl border border-[#e7ebf3] dark:border-[#2a3447] p-4 flex items-center gap-4 hover:border-primary/50 transition-all cursor-pointer">
                                        <div
                                            class="h-12 w-12 rounded-lg bg-gray-50 dark:bg-[#1a2333] flex items-center justify-center border border-[#e7ebf3] dark:border-[#2a3447]">
                                            <span class="material-symbols-outlined text-text-sub">account_balance</span>
                                        </div>
                                        <div class="flex-grow">
                                            <p class="font-bold text-text-main dark:text-white">Chase Bank (**** 8890)
                                            </p>
                                            <p class="text-xs text-text-sub">Checking Account</p>
                                        </div>
                                        <button class="text-text-sub hover:text-red-500 transition-colors">
                                            <span class="material-symbols-outlined text-[20px]">delete</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div
                            class="rounded-xl border border-[#e7ebf3] dark:border-[#2a3447] bg-white dark:bg-card-dark shadow-sm">
                            <div class="border-b border-[#e7ebf3] px-6 py-4 dark:border-[#2a3447]">
                                <h3 class="text-lg font-bold text-text-main dark:text-white">Billing History</h3>
                                <p class="text-sm text-text-sub">Review your recent transactions and invoices.</p>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-sm">
                                    <thead>
                                        <tr
                                            class="border-b border-[#e7ebf3] dark:border-[#2a3447] bg-gray-50 dark:bg-[#1a2333]">
                                            <th class="px-6 py-4 font-bold text-text-main dark:text-white">Date</th>
                                            <th class="px-6 py-4 font-bold text-text-main dark:text-white">Description
                                            </th>
                                            <th class="px-6 py-4 font-bold text-text-main dark:text-white text-right">
                                                Amount</th>
                                            <th class="px-6 py-4 font-bold text-text-main dark:text-white">Status</th>
                                            <th class="px-6 py-4"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-[#e7ebf3] dark:divide-[#2a3447]">
                                        <tr class="hover:bg-gray-50 dark:hover:bg-[#1f2937] transition-colors">
                                            <td class="px-6 py-4 text-text-sub">Oct 24, 2024</td>
                                            <td class="px-6 py-4">
                                                <p class="font-medium text-text-main dark:text-white">React Landing Page
                                                    Development</p>
                                                <p class="text-xs text-text-sub">Project ID: #88210</p>
                                            </td>
                                            <td class="px-6 py-4 text-right font-bold text-green-600">+$1,200.00</td>
                                            <td class="px-6 py-4">
                                                <span
                                                    class="inline-flex items-center rounded-full bg-green-50 dark:bg-green-500/10 px-2 py-1 text-xs font-medium text-green-700 dark:text-green-400 ring-1 ring-inset ring-green-600/20">Completed</span>
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <button
                                                    class="text-primary hover:underline font-medium">Invoice</button>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-gray-50 dark:hover:bg-[#1f2937] transition-colors">
                                            <td class="px-6 py-4 text-text-sub">Oct 15, 2024</td>
                                            <td class="px-6 py-4">
                                                <p class="font-medium text-text-main dark:text-white">NeXLace Pro
                                                    Subscription</p>
                                                <p class="text-xs text-text-sub">Monthly plan</p>
                                            </td>
                                            <td class="px-6 py-4 text-right font-bold text-text-main dark:text-white">
                                                -$29.00</td>
                                            <td class="px-6 py-4">
                                                <span
                                                    class="inline-flex items-center rounded-full bg-green-50 dark:bg-green-500/10 px-2 py-1 text-xs font-medium text-green-700 dark:text-green-400 ring-1 ring-inset ring-green-600/20">Completed</span>
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <button
                                                    class="text-primary hover:underline font-medium">Invoice</button>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-gray-50 dark:hover:bg-[#1f2937] transition-colors">
                                            <td class="px-6 py-4 text-text-sub">Oct 02, 2024</td>
                                            <td class="px-6 py-4">
                                                <p class="font-medium text-text-main dark:text-white">Withdrawal to Bank
                                                    (**** 8890)</p>
                                                <p class="text-xs text-text-sub">Reference: WD-9921</p>
                                            </td>
                                            <td class="px-6 py-4 text-right font-bold text-text-main dark:text-white">
                                                -$850.00</td>
                                            <td class="px-6 py-4">
                                                <span
                                                    class="inline-flex items-center rounded-full bg-blue-50 dark:bg-blue-500/10 px-2 py-1 text-xs font-medium text-blue-700 dark:text-blue-400 ring-1 ring-inset ring-blue-600/20">Processing</span>
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <button
                                                    class="text-primary hover:underline font-medium">Details</button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="border-t border-[#e7ebf3] px-6 py-4 dark:border-[#2a3447] text-center">
                                <button
                                    class="text-sm font-bold text-text-sub hover:text-primary transition-colors">View
                                    All History</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

    </div>

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