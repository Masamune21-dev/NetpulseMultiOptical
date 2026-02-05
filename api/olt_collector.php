<?php
/**
 * OLT COLLECTOR LIBRARY
 * CLI ONLY – MULTI OLT READY
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

set_time_limit(0);
error_reporting(E_ALL);

/* =========================
   TELNET CORE (SESSION BASED)
========================= */

function telnetConnect(array $cfg)
{
    $fp = fsockopen($cfg['host'], $cfg['port'], $errno, $errstr, 10);
    if (!$fp) {
        throw new Exception("Telnet failed: $errstr");
    }

    stream_set_blocking($fp, false);

    telnetRead($fp);
    telnetWrite($fp, $cfg['username']);
    telnetRead($fp);
    telnetWrite($fp, $cfg['password']);
    telnetRead($fp);
    telnetWrite($fp, 'enable');
    telnetRead($fp);

    return $fp;
}

function telnetRead($fp, $timeout = 2)
{
    $data = '';
    $start = microtime(true);

    while (microtime(true) - $start < $timeout) {
        $chunk = fread($fp, 8192);
        if ($chunk !== false && $chunk !== '') {
            $data .= $chunk;
            $start = microtime(true);
        }
        usleep(150000);
    }
    return $data;
}

function telnetWrite($fp, $cmd)
{
    fwrite($fp, $cmd . "\r\n");
    usleep(300000);
}

function telnetCmd($fp, $cmd)
{
    telnetWrite($fp, $cmd);

    $buf = '';
    $start = microtime(true);

    while (true) {
        $chunk = fread($fp, 8192);
        if ($chunk !== false && $chunk !== '') {
            $buf .= $chunk;
            $start = microtime(true);
        }

        // paging
        if (strpos($buf, 'Enter Key To Continue') !== false) {
            $buf = str_replace('--- Enter Key To Continue ----', '', $buf);
            telnetWrite($fp, '');
        }

        // prompt detected
        if (preg_match('/\nEPON[#>]\s*$/', $buf)) {
            break;
        }

        if (microtime(true) - $start > 10) {
            break;
        }

        usleep(150000);
    }

    return cleanCliOutput($buf);
}

function cleanCliOutput($text)
{
    $text = preg_replace('/\x1B\[[0-9;]*[A-Za-z]/', '', $text);
    $text = str_replace("\r", "", $text);
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
    return implode("\n", array_map('trim', explode("\n", $text)));
}

/* =========================
   PARSERS
========================= */

function parseOnuList($raw)
{
    $out = [];

    foreach (explode("\n", $raw) as $l) {
        if (!preg_match(
            '/^(\d+\/\d+:\d+)\s+([0-9a-f:]+)\s+(Up|Down)\s+(\S+)\s+(\S+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s*(.*)$/i',
            $l,
            $m
        )) continue;

        $out[] = [
            'onu_id'   => $m[1],
            'mac'      => $m[2],
            'status'   => $m[3],
            'firmware' => $m[4],
            'chip'     => $m[5],
            'ge'       => (int)$m[6],
            'fe'       => (int)$m[7],
            'pots'     => (int)$m[8],
            'ctc'      => $m[9],
            'ctc_ver'  => $m[10],
            'activate' => $m[11],
            'uptime'   => $m[12],
            'name'     => trim($m[13]),
        ];
    }
    return $out;
}

function parseOpticalDDM($raw)
{
    $d = [];
    if (preg_match('/Temperature\s*:\s*([\d\.]+)/i', $raw, $m)) $d['temperature'] = (float)$m[1];
    if (preg_match('/Voltage\s*:\s*([\d\.]+)/i', $raw, $m))     $d['voltage']     = (float)$m[1];
    if (preg_match('/TxPower\s*:\s*([-\d\.]+)/i', $raw, $m))    $d['tx_power']    = (float)$m[1];
    if (preg_match('/RxPower\s*:\s*([-\d\.]+)/i', $raw, $m))    $d['rx_power']    = (float)$m[1];
    return $d;
}

/* =========================
   PUBLIC API
========================= */

function collectPon(array $olt, string $pon)
{
    $fp = telnetConnect($olt);

    $listRaw = telnetCmd($fp, "show onu info epon {$pon} all");
    $onus = parseOnuList($listRaw);

    foreach ($onus as &$onu) {
        if ($onu['status'] !== 'Up') {
            $onu['signal'] = 'offline';
            continue;
        }

        $id = explode(':', $onu['onu_id'])[1];
        $opt = parseOpticalDDM(
            telnetCmd($fp, "show onu optical-ddm epon {$pon} {$id}")
        );

        $onu += $opt;

        if (isset($onu['rx_power'])) {
            $onu['signal'] =
                $onu['rx_power'] < -28 ? 'critical' :
                ($onu['rx_power'] < -25 ? 'warning' : 'good');
        }
    }

    telnetWrite($fp, 'quit');
    fclose($fp);

    return [
        'pon'   => $pon,
        'total' => count($onus),
        'onu'   => $onus,
    ];
}

function collectAllPon(array $olt)
{
    $fp = telnetConnect($olt);
    $results = [];

    foreach ($olt['pons'] as $pon) {
        $listRaw = telnetCmd($fp, "show onu info epon {$pon} all");
        $onus = parseOnuList($listRaw);

        foreach ($onus as &$onu) {
            if ($onu['status'] !== 'Up') {
                $onu['signal'] = 'offline';
                continue;
            }

            $id = explode(':', $onu['onu_id'])[1];
            $opt = parseOpticalDDM(
                telnetCmd($fp, "show onu optical-ddm epon {$pon} {$id}")
            );

            $onu += $opt;

            if (isset($onu['rx_power'])) {
                $onu['signal'] =
                    $onu['rx_power'] < -28 ? 'critical' :
                    ($onu['rx_power'] < -25 ? 'warning' : 'good');
            }
        }

        $results[$pon] = [
            'pon'   => $pon,
            'total' => count($onus),
            'onu'   => $onus
        ];
    }

    telnetWrite($fp, 'quit');
    fclose($fp);

    return $results;
}
