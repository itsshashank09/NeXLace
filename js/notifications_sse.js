/**
 * NeXLace – Real-time Notifications via Server-Sent Events (SSE)
 *
 * This script connects to api/notifications_stream.php and keeps the
 * notification badge (header), the notification list (notification.php),
 * and an optional toast container in sync – all without page reloads.
 *
 * Gracefully falls back to short-polling when SSE is not supported.
 */

(function () {
    'use strict';

    // ──────────────────── config ────────────────────
    const SSE_URL         = 'api/notifications_stream.php';
    const POLL_URL        = 'api/get_notifications.php';
    const POLL_INTERVAL   = 15000;   // ms – fallback polling interval
    const RECONNECT_DELAY = 5000;    // ms – SSE reconnect delay
    const MAX_RECONNECT   = 10;      // max consecutive reconnect attempts
    const TOAST_DURATION  = 6000;    // ms – how long toasts stay visible

    // ──────────────────── state ─────────────────────
    let eventSource        = null;
    let reconnectAttempts   = 0;
    let pollTimer           = null;
    let lastKnownId         = 0;
    let isNotificationPage  = false;
    let notificationsCache  = [];

    // ──────────────────── DOM refs ──────────────────
    // These are lazily resolved because the script may load before DOM ready.
    function $badge()     { return document.getElementById('notification-badge'); }
    function $container() { return document.getElementById('notificationsContainer'); }
    function $empty()     { return document.getElementById('emptyState'); }
    function $loading()   { return document.getElementById('loadingState'); }
    function $unread()    { return document.getElementById('unreadBadge'); }

    // Detect if we're on the notifications page
    function detectPage() {
        isNotificationPage = !!$container();
    }

    // ──────────────────── init ──────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        detectPage();
        injectToastContainer();
        injectAnimationStyles();

        if (typeof EventSource !== 'undefined') {
            connectSSE();
        } else {
            startPolling();
        }
    });

    // ──────────────────── SSE ───────────────────────

    function connectSSE() {
        if (eventSource) {
            eventSource.close();
        }

        const url = `${SSE_URL}?last_id=${lastKnownId}&_t=${Date.now()}`;
        eventSource = new EventSource(url);

        // ── init event (full state)
        eventSource.addEventListener('init', (e) => {
            reconnectAttempts = 0;
            try {
                const payload = JSON.parse(e.data);
                handleInit(payload);
            } catch (err) {
                console.error('[SSE] Bad init payload:', err);
            }
        });

        // ── new notification
        eventSource.addEventListener('notification', (e) => {
            reconnectAttempts = 0;
            try {
                const notif = JSON.parse(e.data);
                handleNewNotification(notif);
                if (e.lastEventId) lastKnownId = parseInt(e.lastEventId, 10);
            } catch (err) {
                console.error('[SSE] Bad notification payload:', err);
            }
        });

        // ── unread count update
        eventSource.addEventListener('count', (e) => {
            try {
                const payload = JSON.parse(e.data);
                updateBadge(payload.unread_count);
            } catch (err) { /* ignore */ }
        });

        // ── error / reconnect
        eventSource.addEventListener('error', () => {
            eventSource.close();
            eventSource = null;

            if (reconnectAttempts < MAX_RECONNECT) {
                reconnectAttempts++;
                const delay = RECONNECT_DELAY * Math.min(reconnectAttempts, 5);
                console.log(`[SSE] Reconnecting in ${delay}ms (attempt ${reconnectAttempts}/${MAX_RECONNECT})...`);
                setTimeout(connectSSE, delay);
            } else {
                console.warn('[SSE] Max reconnect attempts reached – falling back to polling.');
                startPolling();
            }
        });
    }

    // ──────────────────── polling fallback ──────────
    function startPolling() {
        if (pollTimer) return;
        pollTimer = setInterval(pollNotifications, POLL_INTERVAL);
        pollNotifications(); // immediate first call
    }

    async function pollNotifications() {
        try {
            const resp = await fetch(`${POLL_URL}?filter=all&_t=${Date.now()}`);
            const data = await resp.json();
            if (!data.success) return;

            updateBadge(data.unread_count);

            if (isNotificationPage) {
                // Detect truly new notifications
                const existing = new Set(notificationsCache.map(n => n.id));
                const incoming = data.notifications || [];
                incoming.forEach(n => {
                    if (!existing.has(n.id)) {
                        showToast(n);
                    }
                });

                notificationsCache = incoming;
                if (lastKnownId === 0 && incoming.length) {
                    lastKnownId = Math.max(...incoming.map(n => n.id));
                }

                renderNotificationList(notificationsCache);
            }
        } catch (err) {
            console.error('[Poll] Error:', err);
        }
    }

    // ──────────────────── handlers ──────────────────

    function handleInit(payload) {
        const { notifications: list, unread_count } = payload;

        notificationsCache = list || [];
        updateBadge(unread_count);

        if (notificationsCache.length) {
            lastKnownId = Math.max(...notificationsCache.map(n => n.id));
        }

        // If on the notification page, render the full list
        if (isNotificationPage) {
            hideLoading();
            renderNotificationList(notificationsCache);
        }
    }

    function handleNewNotification(notif) {
        // Add to the top of cache
        notificationsCache.unshift(notif);

        // Show a toast on every page
        showToast(notif);

        // If on the notification page, prepend to the live list
        if (isNotificationPage) {
            hideLoading();
            prependNotification(notif);
        }
    }

    // ──────────────────── badge ─────────────────────

    function updateBadge(count) {
        count = parseInt(count, 10) || 0;

        // Header badge (bell icon)
        let badge = $badge();
        const bellButton = document.querySelector('button[onclick="window.location.href=\'notification.php\'"]');

        if (count > 0) {
            if (!badge && bellButton) {
                badge = document.createElement('span');
                badge.id = 'notification-badge';
                badge.className = 'absolute -top-1 -right-1 h-5 w-5 flex items-center justify-center rounded-full bg-red-500 text-white text-xs font-bold animate-pulse';
                bellButton.appendChild(badge);
            }
            if (badge) {
                badge.textContent = count > 9 ? '9+' : count;
                badge.classList.remove('hidden');
            }
        } else {
            if (badge) badge.classList.add('hidden');
        }

        // Page-level unread badge (filter bar)
        const unreadBadgeEl = $unread();
        if (unreadBadgeEl) {
            unreadBadgeEl.textContent = count;
        }

        // Update page title
        document.title = count > 0
            ? `(${count}) NeXLace - Notifications`
            : 'NeXLace - Notifications';
    }

    // ──────────────────── list rendering ────────────

    function hideLoading() {
        const l = $loading();
        if (l) l.classList.add('hidden');
    }

    /**
     * Render (or re-render) the full notification list.
     * Respects the currently active filter (all / unread).
     */
    function renderNotificationList(list) {
        const container = $container();
        const empty = $empty();
        if (!container) return;

        // Apply current filter
        const activeFilter = typeof currentFilter !== 'undefined' ? currentFilter : 'all';
        const filtered = activeFilter === 'unread'
            ? list.filter(n => !n.is_read)
            : list;

        if (filtered.length === 0) {
            container.innerHTML = '';
            if (empty) empty.classList.remove('hidden');
            return;
        }

        if (empty) empty.classList.add('hidden');
        container.innerHTML = '';

        filtered.forEach(notif => {
            container.appendChild(createNotificationElement(notif));
        });
    }

    /**
     * Prepend a single new notification to the top of the list with an
     * entrance animation, and hide the empty state if visible.
     */
    function prependNotification(notif) {
        const container = $container();
        const empty = $empty();
        if (!container) return;

        if (empty) empty.classList.add('hidden');

        const el = createNotificationElement(notif);
        el.classList.add('nexlace-notif-enter');
        container.prepend(el);

        // Trigger reflow then play entrance animation
        requestAnimationFrame(() => {
            el.classList.add('nexlace-notif-enter-active');
        });
    }

    // ──────────────────── DOM builders ──────────────

    // Re-use the same style map defined in notification.php
    const notifStyles = {
        payment:     { icon: 'attach_money',   bg: 'bg-green-100 dark:bg-green-900/30',   text: 'text-green-600 dark:text-green-400' },
        message:     { icon: 'mail',           bg: 'bg-purple-100 dark:bg-purple-900/30',  text: 'text-purple-600 dark:text-purple-400' },
        warning:     { icon: 'warning',        bg: 'bg-yellow-100 dark:bg-yellow-900/30',  text: 'text-yellow-600 dark:text-yellow-400' },
        project:     { icon: 'work',           bg: 'bg-blue-100 dark:bg-blue-900/30',      text: 'text-blue-600 dark:text-blue-400' },
        system:      { icon: 'system_update',  bg: 'bg-gray-100 dark:bg-gray-800',         text: 'text-gray-600 dark:text-gray-400' },
        success:     { icon: 'check_circle',   bg: 'bg-green-100 dark:bg-green-900/30',    text: 'text-green-600 dark:text-green-400' },
        info:        { icon: 'info',           bg: 'bg-blue-100 dark:bg-blue-900/30',      text: 'text-blue-600 dark:text-blue-400' },
        invitation:  { icon: 'mail',           bg: 'bg-indigo-100 dark:bg-indigo-900/30',  text: 'text-indigo-600 dark:text-indigo-400' },
        application: { icon: 'work',           bg: 'bg-teal-100 dark:bg-teal-900/30',      text: 'text-teal-600 dark:text-teal-400' },
    };

    function esc(text) {
        const d = document.createElement('div');
        d.textContent = text || '';
        return d.innerHTML;
    }

    function createNotificationElement(notif) {
        const style   = notifStyles[notif.type] || notifStyles.info;
        const unread  = !notif.is_read;

        const el = document.createElement('div');
        el.className = `group relative flex gap-4 p-5 rounded-xl border hover:shadow-md transition-all cursor-pointer ${
            unread
                ? 'border-primary/20 bg-blue-50/50 dark:bg-blue-900/10 dark:border-blue-800'
                : 'border-[#e7ebf3] dark:border-[#2a3447] bg-card-light dark:bg-card-dark'
        }`;
        el.setAttribute('data-notif-id', notif.id);

        el.onclick = (e) => {
            if (e.target.tagName === 'A') return;
            if (unread && typeof markAsRead === 'function') {
                markAsRead(notif.id, notif.link);
            } else if (notif.link) {
                window.location.href = notif.link;
            }
        };

        el.innerHTML = `
            <div class="flex-shrink-0">
                <div class="h-10 w-10 rounded-full ${style.bg} ${style.text} flex items-center justify-center">
                    <span class="material-symbols-outlined text-[20px]">${style.icon}</span>
                </div>
            </div>
            <div class="flex-1 pr-16">
                <div class="flex items-center gap-2 mb-1">
                    <h3 class="${unread ? 'font-bold text-text-main dark:text-white' : 'font-semibold text-text-main dark:text-gray-300'} text-sm">${esc(notif.title)}</h3>
                    ${unread ? '<span class="h-2 w-2 rounded-full bg-primary animate-pulse"></span>' : ''}
                </div>
                <p class="text-text-sub text-sm mb-2">${esc(notif.message)}</p>
                <div class="flex items-center gap-4">
                    <span class="text-xs text-gray-500">${notif.time_ago || 'Just now'}</span>
                    ${notif.link ? `
                        <a class="text-xs font-semibold ${unread
                            ? 'text-primary hover:underline'
                            : 'text-gray-600 dark:text-gray-400 hover:text-primary dark:hover:text-primary'
                        }" href="${esc(notif.link)}" onclick="event.stopPropagation(); ${
                            unread ? `markAsRead(${notif.id}, '${esc(notif.link)}'); return false;` : ''
                        }">View Details</a>
                    ` : ''}
                </div>
            </div>
            <!-- Delete Button -->
            <div class="absolute top-4 right-4">
                <button onclick="event.stopPropagation(); deleteNotification(${notif.id})" class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-full transition-all" title="Delete notification">
                    <span class="material-symbols-outlined text-[20px]">delete</span>
                </button>
            </div>
        `;

        return el;
    }

    // ──────────────────── toast system ──────────────

    function injectToastContainer() {
        if (document.getElementById('nexlace-toast-container')) return;
        const wrap = document.createElement('div');
        wrap.id = 'nexlace-toast-container';
        wrap.className = 'fixed top-20 right-4 z-[9999] flex flex-col gap-3 pointer-events-none';
        wrap.style.maxWidth = '380px';
        wrap.style.width = '100%';
        document.body.appendChild(wrap);
    }

    function showToast(notif) {
        const wrap = document.getElementById('nexlace-toast-container');
        if (!wrap) return;

        const style = notifStyles[notif.type] || notifStyles.info;
        const toast = document.createElement('div');
        toast.className = 'pointer-events-auto nexlace-toast flex items-start gap-3 p-4 rounded-xl border border-[#e7ebf3] dark:border-[#2a3447] bg-white dark:bg-card-dark shadow-2xl';
        toast.style.cssText = 'opacity:0; transform:translateX(100%); transition: opacity .35s ease, transform .35s ease;';

        toast.innerHTML = `
            <div class="flex-shrink-0">
                <div class="h-9 w-9 rounded-full ${style.bg} ${style.text} flex items-center justify-center">
                    <span class="material-symbols-outlined text-[18px]">${style.icon}</span>
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-bold text-text-main dark:text-white truncate">${esc(notif.title)}</p>
                <p class="text-xs text-text-sub mt-0.5 line-clamp-2">${esc(notif.message)}</p>
            </div>
            <button class="flex-shrink-0 text-gray-400 hover:text-gray-600 dark:hover:text-white transition-colors" aria-label="Close">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        `;

        // If the notification has a link, clicking the toast body navigates
        toast.addEventListener('click', (e) => {
            if (e.target.closest('button')) {
                // close button
                removeToast(toast);
                return;
            }
            if (notif.link) {
                window.location.href = notif.link;
            }
        });

        wrap.appendChild(toast);

        // Animate in
        requestAnimationFrame(() => {
            toast.style.opacity = '1';
            toast.style.transform = 'translateX(0)';
        });

        // Auto-dismiss
        setTimeout(() => removeToast(toast), TOAST_DURATION);
    }

    function removeToast(el) {
        el.style.opacity = '0';
        el.style.transform = 'translateX(100%)';
        setTimeout(() => el.remove(), 350);
    }

    // ──────────────────── animations CSS ────────────

    function injectAnimationStyles() {
        if (document.getElementById('nexlace-sse-styles')) return;
        const style = document.createElement('style');
        style.id = 'nexlace-sse-styles';
        style.textContent = `
            /* Notification entrance */
            .nexlace-notif-enter {
                opacity: 0;
                transform: translateY(-12px) scale(0.97);
                max-height: 0;
                overflow: hidden;
                transition: opacity .4s ease, transform .4s ease, max-height .4s ease;
            }
            .nexlace-notif-enter-active {
                opacity: 1;
                transform: translateY(0) scale(1);
                max-height: 300px;
            }
            /* Notification removal slide-out */
            .nexlace-notif-exit {
                opacity: 0;
                transform: translateX(40px);
                max-height: 0 !important;
                padding-top: 0 !important;
                padding-bottom: 0 !important;
                margin-top: 0 !important;
                margin-bottom: 0 !important;
                border-width: 0 !important;
                transition: all .35s ease;
            }
            /* Toast line-clamp helper */
            .line-clamp-2 {
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }
            /* Badge pop animation */
            @keyframes badgePop {
                0%   { transform: scale(0); }
                60%  { transform: scale(1.25); }
                100% { transform: scale(1); }
            }
            .nexlace-badge-pop {
                animation: badgePop .35s ease;
            }
        `;
        document.body.appendChild(style);
    }

    // ──────────────────── public API ────────────────
    // Expose helpers so notification.php inline scripts can call them

    /**
     * Removes a notification from the live cache and animates it out.
     */
    window.nexlaceRemoveNotification = function (id) {
        notificationsCache = notificationsCache.filter(n => n.id !== id);
        const el = document.querySelector(`[data-notif-id="${id}"]`);
        if (el) {
            el.classList.add('nexlace-notif-exit');
            setTimeout(() => el.remove(), 350);
        }
        if (notificationsCache.length === 0) {
            const empty = $empty();
            if (empty) empty.classList.remove('hidden');
        }
    };

    /**
     * Marks a notification as read in the local cache and updates
     * its visual styling without reloading.
     */
    window.nexlaceMarkReadLocally = function (id) {
        const n = notificationsCache.find(n => n.id === id);
        if (n) n.is_read = true;

        const el = document.querySelector(`[data-notif-id="${id}"]`);
        if (el) {
            // Remove unread styling
            el.classList.remove('border-primary/20', 'bg-blue-50/50', 'dark:bg-blue-900/10', 'dark:border-blue-800');
            el.classList.add('border-[#e7ebf3]', 'dark:border-[#2a3447]', 'bg-card-light', 'dark:bg-card-dark');
            // Remove the blue dot
            const dot = el.querySelector('.animate-pulse');
            if (dot && dot.classList.contains('rounded-full') && dot.classList.contains('bg-primary')) {
                dot.remove();
            }
            // Lighten title
            const title = el.querySelector('h3');
            if (title) {
                title.classList.remove('font-bold');
                title.classList.add('font-semibold', 'dark:text-gray-300');
            }
        }
    };

    /**
     * Marks ALL notifications as read in the local cache.
     */
    window.nexlaceMarkAllReadLocally = function () {
        notificationsCache.forEach(n => n.is_read = true);
        // Re-render for clean slate
        renderNotificationList(notificationsCache);
        updateBadge(0);
    };

    /**
     * Force update the badge count.
     */
    window.nexlaceUpdateBadge = updateBadge;

    /**
     * Refresh the list from the server (for use after filter change).
     */
    window.nexlaceRefreshList = function () {
        renderNotificationList(notificationsCache);
    };

})();
