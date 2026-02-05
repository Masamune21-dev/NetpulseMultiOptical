<?php
require_once 'includes/layout_start.php';

/* Pastikan hanya admin/technician */
if (!in_array(($_SESSION['role'] ?? ''), ['admin', 'technician'])) {
    echo '<div class="alert error">Access denied</div>';
    require_once 'includes/layout_end.php';
    exit;
}
?>

<div class="topbar">
    <div class="topbar-content">
        <h1><i class="fas fa-map-marked-alt"></i> Network Map</h1>
        <div class="topbar-actions">
            <button class="btn btn-outline" onclick="toggleLock()" id="lockBtn">
                <i class="fas fa-lock-open"></i> Unlocked
            </button>
            <button class="btn" onclick="openAddNodeModal()">
                <i class="fas fa-plus"></i> Add Node
            </button>
            <button class="btn btn-primary" onclick="refreshMap()">
                <i class="fas fa-sync"></i> Refresh
            </button>
        </div>
    </div>
</div>

<div class="map-container">
    <!-- Map Controls -->
    <button id="mapControlToggle" class="map-control-btn">
        <i class="fas fa-sliders-h"></i>
    </button>
    <div class="map-controls">
        <div class="control-group">
            <div class="control-title">Map Layers</div>
            <div class="control-item">
                <input type="checkbox" id="showGrid" checked>
                <label for="showGrid">Show Grid</label>
            </div>
            <div class="control-item">
                <input type="checkbox" id="showConnections" checked>
                <label for="showConnections">Connections</label>
            </div>
            <div class="control-item">
                <input type="checkbox" id="autoArrange" onclick="toggleAutoArrange()">
                <label for="autoArrange">Auto Arrange</label>
            </div>
        </div>

        <div class="control-group">
            <div class="control-title">Node Filters</div>
            <div class="control-item">
                <select id="statusFilter" onchange="filterNodes()">
                    <option value="all">All Status</option>
                    <option value="up">Up Only</option>
                    <option value="down">Down Only</option>
                </select>
            </div>
            <div class="control-item">
                <select id="deviceFilter" onchange="filterNodes()">
                    <option value="all">All Devices</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Map Area -->
    <div id="networkMap"></div>
</div>

<!-- Node Detail Sidebar -->
<div id="nodeSidebar" class="node-sidebar">
    <div class="sidebar-header">
        <h3><i class="fas fa-server"></i> Node Details</h3>
        <button class="sidebar-close" onclick="closeNodeSidebar()">&times;</button>
    </div>
    <div class="sidebar-content" id="nodeDetailContent">
        <div class="sidebar-placeholder">
            <i class="fas fa-mouse-pointer fa-2x"></i>
            <p>Click on a node to view details</p>
        </div>
    </div>
</div>

<!-- Modal Add Node -->
<div class="modal" id="addNodeModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal()">&times;</button>
        <h3><i class="fas fa-map-pin"></i> Add Network Node</h3>

        <div class="form-group">
            <label>Select Device</label>
            <select id="nodeDeviceSelect" class="monitoring-select">
                <option value="">-- Select Device --</option>
            </select>
        </div>

        <div class="form-group">
            <label>Node Name</label>
            <input type="text" id="nodeName" placeholder="Node Display Name">
        </div>

        <div class="form-group">
            <label>Node Type</label>
            <select id="nodeType">
                <option value="router">Router</option>
                <option value="switch">Switch</option>
                <option value="firewall">Firewall</option>
                <option value="ap">Access Point</option>
                <option value="server">Server</option>
                <option value="client">Client</option>
            </select>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>X Position</label>
                <input type="number" id="nodeX" placeholder="0" min="0" max="1000">
            </div>
            <div class="form-group">
                <label>Y Position</label>
                <input type="number" id="nodeY" placeholder="0" min="0" max="800">
            </div>
        </div>

        <div class="form-group">
            <label>Custom Icon</label>
            <select id="nodeIcon">
                <option value="router">Router Icon</option>
                <option value="switch">Switch Icon</option>
                <option value="server">Server Icon</option>
                <option value="cloud">Cloud Icon</option>
                <option value="ap">AP Icon</option>
            </select>
        </div>

        <div class="modal-actions">
            <button class="btn btn-primary" onclick="addNode()">
                <i class="fas fa-plus"></i> Add Node
            </button>
            <button class="btn btn-outline" onclick="closeModal()">
                Cancel
            </button>
        </div>
    </div>
