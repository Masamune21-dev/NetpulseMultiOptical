let chart = null;
let currentRange = '1h';
let autoRefreshTimer = null;

const deviceSelect = document.getElementById('deviceSelect');
const interfaceSelect = document.getElementById('interfaceSelect');

/* ======================================================
   INIT
====================================================== */
document.addEventListener('DOMContentLoaded', () => {
    initChart();
    loadDevices();

    document.querySelectorAll('.btn-range').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.btn-range')
                .forEach(b => b.classList.remove('active'));

            btn.classList.add('active');
            setRange(btn.dataset.range);
        });
    });
});

/* ======================================================
   AUTO REFRESH
====================================================== */
function startAutoRefresh() {
    stopAutoRefresh();

    const refreshIntervals = {
        '1h': 10000,
        '1d': 30000,
        '3d': 60000,
        '7d': 120000,
        '30d': 300000,
        '1y': 600000
    };

    autoRefreshTimer = setInterval(
        loadChart,
        refreshIntervals[currentRange] || 10000
    );
}

function stopAutoRefresh() {
    if (autoRefreshTimer) {
        clearInterval(autoRefreshTimer);
        autoRefreshTimer = null;
    }
}

/* ======================================================
   LOAD DEVICES & INTERFACES
====================================================== */
function loadDevices() {
    fetch('api/monitoring_devices.php')
        .then(r => r.json())
        .then(devices => {
            deviceSelect.innerHTML =
                '<option value="">-- Pilih Device --</option>';

            devices.forEach(d => {
                deviceSelect.innerHTML += `
                    <option value="${d.id}">
                        ${d.device_name}
                    </option>`;
            });
        });
}

deviceSelect.addEventListener('change', () => {
    stopAutoRefresh();
    interfaceSelect.innerHTML =
        '<option value="">-- Pilih Interface --</option>';

    if (!deviceSelect.value) return;

    fetch(`api/monitoring_interfaces.php?device_id=${deviceSelect.value}`)
        .then(r => r.json())
        .then(ifs => {
            ifs.forEach(i => {
                interfaceSelect.innerHTML += `
                    <option value="${i.if_index}"
                            data-name="${i.if_name}"
                            data-alias="${i.if_alias || ''}">
                        ${i.if_name}${i.if_alias ? ' — ' + i.if_alias : ''}
                    </option>`;
            });
        });
});

interfaceSelect.addEventListener('change', () => {
    const opt = interfaceSelect.selectedOptions[0];
    if (!opt) return;

    const name = opt.dataset.name;
    const alias = opt.dataset.alias;

    document.getElementById('ifaceInfo').innerHTML = alias
        ? `Interface: <b>${name}</b> <span style="color:#64748b">(${alias})</span>`
        : `Interface: <b>${name}</b>`;

    loadChart();
    startAutoRefresh();
});

/* ======================================================
   RANGE
====================================================== */
function setRange(r) {
    currentRange = r;
    loadChart();
    startAutoRefresh();
}

/* ======================================================
   INIT CHART (TIME SCALE, 24 JAM WIB)
====================================================== */
function initChart() {
    const ctx = document.getElementById('opticalChart').getContext('2d');

    chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                data: [],
                borderColor: '#b53fec',
                backgroundColor: 'rgba(255,255,255,0.06)',
                borderWidth: 2,
                tension: 0.25,
                pointRadius: 0,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => `${ctx.parsed.y.toFixed(2)} dBm`
                    }
                }
            },
            scales: {
                x: {
                    type: 'time',
                    time: {
                        tooltipFormat: 'dd MMM yyyy HH:mm' // 24 JAM
                    },
                    ticks: {
                        maxTicksLimit: 10,
                        callback: (value) => {
                            const d = new Date(value);
                            return new Intl.DateTimeFormat('id-ID', {
                                hour: '2-digit',
                                minute: '2-digit',
                                hour12: false   // ⬅️ INI KUNCI UTAMA
                            }).format(d);
                        }

                    },
                    grid: {
                        color: 'rgba(255,255,255,0.05)'
                    }
                },
                y: {
                    ticks: {
                        callback: v => v.toFixed(2) + ' dBm'
                    }
                }
            }
        }
    });
}


/* ======================================================
   LOAD CHART DATA
====================================================== */
function loadChart() {
    if (!chart || !deviceSelect.value || !interfaceSelect.value) return;

    fetch(
        `api/interface_chart.php?device_id=${deviceSelect.value}` +
        `&if_index=${interfaceSelect.value}&range=${currentRange}`
    )
        .then(r => r.json())
        .then(data => {
            if (!data.length) return;

            const rx = data.map(d => Number(d.rx_power));

            let labels = data.map(d =>
                new Date(d.created_at.replace(' ', 'T'))
            );
            let rxData = rx;

            if (data.length > 100) {
                const step = Math.ceil(data.length / 100);
                labels = [];
                rxData = [];

                for (let i = 0; i < data.length; i += step) {
                    labels.push(
                        new Date(data[i].created_at.replace(' ', 'T'))
                    );
                    rxData.push(rx[i]);
                }
            }

            // ⏱️ UNIT WAKTU PER RANGE
            const timeUnits = {
                '1h': 'minute',
                '1d': 'hour',
                '3d': 'hour',
                '7d': 'day',
                '30d': 'day',
                '1y': 'month'
            };

            chart.options.scales.x.time.unit = timeUnits[currentRange];

            // 🔒 PAKSA FORMAT 24 JAM (TANPA AM/PM)
            chart.options.scales.x.ticks.callback = (value) => {
                const d = new Date(value);

                const formats = {
                    '1h': { hour: '2-digit', minute: '2-digit' },
                    '1d': { hour: '2-digit', minute: '2-digit' }, // ⬅️ FIX
                    '3d': { weekday: 'short', hour: '2-digit' },  // ⬅️ ada HARI
                    '7d': { weekday: 'short', day: '2-digit' },   // ⬅️ Sen 12
                    '30d': { day: '2-digit', month: 'short' },
                    '1y': { month: 'short', year: '2-digit' }
                };

                return new Intl.DateTimeFormat(
                    'id-ID',
                    { ...formats[currentRange], hour12: false }
                ).format(d);
            };

            chart.data.labels = labels;
            chart.data.datasets[0].data = rxData;

            chart.update();
            updateStats(rx);
        });
}

/* ======================================================
   STATS
====================================================== */
function updateStats(rx) {
    const now = rx.at(-1);
    const avg = rx.reduce((a, b) => a + b, 0) / rx.length;

    document.getElementById('rxStats').innerHTML = `
        Now <b>${now.toFixed(2)} dBm</b>
        Avg <b>${avg.toFixed(2)} dBm</b>
        Min <b>${Math.min(...rx).toFixed(2)} dBm</b>
        Max <b>${Math.max(...rx).toFixed(2)} dBm</b>
    `;
}
