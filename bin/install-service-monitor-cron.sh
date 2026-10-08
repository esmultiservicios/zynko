#!/usr/bin/env bash
set -u

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MARK_BEGIN="# BEGIN ZYNKO SERVICE MONITOR"
MARK_END="# END ZYNKO SERVICE MONITOR"
LOG_DIR="$ROOT/storage/logs"
LOCK_FILE="$ROOT/storage/service-monitor.lock"
mkdir -p "$LOG_DIR"

PHP_BIN=""
for candidate in /usr/local/bin/php /usr/bin/php "$(command -v php 2>/dev/null || true)"; do
    if [ -n "$candidate" ] && [ -x "$candidate" ]; then PHP_BIN="$candidate"; break; fi
done
[ -n "$PHP_BIN" ] || { echo "WARN PHP CLI no disponible; no se instaló el monitor automático." >&2; exit 0; }
command -v crontab >/dev/null 2>&1 || { echo "WARN crontab no disponible; configura bin/service-monitor.php cada minuto desde cPanel." >&2; exit 0; }

CURRENT="$(crontab -l 2>/dev/null || true)"
CLEAN="$(printf '%s\n' "$CURRENT" | awk -v b="$MARK_BEGIN" -v e="$MARK_END" '
$0==b {skip=1; next}
$0==e {skip=0; next}
!skip {print}
')"

if command -v flock >/dev/null 2>&1; then
    RUNNER="$(command -v flock) -n '$LOCK_FILE' '$PHP_BIN' '$ROOT/bin/service-monitor.php' >> '$LOG_DIR/service-monitor.log' 2>&1"
else
    RUNNER="'$PHP_BIN' '$ROOT/bin/service-monitor.php' >> '$LOG_DIR/service-monitor.log' 2>&1"
fi

{
    printf '%s\n' "$CLEAN"
    printf '%s\n' "$MARK_BEGIN"
    # Cada minuto: detecta cambios, envía correo y autorrecupera WebSocket si cae.
    printf '* * * * * %s\n' "$RUNNER"
    printf '%s\n' "$MARK_END"
} | sed '/^[[:space:]]*$/N;/^\n$/D' | crontab -

echo "OK Monitor automático ZYNKO instalado cada minuto."
