<?php
// map_devices.php
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();
if (!$auth->is_logged_in()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$conn = get_db_connection();

// Get all devices that are not already in map
$result = $conn->query("
    SELECT id, device_name, ip_address, last_status
    FROM snmp_devices
    WHERE id NOT IN (SELECT device_id FROM map_nodes WHERE device_id IS NOT NULL)
    ORDER BY device_name
");

$devices = [];
while ($row = $result->fetch_assoc()) {
    $devices[] = $row;
}

echo json_encode($devices);

$conn->close();
?>