<?php
// map_nodes.php
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();
if (!$auth->is_logged_in()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$conn = get_db_connection();

// Buat tabel jika belum ada
$conn->query("
    CREATE TABLE IF NOT EXISTS map_nodes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_id INT,
        node_name VARCHAR(100),
        node_type VARCHAR(50),
        x_position DOUBLE(10,6),
        y_position DOUBLE(10,6),
        icon_type VARCHAR(50),
        is_locked BOOLEAN DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (device_id) REFERENCES snmp_devices(id) ON DELETE SET NULL,
        INDEX idx_device (device_id)
    )
");

$method = $_SERVER['REQUEST_METHOD'];
header('Content-Type: application/json');

switch ($method) {
    case 'GET':
        // Get all nodes
        $result = $conn->query("
            SELECT mn.*, 
                   sd.device_name,
                   sd.ip_address,
                   sd.last_status,
                   sd.snmp_version
            FROM map_nodes mn
            LEFT JOIN snmp_devices sd ON mn.device_id = sd.id
            ORDER BY mn.created_at DESC
        ");

        $nodes = [];
        while ($row = $result->fetch_assoc()) {
            // Get device status
            $row['status'] = $row['last_status'] ?? 'unknown';
            $row['interfaces'] = [];

            // Get active interfaces if device exists
            if ($row['device_id']) {
                $ifResult = $conn->query("
                    SELECT if_name, if_alias, if_description, if_type, is_sfp, last_seen,
                           CAST(rx_power AS DECIMAL(10,2)) as rx_power,
                           interface_type
                    FROM interfaces 
                    WHERE device_id = {$row['device_id']} 
                    AND is_monitored = 1
                    AND oper_status = 1
                    ORDER BY if_index
                    LIMIT 200
                ");

                while ($ifRow = $ifResult->fetch_assoc()) {
                    // Pastikan nilai numerik
                    $ifRow['rx_power'] = $ifRow['rx_power'] !== null ?
                        floatval($ifRow['rx_power']) : null;
                    $ifRow['tx_power'] = $ifRow['tx_power'] !== null ?
                        floatval($ifRow['tx_power']) : null;
                    $row['interfaces'][] = $ifRow;
                }
            }

            $nodes[] = $row;
        }

        echo json_encode($nodes);
        break;

    case 'POST':
        if (!$auth->has_role('admin')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden']);
            exit;
        }
        // Add new node
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data) {
            echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
            exit;
        }

        $stmt = $conn->prepare("
            INSERT INTO map_nodes 
            (device_id, node_name, node_type, x_position, y_position, icon_type, is_locked)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $deviceId = !empty($data['device_id']) ? (int) $data['device_id'] : null;
        $nodeName = $data['node_name'] ?? '';
        $nodeType = $data['node_type'] ?? 'router';
        $x = floatval($data['x_position'] ?? 0);
        $y = floatval($data['y_position'] ?? 0);
        $icon = $data['icon_type'] ?? $nodeType;
        $locked = (int) ($data['is_locked'] ?? 0);

        $stmt->bind_param('issddsi', $deviceId, $nodeName, $nodeType, $x, $y, $icon, $locked);

        if ($stmt->execute()) {
            $nodeId = $conn->insert_id;
            echo json_encode(['success' => true, 'node_id' => $nodeId]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        break;

    case 'PUT':
        if (!$auth->has_role('admin')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden']);
            exit;
        }
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data || !isset($data['id'])) {
            if (!empty($data['lock_all'])) {
                $lockVal = (int) ($data['is_locked'] ?? 0);
                $stmt = $conn->prepare("UPDATE map_nodes SET is_locked = ?");
                $stmt->bind_param('i', $lockVal);
                if ($stmt->execute()) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => $stmt->error]);
                }
                exit;
            }
            echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
            exit;
        }

        $stmt = $conn->prepare("
            UPDATE map_nodes 
            SET node_name = ?, 
                node_type = ?, 
                x_position = ?, 
                y_position = ?, 
                icon_type = ?, 
                is_locked = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            'ssddsii',
            $data['node_name'],
            $data['node_type'],
            $data['x_position'],
            $data['y_position'],
            $data['icon_type'],
            $data['is_locked'],
            $data['id']
        );

        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $stmt->error]);
        }
        break;

    case 'DELETE':
        if (!$auth->has_role('admin')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden']);
            exit;
        }
        // Delete node
        $nodeId = (int) ($_GET['id'] ?? 0);

        if ($nodeId) {
            $conn->query("DELETE FROM map_nodes WHERE id = $nodeId");
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No node ID provided']);
        }
        break;
}

$conn->close();
?>
