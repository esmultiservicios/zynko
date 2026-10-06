# ZYNKO V2.31.108 · Contrato de comunicación bilateral

1. Todo mensaje entrante se persiste antes de intentar IA, automatizaciones o realtime.
2. `conversations` y `messages` son la fuente de verdad para Widget, Bandeja y canales externos.
3. WebSocket es acelerador de entrega, nunca requisito para conservar o visualizar el historial.
4. Bandeja reconcilia lista e historial contra la BD de forma periódica, además de escuchar eventos.
5. NIVO Web Chat reconcilia historial aun con WebSocket conectado, reparando eventos perdidos.
6. Un agente humano asignado obtiene propiedad de respuesta: NIVO no compite con él.
7. Sin humano asignado, NIVO/automatizaciones pueden responder y el resultado se persiste antes de entregarse.
8. WhatsApp Cloud/QR, Messenger, Instagram y Telegram usan el mismo historial canónico y gateway por proveedor.
9. Las respuestas NIVO de canales externos se despachan al proveedor conectado y su estado se registra.
10. `conversation.updated` acompaña cambios de mensajes para mantener orden, previews y lista de Bandeja sincronizados.

## Mejoras NIVO Web Chat / NIVO IA incluidas

- Reconciliación de integridad independiente del WebSocket.
- Recuperación automática del historial desde BD.
- Propiedad estricta humano/NIVO para evitar respuestas dobles.
- Persistencia antes de IA o transporte.
- Orden cronológico canónico por `sent_at` + `id`.
- Reapertura automática de conversaciones activas archivadas al recibir mensaje.
- Eventos de conversación además de eventos de mensaje.
- Despacho real de respuestas NIVO por gateway externo.
- Estado de entrega `sent/failed` para respuestas externas.
- Sincronización durable de Bandeja incluso ante pérdida silenciosa de eventos.
