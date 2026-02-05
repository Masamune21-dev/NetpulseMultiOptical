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
$opticalNames = snmp2_walk(
    $ip,
    $community,
    '1.3.6.1.4.1.14988.1.1.19.1.1.2'
);

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

    if (
        preg_match('/-?\d+/', $txRaw, $m1) &&
        preg_match('/-?\d+/', $rxRaw, $m2)
    ) {
        $opticalMap[$optIfName] = [
            'optical_index' => null, // optional
            'tx' => $m1[0] / 1000,
            'rx' => $m2[0] / 1000
        ];
    }
}

/* =========================================================
 *  SQL PREPARE
 * ========================================================= */
$stmt = $conn->prepare("
INSERT INTO interfaces
(device_id,if_index,if_name,if_alias,if_description,
 optical_index,rx_power,tx_power,last_seen,is_sfp,interface_type)
VALUES (?,?,?,?,?,?,?,?,NOW(),?,?)
ON DUPLICATE KEY UPDATE
 optical_index=VALUES(optical_index),
 rx_power=VALUES(rx_power),
 tx_power=VALUES(tx_power),
 last_seen=NOW(),
 is_sfp=VALUES(is_sfp),
 interface_type=VALUES(interface_type)
");

$inserted = 0;
$sfpCount = 0;

/* =========================================================
 *  LOOP INTERFACES
 * ========================================================= */
foreach ($ifIndex as $i => $raw) {

    if (!isset($ifOper[$i]))
        continue;

    $oper = (int) filter_var($ifOper[$i], FILTER_SANITIZE_NUMBER_INT);

    /* hanya interface UP */
    if ($oper !== 1)
        continue;


    $ifIdx = (int) filter_var($raw, FILTER_SANITIZE_NUMBER_INT);
    $name = trim(str_replace(['STRING:', '"'], '', $ifName[$i] ?? ''));
    $alias = trim(str_replace(['STRING:', '"'], '', $ifAlias[$i] ?? $name));
    $desc = trim(str_replace(['STRING:', '"'], '', $ifDescr[$i] ?? $name));

    $isSfp = 0;
    $type = 'other';
    $optIdx = null;
    $tx = null;
    $rx = null;

    if (
        stripos($name, 'sfp') !== false ||
        stripos($name, 'xgigabit') !== false ||
        stripos($name, '100ge') !== false
    ) {
        $isSfp = 1;

        if (stripos($name, '100ge') !== false) {
            $type = 'QSFP+';
        } else {
            $type = 'SFP+';
        }
    }

    /* ===============================
   GET OPTIC POWER
=============================== */

    if ($isSfp) {

        /* ==== MikroTik ==== */
        if (isset($opticalMap[$name])) {

            $optIdx = $opticalMap[$name]['optical_index'];
            $tx = $opticalMap[$name]['tx'];
            $rx = $opticalMap[$name]['rx'];

        } elseif (stripos($device['device_name'], 'huawei') !== false) {

            $cmd = __DIR__ . "/../huawei_telnet_expect.sh " . escapeshellarg($name);

            $out = [];
            exec("timeout 20s $cmd", $out);
            usleep(300000); 

            if ($rc !== 0)
                continue;

            foreach ($out as $l) {
                if (strpos($l, 'TX=') === 0)
                    $tx = floatval(substr($l, 3));
                if (strpos($l, 'RX=') === 0)
                    $rx = floatval(substr($l, 3));
            }
        }

    }

    /* ===== INSERT / UPDATE INTERFACES ===== */
    $stmt->bind_param(
        'iisssiddis',
        $device_id,
        $ifIdx,
        $name,
        $alias,
        $desc,
        $optIdx,
        $rx,
        $tx,
        $isSfp,
        $type
    );

    if ($stmt->execute()) {
        $inserted++;
        if ($isSfp)
            $sfpCount++;
    }

    /* ===== INSERT HISTORY (INI KUNCI) ===== */
    if ($isSfp && $tx !== null && $rx !== null) {

        $loss = $tx - $rx;

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

        $stmtUpd = $conn->prepare("
    UPDATE interfaces
    SET rx_power=?, tx_power=?, updated_at=NOW()
    WHERE device_id=? AND if_index=?
");

        $stmtUpd->bind_param(
            'ddii',
            $rx,
            $tx,
            $device_id,
            $ifIdx
        );

        $stmtUpd->execute();

    }
}

/* =========================================================
 *  OUTPUT
 * ========================================================= */

$result = [
    'success' => true,
    'inserted' => $inserted,
    'sfp_count' => $sfpCount,
    'optical_found' => count($opticalMap),
    'message' => "Discover OK: $inserted interfaces ($sfpCount SFP/QSFP)"
];

if (!$isCli) {
    echo json_encode($result);
}

return $result;

