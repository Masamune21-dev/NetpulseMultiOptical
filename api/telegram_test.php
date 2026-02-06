<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();
if (!$auth->is_logged_in() || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$conn = get_db_connection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB connection failed']);
    exit;
}

$settings = [
    'bot_token' => '',
    'chat_id' => ''
];

$res = $conn->query("
    SELECT name, value
    FROM settings
    WHERE name IN ('bot_token', 'chat_id')
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        if ($row['name'] === 'bot_token') {
            $settings['bot_token'] = trim((string) $row['value']);
        } elseif ($row['name'] === 'chat_id') {
            $settings['chat_id'] = trim((string) $row['value']);
        }
    }
}

if ($settings['bot_token'] === '' || $settings['chat_id'] === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Bot token atau chat ID belum diset']);
    exit;
}

function telegram_send_message(string $botToken, string $chatId, string $text): bool
{
    if ($botToken === '' || $chatId === '' || $text === '') {
        return false;
    }

    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $payload = http_build_query([
        'chat_id' => $chatId,
        'text' => $text,
        'disable_web_page_preview' => 1
    ]);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $result = curl_exec($ch);
        $ok = ($result !== false);
        curl_close($ch);
        return $ok;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $payload,
            'timeout' => 10
        ]
    ]);
    $result = @file_get_contents($url, false, $context);
    return ($result !== false);
}

$message = "Test Telegram dari Mikrotik CRS Monitor\nTime: " . date('Y-m-d H:i:s');
$sent = telegram_send_message($settings['bot_token'], $settings['chat_id'], $message);

if (!$sent) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Gagal mengirim pesan ke Telegram']);
    exit;
}

echo json_encode(['success' => true]);
