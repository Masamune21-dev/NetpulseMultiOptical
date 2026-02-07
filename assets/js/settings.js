document.addEventListener('DOMContentLoaded', loadSettings);

function openTab(id) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

    document.querySelector(`[onclick="openTab('${id}')"]`).classList.add('active');
    document.getElementById(id).classList.add('active');

    if (id === 'logs') {
        refreshSecurityLogs();
    }
}

function loadSettings() {
    fetch('api/settings.php')
        .then(r => r.json())
        .then(d => {

            // Telegram
            if (typeof bot_token !== 'undefined') {
                bot_token.value = d.bot_token || '';
            }
            if (typeof chat_id !== 'undefined') {
                chat_id.value = d.chat_id || '';
            }

            // Theme
            const theme = d.theme || 'light';
            document.body.dataset.theme = theme;

            const radio = document.querySelector(
                `input[name="theme"][value="${theme}"]`
            );
            if (radio) radio.checked = true;

            // Theme colors
            const primaryInput = document.getElementById('primary_color');
            const softInput = document.getElementById('primary_soft');
            const preview = document.getElementById('themeGradientPreview');
            const primaryHex = document.getElementById('primaryColorHex');
            const softHex = document.getElementById('primarySoftHex');

            if (primaryInput) primaryInput.value = d.primary_color || '#6366f1';
            if (softInput) softInput.value = d.primary_soft || '#8b5cf6';
            if (primaryHex) primaryHex.textContent = (primaryInput && primaryInput.value) || '#6366f1';
            if (softHex) softHex.textContent = (softInput && softInput.value) || '#8b5cf6';

            if (primaryInput && softInput) {
                applyThemeColors(primaryInput.value, softInput.value, preview);
                try {
                    localStorage.setItem('primary_color', primaryInput.value);
                    localStorage.setItem('primary_soft', softInput.value);
                } catch (e) {}
                primaryInput.addEventListener('input', () => {
                    if (primaryHex) primaryHex.textContent = primaryInput.value;
                    applyThemeColors(primaryInput.value, softInput.value, preview);
                });
                softInput.addEventListener('input', () => {
                    if (softHex) softHex.textContent = softInput.value;
                    applyThemeColors(primaryInput.value, softInput.value, preview);
                });
            }

            // Live theme preview on radio change
            document.querySelectorAll('input[name="theme"]').forEach(input => {
                input.addEventListener('change', () => {
                    document.body.dataset.theme = input.value;
                    document.documentElement.dataset.theme = input.value;
                });
            });
        });
}

function saveTelegram() {
    if (window.roleUtils && !window.roleUtils.requireAdmin()) return;
    fetch('api/settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            bot_token: bot_token.value,
            chat_id: chat_id.value
        })
    })
    .then(() => showNotification('Telegram settings saved', 'success'));
}

function saveTheme() {
    if (window.roleUtils && !window.roleUtils.requireAdmin()) return;
    const theme = document.querySelector('input[name="theme"]:checked').value;
    const primaryInput = document.getElementById('primary_color');
    const softInput = document.getElementById('primary_soft');
    const primaryColor = primaryInput ? primaryInput.value : '#6366f1';
    const primarySoft = softInput ? softInput.value : '#8b5cf6';

    document.body.dataset.theme = theme; // 🔥 langsung apply
    applyThemeColors(primaryColor, primarySoft, document.getElementById('themeGradientPreview'));

    fetch('api/settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            theme,
            primary_color: primaryColor,
            primary_soft: primarySoft
        })
    }).then(() => {
        try {
            localStorage.setItem('primary_color', primaryColor);
            localStorage.setItem('primary_soft', primarySoft);
        } catch (e) {}
        showNotification('Theme saved', 'success');
    });
}

