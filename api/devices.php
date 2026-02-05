<?php
require_once '../includes/auth.php';
$auth = new Auth();

if (!$auth->is_logged_in()) {
    http_response_code(403);
    exit;
}

$conn = get_db_connection();
$method = $_SERVER['REQUEST_METHOD'];

// ===============================
// GET LIST DEVICES (WAJIB ADA)
// ===============================
if ($method === 'GET' && !isset($_GET['test'])) {

    $res = $conn->query("SELECT * FROM snmp_devices ORDER BY id DESC");

    $data = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $data[] = $row;
        }
    }

    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['test'])) {

    $id = (int)$_GET['test'];
    $device = $conn->query(
        "SELECT * FROM snmp_devices WHERE id = $id"
    )->fetch_assoc();

    if (!$device) {
        http_response_code(404);
        exit;
    }

    $ip = $device['ip_address'];
    $oid = '1.3.6.1.2.1.1.1.0'; // sysDescr
    $timeout = 1000000; // microseconds (1s)
    $retries = 1;

    try {

        if ($device['snmp_version'] === '2c') {

            $response = @snmp2_get(
                $ip,
                $device['community'],
                $oid,
                $timeout,
                $retries
            );

        } else {
            // SNMP v3 (basic, no authPriv yet)
            $response = @snmp3_get(
                $ip,
                $device['snmp_user'],
                'noAuthNoPriv',
                '',
                '',
                $oid,
                $timeout,
                $retries
            );
        }

        if ($response === false) {
            throw new Exception('SNMP timeout / auth failed');
        }

        // SUCCESS
        $conn->query(
            "UPDATE snmp_devices
             SET last_status='OK', last_error=NULL
             WHERE id=$id"
        );

        echo json_encode([
            'status' => 'OK',
            'response' => $response
        ]);
        exit;

    } catch (Throwable $e) {

        $err = substr($e->getMessage(), 0, 250);

        $conn->query(
            "UPDATE snmp_devices
             SET last_status='FAILED', last_error='$err'
             WHERE id=$id"
        );

        echo json_encode([
            'status' => 'FAILED',
            'error'  => $err
        ]);
        exit;
    }
}

$data = json_decode(file_get_contents('php://input'), true);

if ($method === 'POST') {

    if (empty($data['id'])) {
        $stmt = $conn->prepare(
            "INSERT INTO snmp_devices
            (device_name, ip_address, snmp_version, community, snmp_user, is_active)
            VALUES (?,?,?,?,?,?)"
        );
        $stmt->bind_param(
            'sssssi',
            $data['device_name'],
            $data['ip_address'],
            $data['snmp_version'],
            $data['community'],
            $data['snmp_user'],
            $data['is_active']
        );
    } else {
        $stmt = $conn->prepare(
            "UPDATE snmp_devices SET
             device_name=?, ip_address=?, snmp_version=?, community=?, snmp_user=?, is_active=?
             WHERE id=?"
        );
        $stmt->bind_param(
            'sssssii',
            $data['device_name'],
            $data['ip_address'],
            $data['snmp_version'],
            $data['community'],
            $data['snmp_user'],
            $data['is_active'],
            $data['id']
        );
    }

    $stmt->execute();
    echo json_encode(['success'=>true]);
    exit;
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    $conn->query("DELETE FROM snmp_devices WHERE id=$id");
    echo json_encode(['success'=>true]);
}
