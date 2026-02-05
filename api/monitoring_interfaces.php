<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();
if (!$auth->is_logged_in()) {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

$conn = get_db_connection();

$device_id = (int)($_GET['device_id'] ?? 0);
if (!$device_id) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("
    SELECT if_index, if_name, if_alias, tx_power, rx_power
    FROM interfaces
    WHERE device_id = ?
      AND is_sfp = 1
    ORDER BY if_index
");


$stmt->bind_param('i', $device_id);
$stmt->execute();

$res = $stmt->get_result();
$data = [];

while ($row = $res->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
