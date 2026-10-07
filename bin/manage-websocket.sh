#!/usr/bin/env bash
set -u

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PID_FILE="$ROOT/storage/websocket.pid"
LOG_DIR="$ROOT/storage/logs"
LOG_FILE="$LOG_DIR/websocket.log"
MARKER="$ROOT/storage/websocket.restart.marker"
SERVER="$ROOT/websocket/server.php"
ENV_FILE="$ROOT/.env"
ACTION="${1:-status}"

mkdir -p "$LOG_DIR"

env_value(){
    local key="$1"
    [ -f "$ENV_FILE" ] || return 0
    local line
    line="$(grep -E "^${key}=" "$ENV_FILE" 2>/dev/null | tail -n1 || true)"
    line="${line#*=}"
    line="${line%$'\r'}"
    line="${line#\"}"; line="${line%\"}"
    line="${line#\'}"; line="${line%\'}"
    printf '%s' "$line"
}

WS_HOST="$(env_value WS_HOST)"
WS_PORT="$(env_value WS_PORT)"
[ -n "$WS_HOST" ] || WS_HOST="127.0.0.1"
[ "$WS_HOST" = "localhost" ] && WS_HOST="127.0.0.1"
[[ "$WS_PORT" =~ ^[0-9]+$ ]] || WS_PORT="8080"

valid_pid(){
    local p="$1"
    [ -n "$p" ] || return 1
    kill -0 "$p" 2>/dev/null || return 1
    ps -p "$p" -o args= 2>/dev/null | grep -Fq "$SERVER"
}

read_pid(){ [ -f "$PID_FILE" ] && cat "$PID_FILE" 2>/dev/null || true; }

find_pid(){
    local p
    p="$(read_pid)"
    if valid_pid "$p"; then printf '%s\n' "$p"; return 0; fi
    p="$(pgrep -f "$SERVER" 2>/dev/null | head -n1 || true)"
    if valid_pid "$p"; then
        printf '%s\n' "$p" > "$PID_FILE"
        printf '%s\n' "$p"
        return 0
    fi
    return 1
}

port_open(){
    # Bash TCP probe. It verifies the same host/port Apache must reach.
    (exec 9<>"/dev/tcp/${WS_HOST}/${WS_PORT}") >/dev/null 2>&1
}

healthy(){
    # En hosting compartido el proceso puede no ser visible mediante ps/pgrep.
    # El puerto real es la fuente de verdad operacional.
    port_open
}

stop_one(){
    local p="$1"
    valid_pid "$p" || return 0
    kill "$p" 2>/dev/null || true
    for _ in 1 2 3 4 5 6 7 8; do
        kill -0 "$p" 2>/dev/null || return 0
        sleep 0.25
    done
    kill -0 "$p" 2>/dev/null && kill -9 "$p" 2>/dev/null || true
}

stop_ws(){
    local p
    p="$(read_pid)"
    [ -n "$p" ] && stop_one "$p"
    while IFS= read -r p; do
        [ -n "$p" ] && stop_one "$p"
    done < <(pgrep -f "$SERVER" 2>/dev/null || true)
    rm -f "$PID_FILE"
}

launch_ws(){
    local php_bin
    php_bin="$(command -v php || true)"
    [ -n "$php_bin" ] || { echo "ERROR PHP CLI no disponible" >&2; return 1; }

    # setsid separates the daemon from the web/CLI process group when available.
    # This avoids the child process dying when the request that started it ends.
    if command -v setsid >/dev/null 2>&1; then
        nohup setsid "$php_bin" "$SERVER" >> "$LOG_FILE" 2>&1 < /dev/null &
    else
        nohup "$php_bin" "$SERVER" >> "$LOG_FILE" 2>&1 < /dev/null &
    fi
}

start_ws(){
    local existing
    existing="$(find_pid 2>/dev/null || true)"
    if port_open; then
        touch "$MARKER"
        if [ -n "$existing" ]; then
            echo "RUNNING pid=$existing host=$WS_HOST port=$WS_PORT"
        else
            echo "RUNNING pid=unavailable host=$WS_HOST port=$WS_PORT note=port-confirmed"
        fi
        return 0
    fi

    # A PID without an open port is stale/broken. Clean it before a new start.
    if [ -n "$existing" ]; then
        stop_one "$existing"
        rm -f "$PID_FILE"
    fi

    launch_ws || return 1

    local p=""
    for _ in 1 2 3 4 5 6 7 8 9 10; do
        sleep 0.3
        p="$(find_pid 2>/dev/null || true)"
        if [ -n "$p" ] && port_open; then
            printf '%s\n' "$p" > "$PID_FILE"
            touch "$MARKER"
            echo "RUNNING pid=$p host=$WS_HOST port=$WS_PORT"
            return 0
        fi
    done

    p="$(find_pid 2>/dev/null || true)"
    [ -n "$p" ] && stop_one "$p"
    rm -f "$PID_FILE"
    echo "ERROR El proceso inició pero no quedó escuchando en $WS_HOST:$WS_PORT. Revisa $LOG_FILE" >&2
    tail -n 5 "$LOG_FILE" 2>/dev/null >&2 || true
    return 1
}

case "$ACTION" in
  status)
    p="$(find_pid 2>/dev/null || true)"
    if port_open; then
        if [ -n "$p" ]; then
            echo "RUNNING pid=$p host=$WS_HOST port=$WS_PORT"
        else
            echo "RUNNING pid=unavailable host=$WS_HOST port=$WS_PORT note=port-confirmed"
        fi
        exit 0
    fi
    if [ -n "$p" ]; then
        echo "BROKEN pid=$p host=$WS_HOST port=$WS_PORT"
        exit 4
    fi
    echo "STOPPED host=$WS_HOST port=$WS_PORT"
    exit 3
    ;;
  start)
    start_ws
    ;;
  stop)
    stop_ws
    echo "STOPPED host=$WS_HOST port=$WS_PORT"
    ;;
  restart)
    p="$(find_pid 2>/dev/null || true)"
    if [ -z "$p" ] && port_open; then
        # El hosting oculta el PID, pero el servicio está confirmado por puerto.
        # No lanzamos un segundo daemon ni provocamos "Address already in use".
        touch "$MARKER"
        echo "RUNNING pid=unavailable host=$WS_HOST port=$WS_PORT note=restart-skipped-existing-service"
        exit 0
    fi
    stop_ws
    start_ws
    ;;
  *)
    echo "ERROR Acción inválida" >&2
    exit 2
    ;;
esac
