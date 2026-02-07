#!/usr/bin/env bash
set -euo pipefail

LOG_DIR="$(cd "$(dirname "$0")/../logs" && pwd)"

# Truncate all .log files in logs/ directory
for f in "$LOG_DIR"/*.log; do
  [ -e "$f" ] || continue
  if [ "$(basename "$f")" = "security.log" ]; then
    continue
  fi
  : > "$f"
done
