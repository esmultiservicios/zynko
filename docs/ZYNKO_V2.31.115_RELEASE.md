# ZYNKO V2.31.115

## Monitoreo preventivo y continuidad operativa

- WebSocket, base de datos, API, NIVO Web Chat, NIVO IA y canales externos se supervisan desde un monitor común.
- Los cambios de estado generan un correo resumen al administrador: qué falló, qué sigue activo y cuándo se recuperó.
- La primera ejecución crea una línea base y no genera falsas alarmas.
- El monitor evita spam: notifica cambios, no cada revisión.
- WebSocket puede intentar autorrecuperarse si el monitor confirma una caída real.
- `bin/service-monitor.php` queda preparado para un Cron Job cada 5 minutos.
- `manage-websocket.sh` considera el puerto interno como fuente de verdad en hosting compartido aunque `ps/pgrep` no muestren el proceso.
- Si el puerto ya está ocupado por el servicio correcto y el PID no es visible, no se lanza un segundo daemon.

## Git y cPanel

`.gitignore` excluye archivos runtime y temporales generados automáticamente. Estos archivos pueden existir en producción porque la aplicación los necesita, pero no deben aparecer como cambios del repositorio.

El deploy conserva `.env`, uploads y runtime del servidor, reinicia WebSocket de forma segura y ejecuta una comprobación operativa.

## Cron recomendado

```bash
*/5 * * * * /usr/local/bin/php /home/USUARIO/public_html/zynkocloud.app/bin/service-monitor.php >/dev/null 2>&1
```

Ajusta únicamente el usuario/ruta si el hosting difiere.
