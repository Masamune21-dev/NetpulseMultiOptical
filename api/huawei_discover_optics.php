<?php
require_once __DIR__.'/../config/database.php';

$conn = get_db_connection();

$device_id = 20;
$ip = "192.168.123.3";
$community = "bmkv1234";

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

$result = [];

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
}

echo json_encode([
    'success' => true,
    'count' => count($result),
    'interfaces' => $result
], JSON_PRETTY_PRINT);
?>