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
    <title>NeXLace - Post a Job</title>
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
            </div>
        </header>
        <main class="flex-grow py-8 bg-background-light dark:bg-background-dark">
            <div class="mx-auto max-w-[800px] px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between mb-8">
                    <h1 class="text-3xl font-bold tracking-tight text-text-main dark:text-white">Post a new job</h1>
                </div>
                <div
                    class="bg-card-light dark:bg-card-dark rounded-xl border border-[#e7ebf3] dark:border-[#2a3447] shadow-sm overflow-hidden">
                    <form action="api/post_job.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <div class="p-6 md:p-8 space-y-8">
                            <?php if (isset($_GET['error'])): ?>
                                <div
                                    class="rounded-xl border border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800 p-4">
                                    <p class="text-red-700 dark:text-red-400 font-medium flex items-center gap-2">
                                        <span class="material-symbols-outlined">error</span>
                                        Failed to post job. Please try again.
                                    </p>
                                </div>
                            <?php endif; ?>
                            <div>
                                <label class="block text-sm font-bold text-text-main dark:text-white mb-2"
                                    for="job-title">Job Title <span class="text-red-500">*</span></label>
                                <p class="text-xs text-text-sub mb-3">A clear title helps attract the right freelancers.
                                </p>
                                <input
                                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-[#1a2333] px-4 py-2.5 text-sm text-text-main dark:text-white placeholder-gray-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all outline-none"
                                    id="job-title" name="job_title"
                                    placeholder="e.g. Senior React Developer for Fintech App" type="text" required />
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-text-main dark:text-white mb-2"
                                    for="job-details">Job Details <span class="text-red-500">*</span></label>
                                <p class="text-xs text-text-sub mb-3">Describe your project requirements, deliverables,
                                    and
                                    goals.</p>
                                <div class="relative">
                                    <textarea
                                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-[#1a2333] px-4 py-3 text-sm text-text-main dark:text-white placeholder-gray-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all outline-none min-h-[200px]"
                                        id="job-details" name="job_details" placeholder="Describe your project here..."
                                        maxlength="5000" required></textarea>
                                </div>
                                <div class="flex justify-between items-center mt-2">
                                    <span id="char-count" class="text-xs text-text-sub">0/5000 characters</span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-text-main dark:text-white mb-2">Skills
                                    Required</label>
                                <input
                                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-[#1a2333] px-4 py-2.5 text-sm text-text-main dark:text-white placeholder-gray-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all outline-none"
                                    id="skills-required" name="skills_required"
                                    placeholder="e.g. React, Node.js, TypeScript (comma separated)" />
                                <p class="text-xs text-text-sub mt-2">Separate skills with commas</p>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-4">
                                <div>
                                    <label
                                        class="block text-sm font-bold text-text-main dark:text-white mb-2">Category</label>
                                    <select name="category"
                                        class="w-full appearance-none rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-[#1a2333] px-4 py-2.5 text-sm text-text-main dark:text-white focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all outline-none">
                                        <option value="Web Development">Web Development</option>
                                        <option value="Mobile Apps">Mobile Apps</option>
                                        <option value="UI/UX Design">UI/UX Design</option>
                                        <option value="Scripts & Utilities">Scripts & Utilities</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-text-main dark:text-white mb-2">Project
                                        Type</label>
                                    <select name="project_type"
                                        class="w-full appearance-none rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-[#1a2333] px-4 py-2.5 text-sm text-text-main dark:text-white focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all outline-none">
                                        <option value="Fixed Price">Fixed Price</option>
                                        <option value="Hourly">Hourly</option>
                                    </select>
                                </div>
                                <div>
                                    <label
                                        class="block text-sm font-bold text-text-main dark:text-white mb-2">Experience
                                        Level</label>
                                    <select name="experience_level"
                                        class="w-full appearance-none rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-[#1a2333] px-4 py-2.5 text-sm text-text-main dark:text-white focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all outline-none">
                                        <option value="Entry Level">Entry Level</option>
                                        <option value="Intermediate">Intermediate</option>
                                        <option value="Expert">Expert</option>
                                    </select>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 pt-4">
                                <div>
                                    <label class="block text-sm font-bold text-text-main dark:text-white mb-2">Estimated
                                        Budget</label>
                                    <div class="relative">
                                        <span
                                            class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 font-semibold">₹</span>
                                        <input
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-[#1a2333] pl-8 pr-12 py-2.5 text-sm text-text-main dark:text-white placeholder-gray-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all outline-none"
                                            name="estimated_budget" placeholder="1000" type="number" />
                                        <span
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-medium text-gray-400">INR</span>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-text-main dark:text-white mb-2">Project
                                        Timeline</label>
                                    <div class="relative">
                                        <select name="project_timeline"
                                            class="w-full appearance-none rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-[#1a2333] px-4 py-2.5 text-sm text-text-main dark:text-white focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all outline-none">
                                            <option value="">Select duration</option>
                                            <option value="Less than 1 month">Less than 1 month</option>
                                            <option value="1 to 3 months">1 to 3 months</option>
                                            <option value="3 to 6 months">3 to 6 months</option>
                                            <option value="More than 6 months">More than 6 months</option>
                                        </select>
                                        <span
                                            class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-gray-500">
                                            <span class="material-symbols-outlined text-[20px]">expand_more</span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div
                            class="bg-gray-50 dark:bg-[#1a2333]/50 px-6 py-4 md:px-8 border-t border-[#e7ebf3] dark:border-[#2a3447] flex flex-col sm:flex-row items-center justify-between gap-4">
                            <button onclick="window.location.href='findwork.php'"
                                class="text-sm font-medium text-text-sub hover:text-text-main dark:hover:text-white transition-colors">
                                Cancel
                            </button>
                            <div class="flex items-center gap-3 w-full sm:w-auto">

                                <button type="submit"
                                    class="flex-1 sm:flex-none justify-center px-8 py-2.5 bg-primary hover:bg-primary-dark text-white rounded-full text-sm font-bold shadow-lg shadow-primary/25 transition-all flex items-center gap-2">
                                    <span>Post Job</span>
                                    <span class="material-symbols-outlined text-[18px]">send</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
    </div>
    </main>
    </div>

    <!-- Success Popup Modal -->
    <div id="success-modal"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 backdrop-blur-sm hidden transition-all">
        <div id="success-modal-content"
            class="bg-white dark:bg-card-dark rounded-2xl shadow-2xl border border-[#e7ebf3] dark:border-[#2a3447] p-8 max-w-sm w-full mx-4 text-center transform scale-95 opacity-0 transition-all duration-300">
            <div
                class="mx-auto w-16 h-16 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center mb-5">
                <span
                    class="material-symbols-outlined text-green-600 dark:text-green-400 text-[36px]">check_circle</span>
            </div>
            <h3 class="text-xl font-bold text-text-main dark:text-white mb-2">Job Posted Successfully!</h3>
            <p class="text-sm text-text-sub mb-6">Your job has been posted. Redirecting to Find Work...</p>
            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 overflow-hidden">
                <div id="redirect-progress"
                    class="bg-primary h-1.5 rounded-full transition-all duration-[2000ms] ease-linear"
                    style="width: 0%"></div>
            </div>
        </div>
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

        // Handle form submission via AJAX
        document.querySelector('form[action="api/post_job.php"]').addEventListener('submit', function (e) {
            e.preventDefault();

            const form = this;
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalBtnHTML = submitBtn.innerHTML;

            // Disable button and show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="animate-spin material-symbols-outlined text-[18px]">progress_activity</span><span>Posting...</span>';

            const formData = new FormData(form);

            fetch('api/post_job.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success modal
                        const modal = document.getElementById('success-modal');
                        const modalContent = document.getElementById('success-modal-content');
                        const progressBar = document.getElementById('redirect-progress');

                        modal.classList.remove('hidden');

                        // Animate modal entrance
                        requestAnimationFrame(() => {
                            modalContent.classList.remove('scale-95', 'opacity-0');
                            modalContent.classList.add('scale-100', 'opacity-100');
                        });

                        // Start progress bar animation
                        setTimeout(() => {
                            progressBar.style.width = '100%';
                        }, 100);

                        // Redirect after 2 seconds
                        setTimeout(() => {
                            window.location.href = 'findwork.php';
                        }, 2200);
                    } else {
                        // Show error message
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnHTML;
                        alert(data.message || 'Failed to post job. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnHTML;
                    alert('An error occurred. Please try again.');
                });
        });
        // Live character count for job details textarea
        const jobDetailsTextarea = document.getElementById('job-details');
        const charCountSpan = document.getElementById('char-count');
        jobDetailsTextarea.addEventListener('input', function () {
            const count = this.value.length;
            charCountSpan.textContent = count + '/5000 characters';
            if (count >= 5000) {
                charCountSpan.classList.add('text-red-500');
                charCountSpan.classList.remove('text-text-sub');
            } else {
                charCountSpan.classList.remove('text-red-500');
                charCountSpan.classList.add('text-text-sub');
            }
        });
    </script>
</body>

</html>