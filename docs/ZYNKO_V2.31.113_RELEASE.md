# ZYNKO V2.31.113

## Objetivo
Consolidar la operación WebSocket sin falsos positivos, mantener el repositorio desplegable en cPanel y explicar la salud del tiempo real con términos comprensibles.

## WebSocket verificable
- `manage-websocket.sh` considera saludable el servicio únicamente cuando existe el proceso correcto **y** el puerto `WS_HOST:WS_PORT` acepta conexiones.
- Un PID existente sin puerto abierto se trata como proceso incompleto y se limpia antes de volver a iniciar.
- Cuando existe `setsid`, el proceso se desacopla del request web para reducir cierres al terminar PHP.
- Después de Iniciar/Reiniciar desde el panel, la interfaz hace una segunda comprobación en una solicitud independiente. Si el proceso murió al finalizar la primera solicitud, se informa como error real.

## Términos de Salud
- **WebSocket**: conexión persistente para recibir mensajes sin F5.
- **Servicio/daemon**: programa en segundo plano que mantiene esa conexión disponible.
- **PID**: identificador numérico del proceso.
- **Proxy `/ws`**: puente de Apache entre la URL pública WSS y `127.0.0.1:8080`.

## Git/cPanel
`.gitignore` excluye `.env`, PID, marcadores, logs, cachés y archivos runtime. Esos archivos no deben convertir el repositorio en un working tree sucio ni bloquear `Deploy HEAD Commit`.

## NIVO Web Chat
El archivo `nivo-widget.js` se entrega con cabeceras de no-cache para que los sitios externos validen la versión actual y no mantengan controles antiguos después de una actualización.
