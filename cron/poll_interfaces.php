<?php
require_once __DIR__ . '/../config/database.php';

$conn = get_db_connection();

/* Ambil semua device aktif */
$res = $conn->query("SELECT id FROM snmp_devices WHERE is_active = 1");

while ($row = $res->fetch_assoc()) {
    $device_id = (int)$row['id'];

    $_GET['device_id'] = $device_id;

    include __DIR__ . '/../api/discover_interfaces.php';

    echo "Polled device ID: $device_id\n";
}

/* CLOSE DB DI SINI SAJA */
$conn->close();
