document.addEventListener('DOMContentLoaded', loadSettings);

function openTab(id) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

    document.querySelector(`[onclick="openTab('${id}')"]`).classList.add('active');
    document.getElementById(id).classList.add('active');
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
        });
}

function saveTelegram() {
    fetch('api/settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            bot_token: bot_token.value,
            chat_id: chat_id.value
        })
    })
    .then(() => alert('Telegram settings saved'));
}

function saveTheme() {
    const theme = document.querySelector('input[name="theme"]:checked').value;

    document.body.dataset.theme = theme; // 🔥 langsung apply

    fetch('api/settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ theme })
    }).then(() => {
        alert('Theme saved');
    });
}


function testTelegram() {
    fetch('api/telegram_test.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    })
    .then(r => r.json())
    .then(d => {
        if (d && d.success) {
            alert('Test message sent');
        } else {
            alert(d.error || 'Failed to send test message');
        }
    })
    .catch(() => {
        alert('Failed to send test message');
    });
}
