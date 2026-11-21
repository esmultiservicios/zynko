#!/usr/bin/env bash
# Respalda configuraciones administrativas, no datos ni credenciales de ZYNKO.
# Ejecutar como root. El archivo generado queda FUERA de public_html.
set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
  echo 'ERROR: Ejecuta este respaldo como root.' >&2
  exit 1
fi
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
OWNER="$(stat -c '%U' "$ROOT")"
if [ "$OWNER" = root ] || [ "$OWNER" = UNKNOWN ]; then
  echo 'ERROR: No se pudo determinar el usuario propietario de ZYNKO.' >&2
  exit 1
fi
OWNER_HOME="$(getent passwd "$OWNER" | cut -d: -f6)"
if [ -z "$OWNER_HOME" ] || [ ! -d "$OWNER_HOME" ]; then
  echo 'ERROR: No se encontró el directorio personal del propietario.' >&2
  exit 1
fi
DEST="$OWNER_HOME/zynko-server-backups"
mkdir -p "$DEST"
chmod 700 "$DEST"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

if [ -f /etc/logrotate.d/zynko ]; then
  cp -a /etc/logrotate.d/zynko "$TMP/logrotate-zynko"
fi
# Almacena la tabla entera por seguridad, pero NO debe restaurarse sin comparar
# sus tareas: puede incluir otros servicios del usuario (por ejemplo, IZZY).
crontab -u "$OWNER" -l > "$TMP/crontab-$OWNER.txt" 2>/dev/null || :
{
  printf 'Servidor: %s\n' "$(hostname)"
  printf 'Fecha: %s\n' "$(date -Is)"
  printf 'Instalación ZYNKO: %s\n' "$ROOT"
  printf 'Restauración: ejecutar bin/install-logrotate.sh como root y bin/install-service-monitor-cron.sh como %s.\n' "$OWNER"
  printf 'No restaurar el crontab completo sin comparar sus otras tareas.\n'
} > "$TMP/README.txt"
ARCHIVE="$DEST/ZYNKO_Configuracion_Servidor_$(date +%Y%m%d_%H%M%S).tar.gz"
(umask 077; tar -czf "$ARCHIVE" -C "$TMP" .)
chmod 600 "$ARCHIVE"
echo "OK Respaldo creado: $ARCHIVE"
echo 'IMPORTANTE: conservar también una copia segura fuera de WEB01.'
