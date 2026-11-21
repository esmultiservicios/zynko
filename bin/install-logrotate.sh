#!/usr/bin/env bash
# Instala o valida la rotación de logs de ZYNKO. Ejecutar como root.
set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
  echo 'ERROR: Ejecuta este script como root (requiere escribir en /etc/logrotate.d).' >&2
  exit 1
fi

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
LOG_DIR="$ROOT/storage/logs"
CONFIG="/etc/logrotate.d/zynko"
USER="$(stat -c '%U' "$ROOT")"
GROUP="$(stat -c '%G' "$ROOT")"

if [ "$USER" = root ] || [ "$USER" = UNKNOWN ] || [ "$GROUP" = UNKNOWN ]; then
  echo 'ERROR: No se pudo determinar un propietario de la instalación distinto de root.' >&2
  exit 1
fi
if ! command -v logrotate >/dev/null 2>&1; then
  echo 'ERROR: Logrotate no está instalado.' >&2
  exit 1
fi
mkdir -p "$LOG_DIR"

TEMP="$(mktemp /etc/logrotate.d/.zynko.XXXXXX)"
trap 'rm -f "$TEMP"' EXIT
cat > "$TEMP" <<CONFIGURATION
$LOG_DIR/*.log {
    daily
    rotate 30
    maxsize 20M
    missingok
    notifempty
    compress
    delaycompress
    copytruncate
    su $USER $GROUP
}
CONFIGURATION
chmod 644 "$TEMP"

# Idempotente: no reescribe configuración cuando no hay cambios.
if [ -f "$CONFIG" ] && cmp -s "$TEMP" "$CONFIG"; then
  echo "OK Configuración ya instalada: $CONFIG"
else
  if [ -f "$CONFIG" ]; then
    cp -a "$CONFIG" "${CONFIG}.bak.$(date +%Y%m%d_%H%M%S)"
  fi
  install -m 644 -o root -g root "$TEMP" "$CONFIG"
  echo "OK Configuración instalada: $CONFIG"
fi
logrotate -d "$CONFIG" >/dev/null 2>&1 || { echo 'ERROR: La validación de logrotate falló.' >&2; exit 1; }
echo "OK Validación correcta; directorio: $LOG_DIR"
echo 'Nota: el sistema debe tener habilitado logrotate.timer (o su cron equivalente).'
