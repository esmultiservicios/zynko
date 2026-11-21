#!/usr/bin/env bash
# Inventario de tareas operativas de ZYNKO. Solo lectura: no instala ni ejecuta jobs.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
OWNER="$(stat -c '%U' "$ROOT")"
if [[ "$OWNER" == root || "$OWNER" == UNKNOWN ]]; then
    echo 'ERROR: propietario de proyecto no identificable' >&2
    exit 1
fi
printf 'Proyecto: %s\nUsuario: %s\n\n' "$ROOT" "$OWNER"
echo '===== SCRIPTS OPERATIVOS PRESENTES ====='
for name in service-monitor.php campaign-dispatch.php email-inbound-poll.php nivo-knowledge-sync.php nivo-rag-sync.php manage-websocket.sh restart-websocket.sh install-service-monitor-cron.sh install-logrotate.sh backup-server-config.sh audit-server-jobs.sh; do
    if [[ -f "$ROOT/bin/$name" ]]; then printf 'PRESENTE  bin/%s\n' "$name"; else printf 'AUSENTE   bin/%s\n' "$name"; fi
done
echo
echo '===== TAREAS DEL USUARIO ASOCIADAS A ZYNKO ====='
CURRENT="$(crontab -u "$OWNER" -l 2>/dev/null || true)"
if [[ -z "$CURRENT" ]]; then
    echo 'Ninguna tarea encontrada o crontab no accesible.'
else
    printf '%s\n' "$CURRENT" | grep -Ei 'ZYNKO|zynkocloud\.app' || true
fi
echo
echo 'IMPORTANTE: La presencia de scripts PHP no implica que deban programarse automáticamente.'
echo 'No se instalan nuevos cron, ni se cambia el servidor.'
