# ZYNKO V2.31.112 · Centro de Operaciones

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
