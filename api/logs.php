<?php
require_once '../includes/auth.php';

$auth = new Auth();
if (!$auth->is_logged_in() || !$auth->has_role('admin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

header('Content-Type: application/json');

$type = $_GET['type'] ?? '';
if ($type !== 'security') {
    echo json_encode(['success' => false, 'error' => 'Invalid log type']);
    exit;
}

$logFile = __DIR__ . '/../logs/security.log';
if (!file_exists($logFile)) {
    echo json_encode(['success' => true, 'data' => 'No logs yet.']);
    exit;
}

$lines = file($logFile, FILE_IGNORE_NEW_LINES);
if ($lines === false) {
    echo json_encode(['success' => false, 'error' => 'Failed to read log file']);
    exit;
}

$tail = array_slice($lines, -200);
echo json_encode(['success' => true, 'data' => implode("\n", $tail)]);
