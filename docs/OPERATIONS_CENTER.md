# ZYNKO V2.31.113 · Centro de Operaciones

El Dashboard incorpora accesos rápidos configurables y un centro de servicios para el administrador principal.

## WebSocket
- **Iniciar**: levanta `websocket/server.php` si está detenido.
- **Detener**: apaga únicamente el daemon. La interfaz mantiene reconciliación HTTP como respaldo.
- **Reiniciar**: detiene y vuelve a iniciar el daemon. La ventana esperada es de 1–3 segundos.
- Todas las acciones solicitan confirmación SweetAlert2 y quedan en auditoría.

## Recomendación de reinicio
`storage/websocket.restart.marker` registra el último arranque exitoso. Salud compara ese momento con `.env`, `.htaccess`, `websocket/server.php` y `app/Support/Realtime.php`. Si alguno cambió después, el Dashboard recomienda reiniciar.

## Deploy
`.cpanel.yml` continúa reiniciando WebSocket automáticamente al desplegar por Git/cPanel. El aviso del Dashboard protege los casos donde se reemplazan archivos manualmente o el proceso se detiene fuera del deploy.

## Términos visibles en Salud
- **Servicio/daemon**: proceso en segundo plano que mantiene WebSocket activo. El panel usa “Servicio WebSocket” como nombre principal y conserva “daemon” solo como aclaración técnica.
- **PID**: identificador numérico del proceso.
- **WebSocket interno**: conexión local entre Apache/ZYNKO y el servicio en `WS_HOST:WS_PORT`.
- **WebSocket público**: endpoint `wss://.../ws` que utiliza el navegador.
- **Proxy `/ws`**: regla de Apache que une el endpoint público con el servicio interno.


## V2.31.115 · Monitor preventivo

El administrador principal dispone de monitoreo de WebSocket, DB, API, NIVO Web Chat, NIVO IA y canales. Los correos se envían por transición de estado, no por cada comprobación. Para continuidad fuera de las visitas web, se recomienda ejecutar `bin/service-monitor.php` cada 5 minutos mediante Cron Jobs de cPanel.
