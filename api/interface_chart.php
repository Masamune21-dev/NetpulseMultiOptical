<?php
require_once __DIR__ . '/../config/database.php';
$conn = get_db_connection();

$device_id = (int)($_GET['device_id'] ?? 0);
$if_index  = (int)($_GET['if_index'] ?? 0);
$range     = $_GET['range'] ?? '1h';

$interval = match ($range) {
    '1h'  => '1 HOUR',
    '1d'  => '24 HOUR',
    '3d'  => '72 HOUR',
    '7d'  => '7 DAY',
    '30d' => '30 DAY',
    '1y'  => '1 YEAR',
    default => '1 HOUR'
};

// Query untuk mendapatkan data statistik
if (strpos($interval, 'YEAR') !== false || strpos($interval, 'DAY') !== false) {
    $sql = "
    SELECT 
        created_at,
        tx_power,
        rx_power,
        loss
    FROM interface_stats
    WHERE device_id = ?
      AND if_index = ?
      AND created_at >= DATE_SUB(NOW(), INTERVAL $interval)
    ORDER BY created_at ASC
    ";
} else {
    $sql = "
    SELECT 
        created_at,
        tx_power,
        rx_power,
        loss
    FROM interface_stats
    WHERE device_id = ?
      AND if_index = ?
      AND created_at >= NOW() - INTERVAL $interval
    ORDER BY created_at ASC
    ";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $device_id, $if_index);
$stmt->execute();

$res = $stmt->get_result();
$data = [];

// Data default untuk interface down
$default_down_rx = -40.00;
$default_down_loss = null; // atau sesuaikan dengan logika loss

while ($row = $res->fetch_assoc()) {
    // Jika rx_power null atau kosong (interface down), set ke -40 dBm
    if ($row['rx_power'] === null || $row['rx_power'] === '') {
        $row['rx_power'] = $default_down_rx;
    } else {
        $row['rx_power'] = (float) $row['rx_power'];
    }
    
    // Jika tx_power null, set ke null atau default
    if ($row['tx_power'] !== null && $row['tx_power'] !== '') {
        $row['tx_power'] = (float) $row['tx_power'];
    }
    
    // Hitung loss jika tx dan rx ada
    if ($row['tx_power'] !== null && $row['rx_power'] !== null) {
        $row['loss'] = $row['tx_power'] - $row['rx_power'];
    } else {
        $row['loss'] = null;
    }
    
    $data[] = $row;
}

// Jika tidak ada data sama sekali, buat dummy data untuk chart
if (empty($data)) {
    $now = date('Y-m-d H:i:s');
    $hour_ago = date('Y-m-d H:i:s', strtotime('-1 hour'));
    
    // Buat beberapa titik data dummy dengan -40 dBm
    for ($i = 0; $i < 12; $i++) {
        $time = date('Y-m-d H:i:s', strtotime("-$i * 5 minutes", strtotime($now)));
        $data[] = [
            'created_at' => $time,
            'tx_power' => null,
            'rx_power' => $default_down_rx,
            'loss' => null
        ];
    }
    
    // Urutkan dari yang paling lama
    usort($data, function($a, $b) {
        return strtotime($a['created_at']) - strtotime($b['created_at']);
    });
}

echo json_encode($data);
?>