// Network Map Application
let map = null;
let mapLocked = false;
let nodes = [];
let connections = [];
let selectedNode = null;
let nodeMarkers = [];
let connectionLines = [];
let clickedLatLng = null;
let mapRefreshPaused = false;


// Icon definitions
const nodeIcons = {
    router: { icon: 'fa-router', color: '#667eea' },
    switch: { icon: 'fa-server', color: '#f093fb' },
    firewall: { icon: 'fa-shield-alt', color: '#f5576c' },
    ap: { icon: 'fa-wifi', color: '#4facfe' },
    server: { icon: 'fa-server', color: '#43e97b' },
    client: { icon: 'fa-desktop', color: '#fa709a' },
    cloud: { icon: 'fa-cloud', color: '#a8edea' }
};

// Initialize Map
function initMap() {

    map = L.map('networkMap', {
        minZoom: 3,
        maxZoom: 20
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 20,
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    map.on('click', function (e) {

        clickedLatLng = e.latlng;

        if (window.tempMarker) {
            map.removeLayer(window.tempMarker);
        }

        window.tempMarker = L.marker(e.latlng).addTo(map);

        openAddNodeModal();

    });

    // Default view (Jepara)
    map.setView([-6.748973663434672, 110.97523378333311], 10);

    map.whenReady(() => {
        loadMapData();
        loadAvailableDevices();
    });
}

function addGridLayer() {
    const gridLayer = L.layerGroup().addTo(map);

    // Buat grid 50px
    const gridSize = 50;
    const bounds = map.getBounds();
    const southWest = bounds.getSouthWest();
    const northEast = bounds.getNorthEast();

    // Garis vertikal
    for (let x = Math.floor(southWest.lng / gridSize) * gridSize; x <= northEast.lng; x += gridSize) {
        const line = L.polyline([
            [southWest.lat, x],
            [northEast.lat, x]
        ], {
            color: 'rgba(0,0,0,0.1)',
            weight: 1,
            interactive: false
        }).addTo(gridLayer);
    }

    // Garis horizontal
    for (let y = Math.floor(southWest.lat / gridSize) * gridSize; y <= northEast.lat; y += gridSize) {
        const line = L.polyline([
            [y, southWest.lng],
            [y, northEast.lng]
        ], {
            color: 'rgba(0,0,0,0.1)',
            weight: 1,
            interactive: false
        }).addTo(gridLayer);
    }
}

function testMarker() {
    const testIcon = L.divIcon({
        html: `
            <div class="node-marker">
                <div class="node-icon router">
                    <i class="fas fa-router"></i>
                    <div class="node-status up"></div>
                </div>
                <div class="node-label">Test Router</div>
            </div>
        `,
        className: 'custom-node-marker',
        iconSize: [60, 60],
        iconAnchor: [30, 30]
    });

    const marker = L.marker([0, 0], { icon: testIcon }).addTo(map);

    marker.bindPopup('Test marker - Map is working!').openPopup();

    console.log('Test marker added');
}

// Load all map data
async function loadMapData() {
    if (document.hidden) {
        mapRefreshPaused = true;
        return;
    }
    try {
        const response = await fetch('api/map_nodes.php');
        nodes = await response.json();

        // Clear existing markers
        nodeMarkers.forEach(marker => map.removeLayer(marker));
        nodeMarkers = [];

        // Create new markers
        nodes.forEach(node => {
            createNodeMarker(node);
        });

        // Update device filter
        updateDeviceFilter();

    } catch (error) {
        console.error('Error loading map data:', error);
        showNotification('Failed to load map data', 'error');
    }
}

// Create node marker
function createNodeMarker(node) {
    const icon = nodeIcons[node.node_type] || nodeIcons.router;

    // Create custom HTML marker
    const markerHtml = `
        <div class="node-marker" data-node-id="${node.id}">
            <div class="node-icon ${node.node_type}" 
                 style="background: ${icon.color}">
                <i class="fas ${icon.icon}"></i>
                <div class="node-status ${node.status === 'OK' ? 'up' : 'down'}"></div>
            </div>
        </div>
    `;

    // Create Leaflet marker with custom HTML
    const marker = L.marker([node.y_position, node.x_position], {
        icon: L.divIcon({
            html: markerHtml,
            className: 'custom-node-marker',
            iconSize: [1, 1],
            iconAnchor: [0, 0]

        }),
        draggable: true
    }).addTo(map);
    marker.bindTooltip(node.node_name, {
        permanent:  false,
        direction: 'top',
        offset: [0, -28],
        className: 'leaflet-node-label',
        sticky: true 
    });

    if (mapLocked || node.is_locked == 1) {
        marker.dragging.disable();
    }

    // Store reference
    marker.nodeData = node;
    nodeMarkers.push(marker);

    // Add click event
    marker.on('click', function (e) {
        L.DomEvent.stopPropagation(e);
        selectNode(node);
    });

    // Add drag event
    // Add drag event
    if (!node.is_locked && !mapLocked) {
        marker.on('dragend', function (e) {
            updateNodePosition(node.id, e.target.getLatLng());
        });
    }

    return marker;
}

// Select node and show details
function selectNode(node) {
    selectedNode = node;
    showNodeSidebar(node);
    highlightNode(node.id);
}

// Show node details in sidebar
function showNodeSidebar(node) {
    const sidebar = document.getElementById('nodeSidebar');
    const content = document.getElementById('nodeDetailContent');

    // Create status badge
    const statusBadge = node.status === 'OK'
        ? '<span class="badge badge-success">Online</span>'
        : '<span class="badge badge-danger">Offline</span>';

    // Create interfaces table
    let interfacesHtml = '';
    if (node.interfaces && node.interfaces.length > 0) {
        interfacesHtml = `
            <div class="interfaces-section">
                <h4><i class="fas fa-plug"></i> Active Interfaces</h4>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Interface</th>
                                <th>Type</th>
                                <th>RX Power</th>
                                <th>Loss</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${node.interfaces.map(iface => `
                                <tr>
                                    <td><code>${iface.if_name}</code></td>
                                    <td><span class="badge">${iface.interface_type || 'SFP'}</span></td>
                                    <td>
                                        ${iface.rx_power !== null && !isNaN(parseFloat(iface.rx_power)) ? `
                                            <span class="power-value ${getPowerClass(parseFloat(iface.rx_power))}">
                                                ${parseFloat(iface.rx_power).toFixed(2)} dBm
                                            </span>
                                        ` : '-'}
                                    </td>
                                    <td>
                                        ${iface.tx_power && iface.rx_power ? `
                                            <span class="loss-value">
                                                ${(iface.tx_power - iface.rx_power).toFixed(2)} dB
                                            </span>
                                        ` : '-'}
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    } else {
        interfacesHtml = `
            <div class="alert warning">
                <i class="fas fa-info-circle"></i>
                No interface data available. Run discovery first.
            </div>
        `;
    }

    content.innerHTML = `
        <div class="node-info">
            <div class="node-header">
                <div class="node-icon-large ${node.node_type}">
                    <i class="fas ${nodeIcons[node.node_type]?.icon || 'fa-server'} fa-2x"></i>
                </div>
                <div class="node-title">
                    <h4>${node.node_name}</h4>
                    <p class="node-subtitle">${node.device_name || 'No device linked'}</p>
                </div>
                ${statusBadge}
            </div>
            
            <div class="node-details">
                <div class="detail-grid">
                    <div class="detail-item">
                        <label><i class="fas fa-globe"></i> IP Address</label>
                        <span>${node.ip_address || 'N/A'}</span>
                    </div>
                    <div class="detail-item">
                        <label><i class="fas fa-exchange-alt"></i> SNMP Version</label>
                        <span>${node.snmp_version ? 'v' + node.snmp_version : 'N/A'}</span>
                    </div>
                    <div class="detail-item">
                        <label><i class="fas fa-map-marker-alt"></i> Position</label>
                        <span>(${node.x_position}, ${node.y_position})</span>
                    </div>
                    <div class="detail-item">
                        <label><i class="fas fa-lock"></i> Status</label>
                        <span>${node.is_locked ? 'Locked' : 'Movable'}</span>
                    </div>
                </div>
                
                ${node.device_id ? `
                    <div class="node-actions">
                        <button class="btn btn-sm" onclick="testNodeSNMP(${node.device_id})">
                            <i class="fas fa-plug"></i> Test SNMP
                        </button>
                        <button class="btn btn-sm btn-outline" onclick="discoverNodeInterfaces(${node.device_id})">
                            <i class="fas fa-search"></i> Discover Interfaces
                        </button>
                        <button class="btn btn-sm btn-primary" onclick="editNode(${node.id})">
                            <i class="fas fa-edit"></i> Edit Node
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="deleteNodeQuick(${node.id})">
                            <i class="fas fa-trash-alt"></i> Delete Node
                        </button>
                    </div>
                ` : ''}
                
                ${interfacesHtml}
            </div>
        </div>
    `;

    sidebar.classList.add('open');
}

// Close node sidebar
function closeNodeSidebar() {
    document.getElementById('nodeSidebar').classList.remove('open');
    selectedNode = null;
    unhighlightAllNodes();
}

// Highlight selected node
function highlightNode(nodeId) {
    unhighlightAllNodes();
    const marker = nodeMarkers.find(m => m.nodeData.id === nodeId);
    if (marker) {
        const el = marker.getElement();
        if (el) el.classList.add('highlighted');

    }
}

// Unhighlight all nodes
function unhighlightAllNodes() {
    document.querySelectorAll('.node-marker').forEach(marker => {
        marker.classList.remove('highlighted');
    });
}

// Load available devices for dropdown
async function loadAvailableDevices() {
    try {
        const response = await fetch('api/map_devices.php');
        const devices = await response.json();

        const select = document.getElementById('nodeDeviceSelect');
        select.innerHTML = '<option value="">-- Select Device --</option>';

        devices.forEach(device => {
            select.innerHTML += `
                <option value="${device.id}">
                    ${device.device_name} (${device.ip_address})
                </option>
            `;
        });

    } catch (error) {
        console.error('Error loading devices:', error);
    }
}

// Add new node
async function addNode() {
    const deviceId = document.getElementById('nodeDeviceSelect').value;
    const nodeName = document.getElementById('nodeName').value;
    const nodeType = document.getElementById('nodeType').value;

    if (!clickedLatLng) {
        showNotification('Klik map dulu untuk menentukan posisi node', 'warning');
        return;
    }

    const nodeX = clickedLatLng.lng;
    const nodeY = clickedLatLng.lat;

    const iconType = document.getElementById('nodeIcon').value;

    if (!nodeName.trim()) {
        showNotification('Please enter a node name', 'warning');
        return;
    }

    const nodeData = {
        device_id: deviceId || null,
        node_name: nodeName,
        node_type: nodeType,
        x_position: nodeX,
        y_position: nodeY,
        icon_type: iconType,
        is_locked: 0
    };

    try {
        const response = await fetch('api/map_nodes.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(nodeData)
        });

        const result = await response.json();

        if (result.success) {
            closeModal();
            loadMapData();
            map.panTo([nodeY, nodeX]);

            clickedLatLng = null;

            if (window.tempMarker) {
                map.removeLayer(window.tempMarker);
                window.tempMarker = null;
            }

            showNotification('Node added successfully', 'success');
        } else {
            showNotification('Failed to add node: ' + result.error, 'error');
        }

    } catch (error) {
        console.error('Error adding node:', error);
        showNotification('Failed to add node', 'error');
    }
}


// Edit node
function editNode(nodeId) {
    const node = nodes.find(n => n.id == nodeId);
    if (!node) {
        showNotification('Node not found', 'error');
        return;
    }

    document.getElementById('editNodeId').value = node.id;
    document.getElementById('editNodeName').value = node.node_name;
    document.getElementById('editNodeType').value = node.node_type;
    document.getElementById('editNodeX').value = node.x_position;
    document.getElementById('editNodeY').value = node.y_position;
    document.getElementById('editNodeLocked').value = node.is_locked;

    document.getElementById('editNodeModal').style.display = 'flex';
    closeNodeSidebar();
}


// Update node
async function updateNode() {
    const nodeId = document.getElementById('editNodeId').value;
    const nodeName = document.getElementById('editNodeName').value;
    const nodeType = document.getElementById('editNodeType').value;
    const nodeX = parseFloat(document.getElementById('editNodeX').value);
    const nodeY = parseFloat(document.getElementById('editNodeY').value);
    const isLocked = parseInt(document.getElementById('editNodeLocked').value);

    const nodeData = {
        id: nodeId,
        node_name: nodeName,
        node_type: nodeType,
        x_position: nodeX,
        y_position: nodeY,
        icon_type: nodeType,
        is_locked: isLocked
    };

    try {
        const response = await fetch('api/map_nodes.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(nodeData)
        });

        const result = await response.json();

        if (result.success) {
            closeEditModal();
            loadMapData();
            showNotification('Node updated successfully', 'success');
        } else {
            showNotification('Failed to update node: ' + result.error, 'error');
        }

    } catch (error) {
        console.error('Error updating node:', error);
        showNotification('Failed to update node', 'error');
    }
}

