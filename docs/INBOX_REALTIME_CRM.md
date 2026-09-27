# Bandeja en tiempo real y CRM ligero

La Bandeja usa WebSocket autenticado por tenant como canal principal. Inicia `websocket/start.bat` en Windows. Si el daemon no está disponible, la conversación seleccionada hace sincronización ligera cada 5 segundos para evitar que el agente tenga que actualizar manualmente.

Los productores de mensajes deben insertar el mensaje y publicar un evento en `realtime_events` mediante `zynkoRealtimePublish()`. Cuando se conecte Meta, el webhook de WhatsApp/Messenger debe seguir ese mismo contrato; así la UI no cambia ni requiere recarga.

Cada contacto puede guardar nombre y teléfono, categorías compartidas por el tenant, prioridad de la conversación, seguimiento con fecha/hora y notas internas. Ejemplos de categorías: Cliente potencial, Cliente, Soporte, Facturación, VIP, Seguimiento.

Los mensajes humanos muestran el identificador `AGENTE · EMPRESA` en mayúsculas. NIVO usa `NIVO · EMPRESA`.
