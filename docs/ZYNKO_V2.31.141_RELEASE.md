# ZYNKO v2.31.141

## WebSocket más estable y alertas operativas completas

- El arranque usa un proceso completamente desacoplado y espera más tiempo antes de declarar fallo.
- El navegador verifica el estado varias veces para evitar falsos errores mientras el puerto termina de quedar disponible.
- `.cpanel.yml` instala de forma idempotente un Cron Job cada minuto mediante `bin/install-service-monitor-cron.sh`.
- `bin/service-monitor.php` detecta cambios de WebSocket, BD, API, NIVO Web Chat, NIVO IA y canales; envía correo en cada transición configurada.
- Si WebSocket cae, el monitor registra primero la caída y después la recuperación automática, por lo que el incidente no queda oculto aunque se repare en la misma ejecución.
- Las acciones manuales Iniciar/Detener/Reiniciar ejecutan inmediatamente el monitor para registrar y notificar el cambio real. Un Detener manual crea una marca de intención para que ni el cron ni Web Chat lo vuelvan a iniciar hasta que el administrador pulse Iniciar/Reiniciar.
- No requiere actualización de base de datos.