</div>

<!-- Edit Node Modal -->
<div class="modal" id="editNodeModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeEditModal()">&times;</button>
        <h3><i class="fas fa-edit"></i> Edit Node</h3>

        <input type="hidden" id="editNodeId">

        <div class="form-group">
            <label>Node Name</label>
            <input type="text" id="editNodeName">
        </div>

        <div class="form-group">
            <label>Node Type</label>
            <select id="editNodeType">
                <option value="router">Router</option>
                <option value="switch">Switch</option>
                <option value="firewall">Firewall</option>
                <option value="ap">Access Point</option>
                <option value="server">Server</option>
                <option value="client">Client</option>
            </select>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>X Position</label>
                <input type="number" step="any" id="editNodeX">
            </div>
            <div class="form-group">
                <label>Y Position</label>
                <input type="number" step="any" id="editNodeY">
            </div>
        </div>

        <div class="form-group">
            <label>Lock Position</label>
            <select id="editNodeLocked">
                <option value="0">Unlocked</option>
                <option value="1">Locked</option>
            </select>
        </div>

        <div class="modal-actions">
            <button class="btn btn-primary" onclick="updateNode()">
                <i class="fas fa-save"></i> Save Changes
            </button>
            <button class="btn btn-danger" onclick="deleteNode()">
                <i class="fas fa-trash"></i> Delete Node
            </button>
            <button class="btn btn-outline" onclick="closeEditModal()">
                Cancel
            </button>
        </div>
    </div>
