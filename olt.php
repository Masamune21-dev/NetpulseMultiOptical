<?php
require_once 'includes/layout_start.php';

$olts = require __DIR__ . '/config/olt.php';

$oltId = $_GET['olt'] ?? array_key_first($olts);
$pon = $_GET['pon'] ?? $olts[$oltId]['pons'][0];

if (!isset($olts[$oltId])) {
    $oltId = array_key_first($olts);
}

if (!in_array($pon, $olts[$oltId]['pons'])) {
    $pon = $olts[$oltId]['pons'][0];
}

$ponSafe = str_replace('/', '_', $pon);
$jsonFile = __DIR__ . "/storage/{$oltId}/pon_{$ponSafe}.json";

$data = [
    'pon' => $pon,
    'total' => 0,
    'onu' => []
];

if (file_exists($jsonFile)) {
    $data = json_decode(file_get_contents($jsonFile), true);
}
?>
<style>
    .topbar form {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .topbar label {
        font-size: 14px;
        font-weight: 600;
        color: #444;
    }

    .topbar select {
        min-width: 160px;
        padding: 8px 32px 8px 12px;

        border-radius: 6px;
        border: 1px solid #ccc;
        background: white;
        color: #333;
        font-size: 14px;

        cursor: pointer;
        transition: all 0.2s;


        background-repeat: no-repeat;
        background-position: right 10px center;
        background-size: 14px;
    }

    .topbar select:hover {
        border-color: #888;
        box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.1);
    }

    .topbar select:focus {
        outline: none;
        border-color: #007bff;
        box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
    }

    .topbar select option:checked {
        background: #007bff;
        color: white;
    }

    @media (max-width: 768px) {
        .topbar form {
            width: 100%;
            gap: 6px;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            align-items: center;
        }

        .topbar label {
            font-size: 12px;
            margin-bottom: 2px;
        }

        .topbar select {
            min-width: 0;
            width: 100%;
            font-size: 13px;
            padding: 6px 28px 6px 10px;
        }
    }
</style>
<div class="topbar">
    <h1>
        <i class="fas fa-server"></i>
        OLT Monitor
    </h1>

    <form method="get" class="topbar-filters">
        <label><strong>OLT</strong></label>
        <select name="olt" onchange="this.form.submit()">
            <?php foreach ($olts as $id => $olt): ?>
                <option value="<?= $id ?>" <?= $id === $oltId ? 'selected' : '' ?>>
                    <?= htmlspecialchars($olt['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label><strong>PON</strong></label>
        <select name="pon" onchange="this.form.submit()">
            <?php foreach ($olts[$oltId]['pons'] as $p): ?>
                <option value="<?= $p ?>" <?= $p === $pon ? 'selected' : '' ?>>
                    <?= $p ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<div style="margin-bottom:10px;">
    <b>OLT:</b> <?= htmlspecialchars($olts[$oltId]['name']) ?> |
    <b>PON:</b> <?= $pon ?> |
    <b>Total ONU:</b> <?= (int) $data['total'] ?> |
    <b>Last Update:</b>
    <?= file_exists($jsonFile) ? date('Y-m-d H:i:s', filemtime($jsonFile)) : '-' ?>
</div>

<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th width="90">ONU ID</th>
                <th>Nama</th>
                <th width="130">MAC</th>
                <th width="70">Status</th>
                <th width="90">RX (dBm)</th>
                <th width="90">TX (dBm)</th>
                <th width="90">Temp (°C)</th>
                <th width="90">Signal</th>
                <th width="120">Uptime</th>
            </tr>
        </thead>
        <tbody>

            <?php if (!empty($data['onu'])): ?>
                <?php foreach ($data['onu'] as $onu): ?>
                    <tr>
                        <td><?= $onu['onu_id'] ?></td>
                        <td><?= htmlspecialchars($onu['name'] ?? '-') ?></td>
                        <td><?= $onu['mac'] ?? '-' ?></td> <!-- TAMBAHAN -->

                        <td>
                            <?php if ($onu['status'] === 'Up'): ?>
                                <span style="color:green;font-weight:bold;">Up</span>
                            <?php else: ?>
                                <span style="color:red;font-weight:bold;">Down</span>
                            <?php endif; ?>
                        </td>

                        <td><?= $onu['rx_power'] ?? '-' ?></td>
                        <td><?= $onu['tx_power'] ?? '-' ?></td>
                        <td><?= $onu['temperature'] ?? '-' ?></td>

                        <td>
                            <?php
                            $signal = $onu['signal'] ?? '-';
                            $color = match ($signal) {
                                'good' => 'green',
                                'warning' => 'orange',
                                'critical' => 'red',
                                default => 'gray'
                            };
                            ?>
                            <span style="color:<?= $color ?>; font-weight:bold;">
                                <?= ucfirst($signal) ?>
                            </span>
                        </td>

                        <td><?= $onu['uptime'] ?? '-' ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9" align="center">
                        Data ONU belum tersedia
                    </td>
                </tr>
            <?php endif; ?>

        </tbody>
    </table>
</div>

<?php
require_once 'includes/layout_end.php';
?>