// Delete node
async function deleteNode() {
    if (!confirm('Are you sure you want to delete this node?')) return;

    const nodeId = document.getElementById('editNodeId').value;

    try {
        const response = await fetch(`api/map_nodes.php?id=${nodeId}`, {
            method: 'DELETE'
        });

        const result = await response.json();

        if (result.success) {
            closeEditModal();
            loadMapData();
            showNotification('Node deleted successfully', 'success');
        } else {
            showNotification('Failed to delete node: ' + result.error, 'error');
        }

    } catch (error) {
        console.error('Error deleting node:', error);
        showNotification('Failed to delete node', 'error');
    }
}

async function deleteNodeQuick(id) {

    if (!confirm('Delete this node?')) return;

    try {

        const response = await fetch(`api/map_nodes.php?id=${id}`, {
            method: 'DELETE'
        });

        const result = await response.json();

        if (result.success) {
            closeNodeSidebar();
            loadMapData();
            showNotification('Node deleted', 'success');
        } else {
            showNotification(result.error || 'Delete failed', 'error');
        }

    } catch (e) {
        console.error(e);
        showNotification('Delete failed', 'error');
    }
}

// Update node position after drag
async function updateNodePosition(nodeId, latLng) {

    const node = nodes.find(n => n.id == nodeId);
    if (!node) return;

    const newX = parseFloat(latLng.lng.toFixed(6));
    const newY = parseFloat(latLng.lat.toFixed(6));

    // UPDATE LOCAL CACHE
    node.x_position = newX;
    node.y_position = newY;

    await fetch('api/map_nodes.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id: nodeId,
            node_name: node.node_name,
            node_type: node.node_type,
            x_position: newX,
            y_position: newY,
            icon_type: node.node_type,
            is_locked: node.is_locked
        })
    });

}


