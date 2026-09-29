ZYNKO V2.31.3 — Bandeja Premium · Gestión avanzada de conversaciones

Base: ZYNKO V2.31.2_BANDEJA_ALINEADA_FINAL

Mejoras funcionales incorporadas:
1. Filtro buscable por categorías de contacto directamente desde la Bandeja.
2. Filtro de atención: sin leer, con seguimiento y esperando más de 15 minutos.
3. Ciclo de conversación: resolver/reabrir y archivar/restaurar sin perder historial.
4. Eliminación segura solo para Owner/Admin con reautenticación por contraseña y registro de auditoría; no elimina físicamente mensajes.
5. Gestión de lectura: al abrir explícitamente una conversación se marca como leída y puede volver a marcarse como no leída.

Extras:
- Acciones masivas: asignarme, resolver y archivar.
- Ctrl/Cmd + Enter para enviar mensajes desde el compositor.
- Categorías visibles en cada conversación y búsqueda textual por categoría.
- Conversaciones archivadas se reactivan automáticamente si el visitante vuelve a escribir por NIVO Web Chat.
- Conversaciones resueltas vuelven a estado activo cuando llega un nuevo mensaje web.
- Vistas y filtros se guardan por usuario.

Base de datos:
- conversations: archived_at, deleted_at, deleted_by.
- inbox_preferences: category_id, state_filter, attention_filter.
- conversation_audit_logs: auditoría de acciones sensibles y de ciclo de vida.
- Las columnas/tablas se crean automáticamente por ensureRuntimeSchema() al actualizar una instalación existente.

Seguridad:
- Eliminar conversación requiere rol owner/admin + contraseña actual válida.
- La eliminación es lógica (soft delete) para conservar trazabilidad y evitar pérdida accidental de datos.
