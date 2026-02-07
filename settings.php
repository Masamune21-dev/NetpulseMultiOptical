<?php
require_once 'includes/layout_start.php';

if (!in_array(($_SESSION['role'] ?? ''), ['admin', 'technician'])) {
    echo '<div class="alert error">Access denied</div>';
    require_once 'includes/layout_end.php';
    exit;
}
?>

<div class="topbar">
    <h1>
        <i class="fas fa-gear"></i>
        Settings
    </h1>
</div>

<!-- TABS -->
<div class="tabs">
    <button class="tab active" onclick="openTab('telegram')">
        <i class="fab fa-telegram"></i> Telegram Bot
    </button>
    <button class="tab" onclick="openTab('theme')">
        <i class="fas fa-palette"></i> Theme
    </button>
    <button class="tab" onclick="openTab('logs')">
        <i class="fas fa-file-lines"></i> Logs
    </button>
</div>

<!-- TELEGRAM TAB -->
<div class="tab-content active" id="telegram">
    <div class="card">
        <h3>
            <i class="fab fa-telegram"></i>
            Telegram Bot Settings
        </h3>

        <div class="form-group">
            <label>Bot Token</label>
            <input id="bot_token" placeholder="123456:ABC-DEF...">
        </div>

        <div class="form-group">
            <label>Chat ID</label>
            <input id="chat_id" placeholder="-100xxxxxxxx">
        </div>

        <div class="modal-actions">
            <button class="btn action-edit" onclick="saveTelegram()">
                <i class="fas fa-save"></i> Save
            </button>
            <button class="btn action-edit" style="background:#10b981" onclick="testTelegram()">
                <i class="fas fa-paper-plane"></i> Test Bot
            </button>
        </div>
    </div>
</div>

<!-- THEME TAB -->
<div class="tab-content" id="theme">
    <div class="card theme-card">
        <h3>
            <i class="fas fa-palette"></i>
            Theme
        </h3>

        <div class="theme-grid">
            <div class="theme-section">
                <div class="theme-section-title">Mode</div>
                <label class="theme-mode-card">
                    <input type="radio" name="theme" value="light">
                    <div class="mode-preview mode-light">
                        <div class="mode-bar"></div>
                        <div class="mode-lines"></div>
                    </div>
                    <div class="mode-label">Light</div>
                </label>
                <label class="theme-mode-card">
                    <input type="radio" name="theme" value="dark">
                    <div class="mode-preview mode-dark">
                        <div class="mode-bar"></div>
                        <div class="mode-lines"></div>
                    </div>
                    <div class="mode-label">Dark</div>
                </label>
            </div>

            <div class="theme-section">
                <div class="theme-section-title">Gradient Colors</div>
                <div class="theme-color-row">
                    <div class="theme-color-picker">
                        <label>Primary</label>
                        <div class="picker-field">
                            <input id="primary_color" type="color" value="#6366f1">
                            <span id="primaryColorHex">#6366f1</span>
                        </div>
                    </div>
                    <div class="theme-color-picker">
                        <label>Secondary</label>
                        <div class="picker-field">
                            <input id="primary_soft" type="color" value="#8b5cf6">
                            <span id="primarySoftHex">#8b5cf6</span>
                        </div>
                    </div>
                </div>

                <div class="theme-preview">
                    <div class="preview-header">
                        <div class="preview-title">Preview</div>
                        <div class="preview-sub">Buttons, sidebar & accents</div>
                    </div>
                    <div id="themeGradientPreview" class="preview-gradient"></div>
                    <div class="preview-samples">
                        <div class="preview-pill">Primary</div>
                        <div class="preview-pill outline">Outline</div>
                        <div class="preview-chip">Sidebar</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-actions">
            <button class="btn action-edit" onclick="saveTheme()">
                <i class="fas fa-save"></i> Save Theme
            </button>
            <button class="btn btn-outline" onclick="resetThemeColors()">
                <i class="fas fa-rotate-left"></i> Reset Colors
            </button>
        </div>
    </div>
</div>

<!-- LOGS TAB -->
<div class="tab-content" id="logs">
    <div class="card">
        <h3>
            <i class="fas fa-file-lines"></i>
            Security Logs
        </h3>

        <div class="form-group">
            <label>Latest Entries</label>
            <div class="log-toolbar">
                <input type="text" id="logFilter" placeholder="Filter keyword (username, IP, event)...">
                <select id="logLevelFilter">
                    <option value="all">All</option>
                    <option value="LOGIN_SUCCESS">LOGIN_SUCCESS</option>
                    <option value="LOGIN_FAILED">LOGIN_FAILED</option>
                    <option value="LOGIN_RATE_LIMIT">LOGIN_RATE_LIMIT</option>
                    <option value="SESSION_HIJACK">SESSION_HIJACK</option>
                </select>
            </div>
            <div class="log-box" id="securityLogBox">Loading...</div>
        </div>

        <div class="modal-actions">
            <button class="btn btn-outline" onclick="refreshSecurityLogs()">
                <i class="fas fa-rotate"></i> Refresh
            </button>
        </div>
    </div>
</div>