function applyThemeColors(primaryColor, primarySoft, previewEl) {
    document.documentElement.style.setProperty('--primary', primaryColor);
    document.documentElement.style.setProperty('--primary-soft', primarySoft);
    document.documentElement.style.setProperty(
        '--primary-gradient',
        `linear-gradient(135deg, ${primaryColor} 0%, ${primarySoft} 100%)`
    );
    document.documentElement.style.setProperty(
        '--sidebar',
        `linear-gradient(160deg, ${primaryColor} 0%, ${primarySoft} 55%, ${primaryColor} 100%)`
    );
    if (previewEl) {
        previewEl.style.background = `linear-gradient(135deg, ${primaryColor}, ${primarySoft})`;
    }
}

function resetThemeColors() {
    if (window.roleUtils && !window.roleUtils.requireAdmin()) return;
    const primary = '#6366f1';
    const soft = '#8b5cf6';
    const primaryInput = document.getElementById('primary_color');
    const softInput = document.getElementById('primary_soft');
    if (primaryInput) primaryInput.value = primary;
    if (softInput) softInput.value = soft;
    applyThemeColors(primary, soft, document.getElementById('themeGradientPreview'));
    fetch('api/settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            primary_color: primary,
            primary_soft: soft
        })
    }).then(() => {
        try {
            localStorage.setItem('primary_color', primary);
            localStorage.setItem('primary_soft', soft);
        } catch (e) {}
        showNotification('Theme colors reset', 'success');
    });
}


function testTelegram() {
    if (window.roleUtils && !window.roleUtils.requireAdmin()) return;
    fetch('api/telegram_test.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    })
    .then(r => r.json())
    .then(d => {
        if (d && d.success) {
            showNotification('Test message sent', 'success');
        } else {
            showNotification(d.error || 'Failed to send test message', 'error');
        }
    })
    .catch(() => {
        showNotification('Failed to send test message', 'error');
    });
}

function refreshSecurityLogs() {
    fetch('api/logs.php?type=security')
        .then(r => r.json())
        .then(d => {
            const box = document.getElementById('securityLogBox');
            if (!box) return;
            if (!d || !d.success) {
                box.textContent = d && d.error ? d.error : 'Failed to load logs';
                return;
            }
            const raw = d.data || '';
            renderLogs(raw);
        })
        .catch(() => {
            const box = document.getElementById('securityLogBox');
            if (box) box.textContent = 'Failed to load logs';
        });
}

function renderLogs(raw) {
    const box = document.getElementById('securityLogBox');
    if (!box) return;

    const filterInput = document.getElementById('logFilter');
    const levelSelect = document.getElementById('logLevelFilter');
    const keyword = (filterInput ? filterInput.value : '').toLowerCase();
    const level = levelSelect ? levelSelect.value : 'all';

    const lines = raw.split('\n').filter(Boolean);
    const rows = [];

    lines.forEach(line => {
        // format: [time] [ip] [event] details [User-Agent: ...]
        const match = line.match(/^\[(.+?)\]\s+\[(.+?)\]\s+\[(.+?)\]\s+(.*)$/);
        if (!match) return;
        const time = match[1];
        const ip = match[2];
        const event = match[3];
        const details = match[4];

        if (level !== 'all' && event !== level) return;
        const hay = (line + ' ' + details).toLowerCase();
        if (keyword && !hay.includes(keyword)) return;

        const cls =
            event === 'LOGIN_SUCCESS' ? 'success' :
            event === 'LOGIN_FAILED' ? 'error' :
            event === 'LOGIN_RATE_LIMIT' ? 'warn' :
            event === 'SESSION_HIJACK' ? 'error' : 'info';

        rows.push(`
            <div class="log-row">
                <div class="log-time">${time}</div>
                <div class="log-ip">${ip}</div>
                <div class="log-event ${cls}">${event}</div>
                <div class="log-details">${details}</div>
            </div>
        `);
    });

    box.innerHTML = rows.length ? rows.join('') : 'No logs';
}

document.addEventListener('input', (e) => {
    if (e.target && (e.target.id === 'logFilter' || e.target.id === 'logLevelFilter')) {
        refreshSecurityLogs();
    }
});
