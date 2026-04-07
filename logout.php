<?php
session_start();
require_once 'config/security_headers.php';

/* Remove all session variables */
$_SESSION = [];

/* Destroy the session */
session_destroy();

/* Delete session cookie (important for full logout) */
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="assetes/logo.png" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging Out - NeXLace</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#6366f1',
                        'surface-dark': '#1e1e2e',
                        'border-light': '#e5e7eb',
                        'border-dark': '#374151',
                        'text-main-light': '#1f2937',
                        'text-sub-light': '#6b7280',
                        'text-sub-dark': '#9ca3af',
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes scaleIn {
            from {
                transform: scale(0.9);
                opacity: 0;
            }

            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        @keyframes wave {

            0%,
            100% {
                transform: rotate(0deg);
            }

            25% {
                transform: rotate(20deg);
            }

            75% {
                transform: rotate(-15deg);
            }
        }

        .animate-wave {
            animation: wave 1s ease-in-out infinite;
            transform-origin: 70% 70%;
        }

        .popup-overlay {
            animation: fadeIn 0.3s ease-out forwards;
        }

        .popup-content {
            animation: scaleIn 0.3s ease-out forwards;
        }
    </style>
</head>

<body class="bg-gray-50 dark:bg-gray-900 min-h-screen">
    <!-- Logout Success Popup -->
    <div id="logoutPopup"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm popup-overlay">
        <div
            class="bg-white dark:bg-surface-dark border border-border-light dark:border-border-dark rounded-2xl p-8 shadow-2xl popup-content flex flex-col items-center gap-4 min-w-[320px]">
            <div
                class="h-16 w-16 rounded-full bg-orange-50 dark:bg-orange-900/20 flex items-center justify-center text-orange-500 dark:text-orange-400 mb-2">
                <span class="material-symbols-outlined text-[32px] animate-wave">waving_hand</span>
            </div>
            <div class="text-center">
                <h3 class="text-xl font-bold text-text-main-light dark:text-white mb-2">Logged Out Successfully!</h3>
                <p class="text-text-sub-light dark:text-text-sub-dark">See you again soon. Redirecting...</p>
            </div>
            <div class="w-full bg-gray-100 dark:bg-gray-800 rounded-full h-1.5 mt-4 overflow-hidden">
                <div id="progressBar"
                    class="bg-orange-500 h-full w-0 rounded-full transition-all duration-[2000ms] ease-out"></div>
            </div>
        </div>
    </div>

    <script>
        // Check for dark mode preference
        if (localStorage.getItem('theme') === 'dark' ||
            (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }

        // Animate progress bar and redirect
        window.addEventListener('load', function () {
            setTimeout(function () {
                document.getElementById('progressBar').style.width = '100%';
            }, 100);

            // Redirect after animation completes
            setTimeout(function () {
                window.location.href = 'index.html';
            }, 2500);
        });
    </script>
</body>

</html>