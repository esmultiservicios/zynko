ZYNKO V2.30.7 - NIVO AUTONOMO + WHATSAPP UNIVERSAL

CAMBIOS FUNCIONALES

1. WHATSAPP PUBLICO
- Se mantiene el botón flotante administrable.
- Se usa URL universal de WhatsApp.
- En móvil abre correctamente WhatsApp mediante navegación directa.
- En escritorio abre WhatsApp Web/navegador en pestaña nueva.
- Se agregó fallback si el navegador bloquea la nueva pestaña.
- Se reforzó z-index, pointer-events y touch-action para evitar bloqueos por superposición.

2. NIVO WEB CHAT + NIVO IA
- Se agregó autocorrección runtime de la tabla nivo_rules para bases existentes.
- Si NIVO está activo en la empresa principal y todavía no tiene reglas ni conocimiento, crea reglas iniciales seguras.
- NIVO responde en la misma petición del visitante; el widget no depende exclusivamente del WebSocket para mostrar la respuesta.
- Se agregó estado visual “Escribiendo…” mientras procesa la respuesta.
- Se mantiene WebSocket y además se agregó polling de respaldo cada 5 segundos.
- Se mejoró la normalización de texto y coincidencia de reglas/conocimiento.
- Solo utiliza conocimiento aprobado y publicado.
- Si no tiene información suficiente, responde de forma segura y puede transferir a humano según la configuración existente.
- La transferencia marca la conversación como pendiente y mantiene la notificación al equipo.

5 MEJORAS SIN COSTO APLICADAS
- Runtime self-healing para NIVO.
- Reglas iniciales seguras.
- Matching local mejorado sin API de pago.
- Respuesta inmediata + polling de respaldo.
- Handoff humano seguro con notificación y estado pendiente.

No requiere proveedor externo de IA ni gasto adicional para estas mejoras locales.
