# 🚀 Netpulse Multi Optical Monitoring

Netpulse Multi Optical Monitoring adalah sistem monitoring jaringan berbasis web untuk:

- OLT (Hioso)
- ONU monitoring per PON
- SFP / QSFP optical power (TX / RX / Loss)
- SNMP interface discovery
- Huawei Telnet optical reader
- Real-time dashboard
- Cron based polling
- JSON storage cache
- History database

Dirancang khusus untuk kebutuhan ISP / NOC.

---

# ✨ Features

## 🔦 Optical Monitoring

- TX / RX Power
- Optical Loss
- Temperature
- Interface Status
- SFP / QSFP auto detect

## 🧠 Device Discovery

- SNMP IF-MIB
- XGigabitEthernet / 100GE auto scan
- Huawei Telnet fallback

## 🛰 OLT ONU Monitor

- Multi OLT
- Multi PON
- ONU RX / TX / Temp
- Status Up / Down
- Cached JSON storage

## ⏱ Background Polling

- Cron based collection
- Atomic JSON write
- Lock system (no double run)

---

# 🧱 Requirements

Server:

- Linux (Ubuntu recommended)
- PHP >= 8.0
- MySQL / MariaDB
- Apache / Nginx
- SNMP tools
- Expect

Packages:

```bash
sudo apt install php php-mysql snmp expect bc git
```

# 📁 Folder Structure

```bash
mikrotik-crs-monitor/
│
├── api/                # Backend APIs
├── assets/            # CSS / JS
├── config/            # Config files
├── cron/              # Background jobs
├── storage/           # JSON runtime data
├── includes/          # Layout + Auth
├── huawei_telnet_expect.sh
└── index.php
```

# 🛠 Installation

## 1️⃣ Clone Repo

```bash
git clone https://github.com/Masamune21-dev/NetpulseMultiOptical.git
cd NetpulseMultiOptical
```

## 2️⃣ Database Config

Copy example:
```bash
cp config/database.example.php config/database.php
```

Edit:
```bash
return [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => 'password',
    'name' => 'netpulse'
];
```

## 3️⃣ OLT Config

```bash
cp config/olt.example.php config/olt.php
```

Example:
```bash
return [
    'olt-1' => [
        'name' => 'Huawei C300',
        'ip'   => '192.168.1.1',
        'pons' => ['0/1/0','0/1/1']
    ]
];
```

## 4️⃣ Huawei Telnet Script

```bash
cp huawei_telnet_expect.example.sh huawei_telnet_expect.sh
chmod +x huawei_telnet_expect.sh
```

## 5️⃣ Storage Folder

```bash
mkdir -p storage
chmod -R 777 storage
```

## 6️⃣ Import Database Schema

Create database:

```bash
mysql -u root -p
CREATE DATABASE netpulse;
exit
```

Import schema:

```bash
mysql -u root -p netpulse < database/schema.sql
```

## 7️⃣ Web Server Setup

Apache example:

```bash
sudo chown -R www-data:www-data NetpulseMultiOptical
sudo chmod -R 755 NetpulseMultiOptical
```

Point document root to:

```bash
NetpulseMultiOptical/
```

Open browser:
```bash
http://your-server-ip/
```

# ⏱ Cron Setup

Edit crontab:
```bash
crontab -e
```
Add:
```bash
*/1 * * * * /usr/bin/php /var/www/NetpulseMultiOptical/cron/poll_interfaces.php >> /var/www/NetpulseMultiOptical/cron/cron.log 2>&1
* * * * * /bin/bash -c '/usr/bin/php /var/www/NetpulseMultiOptical/cron/collect_olt.php olt-1 >> /var/www/NetpulseMultiOptical/cron/olt_cron.log 2>&1 & /usr/bin/php /var/www/NetpulseMultiOptical/cron/collect_olt.php olt-2 >> /var/www/NetpulseMultiOptical/cron/olt_cron.log 2>&1 & wait'
*/1 * * * * /usr/bin/php /var/www/NetpulseMultiOptical/api/huawei_discover_optics.php >> /var/www/NetpulseMultiOptical/cron/huawei_cron.log  2>&1
```

# 🧪 Manual Test

Discover Interfaces
```bash
php api/discover_interfaces.php?device_id=1
```

Huawei Optical Pull
```bash
php api/huawei_discover_optics.php
```

OLT Collector
```bash
php cron/collect_olt.php olt-1
```

# 📊 Database Tables

Core tables:

- snmp_devices
- interfaces
- interface_stats
- users
- alerts
- map_nodes
- sfp_optical_logs

Runtime OLT data stored in:
```bash
storage/olt-x/pon_x_x.json
```

# 🔐 Security Notes

Sensitive files are ignored:
```bash
config/database.php
config/olt.php
huawei_telnet_expect.sh
storage/*
cron/*.log
```

Use provided examples:

- database.example.php
- olt.example.php
- huawei_telnet_expect.example.sh
- Never commit credentials.

# 🧠 Architecture Overview

```bash
SNMP → discover_interfaces.php → interfaces table
Huawei Telnet → huawei_discover_optics.php → tx/rx DB
OLT Cron → storage JSON
Frontend → reads DB + JSON
```

# 🛑 Common Issues

RX / TX always NULL

Ensure:

- expect installed
- huawei_telnet_expect.sh executable
- SNMP community correct

# 📈 Roadmap

- LibreNMS integration
- Fiber map visualization
- Alarm threshold engine
- Telegram alerts
- Optical history charts
- Role based access

# ❤️ Credits

Developed for ISP / NOC environment
Author: Misbakhul Munir

# ⭐ Star This Repo

If this project helps your network operation, please ⭐ it.


---
