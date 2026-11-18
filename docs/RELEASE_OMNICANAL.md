# ZYNKO — actualización omnicanal multiempresa

Base: ZIP original zynko(20261118-120128).zip, correspondiente a main, commit 8c74622800bb899acb1979f2098ba87395581410, árbol f011141a5ebd89349e3fb950413374ba03927b20. Paquete completo del árbol de trabajo, no parche. Sin cambios de CSS respecto a esa base; las nuevas acciones usan componentes existentes. No se modifica GitHub ni producción. Los secretos reales, sesiones vinculadas, archivos subidos y datos de producción no están en el paquete.

## Actualización segura

- Respaldar archivos y base de datos. Probar en instancia de ensayo antes de reemplazar producción. Conservar `.env`, `APP_KEY`, `storage/installed.lock`, `storage/env-backups`, sesiones QR (incluida ruta personalizada), archivos subidos y configuración de servidor. No ejecutar el instalador ni importar `schema.sql` encima de una instalación existente.
- Importar únicamente `database/upgrade-omnichannel.sql` en la BD existente, o dejar que el inicializador aditivo cree las tablas si el usuario SQL tiene permisos. No elimina filas ni modifica columnas existentes. Incluye nueve tablas aditivas; no importa el esquema completo.
- Mantener los webhooks existentes con `?channel=UUID` y su verify token. El webhook general opcional requiere `META_WEBHOOK_VERIFY_TOKEN`. La validación HMAC exige el secreto real de la aplicación (`META_APP_SECRET` ya configurado o secreto propio cifrado en su canal). Comprobarlo antes de desplegar. Los webhooks Telegram se configuran con secreto al validar el canal; volver a validar cada Telegram existente. Los cambios en la ruta de webhooks no requieren modificar la URL actual de WhatsApp de Meta.
- El bridge Node.js conserva la ruta de sesiones y la API previa. Si ya está instalado, mantener su token, directorio de sesiones y webhook, reiniciar el proceso con el código nuevo. Requiere Node compatible con fetch y AbortSignal.timeout. Para nuevas conexiones QR, el PHP necesita conocer `ZYNKO_QR_BRIDGE_TOKEN` y la URL interna, que pueden configurarse desde Configuración general de canales (Owner). No rotar tokens de producción sin coordinar ambos procesos.
- El monitor existente procesa hasta cinco mensajes pendientes por ejecución. Verificar que el cron de monitoreo siga activo. Estados inciertos o parciales no se reintentan automáticamente: la entrega podría haber ocurrido.
- No habilitar `META_EMBEDDED_SIGNUP_ENABLED` ni `META_COEXISTENCE_ENABLED` antes de que Meta apruebe la aplicación y valide las capacidades. Las pruebas de Owner previas se conservan. No se migra, desvincula ni elimina automáticamente un número WhatsApp Business. Solo registrar números Cloud nuevos y autorizados con PIN.

## Alcance y estado de las 20 recomendaciones

