// ===============================
// PAGE LOAD
// ===============================
document.addEventListener('DOMContentLoaded', () => {
    loadDevices();
    loadMonitoringDevices(); // ✅ WAJIB
});

// ===============================
// TAB HANDLER
// ===============================
function openTab(id) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

    document.querySelector(`.tab[onclick*="${id}"]`).classList.add('active');
    document.getElementById(id).classList.add('active');
}

// ===============================
// SNMP DEVICE LIST
// ===============================
function loadDevices() {
    fetch('api/devices.php')
        .then(r => r.json())
        .then(data => {
            const tb = document.querySelector('#deviceTable tbody');
            tb.innerHTML = '';

            data.forEach(d => {
                tb.innerHTML += `
                <tr>
                    <td>${d.device_name}</td>
                    <td>${d.ip_address}</td>
                    <td>v${d.snmp_version}</td>
                    <td>${d.snmp_version === '2c' ? d.community : (d.snmp_user || '-')}</td>
                    <td>${d.last_status ?? '-'}</td>
                    <td class="actions-cell">
                        <div class="action-buttons">
                            <button class="btn btn-icon btn-edit" onclick='editDevice(${JSON.stringify(d)})'>
                            <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-icon btn-info" onclick="testSNMP(${d.id})">
                            <i class="fas fa-plug"></i>
                            </button>
                            <button class="btn btn-icon btn-danger" onclick="deleteDevice(${d.id})">
                            <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>`;
            });
        });
}


// ===============================
// MODAL
// ===============================
function openAddDevice() {
    document.getElementById('deviceModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('deviceModal').style.display = 'none';
}

function editDevice(d) {
    openAddDevice();
    device_id.value = d.id;
    device_name.value = d.device_name;
    ip_address.value = d.ip_address;
    snmp_version.value = d.snmp_version;
    community.value = d.community;
    snmp_user.value = d.snmp_user;
    is_active.value = d.is_active;
}

// ===============================
// CRUD
// ===============================
function saveDevice() {
    fetch('api/devices.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id: device_id.value || null,
            device_name: device_name.value,
            ip_address: ip_address.value,
            snmp_version: snmp_version.value,
            community: community.value,
            snmp_user: snmp_user.value,
            is_active: is_active.value
        })
    }).then(() => {
        closeModal();
        loadDevices();
        loadMonitoringDevices(); // 🔄 refresh dropdown
    });
}

function deleteDevice(id) {
    if (!confirm('Delete device?')) return;
    fetch('api/devices.php?id=' + id, { method: 'DELETE' })
        .then(() => {
            loadDevices();
            loadMonitoringDevices();
        });
}

// ===============================
// SNMP TEST
// ===============================
window.testSNMP = function (id) {
    fetch('api/devices.php?test=' + id)
        .then(r => r.json())
        .then(d => {
            alert(
                d.status === 'OK'
                    ? 'SNMP OK\n\n' + d.response
                    : 'SNMP FAILED\n\n' + d.error
            );
            loadDevices();
        })
        .catch(() => alert('SNMP error'));
};

// ===============================
// MONITORING TAB
// ===============================
function loadMonitoringDevices() {
    fetch('api/devices.php')
        .then(r => r.json())
        .then(data => {
            const sel = document.getElementById('monitorDeviceSelect');
            if (!sel) return;

            sel.innerHTML = '<option value="">-- Pilih Device --</option>';

            data.forEach(d => {
                if (d.is_active == 1) {
                    sel.innerHTML += `
                        <option value="${d.id}">
                            ${d.device_name} (${d.ip_address})
                        </option>`;
                }
            });
        });
}

function selectMonitoringDevice() {

    const id = document.getElementById('monitorDeviceSelect').value;
    if (!id) return;

    discoverSelectedInterfaces();
}

// ===============================
// DISCOVER INTERFACES (Dengan error handling yang lebih baik)
// ===============================
async function discoverSelectedInterfaces() {

    const sel = document.getElementById('monitorDeviceSelect');
    const id = sel.value;

    if (!id) return alert('Pilih device dulu');

    const name = sel.selectedOptions[0].text.toLowerCase();

    const vendor = name.includes('sekarjalak') || name.includes('huawei')
        ? 'huawei'
        : 'mikrotik';

    const api = vendor === 'huawei'
        ? 'api/huawei_discover_optics.php'
        : 'api/discover_interfaces.php';

    const box = document.getElementById('monitoringContent');

    box.innerHTML = `<div class="alert info">🔍 Discovering (${vendor})...</div>`;

    try {

        const r = await fetch(`${api}?device_id=${id}&_=${Date.now()}`);
        const raw = await r.text();

        console.log(raw);

        let data = {};

        try {
            data = JSON.parse(raw);
        } catch {
            console.warn('Non JSON Huawei output OK');
        }

        setTimeout(() => loadInterfaces(id), 2000);

    } catch (e) {

        console.error(e);
        alert('Discovery failed');

    }
}