<style>
    .theme-card {
        position: relative;
    }

    .theme-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr);
        gap: 24px;
        margin-top: 16px;
    }

    .theme-section {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .theme-section-title {
        font-size: 0.85rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--text-soft);
    }

    .theme-mode-card {
        display: grid;
        grid-template-columns: 72px 1fr;
        align-items: center;
        gap: 14px;
        padding: 12px 14px;
        border-radius: 14px;
        border: 1px solid var(--border);
        background: var(--surface-glass);
        cursor: pointer;
        transition: all 0.2s ease;
        position: relative;
    }

    .theme-mode-card input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .theme-mode-card .mode-label {
        font-weight: 600;
        color: var(--text);
    }

    .theme-mode-card .mode-preview {
        width: 72px;
        height: 48px;
        border-radius: 10px;
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(0, 0, 0, 0.08);
    }

    .theme-mode-card .mode-bar {
        height: 12px;
        background: var(--primary-gradient);
    }

    .theme-mode-card .mode-lines {
        display: grid;
        gap: 6px;
        padding: 8px;
    }

    .theme-mode-card .mode-lines::before,
    .theme-mode-card .mode-lines::after {
        content: '';
        height: 6px;
        border-radius: 6px;
        background: rgba(148, 163, 184, 0.35);
    }

    .theme-mode-card .mode-dark {
        background: #0f172a;
    }

    .theme-mode-card .mode-dark .mode-lines::before,
    .theme-mode-card .mode-dark .mode-lines::after {
        background: rgba(148, 163, 184, 0.25);
    }

    .theme-mode-card .mode-light {
        background: #f8fafc;
    }

    .theme-mode-card:has(input:checked) {
        border-color: var(--primary);
        box-shadow: 0 8px 24px rgba(99, 102, 241, 0.18);
        transform: translateY(-2px);
    }

    .theme-color-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 16px;
    }

    .theme-color-picker label {
        display: block;
        font-size: 0.85rem;
        color: var(--text-soft);
        margin-bottom: 6px;
    }

    .picker-field {
        display: flex;
        align-items: center;
        gap: 10px;
        background: var(--surface-glass);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 8px 10px;
    }

    .picker-field input[type="color"] {
        width: 44px;
        height: 36px;
        border: none;
        background: transparent;
        padding: 0;
        cursor: pointer;
    }

    .picker-field span {
        font-weight: 600;
        color: var(--text);
        font-size: 0.9rem;
    }

    .theme-preview {
        margin-top: 6px;
        border-radius: 16px;
        border: 1px solid var(--border);
        background: var(--surface);
        padding: 14px 16px;
        display: grid;
        gap: 12px;
    }

    .preview-header {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: 12px;
    }

    .preview-title {
        font-weight: 700;
        color: var(--text);
    }

    .preview-sub {
        font-size: 0.75rem;
        color: var(--text-soft);
    }

    .preview-gradient {
        height: 40px;
        border-radius: 12px;
        border: 1px solid rgba(0, 0, 0, 0.08);
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
    }

    .preview-samples {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .preview-pill {
        padding: 6px 14px;
        border-radius: 999px;
        background: var(--primary-gradient);
        color: #fff;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .preview-pill.outline {
        background: transparent;
        color: var(--primary);
        border: 1.5px solid var(--primary);
    }

    .preview-chip {
        padding: 6px 12px;
        border-radius: 10px;
        color: var(--sidebar-title);
        background: var(--sidebar);
        font-size: 0.75rem;
        font-weight: 600;
    }

    @media (max-width: 900px) {
        .theme-grid {
            grid-template-columns: 1fr;
        }
    }

    .log-box {
        background: #0f172a;
        color: #e2e8f0;
        border-radius: 12px;
        padding: 12px 14px;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
        font-size: 0.8rem;
        line-height: 1.5;
        max-height: 320px;
        overflow: auto;
        white-space: pre-wrap;
        border: 1px solid rgba(148, 163, 184, 0.25);
    }

    .log-toolbar {
        display: grid;
        grid-template-columns: 1fr 220px;
        gap: 10px;
        margin-bottom: 10px;
    }

    .log-toolbar input,
    .log-toolbar select {
        background: var(--surface-glass);
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 8px 10px;
        color: var(--text);
        font-size: 0.85rem;
    }

    .log-row {
        display: grid;
        grid-template-columns: 140px 160px 150px 1fr;
        gap: 10px;
        padding: 6px 8px;
        border-bottom: 1px dashed rgba(148, 163, 184, 0.2);
        align-items: center;
    }

    .log-row:last-child {
        border-bottom: none;
    }

    .log-time {
        color: #94a3b8;
    }

    .log-event {
        font-weight: 600;
    }

    .log-event.success { color: #22c55e; }
    .log-event.warn { color: #f59e0b; }
    .log-event.error { color: #ef4444; }
    .log-event.info { color: #38bdf8; }

    .log-ip {
        color: #93c5fd;
        font-weight: 600;
    }

    .log-details {
        color: #e2e8f0;
        font-size: 0.78rem;
    }

    @media (max-width: 900px) {
        .log-row {
            grid-template-columns: 1fr;
        }
        .log-toolbar {
            grid-template-columns: 1fr;
        }
    }
</style>

<script src="assets/js/settings.js"></script>

<?php
require_once 'includes/layout_end.php';
?>