| # | Estado en el paquete |
|---|---|
| 1 | Configuración general Meta/QR desde el Owner con respaldo y conservación de valores vacíos. |
| 2 | Aislamiento adicional por tenant y activo externo en envío, recepción y adjuntos. No equivale a auditoría de seguridad de toda la aplicación. |
| 3 | Webhook central y compatibilidad con URL histórica; enrutamiento por activo. |
| 4 | Firmas Meta y tokens de Telegram/bridge obligatorios. |
| 5 | Conservación de cifrado existente y secretos no devueltos; registros de error acotados. |
| 6 | Recibos de eventos y envíos, deduplicación transaccional. Reclamo de número verificado único para impedir conexiones incompatibles; números históricos se verifican al volver a validar. No se deduplican retroactivamente mensajes históricos. |
| 7 | Alta por Embedded Signup y suscripción WABA preparadas, protegidas mediante flag; pendiente prueba real de cliente externo. |
| 8 | Procesamiento de eventos coexistence, ecos e historial básico; activación y pruebas externas pendientes. |
| 9 | Bridge QR: sesiones protegidas, reconexión, persistencia de eventos, adjuntos y estado; incluye código de vinculación alternativo por número y desvinculación explícita; requiere prueba de Node/Baileys real. No es API oficial de Meta. |
| 10 | Alta y edición por ID, varios canales del mismo tipo, límites existentes. |
| 11 | Caducidad y alertas automáticas mediante el monitor, reconexión manual para Meta y renovación OAuth de X cuando permite refresh token. No hay renovación automática universal. |
| 12 | Gateway común para los canales ya implementados. X incorpora OAuth 2.0 PKCE, refresh token cifrado, salida de texto y consulta periódica de mensajes directos individuales; requiere API de X habilitada y validación real. Grupos y recuperación de volúmenes mayores a diez páginas por ciclo no están cubiertos. Messenger/Instagram conservan vinculación por credenciales y requieren autorizaciones adicionales. No se simula disponibilidad. |
| 13 | Estados reales de envío, entrega y lectura donde existan eventos del proveedor. |
| 14 | Outbox durable, reintentos de errores seguros, reconciliación de estados. |
| 15 | Archivos según proveedor, descarga protegida de WhatsApp y Telegram y CDN de Messenger/Instagram con caché e identificación por empresa. Ubicaciones salientes Cloud/Telegram y botones Cloud, más recepción de ubicación e interactivos. X admite texto, no archivos; QR no admite los nuevos mensajes estructurados. Los adjuntos de Meta se limitan a CDN permitidas y dependen de vigencia de la URL. |
| 16 | Se conserva la reconciliación existente y se añaden eventos de estado; no se sustituyen servicios ni protocolos WebSocket. |
| 17 | Listar, crear, editar y borrar plantillas Meta UTILITY/MARKETING, encabezado/pie fijo, variables de cuerpo, hasta tres botones (respuesta, URL fija, teléfono); actualización por eventos cuando Meta los entrega; envío de plantilla aprobada por ID y variables desde bandeja. Componentes especiales (encabezados multimedia o dinámicos, autenticación, carruseles, URL dinámica) se gestionan en Meta. |
| 18 | Validación de límites por plan al vincular canales y permiso al enviar. |
| 19 | Modos NIVO, humano y agente externo (sin duplicar bot); no conecta ni autoriza Meta Business Agent automáticamente. |
| 20 | Auditoría de cambios/estados y cola persistente; se conserva monitor existente. Consulta de métricas de mensajes y estados por canal, período y resumen agregado de empresas para Owner; alertas de caducidad y monitor de cola. |

## Validación

- 82 archivos PHP verificados mediante `php -l` (PHP CLI 8.4; código compatible sintácticamente con 8.2).
- `node --check` aplicado al JavaScript de interfaz y bridge QR.
- `php tests/channel-security-smoke.php`: 19 comprobaciones locales: firmas, rutas, enrutamiento, identidad, estados, cifrado, CDN, plantillas y mensajes estructurados.
- Se comprobó que los 264 archivos originales (excepto historial .git) están presentes, que ningún CSS cambió y que `.env.example` es idéntico. No se dispuso de un servidor MariaDB ni del PHP de producción, ni de credenciales/sesiones reales de Meta, Telegram o WhatsApp. No se ejecutaron pruebas integrales ni se garantiza un despliegue sin incidencias. Es una versión candidata, no una certificación de producción. No sustituir el proyecto en producción sin validar: canal WhatsApp de revisión, dos empresas con diferentes activos, duplicados y reintentos, QR por separado, entrega e historial, permisos de usuario y WebSocket.

## Configuración adicional opcional

- X: configurar desde Owner `X_DM_ENABLED`, `X_CLIENT_ID`, secreto si corresponde y `X_REDIRECT_URI`; registrar exactamente el callback mostrado en configuración. Requiere acceso a DM de X. El catálogo empieza deshabilitado, sin cambiar los canales actuales. La consulta periódica depende del monitor.
- En Canales: actividad, plantillas oficiales, vinculación QR por número y desconexión. En Bandeja: botón de mensaje especial (ubicación, botones o ID de plantilla y variables; no sustituye el compositor existente). Los permisos del proveedor siguen aplicando.
- Esta entrega cubre los 20 puntos originales con los límites indicados. No activa ni implementa el número corporativo compartido entre empresas de las ampliaciones 21–35; su autorización y enrutamiento comercial se deben revisar por separado. La selección de agente externo solo evita que NIVO compita: no implementa una API de Meta Business Agent no autorizada.
- Antes de publicar: verificar por cada empresa con una cuenta de ensayo, permisos de SQL, ejecutables PHP 8.2 y Node de producción, cron y suscripciones de webhooks (incluyendo plantillas y eventos coexistence si autorizados). Mantener un respaldo del código y la BD.
