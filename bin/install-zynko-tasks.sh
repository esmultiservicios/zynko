#!/usr/bin/env bash
# Gestiona exclusivamente las tareas adicionales de ZYNKO; no sustituye otros crons.
# --check muestra el plan; --apply instala las tareas habilitadas en el manifiesto.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
CONFIG="$ROOT/bin/zynko-schedules.conf"
MARK_START='# BEGIN ZYNKO ADDITIONAL TASKS'
MARK_END='# END ZYNKO ADDITIONAL TASKS'
MODE="${1:---check}"
if [[ "$MODE" != '--check' && "$MODE" != '--apply' ]]; then
    echo 'Uso: bash bin/install-zynko-tasks.sh [--check|--apply]' >&2
    exit 2
fi
[[ -r "$CONFIG" ]] || { echo "ERROR: No se encuentra $CONFIG" >&2; exit 1; }
command -v crontab >/dev/null || { echo 'ERROR: crontab no está disponible.' >&2; exit 1; }
OWNER="$(stat -c '%U' "$ROOT")"
[[ "$OWNER" != root && "$OWNER" != UNKNOWN ]] || { echo 'ERROR: Ejecuta desde una instalación propiedad del usuario de cPanel.' >&2; exit 1; }
# Si lo ejecuta root, operamos SOLAMENTE sobre el cron del propietario, nunca sobre el de root.
if [[ "$(id -u)" != 0 && "$(id -un)" != "$OWNER" ]]; then
    echo "ERROR: Ejecuta como $OWNER o root." >&2; exit 1
fi
cron_read(){ if [[ "$(id -u)" == 0 ]]; then crontab -u "$OWNER" -l; else crontab -l; fi; }
cron_write(){ if [[ "$(id -u)" == 0 ]]; then crontab -u "$OWNER" -; else crontab -; fi; }
# En caso de actualizar el manifiesto, se permiten únicamente estos programas existentes.
declare -A PROGRAMS=(
  [campaign-dispatch]='campaign-dispatch.php'
  [email-inbound-poll]='email-inbound-poll.php'
  [nivo-knowledge-sync]='nivo-knowledge-sync.php'
  [nivo-rag-sync]='nivo-rag-sync.php'
)
PHP_BIN=''
for p in /usr/local/bin/php /usr/bin/php /opt/cpanel/ea-php82/root/usr/bin/php; do
    [[ -x "$p" ]] && { PHP_BIN="$p"; break; }
done
[[ -n "$PHP_BIN" ]] || { echo 'ERROR: PHP CLI no encontrado.' >&2; exit 1; }
mkdir -p "$ROOT/storage/logs"
if [[ "$(id -u)" == 0 ]]; then
    chown "$OWNER:$(stat -c '%G' "$ROOT")" "$ROOT/storage/logs"
fi
lines=()
declare -A SEEN=()
while IFS='|' read -r key enabled schedule extra || [[ -n "${key:-}" ]]; do
    key="${key%$'\r'}"
    [[ -z "$key" || "$key" == \#* ]] && continue
    [[ -z "${extra:-}" ]] || { echo "ERROR: Columnas adicionales en $key" >&2; exit 1; }
    [[ -n "${PROGRAMS[$key]+yes}" ]] || { echo "ERROR: Tarea desconocida: $key" >&2; exit 1; }
    [[ -z "${SEEN[$key]+yes}" ]] || { echo "ERROR: Tarea duplicada: $key" >&2; exit 1; }
    SEEN[$key]=1
    [[ "$enabled" == 0 || "$enabled" == 1 ]] || { echo "ERROR: Valor activo incorrecto: $key" >&2; exit 1; }
    read -ra fields <<< "$schedule"
    [[ ${#fields[@]} -eq 5 ]] || { echo "ERROR: Cron requiere 5 campos: $key" >&2; exit 1; }
    for part in "${fields[@]}"; do
        [[ "$part" =~ ^[0-9*,-]+(/[0-9]+)?$ ]] || { echo "ERROR: Campo cron no válido para $key: $part" >&2; exit 1; }
    done
    [[ -f "$ROOT/bin/${PROGRAMS[$key]}" ]] || { echo "ERROR: Falta bin/${PROGRAMS[$key]}" >&2; exit 1; }
    if [[ "$enabled" == 1 ]]; then
        logfile="$ROOT/storage/logs/${key}.log"
        script="$ROOT/bin/${PROGRAMS[$key]}"
        lock="$ROOT/storage/${key}.lock"
        if command -v flock >/dev/null; then
          cmd="/bin/flock -n '$lock' '$PHP_BIN' '$script' >> '$logfile' 2>&1"
        else
          cmd="'$PHP_BIN' '$script' >> '$logfile' 2>&1"
        fi
        lines+=("$schedule $cmd")
        echo "HABILITADA: $key ($schedule)"
    else
        echo "DISPONIBLE, NO PROGRAMADA: $key"
    fi
done < "$CONFIG"
[[ "$MODE" == '--check' ]] && { echo 'Modo comprobación: no se modificó crontab.'; exit 0; }
# Preservamos las tareas IZZY y el bloque actual del monitor. Eliminamos sólo nuestro bloque.
CURRENT="$(cron_read 2>/dev/null || true)"
CLEAN="$(printf '%s\n' "$CURRENT" | awk -v b="$MARK_START" -v e="$MARK_END" '
  $0==b {inside=1;next}
  $0==e {inside=0;next}
  !inside {print}
')"
TMP="$(mktemp)"
trap 'rm -f "$TMP"' EXIT
printf '%s\n' "$CLEAN" | sed '/^[[:space:]]*$/d' > "$TMP"
if [[ ${#lines[@]} -gt 0 ]]; then
    printf '%s\n' "$MARK_START" >> "$TMP"
    printf '%s\n' "${lines[@]}" >> "$TMP"
    printf '%s\n' "$MARK_END" >> "$TMP"
fi
# Prevalidar sintaxis con crontab de forma no destructiva cuando esté disponible.
if crontab --help 2>&1 | grep -q -- '-T'; then crontab -T "$TMP"; fi
cron_write < "$TMP"
echo "OK: Tareas adicionales de ZYNKO sincronizadas para $OWNER. No se modificaron otros bloques de cron."
