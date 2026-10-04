#!/usr/bin/env bash
set -u

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PID_FILE="$ROOT/storage/websocket.pid"
LOG_DIR="$ROOT/storage/logs"
LOG_FILE="$LOG_DIR/websocket.log"
SERVER="$ROOT/websocket/server.php"

mkdir -p "$LOG_DIR"

stop_pid() {
    local pid="$1"
    if [ -n "$pid" ] && kill -0 "$pid" 2>/dev/null; then
        local command_line
        command_line="$(ps -p "$pid" -o args= 2>/dev/null || true)"
        if [[ "$command_line" == *"websocket/server.php"* ]]; then
            kill "$pid" 2>/dev/null || true
            for _ in 1 2 3 4 5; do
                kill -0 "$pid" 2>/dev/null || return 0
                sleep 1
            done
            kill -9 "$pid" 2>/dev/null || true
        fi
    fi
}

if [ -f "$PID_FILE" ]; then
    stop_pid "$(cat "$PID_FILE" 2>/dev/null || true)"
fi

while IFS= read -r pid; do
    [ -n "$pid" ] && stop_pid "$pid"
done < <(pgrep -f "${SERVER//\//\\/}" 2>/dev/null || true)

PHP_BIN="$(command -v php || true)"
if [ -z "$PHP_BIN" ]; then
    echo "No se encontró PHP CLI para iniciar el WebSocket." >&2
    exit 1
fi

nohup "$PHP_BIN" "$SERVER" >> "$LOG_FILE" 2>&1 < /dev/null &
PID=$!
echo "$PID" > "$PID_FILE"
sleep 1

if ! kill -0 "$PID" 2>/dev/null; then
    echo "El WebSocket no quedó activo. Revisa $LOG_FILE" >&2
    exit 1
fi

echo "WebSocket activo con PID $PID"
