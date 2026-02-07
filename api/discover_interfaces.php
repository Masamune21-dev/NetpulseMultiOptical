<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

/* ===== DETECT CLI FIRST (WAJIB PALING ATAS) ===== */
$isCli = (php_sapi_name() === 'cli');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

/* ===== HEADER HANYA UNTUK HTTP ===== */
if (!$isCli) {
    header('Content-Type: application/json');
}

/* ===== AUTH HANYA UNTUK WEB ===== */
if (!$isCli) {
    $auth = new Auth();
    if (!$auth->is_logged_in()) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
}

/* ========= DB ========= */
$conn = get_db_connection();
if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'DB connection failed']);
    exit;
}

/* ========= SETTINGS (TELEGRAM) ========= */
$telegram = [
    'bot_token' => '',
    'chat_id' => '',
    'rx_threshold' => -25.0
];

$settingsRes = $conn->query("
    SELECT name, value
    FROM settings
    WHERE name IN ('bot_token', 'chat_id', 'rx_power_threshold')
");
if ($settingsRes) {
    while ($row = $settingsRes->fetch_assoc()) {
        if ($row['name'] === 'bot_token') {
            $telegram['bot_token'] = trim((string) $row['value']);
        } elseif ($row['name'] === 'chat_id') {
            $telegram['chat_id'] = trim((string) $row['value']);
        } elseif ($row['name'] === 'rx_power_threshold') {
            $telegram['rx_threshold'] = (float) $row['value'];
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

/* ========= INPUT ========= */
$device_id = (int) ($_GET['device_id'] ?? 0);
if (!$device_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid device_id']);
    exit;
}

/* ========= DEVICE ========= */
$device = $conn->query("SELECT * FROM snmp_devices WHERE id=$device_id")->fetch_assoc();
if (!$device) {
    echo json_encode(['success' => false, 'error' => 'Device not found']);
    exit;
}

$ip = $device['ip_address'];
$community = $device['community'];

/* =========================================================
 *  IF-MIB
 * ========================================================= */
$ifIndex = @snmp2_walk($ip, $community, '1.3.6.1.2.1.2.2.1.1');
$ifName = @snmp2_walk($ip, $community, '1.3.6.1.2.1.31.1.1.1.1');
$ifDescr = @snmp2_walk($ip, $community, '1.3.6.1.2.1.2.2.1.2');
$ifAlias = @snmp2_walk($ip, $community, '1.3.6.1.2.1.31.1.1.1.18');
$ifOper = @snmp2_walk($ip, $community, '1.3.6.1.2.1.2.2.1.8');

if (!$ifIndex || !$ifName) {
    echo json_encode(['success' => false, 'error' => 'Cannot read IF-MIB']);
    exit;
}

/* =========================================================
 *  OPTICAL MAP (MikroTik)
 *  optical-name index != power index
 *  power index = optical index + 2
 * ========================================================= */
$opticalMap = [];

/* ambil IF-MIB mapping */
$ifNameMap = [];
foreach ($ifIndex as $i => $raw) {
    $idx = (int) filter_var($raw, FILTER_SANITIZE_NUMBER_INT);
    $name = trim(str_replace(['STRING:', '"'], '', $ifName[$i]));
    $ifNameMap[$name] = $idx;
}

/* ambil optical-name */
$opticalNames = @snmp2_walk(
    $ip,
    $community,
    '1.3.6.1.4.1.14988.1.1.19.1.1.2'
);

if ($opticalNames === false) {
    $opticalNames = [];
}

foreach ($opticalNames as $oid => $val) {
    $optIfName = trim(str_replace(['STRING:', '"'], '', $val));

    if (!isset($ifNameMap[$optIfName])) {
        continue;
    }

    $ifIdx = $ifNameMap[$optIfName];

    $txRaw = snmp2_get(
        $ip,
        $community,
        "1.3.6.1.4.1.14988.1.1.19.1.1.9.$ifIdx"
    );
    $rxRaw = snmp2_get(
        $ip,
        $community,
        "1.3.6.1.4.1.14988.1.1.19.1.1.10.$ifIdx"
    );

    if ($txRaw !== false && $rxRaw !== false) {
        $txMatch = preg_match('/-?\d+/', $txRaw, $m1);
        $rxMatch = preg_match('/-?\d+/', $rxRaw, $m2);
        
        if ($txMatch && $rxMatch) {
            $opticalMap[$optIfName] = [
                'optical_index' => null,
                'tx' => $m1[0] / 1000,
                'rx' => $m2[0] / 1000,
                'oper' => 1 // Diasumsikan up karena dapat membaca power
            ];
        }
    }
}

/* =========================================================
 *  SQL PREPARE
 * ========================================================= */
$stmt = $conn->prepare("
INSERT INTO interfaces
(device_id,if_index,if_name,if_alias,if_description,
 optical_index,rx_power,tx_power,oper_status,last_seen,is_sfp,interface_type)
VALUES (?,?,?,?,?,?,?,?,?,NOW(),?,?)
ON DUPLICATE KEY UPDATE
 optical_index=VALUES(optical_index),
 rx_power=VALUES(rx_power),
 tx_power=VALUES(tx_power),
 oper_status=VALUES(oper_status),
 last_seen=NOW(),
 is_sfp=VALUES(is_sfp),
 interface_type=VALUES(interface_type)
");

$inserted = 0;
$sfpCount = 0;
$downSfpCount = 0;
$alertState = $isCli ? load_alert_state($alertStateFile) : [];
$alertStateDirty = false;

/* =========================================================
 *  LOOP INTERFACES - PERUBAHAN UTAMA: PROSES SEMUA, TIDAK HANYA UP
 * ========================================================= */
foreach ($ifIndex as $i => $raw) {
    $ifIdx = (int) filter_var($raw, FILTER_SANITIZE_NUMBER_INT);
    $name = trim(str_replace(['STRING:', '"'], '', $ifName[$i] ?? ''));
    $alias = trim(str_replace(['STRING:', '"'], '', $ifAlias[$i] ?? $name));
    $desc = trim(str_replace(['STRING:', '"'], '', $ifDescr[$i] ?? $name));
    
    // Ambil status operasional (default ke 2 = down jika tidak ada)
    $oper = 2; // default down
    if (isset($ifOper[$i])) {
        $oper = (int) filter_var($ifOper[$i], FILTER_SANITIZE_NUMBER_INT);
    }

    $isSfp = 0;
    $type = 'other';
    $optIdx = null;
    $tx = null;
    $rx = null;

    // Deteksi apakah ini interface SFP/QSFP
    if (
        stripos($name, 'sfp') !== false ||
        stripos($name, 'xgigabit') !== false ||
        stripos($name, '100ge') !== false ||
        stripos($name, 'gpon') !== false ||
        stripos($name, 'xpon') !== false
    ) {
        $isSfp = 1;

        if (stripos($name, '100ge') !== false) {
            $type = 'QSFP+';
        } elseif (stripos($name, 'gpon') !== false || stripos($name, 'xpon') !== false) {
            $type = 'PON';
        } else {
            $type = 'SFP+';
        }
    }

    /* ===============================
       GET OPTIC POWER - PERUBAHAN UTAMA
    =============================== */
    if ($isSfp) {
        /* ==== Interface UP ==== */
        if ($oper == 1) {
            /* MikroTik optical map */
            if (isset($opticalMap[$name])) {
                $optIdx = $opticalMap[$name]['optical_index'];
                $tx = $opticalMap[$name]['tx'];
                $rx = $opticalMap[$name]['rx'];
            } 
            /* Huawei device - coba telnet */
            elseif (stripos($device['device_name'], 'huawei') !== false) {
                $cmd = __DIR__ . "/../huawei_telnet_expect.sh " . escapeshellarg($name);
                $out = [];
                $rc = 0;
                exec("timeout 20s $cmd 2>&1", $out, $rc);
                
                if ($rc === 0) {
                    foreach ($out as $l) {
                        if (strpos($l, 'TX=') === 0)
                            $tx = floatval(substr($l, 3));
                        if (strpos($l, 'RX=') === 0)
                            $rx = floatval(substr($l, 3));
                    }
                }
            }
        }
        /* ==== Interface DOWN ==== */
        else {
            // Set default -40 dBm untuk interface down
            $rx = -40.00;
            $tx = null; // TX tidak terukur saat down
            
            $downSfpCount++;
        }
    }

    /* ===== TELEGRAM ALERTS (CLI ONLY) ===== */
    if ($isCli && $isSfp) {
        $deviceLabel = trim(($device['device_name'] ?? '') . ' (' . $ip . ')');
        $ifaceLabel = $alias !== '' ? $alias : $name;
        $timeLabel = date('Y-m-d H:i:s');

        $stateKey = $device_id . ':' . $ifIdx;
        $prevState = $alertState[$stateKey] ?? null;
        $prevOper = is_array($prevState) ? (int)($prevState['oper'] ?? 0) : 0;
        $prevHadOptic = is_array($prevState) ? (bool)($prevState['had_optic'] ?? false) : false;

        $hasOpticUp = ($oper == 1) && ($rx !== null || $tx !== null);

        // Update state first (so we always have current snapshot)
        $alertState[$stateKey] = [
            'oper' => (int)$oper,
            'had_optic' => ($hasOpticUp || $prevHadOptic)
        ];
        $alertStateDirty = true;

        // Alert hanya saat transisi up <-> down, dan hanya jika pernah up dengan optic
        if ($prevOper === 1 && $oper != 1 && ($prevHadOptic || $hasOpticUp)) {
            $msg = "🔴 LINK DOWN\n📟 Device: {$deviceLabel}\n🔌 Interface: {$ifaceLabel}\n🕒 Time: {$timeLabel}";
            telegram_send_message($telegram['bot_token'], $telegram['chat_id'], $msg);
        } elseif ($prevOper !== 1 && $oper == 1 && ($prevHadOptic || $hasOpticUp)) {
            $msg = "🟢 LINK UP\n📟 Device: {$deviceLabel}\n🔌 Interface: {$ifaceLabel}\n📡 RX: " . ($rx !== null ? "{$rx} dBm" : "N/A") . "\n🕒 Time: {$timeLabel}";
            telegram_send_message($telegram['bot_token'], $telegram['chat_id'], $msg);
        }
    }

    /* ===== INSERT / UPDATE INTERFACES ===== */
    $stmt->bind_param(
        'iisssiddiis',
        $device_id,
        $ifIdx,
        $name,
        $alias,
        $desc,
        $optIdx,
        $rx,
        $tx,
        $oper,
        $isSfp,
        $type
    );

    if ($stmt->execute()) {
        $inserted++;
        if ($isSfp) {
            $sfpCount++;
        }
    }

    /* ===== INSERT HISTORY (KUNCI: SIMPAN JUGA SAAT DOWN) ===== */
    if ($isSfp && $rx !== null) {
        $loss = ($tx !== null && $rx !== null) ? ($tx - $rx) : null;
        
        $stmtHist = $conn->prepare("
            INSERT INTO interface_stats
            (device_id, if_index, tx_power, rx_power, loss, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmtHist->bind_param(
            'iiddd',
            $device_id,
            $ifIdx,
            $tx,
            $rx,
            $loss
        );
        
        $stmtHist->execute();
        
        // Update interfaces dengan nilai terbaru
        $stmtUpd = $conn->prepare("
            UPDATE interfaces
            SET rx_power=?, tx_power=?, oper_status=?, updated_at=NOW()
            WHERE device_id=? AND if_index=?
        ");
        
        $stmtUpd->bind_param(
            'ddiii',
            $rx,
            $tx,
            $oper,
            $device_id,
            $ifIdx
        );
        
        $stmtUpd->execute();
    }
}

if ($isCli && $alertStateDirty) {
    save_alert_state($alertStateFile, $alertState);
}

/* =========================================================
 *  OUTPUT
 * ========================================================= */
$result = [
    'success' => true,
    'inserted' => $inserted,
    'sfp_count' => $sfpCount,
    'sfp_down_count' => $downSfpCount,
    'optical_found' => count($opticalMap),
    'message' => "Discover OK: $inserted interfaces ($sfpCount SFP/QSFP, $downSfpCount down)"
];

if (!$isCli) {
    echo json_encode($result);
}

return $result;
?>
