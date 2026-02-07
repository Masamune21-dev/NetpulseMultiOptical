<?php
require_once __DIR__ . '/auth.php';

$auth = new Auth();
if (!$auth->is_logged_in()) {
    header('Location: login.php');
    exit;
}

$user = $auth->get_current_user();
$current = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>NetPulse</title>

    <script>
        (function () {
            try {
                var theme = localStorage.getItem('theme');
                if (theme) {
                    document.documentElement.setAttribute('data-theme', theme);
                    document.body && document.body.setAttribute('data-theme', theme);
                }
                var primary = localStorage.getItem('primary_color') || '#6366f1';
                var soft = localStorage.getItem('primary_soft') || '#8b5cf6';
                document.documentElement.style.setProperty('--primary', primary);
                document.documentElement.style.setProperty('--primary-soft', soft);
                document.documentElement.style.setProperty(
                    '--primary-gradient',
                    'linear-gradient(135deg, ' + primary + ' 0%, ' + soft + ' 100%)'
                );
                document.documentElement.style.setProperty(
                    '--sidebar',
                    'linear-gradient(160deg, ' + primary + ' 0%, ' + soft + ' 55%, ' + primary + ' 100%)'
                );
            } catch (e) { }
        })();
    </script>

    <link rel="stylesheet" href="assets/css/style.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
        crossorigin="anonymous">

    <script src="assets/js/theme.js"></script>
</head>

<body data-role="<?= htmlspecialchars($user['role'] ?? 'viewer') ?>">
    <script>
        (function () {
            try {
                var theme = document.documentElement.getAttribute('data-theme');
                if (theme) document.body.setAttribute('data-theme', theme);
            } catch (e) { }
        })();
    </script>
    <div class="layout">
        <header class="mobile-header">
            <div class="mobile-brand">
                <div class="mobile-logo">
                    <i class="fas fa-wave-square"></i>
                </div>
                <div class="mobile-titles">
                    <div class="mobile-title">NetPulse MultiOptical</div>
                    <div class="mobile-subtitle">Network Optical Monitoring</div>
                </div>
            </div>
        </header>

        <!-- SIDEBAR -->
        <aside class="sidebar">
            <h2>
                <i class="fas fa-wave-square"></i>
                NetPulse MultiOptical
            </h2>
            <ul>
                <li class="<?= $current === 'dashboard.php' ? 'active' : '' ?>">
                    <a href="dashboard.php">
                        <i class="fas fa-chart-pie"></i><span>Dashboard</span>
                    </a>
                </li>

                <li class="<?= $current === 'monitoring.php' ? 'active' : '' ?>">
                    <a href="monitoring.php">
                        <i class="fas fa-heartbeat"></i><span>Monitoring</span>
                    </a>
                </li>

                <li class="<?= $current === 'devices.php' ? 'active' : '' ?>">
                    <a href="devices.php">
                        <i class="fas fa-network-wired"></i><span>Devices</span>
                    </a>
                </li>

                <li class="<?= $current === 'map.php' ? 'active' : '' ?>">
                    <a href="map.php">
                        <i class="fas fa-map-marked-alt"></i><span>Map</span>
                    </a>
                </li>

                <li class="<?= $current === 'olt.php' ? 'active' : '' ?>">
                    <a href="olt.php">
                        <i class="fas fa-server"></i><span>OLT</span>
                    </a>
                </li>

                <li class="<?= $current === 'users.php' ? 'active' : '' ?>">
                    <a href="users.php">
                        <i class="fas fa-user-shield"></i><span>Users</span>
                    </a>
                </li>

                <li class="<?= $current === 'settings.php' ? 'active' : '' ?>">
                    <a href="settings.php">
                        <i class="fas fa-sliders-h"></i><span>Settings</span>
                    </a>
                </li>

                <li>
                    <a href="logout.php">
                        <i class="fas fa-power-off"></i><span>Logout</span>
                    </a>
                </li>
            </ul>
        </aside>

        <main class="content">

            <!-- TOPBAR -->
            <div class="topbar">
                <h1><?= ucfirst(str_replace('.php', '', $current)) ?></h1>
                <div class="header-clock" data-live-clock></div>
                <div class="user-info">
                    <i class="fas fa-user"></i>
                    <?= htmlspecialchars($user['username']) ?>
                </div>
            </div>

            <!-- CONTENT BODY -->
            <div class="content-body">
                <!-- cards, tables, dll kamu -->
