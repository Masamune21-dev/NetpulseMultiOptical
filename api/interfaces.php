<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once '../config/database.php';
require_once '../includes/auth.php';

$auth = new Auth();
if (!$auth->is_logged_in()) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $conn = get_db_connection();

    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    $device_id = (int) ($_GET['device_id'] ?? 0);
    if (!$device_id) {
        echo json_encode([]);
        exit;
    }

    // Cek apakah device ada
    $checkDevice = $conn->prepare("SELECT id FROM snmp_devices WHERE id = ?");
    $checkDevice->bind_param('i', $device_id);
    $checkDevice->execute();
    $deviceExists = $checkDevice->get_result()->fetch_assoc();

    if (!$deviceExists) {
        echo json_encode(['error' => 'Device not found']);
        exit;
    }

    // Cek struktur tabel
    $checkColumns = $conn->query("SHOW COLUMNS FROM interfaces");
    $columns = [];
    while ($col = $checkColumns->fetch_assoc()) {
        $columns[] = $col['Field'];
    }

    // Bangun query dinamis
    $selectColumns = ['if_index', 'if_name', 'optical_index', 'rx_power', 'last_seen', 'is_sfp'];

    // Tambahkan tx_power jika ada
    if (in_array('tx_power', $columns)) {
        $selectColumns[] = 'tx_power';
    }

    // Tambahkan kolom opsional lainnya
    if (in_array('if_alias', $columns)) {
        $selectColumns[] = 'if_alias';
    }

    if (in_array('if_description', $columns)) {
        $selectColumns[] = 'if_description';
    }

    if (in_array('interface_type', $columns)) {
        $selectColumns[] = 'interface_type';
    }

    $sql = "SELECT " . implode(', ', $selectColumns) . " 
            FROM interfaces 
            WHERE device_id = ? 
            ORDER BY is_sfp DESC, if_index ASC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare statement failed: ' . $conn->error);
    }

    $stmt->bind_param('i', $device_id);
    $stmt->execute();

    $res = $stmt->get_result();
    $data = [];

    while ($row = $res->fetch_assoc()) {
        // Pastikan tipe data benar
        if (isset($row['is_sfp'])) {
            $row['is_sfp'] = (int) $row['is_sfp'];
        }
        if (array_key_exists('rx_power', $row)) {
            $row['rx_power'] = $row['rx_power'] !== null
                ? (float) $row['rx_power']
                : null;
        }

        if (array_key_exists('tx_power', $row)) {
            $row['tx_power'] = $row['tx_power'] !== null
                ? (float) $row['tx_power']
                : null;
        }

        $data[] = $row;
    }

    header('Content-Type: application/json');
    echo json_encode($data ?: []);

} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
exit;