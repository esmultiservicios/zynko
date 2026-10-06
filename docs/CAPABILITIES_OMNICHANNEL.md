# Matriz de capacidades omnicanal · ZYNKO V2.31.103

| Capacidad | Estado en ZYNKO | Nota operativa |
|---|---|---|
| SaaS multiempresa | Operativo | Aislamiento por tenant, planes, usuarios y roles. |
| CRM omnicanal | Operativo | Contactos, identidades, conversaciones, categorías, etiquetas y seguimiento. |
| Bandeja WhatsApp | Preparada/operativa con proveedor | Requiere canal WhatsApp autorizado. |
| NIVO IA / chatbot | Operativo | Reglas, conocimiento, OpenAI opcional y handoff. |
| Automatización de conversaciones | Operativo | Flujos activos se evalúan al recibir mensajes por Web Chat y API. |
| API / Webhooks | Operativo | API autenticada y webhooks salientes. |
| Instagram Messaging | Preparado | Requiere autorización oficial de Meta. |
| Instagram Comment-to-DM | Configurable | Reglas persistentes; ejecución externa depende de permisos de Instagram/Meta. |
| Facebook Messenger | Preparado | Requiere autorización oficial de Meta. |
| Constructor visual | Operativo | Disparador, canal, condición y acción estructurados. |
| Campañas WhatsApp | Operativo en preparación/segmentación | Audiencias persistentes; despacho exige canal y plantillas oficiales cuando corresponda. |
| Llamadas WhatsApp + IA | Configurable | Perfil NIVO listo; la llamada requiere Calling API/proveedor compatible habilitado. |
| Telegram + chatbot | Arquitectura preparada | Catálogo y NIVO contemplan Telegram; no se simula conexión mientras falte conector real. |
| Múltiples agentes | Operativo | Roles, equipos, asignación y sesiones. |
| WhatsApp por QR | No se presenta como API Oficial Meta | El QR de WhatsApp Web y Cloud API oficial son mecanismos diferentes; no se incluye un puente no oficial disfrazado de integración Meta. |

## Principio de estado real

ZYNKO no debe mostrar un canal o entrega como conectada/enviada si el proveedor no confirmó la operación. Las funciones dependientes de Meta, WhatsApp Calling u otros servicios externos se activan únicamente con credenciales y permisos reales.


## Integraciones añadidas en V2.31.103
- WhatsApp Cloud API: envío/recepción mediante Graph API y webhook por canal.
- WhatsApp por QR: servicio bridge Node.js con sesión persistente, QR, recepción y envío.
- Messenger e Instagram Messaging: entrada por webhooks Meta y salida por Graph API.
- Instagram Comment-to-DM: evento de comentario enlazado a reglas sociales activas.
- Telegram: Bot API para envío y webhook para recepción.
- Campañas de WhatsApp: preparación de audiencia y despacho real por lotes; soporta plantillas Meta cuando se configura `template_name`.
- Llamadas WhatsApp + NIVO IA: webhook de orquestación que registra sesiones y entrega al proveedor el perfil de voz, prompt y política de handoff. La capa de audio/transporte sigue siendo responsabilidad de Meta/proveedor compatible.
