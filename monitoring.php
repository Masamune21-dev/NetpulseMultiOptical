<?php
require_once 'includes/layout_start.php';
?>

<div class="topbar">
    <h1>
        <i class="fas fa-wave-square"></i>
        Optical Monitoring
    </h1>
</div>

<div class="card monitoring-card">
    <!-- FILTER -->
    <div class="monitoring-filters">
        <div class="form-group">
            <label>Device</label>
            <select id="deviceSelect" class="monitoring-select">
                <option value="">-- Pilih Device --</option>
            </select>
        </div>

        <div class="form-group">
            <label>Interface (SFP)</label>
            <select id="interfaceSelect" class="monitoring-select">
                <option value="">-- Pilih Interface --</option>
            </select>
        </div>
    </div>

    <!-- RANGE BUTTONS -->
    <div class="range-buttons">
        <button class="btn btn-range active" data-range="1h">
            <i class="fas fa-clock"></i> 1 Jam
        </button>
        <button class="btn btn-range" data-range="1d">
            <i class="fas fa-sun"></i> 1 Hari
        </button>
        <button class="btn btn-range" data-range="3d">
            <i class="fas fa-calendar-days"></i> 3 Hari
        </button>
        <button class="btn btn-range" data-range="7d">
            <i class="fas fa-calendar-week"></i> 1 Minggu
        </button>
        <button class="btn btn-range" data-range="30d">
            <i class="fas fa-calendar"></i> 1 Bulan
        </button>
        <button class="btn btn-range" data-range="1y">
            <i class="fas fa-calendar-alt"></i> 1 Tahun
        </button>
    </div>

    <div id="ifaceInfo" class="interface-info">
        Interface: -
    </div>

    <!-- STATS -->
    <div id="rxStats" class="rx-stats">
        Now: - dBm | Avg: - dBm | Min: - dBm | Max: - dBm
    </div>

    <!-- CHART -->
    <div class="chart-container">
        <canvas id="opticalChart"></canvas>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>
<script src="assets/js/monitoring.js"></script>

<?php
require_once 'includes/layout_end.php';
?>