// Toggle map lock
function toggleLock() {
    mapLocked = !mapLocked;
    const lockBtn = document.getElementById('lockBtn');

    if (mapLocked) {
        lockBtn.innerHTML = '<i class="fas fa-lock"></i> Locked';
        lockBtn.classList.remove('btn-outline');
        showNotification('Map locked - nodes cannot be moved', 'info');
    } else {
        lockBtn.innerHTML = '<i class="fas fa-lock-open"></i> Unlocked';
        lockBtn.classList.add('btn-outline');
        showNotification('Map unlocked - nodes can be moved', 'info');
    }

    // Update draggable state of all markers
    nodeMarkers.forEach(marker => {

        if (mapLocked || marker.nodeData.is_locked == 1) {
            marker.dragging.disable();
        } else {
            marker.dragging.enable();
        }

    });

}

// Refresh map data
function refreshMap() {
    loadMapData();
    showNotification('Map refreshed', 'success');
}

// Filter nodes
function filterNodes() {
    const statusFilter = document.getElementById('statusFilter').value;
    const deviceFilter = document.getElementById('deviceFilter').value;

    nodeMarkers.forEach(marker => {
        let show = true;

        // Status filter
        if (statusFilter !== 'all') {
            if (statusFilter === 'up' && marker.nodeData.status !== 'OK') {
                show = false;
            } else if (statusFilter === 'down' && marker.nodeData.status === 'OK') {
                show = false;
            }
        }

        // Device filter
        if (deviceFilter !== 'all' && marker.nodeData.device_id != deviceFilter) {
            show = false;
        }

        // Show/hide marker
        if (show) {
            map.addLayer(marker);
        } else {
            map.removeLayer(marker);
        }
    });
}

