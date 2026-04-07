// ========== GLOBAL SEARCH ENGINE ==========
(function () {
    const input = document.getElementById('global-search-input');
    if (!input) return; // Guard: exit if search bar not present

    const dropdown = document.getElementById('search-results-dropdown');
    const clearBtn = document.getElementById('search-clear-btn');
    const searchIcon = document.getElementById('search-icon');
    const loadingEl = document.getElementById('search-loading');
    const resultsContent = document.getElementById('search-results-content');
    const emptyEl = document.getElementById('search-empty');
    const quickActions = document.getElementById('search-quick-actions');
    const footer = document.getElementById('search-footer');
    const resultCount = document.getElementById('search-result-count');

    // Sections
    const jobsSection = document.getElementById('search-jobs-section');
    const devsSection = document.getElementById('search-devs-section');
    const usersSection = document.getElementById('search-users-section');
    const jobsList = document.getElementById('search-jobs-list');
    const devsList = document.getElementById('search-devs-list');
    const usersList = document.getElementById('search-users-list');
    const viewAllJobsLink = document.getElementById('search-view-all-jobs');

    let debounceTimer = null;
    let currentRequest = null;
    let selectedIndex = -1;
    let currentItems = [];

    // Resolve the base path for API calls (works from any directory depth)
    const scriptTags = document.querySelectorAll('script[src*="search_engine.js"]');
    let apiBase = 'api/search.php';
    if (scriptTags.length > 0) {
        const src = scriptTags[0].getAttribute('src');
        const jsDir = src.substring(0, src.lastIndexOf('/'));
        // js/ is sibling to api/, so go up one level
        apiBase = jsDir + '/../api/search.php';
    }

    // Highlight matching text
    function highlightText(text, query) {
        if (!query || !text) return text || '';
        const escaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const regex = new RegExp(`(${escaped})`, 'gi');
        return text.replace(regex, '<mark class="bg-yellow-200 dark:bg-yellow-800/50 text-inherit rounded px-0.5">$1</mark>');
    }

    // Show/hide helpers
    function showEl(el) { if (el) el.classList.remove('hidden'); }
    function hideEl(el) { if (el) el.classList.add('hidden'); }

    function showDropdown() {
        showEl(dropdown);
        dropdown.style.opacity = '0';
        dropdown.style.transform = 'translateY(-8px)';
        requestAnimationFrame(() => {
            dropdown.style.transition = 'opacity 200ms ease, transform 200ms ease';
            dropdown.style.opacity = '1';
            dropdown.style.transform = 'translateY(0)';
        });
    }

    function hideDropdown() {
        dropdown.style.opacity = '0';
        dropdown.style.transform = 'translateY(-8px)';
        setTimeout(() => {
            hideEl(dropdown);
            dropdown.style.transition = '';
        }, 200);
        selectedIndex = -1;
        updateSelection();
    }

    function resetStates() {
        hideEl(loadingEl);
        hideEl(resultsContent);
        hideEl(emptyEl);
        hideEl(quickActions);
        hideEl(footer);
        hideEl(jobsSection);
        hideEl(devsSection);
        hideEl(usersSection);
    }

    // Render job result item
    function renderJob(job, query) {
        const skills = (job.skills || []).slice(0, 3).map(s =>
            `<span class="px-1.5 py-0.5 bg-gray-100 dark:bg-[#2a3447] text-text-sub text-[10px] rounded">${highlightText(s, query)}</span>`
        ).join('');

        return `
            <a href="${job.url}" class="search-result-item flex items-start gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-[#1f2937] transition-colors cursor-pointer border-b border-gray-50 dark:border-[#1f2937] last:border-0">
                <div class="h-10 w-10 rounded-lg bg-blue-50 dark:bg-blue-900/20 flex-shrink-0 flex items-center justify-center text-blue-600 dark:text-blue-400">
                    <span class="material-symbols-outlined text-[20px]">work</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-text-main dark:text-white truncate">${highlightText(job.title, query)}</p>
                    <div class="flex items-center gap-2 mt-0.5">
                        <span class="text-xs text-green-600 dark:text-green-400 font-bold">${job.budget}</span>
                        <span class="text-gray-300 dark:text-gray-600">•</span>
                        <span class="text-xs text-text-sub">${job.category}</span>
                        <span class="text-gray-300 dark:text-gray-600">•</span>
                        <span class="text-xs text-text-sub">${job.timeline}</span>
                    </div>
                    ${skills ? `<div class="flex flex-wrap gap-1 mt-1.5">${skills}</div>` : ''}
                </div>
                <span class="material-symbols-outlined text-[16px] text-gray-400 mt-1">arrow_forward</span>
            </a>`;
    }

    // Render developer result item
    function renderDev(dev, query) {
        const skills = (dev.skills || []).slice(0, 3).map(s =>
            `<span class="px-1.5 py-0.5 bg-purple-50 dark:bg-purple-900/20 text-purple-600 dark:text-purple-400 text-[10px] rounded">${highlightText(s, query)}</span>`
        ).join('');

        const avatar = dev.image
            ? `<div class="h-10 w-10 rounded-full bg-cover bg-center flex-shrink-0 border border-gray-100 dark:border-gray-700" style="background-image: url('${dev.image}')"></div>`
            : `<div class="h-10 w-10 rounded-full bg-primary flex-shrink-0 flex items-center justify-center text-white font-bold text-sm">${dev.name[0].toUpperCase()}</div>`;

        return `
            <a href="${dev.url}" class="search-result-item flex items-start gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-[#1f2937] transition-colors cursor-pointer border-b border-gray-50 dark:border-[#1f2937] last:border-0">
                ${avatar}
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-1.5">
                        <p class="text-sm font-semibold text-text-main dark:text-white">${highlightText(dev.name, query)}</p>
                        <span class="material-symbols-outlined text-primary text-[14px]" style="font-variation-settings: 'FILL' 1;">verified</span>
                    </div>
                    <p class="text-xs text-text-sub truncate">${highlightText(dev.title, query)}</p>
                    <div class="flex items-center gap-2 mt-0.5">
                        <span class="text-xs font-bold text-text-main dark:text-white">${dev.rate}</span>
                        <span class="text-gray-300 dark:text-gray-600">•</span>
                        <span class="text-xs text-text-sub">${dev.location}</span>
                    </div>
                    ${skills ? `<div class="flex flex-wrap gap-1 mt-1.5">${skills}</div>` : ''}
                </div>
                <span class="material-symbols-outlined text-[16px] text-gray-400 mt-1">arrow_forward</span>
            </a>`;
    }

    // Render user/people result item
    function renderUser(user, query) {
        const avatar = user.image
            ? `<div class="h-10 w-10 rounded-full bg-cover bg-center flex-shrink-0 border border-gray-100 dark:border-gray-700" style="background-image: url('${user.image}')"></div>`
            : `<div class="h-10 w-10 rounded-full bg-green-500 flex-shrink-0 flex items-center justify-center text-white font-bold text-sm">${user.name[0].toUpperCase()}</div>`;

        return `
            <a href="${user.url}" class="search-result-item flex items-center gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-[#1f2937] transition-colors cursor-pointer border-b border-gray-50 dark:border-[#1f2937] last:border-0">
                ${avatar}
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-text-main dark:text-white">${highlightText(user.name, query)}</p>
                    ${user.headline ? `<p class="text-xs text-text-sub truncate">${highlightText(user.headline, query)}</p>` : ''}
                </div>
                <span class="material-symbols-outlined text-[16px] text-gray-400">arrow_forward</span>
            </a>`;
    }

    // Perform search
    function performSearch(query) {
        if (query.length < 2) {
            resetStates();
            showEl(quickActions);
            showEl(footer);
            return;
        }

        // Show loading
        resetStates();
        showEl(loadingEl);

        // Abort previous request
        if (currentRequest) currentRequest.abort();

        currentRequest = new XMLHttpRequest();
        currentRequest.open('GET', apiBase + '?q=' + encodeURIComponent(query));
        currentRequest.onload = function () {
            hideEl(loadingEl);
            if (this.status === 200) {
                try {
                    const data = JSON.parse(this.responseText);
                    renderResults(data, query);
                } catch (e) {
                    console.error('Search parse error:', e);
                }
            }
        };
        currentRequest.onerror = function () {
            hideEl(loadingEl);
        };
        currentRequest.send();
    }

    // Render all results
    function renderResults(data, query) {
        resetStates();
        const results = data.results;
        const total = data.total;

        if (total === 0) {
            showEl(emptyEl);
            showEl(footer);
            resultCount.textContent = '0 results';
            return;
        }

        showEl(resultsContent);
        showEl(footer);

        // Jobs
        if (results.jobs && results.jobs.length > 0) {
            showEl(jobsSection);
            jobsList.innerHTML = results.jobs.map(j => renderJob(j, query)).join('');
            viewAllJobsLink.href = 'findwork.php?q=' + encodeURIComponent(query);
        }

        // Developers
        if (results.developers && results.developers.length > 0) {
            showEl(devsSection);
            devsList.innerHTML = results.developers.map(d => renderDev(d, query)).join('');
        }

        // Users
        if (results.users && results.users.length > 0) {
            showEl(usersSection);
            usersList.innerHTML = results.users.map(u => renderUser(u, query)).join('');
        }

        resultCount.textContent = total + ' result' + (total !== 1 ? 's' : '');

        // Update selectable items
        currentItems = dropdown.querySelectorAll('.search-result-item');
        selectedIndex = -1;
    }

    // Keyboard navigation
    function updateSelection() {
        currentItems.forEach((item, i) => {
            if (i === selectedIndex) {
                item.classList.add('bg-gray-50', 'dark:bg-[#1f2937]');
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.classList.remove('bg-gray-50', 'dark:bg-[#1f2937]');
            }
        });
    }

    // Input events
    input.addEventListener('input', function () {
        const val = this.value.trim();

        // Toggle clear button
        if (val.length > 0) {
            showEl(clearBtn);
            searchIcon.style.color = '#135bec';
        } else {
            hideEl(clearBtn);
            searchIcon.style.color = '';
        }

        // Debounce
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => performSearch(val), 300);
    });

    input.addEventListener('focus', function () {
        showDropdown();
        if (this.value.trim().length < 2) {
            resetStates();
            showEl(quickActions);
            showEl(footer);
        } else {
            performSearch(this.value.trim());
        }
    });

    input.addEventListener('keydown', function (e) {
        if (!dropdown.classList.contains('hidden')) {
            currentItems = dropdown.querySelectorAll('.search-result-item');

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedIndex = Math.min(selectedIndex + 1, currentItems.length - 1);
                updateSelection();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedIndex = Math.max(selectedIndex - 1, -1);
                updateSelection();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (selectedIndex >= 0 && currentItems[selectedIndex]) {
                    currentItems[selectedIndex].click();
                } else if (this.value.trim().length >= 2) {
                    window.location.href = 'findwork.php?q=' + encodeURIComponent(this.value.trim());
                }
            } else if (e.key === 'Escape') {
                hideDropdown();
                input.blur();
            }
        }
    });

    // Clear button
    clearBtn.addEventListener('click', function () {
        input.value = '';
        hideEl(clearBtn);
        searchIcon.style.color = '';
        input.focus();
        resetStates();
        showEl(quickActions);
        showEl(footer);
    });

    // Close on outside click
    document.addEventListener('click', function (e) {
        const container = document.getElementById('search-engine-container');
        if (container && !container.contains(e.target)) {
            hideDropdown();
        }
    });

    // Ctrl+K / Cmd+K shortcut
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            input.focus();
            input.select();
        }
    });
})();
