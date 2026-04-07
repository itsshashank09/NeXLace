/**
 * NeXBot Chatbot – original widget logic.
 * Works on every page. On mainpage the panel is embedded (always visible via CSS).
 * On other pages the FAB shows/hides the panel as usual.
 */
(function () {
    'use strict';

    /* ── Path detection ── */
    function getBasePath() {
        var scripts = document.getElementsByTagName('script');
        for (var i = 0; i < scripts.length; i++) {
            if ((scripts[i].src || '').indexOf('chatbot.js') !== -1) {
                return scripts[i].src.replace(/\/js\/chatbot\.js.*$/, '');
            }
        }
        var m = window.location.pathname.match(/^(\/[^\/]+)\//);
        return m ? window.location.origin + m[1] : window.location.origin + '/NeXLace';
    }

    var basePath = getBasePath();
    var apiBase = basePath + '/api/chatbot.php';
    console.log('NeXBot | API:', apiBase);

    /* ── Init once DOM is ready ── */
    function init() {
        var fab = document.getElementById('nexbot-fab');
        var fabBadge = document.getElementById('nexbot-fab-badge');
        var panel = document.getElementById('nexbot-panel');
        var closeBtn = document.getElementById('nexbot-close-btn');
        var messagesEl = document.getElementById('nexbot-messages');
        var inputEl = document.getElementById('nexbot-input');
        var sendBtn = document.getElementById('nexbot-send-btn');

        if (!messagesEl || !inputEl || !sendBtn) {
            console.error('NeXBot: required elements missing');
            return;
        }

        /* On mainpage the panel is always visible via CSS override.
           On other pages the panel starts hidden and the FAB opens it. */
        var embedded = !fab; // no FAB = embedded on mainpage

        var isOpen = embedded;
        var isLoading = false;
        var history = [];
        var welcomed = false;

        /* ── Markdown ── */
        function md(text) {
            if (!text) return '';
            var h = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            h = h.replace(/```(\w*)\n([\s\S]*?)```/g, function (_, l, c) { return '<pre><code>' + c.trim() + '</code></pre>'; });
            h = h.replace(/`([^`]+)`/g, '<code>$1</code>');
            h = h.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
            h = h.replace(/\*(.+?)\*/g, '<em>$1</em>');
            h = h.replace(/^[\s]*[-•]\s+(.+)/gm, '<li>$1</li>');
            h = h.replace(/(<li>[\s\S]*?<\/li>\n?)+/g, '<ul>$&</ul>');
            h = h.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
            h = h.replace(/\n\n/g, '</p><p>').replace(/\n/g, '<br>');
            if (h.charAt(0) !== '<') h = '<p>' + h + '</p>';
            return h;
        }

        /* ── UI helpers ── */
        function scrollBottom() {
            requestAnimationFrame(function () { messagesEl.scrollTop = messagesEl.scrollHeight; });
        }

        function esc(t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

        function addMsg(role, text, err) {
            var wrap = document.createElement('div');
            wrap.className = 'nexbot-msg nexbot-msg-' + role;
            var icon = role === 'bot' ? 'smart_toy' : 'person';
            var av = '<div class="nexbot-msg-avatar"><span class="material-symbols-outlined" style="font-size:16px">' + icon + '</span></div>';
            var cls = err ? 'nexbot-bubble nexbot-error-bubble' : 'nexbot-bubble';
            var body = (role === 'bot' && !err) ? md(text) : esc(text);
            wrap.innerHTML = av + '<div class="' + cls + '">' + body + '</div>';
            var typing = messagesEl.querySelector('.nexbot-typing-wrapper');
            if (typing) typing.remove();
            messagesEl.appendChild(wrap);
            scrollBottom();
        }

        function showTyping() {
            var w = document.createElement('div');
            w.className = 'nexbot-msg nexbot-msg-bot nexbot-typing-wrapper';
            w.innerHTML =
                '<div class="nexbot-msg-avatar"><span class="material-symbols-outlined" style="font-size:16px">smart_toy</span></div>' +
                '<div class="nexbot-bubble"><div class="nexbot-typing">' +
                '<span class="nexbot-typing-dot"></span>' +
                '<span class="nexbot-typing-dot"></span>' +
                '<span class="nexbot-typing-dot"></span>' +
                '</div></div>';
            messagesEl.appendChild(w);
            scrollBottom();
        }

        /* ── Welcome message ── */
        function showWelcome() {
            if (welcomed) return;
            welcomed = true;

            addMsg('bot',
                "Hey there! 👋 I'm **NeXBot**, your NeXLace assistant.\n\n" +
                "I can help you with:\n" +
                "- 🧭 Navigating the platform\n" +
                "- 💼 Finding work or hiring talent\n" +
                "- 💻 Coding questions & debugging\n" +
                "- 📝 Tips for proposals & profiles\n\n" +
                "How can I help you today?"
            );

            /* Quick action pills */
            var pills = [
                { label: '🔍 Find Work', msg: 'How do I find work?' },
                { label: '📝 Post a Job', msg: 'How do I post a job?' },
                { label: '👤 Create Profile', msg: 'How do I create a developer profile?' },
                { label: '💻 Coding Help', msg: 'Help me with coding' },
                { label: '💬 Messaging', msg: 'How do I message someone?' },
                { label: '🚀 About NeXLace', msg: 'What is NeXLace?' }
            ];

            var qDiv = document.createElement('div');
            qDiv.className = 'nexbot-msg nexbot-msg-bot';
            qDiv.style.maxWidth = '100%';

            var inner = '<div style="width:28px;flex-shrink:0"></div><div class="nexbot-quick-actions">';
            pills.forEach(function (p) {
                inner += '<button class="nexbot-quick-btn" data-msg="' + esc(p.msg) + '">' + p.label + '</button>';
            });
            inner += '</div>';
            qDiv.innerHTML = inner;
            messagesEl.appendChild(qDiv);

            qDiv.querySelectorAll('.nexbot-quick-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    sendMessage(btn.getAttribute('data-msg'));
                });
            });

            scrollBottom();
        }

        /* ── FAB open/close (widget pages only) ── */
        function openChat() {
            isOpen = true;
            if (panel) panel.classList.add('nexbot-visible');
            if (fab) fab.classList.add('nexbot-open');
            if (fab) fab.querySelector('.nexbot-icon').textContent = 'close';
            if (fabBadge) fabBadge.classList.remove('nexbot-show');
            inputEl.focus();
            showWelcome();
        }

        function closeChat() {
            isOpen = false;
            if (panel) panel.classList.remove('nexbot-visible');
            if (fab) fab.classList.remove('nexbot-open');
            if (fab) fab.querySelector('.nexbot-icon').textContent = 'smart_toy';
        }

        /* ── Clear welcome on first send ── */
        function clearWelcome() {
            // Remove all nexbot-msg-bot rows (welcome + pills)
            var bots = messagesEl.querySelectorAll('.nexbot-msg-bot');
            bots.forEach(function (el) { el.remove(); });
        }

        /* ── Send message ── */
        function sendMessage(text) {
            var msg = (text || inputEl.value || '').trim();
            if (!msg || isLoading) return;

            inputEl.value = '';
            inputEl.style.height = 'auto';

            clearWelcome();

            addMsg('user', msg);
            history.push({ role: 'user', text: msg });

            isLoading = true;
            sendBtn.disabled = true;
            showTyping();

            fetch(apiBase, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: msg, history: history.slice(-40) })
            })
                .then(function (res) {
                    return res.text().then(function (raw) {
                        var data;
                        try { data = JSON.parse(raw); }
                        catch (e) {
                            var t = raw.match(/<title>(.*?)<\/title>/i);
                            throw new Error('Server error' + (t ? ': ' + t[1] : '') + ' — URL: ' + apiBase);
                        }
                        if (!res.ok) throw new Error(data.error || 'Something went wrong');
                        return data;
                    });
                })
                .then(function (data) {
                    addMsg('bot', data.reply);
                    history.push({ role: 'model', text: data.reply });
                })
                .catch(function (err) {
                    console.error('NeXBot:', err);
                    addMsg('bot', err.message || 'Sorry, something went wrong.', true);
                })
                .finally(function () {
                    isLoading = false;
                    sendBtn.disabled = false;
                    inputEl.focus();
                });
        }

        /* ── Event listeners ── */
        sendBtn.addEventListener('click', function (e) {
            e.preventDefault();
            sendMessage();
        });

        inputEl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                e.stopPropagation();
                sendMessage();
            }
        });

        inputEl.addEventListener('input', function () {
            inputEl.style.height = 'auto';
            inputEl.style.height = Math.min(inputEl.scrollHeight, 120) + 'px';
        });

        /* ── Boot ── */
        if (embedded) {
            /* Mainpage: panel is already visible, just show welcome */
            showWelcome();
        } else {
            /* Other pages: FAB wires up open/close */
            fab.addEventListener('click', function () {
                if (isOpen) closeChat(); else openChat();
            });
            if (closeBtn) closeBtn.addEventListener('click', closeChat);
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && isOpen) closeChat();
            });
            setTimeout(function () {
                if (!isOpen && !welcomed) fabBadge && fabBadge.classList.add('nexbot-show');
            }, 3000);
        }

        console.log('NeXBot: ready —', embedded ? 'embedded' : 'widget', 'mode');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
