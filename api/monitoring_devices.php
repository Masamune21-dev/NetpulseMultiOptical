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

$res = $conn->query("
    SELECT id, device_name 
    FROM snmp_devices 
    WHERE is_active = 1
    ORDER BY device_name
");

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
