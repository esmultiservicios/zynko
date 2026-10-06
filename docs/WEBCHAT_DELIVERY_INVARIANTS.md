# ZYNKO V2.31.107 · Invariantes de entrega de NIVO Web Chat

## Regla principal
Un mensaje que el widget muestra como enviado debe existir primero en `messages` y pertenecer a una conversación persistida en `conversations`.

## Flujo durable
1. El visitante envía el mensaje a `webchat-api.php`.
2. El servidor valida el origen y seguridad.
3. Se crea/recupera contacto y conversación.
4. El mensaje se persiste en `messages`.
5. En la misma transacción se escribe `message.created`/`conversation.updated` en `realtime_events`.
6. El WebSocket consume el outbox y difunde a la Bandeja y al widget.
7. La Bandeja y el widget reconcilian contra BD como respaldo; no requieren F5.
8. NIVO evalúa conocimiento/automatizaciones y persiste su respuesta antes de emitirla.

## Anti-spam
Las heurísticas (repetición, rapidez, enlaces) pueden marcar un mensaje como `suspicious`, pero no pueden descartarlo por sí solas. Solo señales inequívocas como honeypot o límite duro de IP bloquean.

Nunca se devuelve `ok=true` a un mensaje que no fue persistido. Si un bloqueo real ocurre, el widget retira el mensaje optimista, restaura el texto y comunica el error.

## Razón del cambio 2.31.107
Versiones anteriores podían elevar el puntaje por repetir una misma pregunta durante pruebas. Al superar el umbral, el servidor respondía `Mensaje recibido` pero descartaba el contenido antes de crear el mensaje. El widget lo mostraba localmente, mientras la Bandeja y NIVO no podían verlo porque nunca existió en BD. Este flujo quedó eliminado.
