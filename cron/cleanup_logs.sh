#!/usr/bin/env bash
set -euo pipefail

LOG_DIR="$(cd "$(dirname "$0")" && pwd)"

# Truncate all .log files in cron/ directory
for f in "$LOG_DIR"/*.log; do
  [ -e "$f" ] || continue
  : > "$f"
done
