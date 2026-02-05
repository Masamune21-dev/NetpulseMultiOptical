<?php

$port = $_GET['port'] ?? 'XGigabitEthernet0/0/10';

$cmd = escapeshellcmd("./huawei_telnet_expect.sh");

exec($cmd, $out);

$data = [];

foreach ($out as $line) {
    if (strpos($line,'=') !== false) {
        [$k,$v] = explode('=', $line);
        $data[strtolower(trim($k))] = floatval(trim($v));
    }
}

echo json_encode([
    'success'=>true,
    'tx'=>$data['tx'] ?? null,
    'rx'=>$data['rx'] ?? null,
    'temp'=>$data['temp'] ?? null,
    'loss'=>$data['loss'] ?? null
]);
