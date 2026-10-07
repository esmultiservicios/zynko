# Logs y observabilidad

ZYNKO mantiene la consola pública silenciosa por defecto. Los eventos administrativos y técnicos relevantes se registran en `system_event_logs` y se consultan desde **Logs** en el menú administrativo.

Niveles: información, advertencia y error. Los secretos y contraseñas no deben escribirse en los logs.

La consola del navegador solo muestra mensajes internos del widget si `window.ZYNKO_DEBUG === true`.
