#!/usr/bin/env bash
set -u

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PID_FILE="$ROOT/storage/websocket.pid"
LOG_DIR="$ROOT/storage/logs"
LOG_FILE="$LOG_DIR/websocket.log"
MARKER="$ROOT/storage/websocket.restart.marker"
SERVER="$ROOT/websocket/server.php"
ACTION="${1:-status}"

mkdir -p "$LOG_DIR"

valid_pid(){ local p="$1"; [ -n "$p" ] && kill -0 "$p" 2>/dev/null && ps -p "$p" -o args= 2>/dev/null | grep -Fq "$SERVER"; }
read_pid(){ [ -f "$PID_FILE" ] && cat "$PID_FILE" 2>/dev/null || true; }
find_pid(){
  local p; p="$(read_pid)"
  if valid_pid "$p"; then echo "$p"; return 0; fi
  p="$(pgrep -f "${SERVER//\//\\/}" 2>/dev/null | head -n1 || true)"
  if valid_pid "$p"; then echo "$p" > "$PID_FILE"; echo "$p"; return 0; fi
  return 1
}
is_running(){ find_pid >/dev/null 2>&1; }
stop_one(){
  local p="$1"; valid_pid "$p" || return 0
  kill "$p" 2>/dev/null || true
  for _ in 1 2 3 4 5; do kill -0 "$p" 2>/dev/null || return 0; sleep 0.4; done
  kill -0 "$p" 2>/dev/null && kill -9 "$p" 2>/dev/null || true
}
stop_ws(){
  local p; p="$(read_pid)"; [ -n "$p" ] && stop_one "$p"
  while IFS= read -r p; do [ -n "$p" ] && stop_one "$p"; done < <(pgrep -f "${SERVER//\//\\/}" 2>/dev/null || true)
  rm -f "$PID_FILE"
}
start_ws(){
  local existing; existing="$(find_pid 2>/dev/null || true)"
  if [ -n "$existing" ]; then echo "RUNNING pid=$existing"; return 0; fi
  local php_bin; php_bin="$(command -v php || true)"
  [ -n "$php_bin" ] || { echo "ERROR PHP CLI no disponible" >&2; return 1; }
  nohup "$php_bin" "$SERVER" >> "$LOG_FILE" 2>&1 < /dev/null &
  local pid=$!; echo "$pid" > "$PID_FILE"
  sleep 1
  if ! valid_pid "$pid"; then
    echo "ERROR El proceso WebSocket no quedó activo. Revisa $LOG_FILE" >&2; rm -f "$PID_FILE"; return 1
  fi
  touch "$MARKER"
  echo "RUNNING pid=$pid"
}

case "$ACTION" in
  status)
    p="$(find_pid 2>/dev/null || true)"; if [ -n "$p" ]; then echo "RUNNING pid=$p"; exit 0; fi
    echo "STOPPED"; exit 3;;
  start) start_ws;;
  stop) stop_ws; echo "STOPPED";;
  restart) stop_ws; start_ws;;
  *) echo "ERROR Acción inválida" >&2; exit 2;;
esac
