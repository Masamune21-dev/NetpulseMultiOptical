<?php
require_once 'includes/layout_start.php';

/* Pastikan hanya admin */
if (($_SESSION['role'] ?? '') !== 'admin') {
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
            <button class="btn" onclick="saveTelegram()">
                <i class="fas fa-save"></i> Save
            </button>
            <button class="btn" style="background:#10b981" onclick="testTelegram()">
                <i class="fas fa-paper-plane"></i> Test Bot
            </button>
        </div>
    </div>
</div>

<!-- THEME TAB -->
<div class="tab-content" id="theme">
    <div class="card">
        <h3>
            <i class="fas fa-palette"></i>
            Theme
        </h3>

        <div class="form-group">
            <label>
                <input type="radio" name="theme" value="light">
                Light
            </label>
        </div>

        <div class="form-group">
            <label>
                <input type="radio" name="theme" value="dark">
                Dark
            </label>
        </div>

        <div class="modal-actions">
            <button class="btn" onclick="saveTheme()">
                <i class="fas fa-save"></i> Save Theme
            </button>
        </div>
    </div>
</div>

<script src="assets/js/settings.js"></script>

<?php
require_once 'includes/layout_end.php';
?>
