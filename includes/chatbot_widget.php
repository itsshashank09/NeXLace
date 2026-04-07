<!-- NeXBot Chatbot Widget -->
<link rel="stylesheet" href="css/chatbot.css?v=<?= time() ?>" />

<!-- Floating Action Button -->
<button id="nexbot-fab" type="button" aria-label="Open chat assistant">
    <span class="nexbot-icon material-symbols-outlined">smart_toy</span>
    <span id="nexbot-fab-badge">1</span>
</button>

<!-- Chat Panel -->
<div id="nexbot-panel" role="dialog" aria-label="NeXBot Chat Assistant">
    <!-- Header -->
    <div id="nexbot-header">
        <div id="nexbot-header-info">
            <div id="nexbot-avatar">
                <span class="material-symbols-outlined">smart_toy</span>
            </div>
            <div id="nexbot-header-text">
                <h3>NeXBot</h3>
                <p><span id="nexbot-status-dot"></span>Always here to help</p>
            </div>
        </div>
        <button id="nexbot-close-btn" type="button" aria-label="Close chat">
            <span class="material-symbols-outlined" style="font-size: 18px;">close</span>
        </button>
    </div>

    <!-- Messages -->
    <div id="nexbot-messages"></div>

    <!-- Input Area -->
    <div id="nexbot-input-area">
        <textarea id="nexbot-input" placeholder="Ask me anything..." rows="1" maxlength="1000"></textarea>
        <button id="nexbot-send-btn" type="button" aria-label="Send message">
            <span class="material-symbols-outlined">send</span>
        </button>
    </div>

    <!-- Footer -->
    <div id="nexbot-footer">
        Powered by <a href="https://ai.google.dev" target="_blank" rel="noopener">Gemini AI</a> · NeXLace Assistant
    </div>
</div>