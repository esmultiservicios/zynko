#!/usr/bin/env bash
set -u
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
exec /bin/bash "$ROOT/bin/manage-websocket.sh" restart
