# ZYNKO WebSocket

Servidor WebSocket propio de ZYNKO, aislado por `tenant_id` y autenticado con tokens HMAC de corta duración.

## Arranque local
1. Completa el instalador de ZYNKO.
2. Ejecuta `websocket\start.bat` o `php websocket/server.php`.
3. Debe mostrar: `ZYNKO WebSocket activo en ws://127.0.0.1:8080`.

El navegador reconecta automáticamente. Los módulos backend pueden publicar eventos usando `app/Support/Realtime.php` y `zynkoRealtimePublish(...)`.