function loadInterfaces(deviceId) {
    const box = document.getElementById('monitoringContent');
    box.innerHTML = '<div class="alert info">📡 Loading interface data...</div>';

    fetch(`api/interfaces.php?device_id=${deviceId}&_=${Date.now()}`)
        .then(r => {
            if (!r.ok) throw new Error(`HTTP ${r.status}`);
            return r.json();
        })
        .then(data => {

            if (!Array.isArray(data) || data.length === 0) {
                box.innerHTML = `
                    <div class="alert warning">
                        <strong>No interfaces found</strong><br>
                        Click <b>Discover Interface</b> first.
                    </div>`;
                return;
            }

            const sfp = data.filter(i => Number(i.is_sfp) === 1)
                .sort((a, b) => a.if_index - b.if_index);
            const other = data.filter(i => Number(i.is_sfp) === 0);

            let html = `
<div class="table-responsive">
<h3>🔦 SFP / QSFP Interfaces (${sfp.length})</h3>
<table class="table">
<thead>
<tr>
    <th>Idx</th>
    <th>Interface</th>
    <th>Type</th>
    <th>TX (dBm)</th>
    <th>RX (dBm)</th>
    <th>Loss</th>
    <th>Status</th>
</tr>
</thead>
<tbody>`;

            sfp.forEach(i => {

                /* ===== FORCE NUMBER ===== */
                const tx = Number(i.tx_power);
                const rx = Number(i.rx_power);

                let txHtml = '-';
                let rxHtml = '-';
                let lossHtml = '-';
                let status = 'Unknown';
                let statusClass = 'badge-unknown';

                /* ===== TX ===== */
                if (Number.isFinite(tx)) {
                    let c = tx < -10 ? '#dc2626'
                        : tx < 0 ? '#f97316'
                            : tx < 5 ? '#16a34a'
                                : '#dc2626';
                    txHtml = `<b style="color:${c}">${tx.toFixed(2)}</b>`;
                }

                /* ===== RX ===== */
                if (Number.isFinite(rx)) {
                    let c = rx < -30 ? '#dc2626'
                        : rx < -20 ? '#f97316'
                            : rx < 0 ? '#16a34a'
                                : rx < 5 ? '#2563eb'
                                    : '#dc2626';
                    rxHtml = `<b style="color:${c}">${rx.toFixed(2)}</b>`;
                }

                /* ===== ATTENUATION ===== */
                if (Number.isFinite(tx) && Number.isFinite(rx)) {
                    const loss = tx - rx;
                    let c = '#16a34a';

                    if (loss < 0) {
                        c = '#dc2626';
                        status = 'Invalid';
                        statusClass = 'badge-invalid';
                    } else if (loss > 30) {
                        c = '#dc2626';
                        status = 'Critical';
                        statusClass = 'badge-critical';
                    } else if (loss > 15) {
                        c = '#f97316';
                        status = 'Warning';
                        statusClass = 'badge-warning';
                    } else if (loss > 5) {
                        c = '#eab308';
                        status = 'Moderate';
                        statusClass = 'badge-moderate';
                    } else {
                        status = 'Good';
                        statusClass = 'badge-good';
                    }

                    lossHtml = `<b style="color:${c}">${loss.toFixed(2)}</b>`;
                }

                html += `
<tr>
    <td class="text-center"><code>${i.if_index}</code></td>
    <td>
        <b>${i.if_name}</b>
        ${i.if_alias ? `<div class="iface-comment">${i.if_alias}</div>` : ''}
    </td>
    <td class="text-center">
    <span class="interface-badge ${i.interface_type === 'QSFP+' ? 'badge-qsfp' : 'badge-sfp'}">
        ${i.interface_type}
    </span>
    </td>
    <td class="text-center">${txHtml}</td>
    <td class="text-center">${rxHtml}</td>
    <td class="text-center">${lossHtml}</td>
    <td class="text-center">
        <span class="status-badge ${statusClass}">${status}</span>
    </td>
</tr>`;
            });

            html += `</tbody></table></div>`;

            /* ===== NON SFP ===== */
            if (other.length) {
                html += `
<h3 style="margin-top:20px">📶 Other Interfaces</h3>
<table class="table">
<thead><tr><th>Idx</th><th>Name</th><th>Comment</th><th>Type</th></tr></thead><tbody>`;
                other.forEach(i => {
                    html += `
<tr>
<td class="text-center">${i.if_index}</td>
<td>${i.if_name}</td>
<td class="text-center">${i.if_alias || '-'}</td>
<td class="text-center">${i.interface_type}</td>
</tr>`;
                });
                html += `</tbody></table>`;
            }

            box.innerHTML = html;
        })
        .catch(err => {
            console.error(err);
            box.innerHTML = `<div class="alert error">❌ ${err.message}</div>`;
        });
}
