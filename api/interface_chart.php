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

// Jika menggunakan YEAR dalam query, pastikan formatnya benar
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

while ($row = $res->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
?>