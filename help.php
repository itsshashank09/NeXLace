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
                        class="text-sm font-bold text-primary dark:text-primary hover:text-primary dark:hover:text-primary transition-colors">
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
        <main class="flex-grow py-12">
            <div class="mx-auto max-w-[1000px] px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h1 class="text-4xl font-bold tracking-tight text-text-main dark:text-white mb-4">Detailed Success
                        Guides</h1>
                    <p class="text-lg text-text-sub max-w-2xl mx-auto">Master the NeXLace platform with our
                        comprehensive step-by-step guides for developers and clients alike.</p>
                </div>
                <div class="max-w-xl mx-auto mb-16 relative">
                    <span
                        class="absolute left-4 top-1/2 -translate-y-1/2 material-symbols-outlined text-gray-400">search</span>
                    <input
                        class="w-full h-12 rounded-full border border-gray-300 dark:border-gray-700 bg-white dark:bg-card-dark pl-12 pr-4 text-base focus:border-primary focus:ring-primary shadow-sm"
                        placeholder="Search for help topics..." type="text" />
                </div>
                <div class="space-y-6">
                    <div
                        class="bg-white dark:bg-card-dark rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden transition-all duration-300 hover:shadow-md">
                        <input checked="" class="hidden peer" id="guide1" type="checkbox" />
                        <label
                            class="flex items-center justify-between p-6 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
                            for="guide1">
                            <div class="flex items-center gap-4">
                                <div
                                    class="w-12 h-12 rounded-lg bg-blue-100 dark:bg-blue-900/30 text-primary flex items-center justify-center">
                                    <span class="material-symbols-outlined text-3xl">account_circle</span>
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-text-main dark:text-white">1. Creating a Developer
                                        Profile</h3>
                                    <p class="text-sm text-text-sub">Stand out from the crowd with a professional
                                        presence.</p>
                                </div>
                            </div>
                            <span
                                class="material-symbols-outlined text-gray-400 transition-transform duration-300 peer-checked:rotate-180">expand_more</span>
                        </label>
                        <div
                            class="grid grid-rows-[0fr] peer-checked:grid-rows-[1fr] transition-[grid-template-rows] duration-300 ease-out">
                            <div class="overflow-hidden">
                                <div class="p-8 pt-0 border-t border-gray-100 dark:border-gray-800">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-6">
                                        <div class="space-y-6">
                                            <div class="flex gap-4">
                                                <span class="step-number">1</span>
                                                <div>
                                                    <h4 class="font-bold mb-1">Professional Photo</h4>
                                                    <p class="text-sm text-text-sub">Upload a high-quality headshot.
                                                        Profiles with clear photos receive 40% more invitations.</p>
                                                </div>
                                            </div>
                                            <div class="flex gap-4">
                                                <span class="step-number">2</span>
                                                <div>
                                                    <h4 class="font-bold mb-1">Compelling Bio</h4>
                                                    <p class="text-sm text-text-sub">Explain what problems you solve.
                                                        Highlight your unique approach and years of experience.</p>
                                                </div>
                                            </div>
                                            <div class="flex gap-4">
                                                <span class="step-number">3</span>
                                                <div>
                                                    <h4 class="font-bold mb-1">Skill Tags</h4>
                                                    <p class="text-sm text-text-sub">Add up to 15 relevant technical
                                                        skills.
                                                        Be specific (e.g., "React.js" instead of just "JS").</p>
                                                </div>
                                            </div>
                                            <div class="flex gap-4">
                                                <span class="step-number">4</span>
                                                <div>
                                                    <h4 class="font-bold mb-1">Portfolio Highlights</h4>
                                                    <p class="text-sm text-text-sub">Link your best projects with
                                                        screenshots and a brief description of your role.</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="pro-tip h-fit">
                                            <div class="flex items-center gap-2 mb-3 text-primary font-bold">
                                                <span class="material-symbols-outlined text-[20px]">lightbulb</span>
                                                <span>Pro-Tip: Profile SEO</span>
                                            </div>
                                            <p class="text-sm text-text-sub leading-relaxed">
                                                Use keywords in your bio that clients are likely to search for. Instead
                                                of
                                                "I build sites," try "Senior Full-Stack Developer specializing in
                                                Scalable
                                                SaaS Architecture."
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        class="bg-white dark:bg-card-dark rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden transition-all duration-300 hover:shadow-md">
                        <input class="hidden peer" id="guide2" type="checkbox" />
                        <label
                            class="flex items-center justify-between p-6 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
                            for="guide2">
                            <div class="flex items-center gap-4">
                                <div
                                    class="w-12 h-12 rounded-lg bg-green-100 dark:bg-green-900/30 text-green-600 flex items-center justify-center">
                                    <span class="material-symbols-outlined text-3xl">group_add</span>
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-text-main dark:text-white">2. Hiring Talent</h3>
                                    <p class="text-sm text-text-sub">Find the perfect expert for your project needs.</p>
                                </div>
                            </div>
                            <span
                                class="material-symbols-outlined text-gray-400 transition-transform duration-300 peer-checked:rotate-180">expand_more</span>
                        </label>
                        <div
                            class="grid grid-rows-[0fr] peer-checked:grid-rows-[1fr] transition-[grid-template-rows] duration-300 ease-out">
                            <div class="overflow-hidden">
                                <div class="p-8 pt-0 border-t border-gray-100 dark:border-gray-800">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-6">
                                        <div class="space-y-6">
                                            <div class="flex gap-4">
                                                <span class="step-number">1</span>
                                                <div>
                                                    <h4 class="font-bold mb-1">Targeted Search</h4>
                                                    <p class="text-sm text-text-sub">Use filters to narrow down
                                                        candidates
                                                        by hourly rate, rating, and location.</p>
                                                </div>
                                            </div>
                                            <div class="flex gap-4">
                                                <span class="step-number">2</span>
                                                <div>
                                                    <h4 class="font-bold mb-1">Effective Interviewing</h4>
                                                    <p class="text-sm text-text-sub">Schedule a short video call to
                                                        assess
                                                        communication skills and technical fit.</p>
                                                </div>
                                            </div>
                                            <div class="flex gap-4">
                                                <span class="step-number">3</span>
                                                <div>
                                                    <h4 class="font-bold mb-1">Secure Escrow</h4>
                                                    <p class="text-sm text-text-sub">Always fund milestones through
                                                        NeXLace
                                                        Escrow before work begins for 100% protection.</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="pro-tip h-fit">
                                            <div class="flex items-center gap-2 mb-3 text-primary font-bold">
                                                <span class="material-symbols-outlined text-[20px]">verified</span>
                                                <span>Pro-Tip: Speed Matters</span>
                                            </div>
                                            <p class="text-sm text-text-sub leading-relaxed">
                                                Top developers are often hired within 48 hours. When you find a good
                                                match,
                                                reach out quickly to secure their availability.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        class="bg-white dark:bg-card-dark rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden transition-all duration-300 hover:shadow-md">
                        <input class="hidden peer" id="guide3" type="checkbox" />
                        <label
                            class="flex items-center justify-between p-6 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
                            for="guide3">
                            <div class="flex items-center gap-4">
                                <div
                                    class="w-12 h-12 rounded-lg bg-purple-100 dark:bg-purple-900/30 text-purple-600 flex items-center justify-center">
                                    <span class="material-symbols-outlined text-3xl">post_add</span>
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-text-main dark:text-white">3. Posting a Job</h3>
                                    <p class="text-sm text-text-sub">Create listings that attract high-quality
                                        proposals.</p>
                                </div>
                            </div>
                            <span
                                class="material-symbols-outlined text-gray-400 transition-transform duration-300 peer-checked:rotate-180">expand_more</span>
                        </label>
                        <div
                            class="grid grid-rows-[0fr] peer-checked:grid-rows-[1fr] transition-[grid-template-rows] duration-300 ease-out">
                            <div class="overflow-hidden">
                                <div class="p-8 pt-0 border-t border-gray-100 dark:border-gray-800">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-6">
                                        <div class="space-y-6">
                                            <div class="flex gap-4">
                                                <span class="step-number">1</span>
                                                <div>
                                                    <h4 class="font-bold mb-1">Clear Title</h4>
                                                    <p class="text-sm text-text-sub">Be specific. Instead of "Help with
                                                        CSS," use "Responsive Header Fix for E-commerce React Site."</p>
                                                </div>
                                            </div>
                                            <div class="flex gap-4">
                                                <span class="step-number">2</span>
                                                <div>
                                                    <h4 class="font-bold mb-1">Detailed Description</h4>
                                                    <p class="text-sm text-text-sub">Outline goals, deliverables, and
                                                        technical requirements. Attach mockups if available.</p>
                                                </div>
                                            </div>
                                            <div class="flex gap-4">
                                                <span class="step-number">3</span>
                                                <div>
                                                    <h4 class="font-bold mb-1">Realistic Budget &amp; Timeline</h4>
                                                    <p class="text-sm text-text-sub">Research market rates to ensure you
                                                        attract experienced professionals.</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="pro-tip h-fit">
                                            <div class="flex items-center gap-2 mb-3 text-primary font-bold">
                                                <span class="material-symbols-outlined text-[20px]">psychology</span>
                                                <span>Pro-Tip: Screening Questions</span>
                                            </div>
                                            <p class="text-sm text-text-sub leading-relaxed">
                                                Add 2-3 specific questions to the job post. This helps filter out
                                                automated
                                                proposals and reveals the developer's attention to detail.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        class="bg-white dark:bg-card-dark rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden transition-all duration-300 hover:shadow-md">
                        <input class="hidden peer" id="guide4" type="checkbox" />
                        <label
                            class="flex items-center justify-between p-6 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
                            for="guide4">
                            <div class="flex items-center gap-4">
                                <div
                                    class="w-12 h-12 rounded-lg bg-orange-100 dark:bg-orange-900/30 text-orange-600 flex items-center justify-center">
                                    <span class="material-symbols-outlined text-3xl">search_check</span>
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-text-main dark:text-white">4. Finding Work</h3>
                                    <p class="text-sm text-text-sub">How to win projects and grow your freelance career.
                                    </p>
                                </div>
                            </div>
                            <span
                                class="material-symbols-outlined text-gray-400 transition-transform duration-300 peer-checked:rotate-180">expand_more</span>
                        </label>
                        <div
                            class="grid grid-rows-[0fr] peer-checked:grid-rows-[1fr] transition-[grid-template-rows] duration-300 ease-out">
                            <div class="overflow-hidden">
                                <div class="p-8 pt-0 border-t border-gray-100 dark:border-gray-800">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-6">
                                        <div class="space-y-6">
                                            <div class="flex gap-4">
                                                <span class="step-number">1</span>
                                                <div>
                                                    <h4 class="font-bold mb-1">Smart Filtering</h4>
                                                    <p class="text-sm text-text-sub">Set up 'Saved Searches' for your
                                                        core
                                                        stack to be notified immediately of new postings.</p>
                                                </div>
                                            </div>
                                            <div class="flex gap-4">
                                                <span class="step-number">2</span>
                                                <div>
                                                    <h4 class="font-bold mb-1">Custom Proposals</h4>
                                                    <p class="text-sm text-text-sub">Don't copy-paste. Address the
                                                        client's
                                                        specific problem in the first two sentences.</p>
                                                </div>
                                            </div>
                                            <div class="flex gap-4">
                                                <span class="step-number">3</span>
                                                <div>
                                                    <h4 class="font-bold mb-1">Direct Communication</h4>
                                                    <p class="text-sm text-text-sub">Be responsive. Quick replies during
                                                        the
                                                        proposal phase build trust before the contract starts.</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="pro-tip h-fit">
                                            <div class="flex items-center gap-2 mb-3 text-primary font-bold">
                                                <span class="material-symbols-outlined text-[20px]">stars</span>
                                                <span>Pro-Tip: The First 20 Words</span>
                                            </div>
                                            <p class="text-sm text-text-sub leading-relaxed">
                                                Clients only see the first few words of your proposal in their inbox.
                                                Make
                                                them count by showing immediate value or asking a smart question.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-16 bg-primary rounded-2xl p-10 text-white text-center">
                    <h3 class="text-2xl font-bold mb-4">Still need assistance?</h3>
                    <p class="mb-8 opacity-90 max-w-xl mx-auto text-lg leading-relaxed">Our support team is available
                        24/7 to help you with any technical or account-related questions.</p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <button
                            class="bg-white text-primary px-8 py-3 rounded-full font-bold hover:bg-opacity-90 transition-all">Chat
                            with Support</button>
                        <button
                            class="border border-white/40 text-white px-8 py-3 rounded-full font-bold hover:bg-white/10 transition-all">Contact
                            us by Email</button>
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