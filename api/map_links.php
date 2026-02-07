<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();
if (!$auth->is_logged_in()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$conn = get_db_connection();
header('Content-Type: application/json');

$conn->query("
    CREATE TABLE IF NOT EXISTS map_links (
        id INT AUTO_INCREMENT PRIMARY KEY,
        node_a_id INT NOT NULL,
        node_b_id INT NOT NULL,
        interface_a_id INT NOT NULL,
        interface_b_id INT NOT NULL,
        attenuation_db DECIMAL(6,2) DEFAULT NULL,
        notes VARCHAR(255) DEFAULT NULL,
        path_json TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_nodes (node_a_id, node_b_id),
        INDEX idx_interfaces (interface_a_id, interface_b_id),
        FOREIGN KEY (node_a_id) REFERENCES map_nodes(id) ON DELETE CASCADE,
        FOREIGN KEY (node_b_id) REFERENCES map_nodes(id) ON DELETE CASCADE,
        FOREIGN KEY (interface_a_id) REFERENCES interfaces(id) ON DELETE CASCADE,
        FOREIGN KEY (interface_b_id) REFERENCES interfaces(id) ON DELETE CASCADE
    )
");

// Ensure path_json column exists for older installations
$colCheck = $conn->query("SHOW COLUMNS FROM map_links LIKE 'path_json'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE map_links ADD COLUMN path_json TEXT DEFAULT NULL AFTER notes");
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $result = $conn->query("
            SELECT ml.*,
                   na.node_name AS node_a_name,
                   nb.node_name AS node_b_name,
                   COALESCE(ia.if_name, ia.if_alias) AS interface_a_name,
                   COALESCE(ib.if_name, ib.if_alias) AS interface_b_name,
                   ia.oper_status AS interface_a_status,
                   ib.oper_status AS interface_b_status,
                   ia.rx_power AS interface_a_rx,
                   ia.tx_power AS interface_a_tx,
                   ib.rx_power AS interface_b_rx,
                   ib.tx_power AS interface_b_tx
            FROM map_links ml
            LEFT JOIN map_nodes na ON ml.node_a_id = na.id
            LEFT JOIN map_nodes nb ON ml.node_b_id = nb.id
            LEFT JOIN interfaces ia ON ml.interface_a_id = ia.id
            LEFT JOIN interfaces ib ON ml.interface_b_id = ib.id
            ORDER BY ml.created_at DESC
        ");

        $links = [];
        while ($row = $result->fetch_assoc()) {
            $row['interface_a_status'] = isset($row['interface_a_status']) ? (int) $row['interface_a_status'] : null;
            $row['interface_b_status'] = isset($row['interface_b_status']) ? (int) $row['interface_b_status'] : null;
            $row['interface_a_rx'] = $row['interface_a_rx'] !== null ? (float) $row['interface_a_rx'] : null;
            $row['interface_a_tx'] = $row['interface_a_tx'] !== null ? (float) $row['interface_a_tx'] : null;
            $row['interface_b_rx'] = $row['interface_b_rx'] !== null ? (float) $row['interface_b_rx'] : null;
            $row['interface_b_tx'] = $row['interface_b_tx'] !== null ? (float) $row['interface_b_tx'] : null;
            $links[] = $row;
        }

        echo json_encode($links);
        break;

    case 'POST':
        if (!$auth->has_role('admin')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
            exit;
        }

        if (!empty($data['action']) && $data['action'] === 'delete') {
            $id = (int) ($data['id'] ?? 0);
            if (!$id) {
                echo json_encode(['success' => false, 'error' => 'No link ID provided']);
                exit;
            }
            $conn->query("DELETE FROM map_links WHERE id = $id");
            echo json_encode(['success' => true]);
            exit;
        }

        if (!empty($data['action']) && $data['action'] === 'update_path') {
            $id = (int) ($data['id'] ?? 0);
            if (!$id) {
                echo json_encode(['success' => false, 'error' => 'No link ID provided']);
                exit;
            }
            $path = isset($data['path']) ? json_encode($data['path']) : null;
            $stmt = $conn->prepare("UPDATE map_links SET path_json = ? WHERE id = ?");
            $stmt->bind_param('si', $path, $id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => $stmt->error]);
            }
            exit;
        }

        $stmt = $conn->prepare("
            INSERT INTO map_links 
            (node_a_id, node_b_id, interface_a_id, interface_b_id, attenuation_db, notes, path_json)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $nodeA = (int) ($data['node_a_id'] ?? 0);
        $nodeB = (int) ($data['node_b_id'] ?? 0);
        $ifaceA = (int) ($data['interface_a_id'] ?? 0);
        $ifaceB = (int) ($data['interface_b_id'] ?? 0);
        $attenuation = isset($data['attenuation_db']) ? $data['attenuation_db'] : null;
        $notes = $data['notes'] ?? null;
        $pathJson = isset($data['path']) ? json_encode($data['path']) : null;

        if ($nodeA === 0 || $nodeB === 0 || $ifaceA === 0 || $ifaceB === 0) {
            echo json_encode(['success' => false, 'error' => 'Missing node or interface selection']);
            exit;
        }

        if ($nodeA === $nodeB) {
            echo json_encode(['success' => false, 'error' => 'Node A and Node B must be different']);
            exit;
        }

        $attenuationValue = ($attenuation === '' || $attenuation === null) ? null : (string) $attenuation;
        $stmt->bind_param('iiiisss', $nodeA, $nodeB, $ifaceA, $ifaceB, $attenuationValue, $notes, $pathJson);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'id' => $conn->insert_id]);
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

        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'No link ID provided']);
            exit;
        }

        $conn->query("DELETE FROM map_links WHERE id = $id");
        echo json_encode(['success' => true]);
        break;
}

$conn->close();
?>
