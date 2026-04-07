<!-- Global Search Engine -->
<div class="hidden sm:flex relative" id="search-engine-container">
    <span
        class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-gray-400 text-[20px] z-10 transition-colors duration-200"
        id="search-icon">search</span>
    <input
        class="h-9 w-64 rounded-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-[#1a2333] pl-10 pr-10 text-sm focus:border-primary focus:ring-primary focus:w-80 transition-all duration-300 ease-in-out"
        placeholder="Search jobs, talent, people..." type="text" id="global-search-input" autocomplete="off" />
    <!-- Clear button -->
    <button id="search-clear-btn"
        class="hidden absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors z-10"
        type="button">
        <span class="material-symbols-outlined text-[18px]">close</span>
    </button>
    <!-- Search Results Dropdown -->
    <div id="search-results-dropdown"
        class="hidden absolute top-full right-0 mt-2 w-[460px] max-h-[520px] overflow-y-auto rounded-2xl border border-[#e7ebf3] dark:border-[#2a3447] bg-white dark:bg-card-dark shadow-2xl dark:shadow-black/60 z-[60] overscroll-contain"
        style="scrollbar-width: thin;">
        <!-- Loading State -->
        <div id="search-loading" class="hidden p-8 text-center">
            <div class="inline-flex items-center gap-3">
                <div class="w-5 h-5 border-2 border-primary border-t-transparent rounded-full animate-spin">
                </div>
                <span class="text-sm text-text-sub">Searching...</span>
            </div>
        </div>
        <!-- Results Container -->
        <div id="search-results-content" class="hidden">
            <!-- Jobs Section -->
            <div id="search-jobs-section" class="hidden">
                <div
                    class="sticky top-0 bg-gray-50 dark:bg-[#1a2333] px-4 py-2.5 border-b border-[#e7ebf3] dark:border-[#2a3447]">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-[16px] text-primary">work</span>
                            <span class="text-xs font-bold text-text-sub uppercase tracking-wider">Jobs</span>
                        </div>
                        <a href="#" id="search-view-all-jobs"
                            class="text-xs font-bold text-primary hover:underline">View all</a>
                    </div>
                </div>
                <div id="search-jobs-list" class="py-1"></div>
            </div>
            <!-- Developers Section -->
            <div id="search-devs-section" class="hidden">
                <div
                    class="sticky top-0 bg-gray-50 dark:bg-[#1a2333] px-4 py-2.5 border-b border-[#e7ebf3] dark:border-[#2a3447]">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-[16px] text-purple-500">code</span>
                            <span class="text-xs font-bold text-text-sub uppercase tracking-wider">Developers</span>
                        </div>
                        <a href="developer.php" class="text-xs font-bold text-primary hover:underline">Browse all</a>
                    </div>
                </div>
                <div id="search-devs-list" class="py-1"></div>
            </div>
            <!-- People Section -->
            <div id="search-users-section" class="hidden">
                <div
                    class="sticky top-0 bg-gray-50 dark:bg-[#1a2333] px-4 py-2.5 border-b border-[#e7ebf3] dark:border-[#2a3447]">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[16px] text-green-500">person</span>
                        <span class="text-xs font-bold text-text-sub uppercase tracking-wider">People</span>
                    </div>
                </div>
                <div id="search-users-list" class="py-1"></div>
            </div>
        </div>
        <!-- Empty State -->
        <div id="search-empty" class="hidden p-8 text-center">
            <span
                class="material-symbols-outlined text-[48px] text-gray-300 dark:text-gray-600 mb-2 block">search_off</span>
            <p class="text-sm font-bold text-text-main dark:text-white mb-1">No results found</p>
            <p class="text-xs text-text-sub">Try different keywords or browse categories below</p>
            <div class="flex flex-wrap gap-2 justify-center mt-4">
                <a href="findwork.php"
                    class="px-3 py-1.5 text-xs font-medium bg-primary/10 text-primary rounded-full hover:bg-primary/20 transition-colors">Browse
                    Jobs</a>
                <a href="developer.php"
                    class="px-3 py-1.5 text-xs font-medium bg-purple-100 dark:bg-purple-900/20 text-purple-600 dark:text-purple-400 rounded-full hover:bg-purple-200 dark:hover:bg-purple-900/30 transition-colors">Find
                    Talent</a>
            </div>
        </div>
        <!-- Quick Actions (shown on focus, before typing) -->
        <div id="search-quick-actions" class="hidden p-4">
            <p class="text-xs font-bold text-text-sub uppercase tracking-wider mb-3 px-1">Quick
                Actions</p>
            <div class="space-y-1">
                <a href="findwork.php"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-gray-50 dark:hover:bg-[#1f2937] transition-colors group">
                    <div
                        class="h-9 w-9 rounded-lg bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center text-blue-600 dark:text-blue-400 group-hover:scale-110 transition-transform">
                        <span class="material-symbols-outlined text-[20px]">work</span>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-text-main dark:text-white">Find Work</p>
                        <p class="text-xs text-text-sub">Browse available jobs</p>
                    </div>
                </a>
                <a href="developer.php"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-gray-50 dark:hover:bg-[#1f2937] transition-colors group">
                    <div
                        class="h-9 w-9 rounded-lg bg-purple-50 dark:bg-purple-900/20 flex items-center justify-center text-purple-600 dark:text-purple-400 group-hover:scale-110 transition-transform">
                        <span class="material-symbols-outlined text-[20px]">group</span>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-text-main dark:text-white">Hire Talent
                        </p>
                        <p class="text-xs text-text-sub">Browse top developers</p>
                    </div>
                </a>
                <a href="postjob.php"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-gray-50 dark:hover:bg-[#1f2937] transition-colors group">
                    <div
                        class="h-9 w-9 rounded-lg bg-green-50 dark:bg-green-900/20 flex items-center justify-center text-green-600 dark:text-green-400 group-hover:scale-110 transition-transform">
                        <span class="material-symbols-outlined text-[20px]">add_circle</span>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-text-main dark:text-white">Post a Job</p>
                        <p class="text-xs text-text-sub">Create a new project</p>
                    </div>
                </a>
                <a href="messages.php"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-gray-50 dark:hover:bg-[#1f2937] transition-colors group">
                    <div
                        class="h-9 w-9 rounded-lg bg-orange-50 dark:bg-orange-900/20 flex items-center justify-center text-orange-600 dark:text-orange-400 group-hover:scale-110 transition-transform">
                        <span class="material-symbols-outlined text-[20px]">chat</span>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-text-main dark:text-white">Messages</p>
                        <p class="text-xs text-text-sub">Check your conversations</p>
                    </div>
                </a>
            </div>
        </div>
        <!-- Footer -->
        <div id="search-footer"
            class="hidden border-t border-[#e7ebf3] dark:border-[#2a3447] px-4 py-3 bg-gray-50 dark:bg-[#1a2333] rounded-b-2xl">
            <div class="flex items-center justify-between text-xs text-text-sub">
                <div class="flex items-center gap-3">
                    <span class="flex items-center gap-1"><kbd
                            class="px-1.5 py-0.5 bg-white dark:bg-card-dark border border-gray-200 dark:border-gray-600 rounded text-[10px] font-mono">↑↓</kbd>
                        Navigate</span>
                    <span class="flex items-center gap-1"><kbd
                            class="px-1.5 py-0.5 bg-white dark:bg-card-dark border border-gray-200 dark:border-gray-600 rounded text-[10px] font-mono">↵</kbd>
                        Open</span>
                    <span class="flex items-center gap-1"><kbd
                            class="px-1.5 py-0.5 bg-white dark:bg-card-dark border border-gray-200 dark:border-gray-600 rounded text-[10px] font-mono">Esc</kbd>
                        Close</span>
                </div>
                <span id="search-result-count" class="font-medium"></span>
            </div>
        </div>
    </div>
</div>