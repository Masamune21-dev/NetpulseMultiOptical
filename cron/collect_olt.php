<?php
/**
 * COLLECT SINGLE OLT (FINAL – PRODUCTION)
 * Usage:
 *   php collect_olt.php olt-1
 */

if (php_sapi_name() !== 'cli') {
    exit("CLI only\n");
}

set_time_limit(0);
error_reporting(E_ALL);

/* ======================
   ARGUMENT
====================== */
$oltId = $argv[1] ?? null;
if (!$oltId) {
    exit("OLT ID required\n");
}

/* ======================
   LOCK PER OLT
====================== */
$lockFile = "/tmp/olt_{$oltId}.lock";
$lockFp = fopen($lockFile, 'c');

if (!$lockFp || !flock($lockFp, LOCK_EX | LOCK_NB)) {
    echo "[" . date('H:i:s') . "] {$oltId} still running, skip\n";
    exit;
}

/* ======================
   LOAD CONFIG
====================== */
$olts = require __DIR__ . '/../config/olt.php';

if (!isset($olts[$oltId])) {
    echo "Invalid OLT: {$oltId}\n";
    exit;
}

$olt  = $olts[$oltId];
$base = __DIR__ . '/../storage';

/* ======================
   PREPARE STORAGE
====================== */
$dir = "{$base}/{$oltId}";
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

/* ======================
   COLLECT DATA
====================== */
require_once __DIR__ . '/../api/olt_collector.php';

echo "[" . date('H:i:s') . "] Collecting {$olt['name']}...\n";

try {
    $all = collectAllPon($olt);
} catch (Throwable $e) {
    echo "❌ {$olt['name']} error: {$e->getMessage()}\n";
    exit;
}

/* ======================
   SAVE JSON (ATOMIC)
====================== */
foreach ($all as $pon => $data) {
    $safe = str_replace('/', '_', $pon);

    $tmp  = "{$dir}/pon_{$safe}.json.tmp";
    $out  = "{$dir}/pon_{$safe}.json";

    file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT));
    rename($tmp, $out);
}

/* ======================
   META INFO
====================== */
file_put_contents(
    "{$dir}/meta.json",
    json_encode([
        'name'       => $olt['name'],
        'last_poll'  => date('Y-m-d H:i:s'),
        'pon_count'  => count($all),
    ], JSON_PRETTY_PRINT)
);

echo "[" . date('H:i:s') . "] DONE {$olt['name']}\n";

/* ======================
   END (LOCK AUTO RELEASE)
====================== */
