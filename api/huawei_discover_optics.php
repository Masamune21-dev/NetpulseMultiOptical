<?php
require_once __DIR__.'/../config/database.php';

$isCli = (php_sapi_name() === 'cli');

$conn = get_db_connection();

$device_id = 20;
$ip = "192.168.123.3";
$community = "bmkv1234";

/* ========= SETTINGS (TELEGRAM) ========= */
$telegram = [
    'bot_token' => '',
    'chat_id' => ''
];

$settingsRes = $conn->query("
    SELECT name, value
    FROM settings
    WHERE name IN ('bot_token', 'chat_id')
");
if ($settingsRes) {
    while ($row = $settingsRes->fetch_assoc()) {
        if ($row['name'] === 'bot_token') {
            $telegram['bot_token'] = trim((string) $row['value']);
        } elseif ($row['name'] === 'chat_id') {
            $telegram['chat_id'] = trim((string) $row['value']);
        }
    }
}

/* ========= ALERT HELPERS ========= */
$alertStateFile = __DIR__ . '/../storage/alert_state.json';

if (!function_exists('load_alert_state')) {
    function load_alert_state(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }
        $fp = fopen($path, 'r');
        if (!$fp) {
            return [];
        }
        flock($fp, LOCK_SH);
        $raw = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}

if (!function_exists('save_alert_state')) {
    function save_alert_state(string $path, array $state): void
    {
        $fp = fopen($path, 'c+');
        if (!$fp) {
            return;
        }
        flock($fp, LOCK_EX);
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($state));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

if (!function_exists('telegram_send_message')) {
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
}

$names = snmp2_walk($ip, $community, "1.3.6.1.2.1.2.2.1.2");
$status = snmp2_walk($ip, $community, "1.3.6.1.2.1.2.2.1.8");

$ports = [];

foreach($names as $i => $v) {
    if(!isset($status[$i])) continue;
    
    $oper = intval(preg_replace('/\D/', '', $status[$i]));
    $name = trim(str_replace(['STRING:', '"'], '', $v));
    
    // Simpan semua port, tidak hanya yang oper=1
    if(strpos($name, 'XGigabitEthernet') !== false || strpos($name, '100GE') !== false) {
        $ports[] = [
            'name' => $name,
            'oper' => $oper
        ];
    }
}

$stmt = $conn->prepare("
UPDATE interfaces
SET
    tx_power = ?,
    rx_power = ?,
    oper_status = ?,
    last_seen = NOW(),
    updated_at = NOW()
WHERE device_id = ? AND if_name = ?
");

$stmtIface = $conn->prepare("
    SELECT if_alias, if_description
    FROM interfaces
    WHERE device_id = ? AND if_name = ?
    LIMIT 1
");

$result = [];
$alertState = $isCli ? load_alert_state($alertStateFile) : [];
$alertStateDirty = false;

foreach($ports as $port) {
    $p = $port['name'];
    $oper = $port['oper'];
    
    $tx = null;
    $rx = null;
    
    if ($oper == 1) { // Interface up
        $cmd = __DIR__."/../huawei_telnet_expect.sh ".escapeshellarg($p);
        $out = [];
        
        exec("timeout 8 $cmd", $out);
        
        foreach($out as $l) {
            if(strpos($l, 'TX=') === 0) $tx = floatval(substr($l, 3));
            if(strpos($l, 'RX=') === 0) $rx = floatval(substr($l, 3));
        }
    } else { // Interface down
        $rx = -40.00; // Default -40 dBm untuk interface down
        $tx = null;
    }
    
    if ($rx !== null) {
        $stmt->bind_param('ddiis', $tx, $rx, $oper, $device_id, $p);
        $stmt->execute();
        
        // Insert ke interface_stats
        $loss = ($tx !== null && $rx !== null) ? ($tx - $rx) : null;
        
        $conn->query("
        INSERT INTO interface_stats
        (device_id, if_index, tx_power, rx_power, loss, created_at)
        SELECT
            device_id,
            if_index,
            " . ($tx ?? 'NULL') . ",
            $rx,
            " . ($loss ?? 'NULL') . ",
            NOW()
        FROM interfaces
        WHERE device_id = $device_id AND if_name = '$p'
        LIMIT 1
        ");
        
        $result[] = [
            'port' => $p,
            'tx' => $tx,
            'rx' => $rx,
            'loss' => $loss,
            'oper' => $oper
        ];
    }

    /* ===== TELEGRAM ALERTS (CLI ONLY) ===== */
    // ALERTS DISABLED for huawei_discover_optics.php
}

// ALERTS DISABLED for huawei_discover_optics.php

echo json_encode([
    'success' => true,
    'count' => count($result),
    'interfaces' => $result
], JSON_PRETTY_PRINT);
?>