</div>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<style>
    /* Map Container Styles */
    .map-container {
        position: relative;
        height: calc(100vh - 300px);
        width: 100%;
        min-height: 600px;
        background: var(--bg-secondary);
    }

    #networkMap {
        width: 100%;
        height: 100%;
        z-index: 1;
    }

    /* Map Controls */
    .map-controls {
        position: absolute;
        top: 15px;
        right: 15px;
        z-index: 1000;

        background: rgba(255, 255, 255, .95);
        backdrop-filter: blur(12px);
        border-radius: 14px;
        padding: 18px;
        width: 260px;

        box-shadow: 0 8px 30px rgba(0, 0, 0, .12);
        border: 1px solid rgba(255, 255, 255, .3);

        opacity: 0;
        pointer-events: none;
        transform: translateY(-10px) scale(.95);
        transition: .25s ease;
    }

    /* aktif */
    .map-controls.open {
        opacity: 1;
        pointer-events: auto;
        transform: translateY(0) scale(1);
    }

    .map-control-btn {
        position: absolute;
        top: 15px;
        right: 15px;
        z-index: 1001;

        width: 44px;
        height: 44px;
        border-radius: 12px;

        background: white;
        border: none;
        cursor: pointer;

        box-shadow: 0 6px 20px rgba(0, 0, 0, .15);

        display: flex;
        align-items: center;
        justify-content: center;

        font-size: 18px;
        color: #6366f1;

        transition: .2s;
    }

    .map-control-btn:hover {
        transform: scale(1.1);
    }

    .control-group {
        margin-bottom: 25px;
    }

    .control-group:last-child {
        margin-bottom: 0;
    }

    .control-title {
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 12px;
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .control-item {
        display: flex;
        align-items: center;
        margin-bottom: 10px;
        padding: 8px 12px;
        background: rgba(0, 0, 0, 0.02);
        border-radius: 8px;
        transition: background 0.3s;
    }

    .control-item:hover {
        background: rgba(0, 0, 0, 0.05);
    }

    .control-item label {
        margin-left: 10px;
        cursor: pointer;
        color: var(--text-secondary);
        font-size: 14px;
    }

    .control-item select {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid rgba(0, 0, 0, 0.1);
        border-radius: 6px;
        background: white;
        font-size: 14px;
    }

    /* Node Sidebar */
    .node-sidebar {
        position: fixed;
        right: -400px;
        top: 80px;
        width: 380px;
        height: calc(100vh - 100px);
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(20px);
        border-left: 1px solid rgba(0, 0, 0, 0.1);
        box-shadow: -5px 0 30px rgba(0, 0, 0, 0.1);
        z-index: 1001;
        transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        overflow-y: auto;
    }

    .node-sidebar.open {
        right: 0;
    }

    .sidebar-header {
        padding: 25px 25px 15px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .sidebar-header h3 {
        margin: 0;
        font-size: 20px;
        color: var(--text-primary);
    }

    .sidebar-close {
        background: none;
        border: none;
        font-size: 28px;
        cursor: pointer;
        color: var(--text-secondary);
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: all 0.3s;
    }

    .sidebar-close:hover {
        background: rgba(0, 0, 0, 0.1);
        color: var(--text-primary);
    }

    .sidebar-content {
        padding: 25px;
    }

    .sidebar-placeholder {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-tertiary);
    }

    .sidebar-placeholder i {
        color: var(--primary);
        margin-bottom: 20px;
        opacity: 0.5;
    }

    /* Node Styles */
    .node-marker {
        position: absolute;
        transform: translate(-50%, -50%);
        transform-origin: center center !important;
        will-change: transform;
        cursor: move;
        transition: all 0.3s;
        z-index: 100;
    }

    .node-marker.locked {
        cursor: default;
    }

    .node-icon {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        transform-origin: center center;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        color: white;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
        transition: all 0.2s ease;
        position: relative;
    }

    .node-icon::after {
        content: "";
        position: absolute;
        inset: -2px;
        border-radius: 50%;
        border: 2px solid white;
        pointer-events: none;
    }

    .node-icon.router {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .node-icon.switch {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }

    .node-icon.firewall {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }

    .node-icon.ap {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }

    .node-icon.server {
        background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    }

    .node-icon.client {
        background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
    }

    .node-icon.cloud {
        background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
    }

    .node-icon:hover {
        transform: scale(1.1);
    }

    .node-status {
        position: absolute;
        top: -5px;
        right: -5px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        border: 2px solid white;
    }

    .node-status.up {
        background: #10b981;
    }

    .node-status.down {
        background: #ef4444;
    }

    .node-status.warning {
        background: #f59e0b;
    }

    .node-label {
        position: absolute;
        top: 70px;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(0, 0, 0, 0.8);
        color: white;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 500;
        white-space: nowrap;
        opacity: 0;
        transition: opacity 0.3s;
        pointer-events: none;
    }

    .node-marker:hover .node-label {
        opacity: 1;
    }

    /* Connection Lines */
    .connection-line {
        stroke: rgba(100, 116, 139, 0.4);
        stroke-width: 2;
        stroke-dasharray: 5, 5;
        animation: dash 20s linear infinite;
    }

    @keyframes dash {
        to {
            stroke-dashoffset: -1000;
        }
    }

    /* Grid Layer */
    .grid-layer {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        background-image:
            linear-gradient(rgba(0, 0, 0, 0.1) 1px, transparent 1px),
            linear-gradient(90deg, rgba(0, 0, 0, 0.1) 1px, transparent 1px);
        background-size: 50px 50px;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .map-controls {
            width: 250px;
            top: 10px;
            right: 10px;
            padding: 15px;
        }

        .node-sidebar {
            width: 100%;
            right: -100%;
        }
    }
</style>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<!-- Custom Map JS -->
<script src="assets/js/map.js"></script>

<?php
require_once 'includes/layout_end.php';
?>