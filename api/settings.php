<?php
require_once '../includes/auth.php';
$auth = new Auth();

if (!$auth->is_logged_in() || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit;
}

$conn = get_db_connection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $res = $conn->query("SELECT name,value FROM settings");
    $data = [];
    while ($r = $res->fetch_assoc()) {
        $data[$r['name']] = $r['value'];
    }
    echo json_encode($data);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

foreach ($data as $k => $v) {
    $stmt = $conn->prepare(
        "INSERT INTO settings (name,value)
         VALUES (?,?)
         ON DUPLICATE KEY UPDATE value=VALUES(value)"
    );
    $stmt->bind_param('ss', $k, $v);
    $stmt->execute();
}

echo json_encode(['success' => true]);
