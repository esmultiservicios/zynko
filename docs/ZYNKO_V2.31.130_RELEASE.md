# ZYNKO V2.31.130

## Objetivo
Evolucionar NIVO desde respuestas por coincidencia hacia un asistente conversacional multiempresa con memoria, recuperación semántica, razonamiento contextual y seguimiento comercial interno.

## Cambios principales
- Orquestador conversacional general por tenant.
- Memoria estructurada persistente por conversación.
- Prospectos comerciales derivados de conversaciones.
- RAG por fragmentos con recuperación híbrida y embeddings opcionales.
- Aislamiento estricto por tenant_id.
- Regla comercial: recomendar el plan más económico que cubra la necesidad.
- Seguimiento sugerido interno, nunca invasivo por defecto.
- Contexto NIVO visible en Cliente 360°.
- Fallback seguro: una pregunta no comprendida nunca debe cerrar ni romper la conversación.

## Base de datos
Sí requiere actualización. Ejecutar el paquete acumulativo `ZYNKO_UPDATE_DB_COMPLETO_SERVER.sql` o `ZYNKO_UPDATE_DB_COMPLETO.sql`.
