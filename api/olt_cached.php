<?php
header('Content-Type: application/json');

$olt = $_GET['olt'] ?? null;
$pon = $_GET['pon'] ?? null;

if (!$olt || !$pon) {
    http_response_code(400);
    echo json_encode(['error' => 'olt & pon required']);
    exit;
}

$ponSafe = str_replace('/', '_', $pon);
$file = __DIR__ . "/../storage/{$olt}/pon_{$ponSafe}.json";

if (!file_exists($file)) {
    http_response_code(404);
    echo json_encode(['error' => 'cache not found']);
    exit;
}

readfile($file);
