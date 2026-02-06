<?php
require_once 'config/database.php';
$conn = get_db_connection();

// GABUNG SEMUA QUERY JADI 1
$query = "
SELECT 
    (SELECT COUNT(*) FROM snmp_devices WHERE is_active = 1) as device_count,
    (SELECT COUNT(*) FROM interfaces) as interface_count,
    (SELECT COUNT(*) FROM interfaces WHERE is_sfp = 1 AND tx_power IS NOT NULL) as sfp_count,
    (SELECT COUNT(*) FROM interfaces 
     WHERE is_sfp = 1 
     AND tx_power IS NOT NULL
     AND (tx_power - rx_power) > 30) as bad_optical_count
";

$result = $conn->query($query);
$data = $result->fetch_assoc();

$deviceCount = $data['device_count'] ?? 0;
$ifCount = $data['interface_count'] ?? 0;
$sfpCount = $data['sfp_count'] ?? 0;
$badOptical = $data['bad_optical_count'] ?? 0;

// OLT summary
$oltConfig = require __DIR__ . '/config/olt.php';
$oltCount = is_array($oltConfig) ? count($oltConfig) : 0;
$ponCount = 0;
$onuCount = 0;

if (is_array($oltConfig)) {
    foreach ($oltConfig as $oltId => $olt) {
        $pons = $olt['pons'] ?? [];
        $ponCount += count($pons);

        foreach ($pons as $pon) {
            $ponSafe = str_replace('/', '_', $pon);
            $jsonFile = __DIR__ . "/storage/{$oltId}/pon_{$ponSafe}.json";
            if (!file_exists($jsonFile)) {
                continue;
            }
            $json = json_decode(file_get_contents($jsonFile), true);
            if (is_array($json) && isset($json['total'])) {
                $onuCount += (int) $json['total'];
            }
        }
    }
}

require_once 'includes/layout_start.php';
?>

<div class="cards">

    <div class="card">
        <h3><i class="fas fa-server"></i> Device Aktif</h3>
        <p><strong><?= $deviceCount ?></strong> device dimonitor</p>
    </div>

    <div class="card">
        <h3><i class="fas fa-plug"></i> Total Interface</h3>
        <p><strong><?= $ifCount ?></strong> interface</p>
    </div>

    <div class="card">
        <h3><i class="fas fa-fiber-optic"></i> SFP Aktif</h3>
        <p><strong><?= $sfpCount ?></strong> port optik</p>
    </div>

    <div class="card <?= $badOptical ? 'danger' : '' ?>">
        <h3><i class="fas fa-triangle-exclamation"></i> Optical Critical</h3>
        <p><strong><?= $badOptical ?></strong> port bermasalah</p>
    </div>

    <div class="card">
        <h3><i class="fas fa-layer-group"></i> Total OLT</h3>
        <p><strong><?= $oltCount ?></strong> olt terdaftar</p>
    </div>

    <div class="card">
        <h3><i class="fas fa-network-wired"></i> Total PON</h3>
        <p><strong><?= $ponCount ?></strong> pon aktif</p>
    </div>

    <div class="card">
        <h3><i class="fas fa-user-friends"></i> Total ONU</h3>
        <p><strong><?= $onuCount ?></strong> onu terdaftar</p>
    </div>

</div>

<script>
    setTimeout(() => location.reload(), 60000);
</script>

<?php require_once 'includes/layout_end.php'; ?>
