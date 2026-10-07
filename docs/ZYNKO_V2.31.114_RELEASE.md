# ZYNKO V2.31.114

## Estado WebSocket unificado

- Dashboard, Configuración y el indicador global `Canales x/x` usan el mismo estado confirmado del servicio.
- Después de Iniciar, Detener o Reiniciar, ZYNKO realiza la verificación real del servicio y actualiza inmediatamente el indicador global.
- Tras confirmar la operación, la página se recarga una sola vez para recalcular Salud integral, PID, puerto interno, endpoint público y recomendación de reinicio.
- Un servicio confirmado como activo muestra `Tiempo real`; un servicio detenido o con fallo muestra `Auto`/estado de atención.
- No se declara éxito basándose únicamente en el clic o en un PID: se conserva la comprobación posterior del puerto.

## Operación

Los controles de WebSocket disponibles en Dashboard y Configuración comparten exactamente el mismo flujo, confirmación SweetAlert2, verificación y resultado.