// Update device filter dropdown
function updateDeviceFilter() {
    const select = document.getElementById('deviceFilter');
    select.innerHTML = '<option value="all">All Devices</option>';

    nodes.forEach(node => {
        if (node.device_name) {
            select.innerHTML += `
                <option value="${node.device_id}">
                    ${node.device_name}
                </option>
            `;
        }
    });
}

// Test SNMP for node
async function testNodeSNMP(deviceId) {
    try {
        const response = await fetch(`api/devices.php?test=${deviceId}`);
        const result = await response.json();

        if (result.status === 'OK') {
            showNotification('SNMP test successful', 'success');
        } else {
            showNotification('SNMP test failed: ' + result.error, 'error');
        }

        // Refresh to update status
        setTimeout(loadMapData, 1000);

    } catch (error) {
        console.error('Error testing SNMP:', error);
        showNotification('SNMP test failed', 'error');
    }
}

// Discover interfaces for node
async function discoverNodeInterfaces(deviceId) {
    try {
        const response = await fetch(`api/discover_interfaces.php?device_id=${deviceId}`);
        const result = await response.json();

        if (result.success) {
            showNotification(`Discovered ${result.sfp_count} SFP interfaces`, 'success');
            // Refresh node details
            if (selectedNode) {
                setTimeout(() => selectNode(selectedNode), 1000);
            }
        } else {
            showNotification('Discovery failed: ' + result.error, 'error');
        }

    } catch (error) {
        console.error('Error discovering interfaces:', error);
        showNotification('Discovery failed', 'error');
    }
}

// Helper: Get power class for styling
function getPowerClass(power) {
    if (power >= -10) return 'power-good';
    if (power >= -20) return 'power-warning';
    return 'power-critical';
}

// Use global toast notifications when available
function showNotification(message, type = 'info') {
    if (window.mikrotikMonitor && typeof window.mikrotikMonitor.showNotification === 'function') {
        window.mikrotikMonitor.showNotification(message, type);
        return;
    }
    if (typeof window.showNotification === 'function') {
        window.showNotification(message, type);
    }
}

// Modal functions
function openAddNodeModal() {
    document.getElementById('addNodeModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('addNodeModal').style.display = 'none';
    document.getElementById('editNodeModal').style.display = 'none';
}

function closeEditModal() {
    document.getElementById('editNodeModal').style.display = 'none';
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('mapControlToggle').addEventListener('click', () => {
        document.querySelector('.map-controls').classList.toggle('open');
    });

    initMap();
});

// Resume refresh when tab becomes active
document.addEventListener('visibilitychange', () => {
    if (!document.hidden && mapRefreshPaused) {
        mapRefreshPaused = false;
        if (map) {
            loadMapData();
        }
    }
});
