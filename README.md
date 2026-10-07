## V2.31.120 · Navegación lateral premium y compacta

- Encuestas deja de ocupar una entrada principal y queda dentro de NIVO Web Chat.
- Logs deja de ocupar una entrada principal y queda dentro de Configuración.
- Automatizaciones queda como acceso de NIVO IA.
- Integraciones queda como acceso de Canales.
- El menú lateral mantiene resaltado el módulo padre cuando se navega a cualquiera de esos submódulos.
- El scrollbar del sidebar usa un diseño oscuro/teal discreto, redondeado y coherente con ZYNKO.
- Los flyouts de submenús reciben un estilo premium con iconografía y separación uniforme.

## V2.31.119 · Turnstile canónico y gráficas más claras

- La nota “Cambio seguro” y avisos similares mantienen el icono alineado a la izquierda del texto con mejor proporción visual.
- Cloudflare Turnstile deja de pedir un hostname opcional y fija como dominio protegido `https://zynkocloud.app/`.
- El servidor guarda y valida `zynkocloud.app` como hostname canónico para Turnstile.
- Los gráficos muestran el valor visible en cada barra para una lectura más profesional.

## V2.31.117 · Consistencia operativa y UI administrativa

- WebSocket usa controles inteligentes: Iniciar solo cuando está detenido; Detener/Reiniciar solo cuando está activo.
- Dashboard y Configuración explican con el mismo detalle qué significa “Reinicio recomendado”.
- Vista previa administrativa de NIVO replica la disposición vertical Expandir/Cerrar del widget publicado.
- KPIs de Integraciones recuperan diseño premium y movimiento; tarjetas de Automatizaciones también tienen microinteracción.
- Filtros de Bandeja alinean Restablecer con todos los selectores.
- Analítica pública amplía el gráfico para aprovechar la altura del panel lateral.
- Encuestas aparece como módulo visible en el menú administrativo.
- Correo explica que “Permitir vincular” requiere recepción entrante real (IMAP o Graph webhook/subscription).
- .gitignore se amplía para runtime genérico y archivos temporales del hosting/editor.

## V2.31.115 · Monitoreo preventivo y continuidad operativa

- Alertas por correo ante caídas/recuperaciones de WebSocket, API, NIVO Web Chat, NIVO IA, BD y canales.
- Autorrecuperación WebSocket y monitor CLI para Cron Jobs.
- Estado por puerto real cuando el hosting oculta PID/procesos.
- `.gitignore` reforzado contra locks, PID, marcadores, logs, caché, sesiones y temporales runtime.
- Panel administrativo para revisar estados e historial sin entrar al servidor.

## V2.31.114 · Estado WebSocket sincronizado

Dashboard, Configuración y el indicador global de canales reflejan el mismo estado WebSocket confirmado. Después de iniciar, detener o reiniciar, ZYNKO actualiza el estado visible y recalcula Salud integral automáticamente.

## V2.31.113 · WebSocket verificable + deploy limpio + salud comprensible

- Estado WebSocket real: proceso + puerto + endpoint público, sin falsos mensajes de éxito.
- Inicio/Reinicio desde panel con comprobación posterior independiente.
- Glosario en Salud para WebSocket, servicio/daemon, PID y proxy `/ws`.
- `.gitignore` protege el repositorio de `.env`, PID, logs y archivos runtime que pueden bloquear cPanel Deploy.
- NIVO Web Chat evita caché obsoleta del widget y la vista previa del administrador puede expandirse/restaurarse.

## V2.31.112 · Consolidación operativa, encuestas y documentación versionada

- Salud WebSocket más precisa y control desde panel con múltiples métodos permitidos por el hosting.
- Dashboard y analítica con cabeceras uniformes, estados con formato y KPIs con movimiento.
- Vista previa administrativa de NIVO Web Chat alineada con el widget publicado.
- Módulo de Encuestas y satisfacción con métricas, filtros y acceso a la conversación.
- Documentación versionada en panel y sitio público con historial seleccionable.
- Selector de emojis estabilizado y experiencia responsive preservada.
- Correo diferenciado correctamente entre envío/notificaciones y recepción omnicanal.

## V2.31.112 · Dashboard operativo + control seguro de servicios
- Accesos rápidos configurables por usuario.
- Centro de servicios con estado, iniciar, detener y reiniciar WebSocket con confirmación SweetAlert2.
- Recomendación automática de reinicio cuando archivos operativos cambian después del último arranque.
- Auditoría y duración de acciones del servicio.
- Salud integral accesible desde Dashboard y Configuración.

## V2.31.110 · NIVO preciso + CRM de conversaciones + UX estable
- Respuestas determinísticas para intenciones clave (IZZY, ZYNKO, WhatsApp, inventario, restaurantes) con normalización tolerante a errores comunes.
- Categorías CRM por conversación y acceso visible desde la cabecera del chat.
- Eliminación segura con campo de contraseña visible y enfocado.
- Emoji picker con X y cierre al hacer clic fuera.
- Chat expandible/restaurable, scroll inteligente y cierre/encuesta sin obligar a pulsar “Iniciar nuevo chat”.
- Salud del servidor y filtros de Bandeja ordenados.

## V2.31.110 · NIVO bilateral + salud integral del servidor

- Corrige la detección de saludos para que `Hola, ¿qué es ES MULTISERVICIOS?` no se reduzca a un saludo genérico.
- NIVO procesa la intención completa y prioriza reglas/conocimiento de empresa y soluciones.
- El widget solo deduplica mensajes cuyo tipo real es `greeting`; una respuesta legítima ya no se oculta por contener “Soy NIVO…”.
- Configuración permite administrar `WS_HOST` y normaliza `localhost` a `127.0.0.1` en producción.
- Añade Salud integral del servidor con DB, WebSocket interno/público, proxy, PID, PHP, storage, URL y canales.
- La Bandeja recupera el dominio de origen desde `webchat_visitors` cuando una conversación antigua no tiene aún registro de seguridad.

## V2.31.110 · Entrega confirmada y anti-spam no destructivo
- Corrige una causa real de mensajes visibles solo en el widget: el anti-spam podía descartar preguntas repetidas y aun responder HTTP como si hubieran sido recibidas.
- Ningún mensaje se considera enviado si no quedó persistido. Repetir preguntas ya no bloquea por sí solo una conversación.
- Widget, Bandeja y NIVO conservan WebSocket como tiempo real y BD como fuente de verdad.

## V2.31.110 · Comunicación bilateral durable y omnicanal

- La Bandeja ya no depende exclusivamente del WebSocket: reconcilia periódicamente contra la base de datos.
- NIVO Web Chat conserva WebSocket para inmediatez y agrega reconciliación de integridad aunque el socket figure conectado.
- Cada mensaje Web Chat publica eventos de mensaje y de conversación, pero la BD permanece como fuente de verdad.
- Cuando un humano tiene asignada una conversación, NIVO deja de responder automáticamente en Web Chat y canales externos.
- Las respuestas NIVO generadas desde WhatsApp, Messenger, Instagram o Telegram se despachan por el gateway real del canal cuando éste está autorizado.
- La conversación, el historial y el orden de mensajes se reconstruyen por ID persistido, evitando depender de memoria de navegador.

## V2.31.103 · Mensajería NIVO Web Chat resiliente

- Restaura y blinda el ciclo emisor/receptor de NIVO Web Chat.
- Garantiza respuesta explícita cuando NIVO conserva el control y ningún humano está asignado.
- Mantiene WebSocket como transporte principal y activa polling de recuperación únicamente cuando el socket no está disponible.
- Reconcilia el historial después de enviar para recuperar respuestas que lleguen durante latencia de proxy/proveedor.
- Conserva la persistencia en BD como fuente de verdad y evita duplicados al re-renderizar.

## V2.31.103 · Conectores omnicanal completos y WhatsApp QR

Esta versión conecta la bandeja/CRM con Meta Cloud API (WhatsApp, Messenger e Instagram), Telegram Bot API y un bridge dedicado para WhatsApp por QR. También incorpora despacho real de campañas, webhooks entrantes para NIVO/automatizaciones, Comment-to-DM y orquestación de perfiles de llamadas WhatsApp + IA.

## V2.31.101 · Automatizaciones omnicanal y crecimiento

Esta versión incorpora un centro de automatizaciones con constructor visual, reglas sociales, campañas de WhatsApp y perfiles de llamadas con NIVO IA. También integra los flujos activos al procesamiento real de mensajes de NIVO en Web Chat y API.

Los conectores externos continúan respetando el principio de no simular estados: WhatsApp, Messenger, Instagram, llamadas y Comment-to-DM solo pueden completar entregas cuando la cuenta/proveedor oficial correspondiente está autorizado. El emparejamiento de WhatsApp por QR no se presenta como API Oficial de Meta porque son mecanismos distintos; ZYNKO mantiene la conexión oficial como vía principal.

## V2.31.100 · Limpieza visual de Configuración
- Corrige la duplicación del icono de servidor en la cabecera “Servidor y dominio”.
- Protege las cabeceras con iconografía propia frente al mejorador universal de tarjetas.
- Mantiene intacta la lógica de configuración, .env, permisos, WebSocket y base de datos.


## V2.31.99 · Sitio principal NIVO único

- El sitio principal de ZYNKO se vincula únicamente al dominio canónico configurado en `seo_site_url` / `APP_URL`.
- Se consolidan automáticamente instalaciones antiguas generadas por alias o dominios históricos.
- Los sitios autorizados manualmente, como IZZY y ES MULTISERVICIOS, se conservan intactos.
- Visitar ZYNKO desde un alias ya no crea otra tarjeta `Sitio principal ZYNKO`.

## V2.32.01 · Mensajería bilateral realtime sin pérdida de estado

- La mensajería usa `messages` como fuente de verdad y `realtime_events` como outbox durable para WebSocket.
- Los eventos `message.created` transportan el mensaje canónico completo (`message_id`, conversación, remitente, cuerpo, adjuntos, estado y fecha), evitando reconstrucciones ambiguas en el navegador.
- El primer mensaje del Web Chat, el saludo inicial, la conversación y sus eventos realtime se confirman en la misma transacción.
- El WebSocket de visitantes ya no queda atado a `conversation_id=0`: resuelve dinámicamente la conversación del `visitor_id`, por lo que el mismo socket sigue vivo desde antes del primer mensaje.
- Widget y Bandeja insertan los mensajes recibidos por WebSocket directamente y en orden por `message_id`; no dependen de polling periódico para la comunicación normal.
- Cuando un WebSocket se reconecta, se hace una resincronización canónica para recuperar cualquier gap sin borrar el historial local.
- El daemon WebSocket usa cola de salida por cliente para evitar frames parciales en sockets no bloqueantes.
- Producción HTTPS usa por defecto `wss://<dominio>/ws`, con proxy interno hacia `ws://127.0.0.1:8080`; el puerto 8080 puede permanecer privado.
- El despliegue cPanel reinicia el daemon mediante `bin/restart-websocket.sh` para que los cambios de realtime entren en vigor después de publicar.
- La API de canales externos también publica eventos completos, dejando la misma base preparada para WhatsApp, Messenger y futuras integraciones.
- No requiere cambios estructurales de base de datos ni ejecutar UPDATE SQL.

## V2.32.00 · Historial persistente, Bandeja sincronizada y NIVO IA coherente

- El primer mensaje del visitante, el saludo inicial y la creación de la conversación se persisten de forma atómica para evitar conversaciones parciales o historiales incompletos.
- El widget usa como fuente de verdad el historial completo devuelto por el servidor después de cada envío y evita borrar la conversación visible ante una respuesta temporal vacía del polling.
- Si una instalación conserva un visitante válido pero pierde el vínculo `conversation_id`, el backend intenta recuperar de forma segura la conversación activa del mismo contacto, tenant y widget.
- La Bandeja recibe los eventos de creación/mensaje por WebSocket y resincroniza al reconectar o volver a enfocar la ventana; no depende de polling periódico para el flujo normal.
- Las respuestas de capacidades de NIVO enumeran dinámicamente las soluciones configuradas del tenant (por ejemplo IZZY, CAMI y ZYNKO), no solo ZYNKO.
- La consulta combinada sobre **NIVO Web Chat y NIVO IA** tiene una respuesta explícita y no depende de que una regla parcial gane la coincidencia.
- Se conserva el aislamiento por tenant y no se comparte conocimiento entre empresas distintas.
- No requiere cambios estructurales de base de datos ni ejecutar UPDATE SQL.

## V2.31.98 · Compatibilidad de actualización Local + Servidor

Esta entrega separa el update acumulativo de base de datos por entorno porque MariaDB/phpMyAdmin local puede devolver el error **#1295** al ejecutar `PREPARE/EXECUTE` con DDL, mientras el servidor de producción ya ejecuta correctamente ese método.

### Qué archivo ejecutar

- **Servidor / producción (el que ya te funcionó):** `ZYNKO_UPDATE_DB_COMPLETO_SERVER.sql`
- **Local / XAMPP / MariaDB / phpMyAdmin:** `ZYNKO_UPDATE_DB_COMPLETO_LOCAL.sql`
- `ZYNKO_UPDATE_DB_COMPLETO.sql` se conserva como copia del update de servidor para mantener compatibilidad con el flujo existente.

### Importante

Los dos archivos son acumulativos y terminan en la misma versión objetivo **2.31.98**. No debes ejecutar ambos sobre la misma base. Selecciona la base correcta antes de correr el archivo correspondiente.

El archivo LOCAL elimina `PREPARE/EXECUTE` y usa DDL idempotente de MariaDB (`IF EXISTS` / `IF NOT EXISTS`) para que pueda ejecutarse desde phpMyAdmin local.


- Las fuentes web, reglas y conocimiento aprobado se comparten entre todos los sitios autorizados que pertenecen al mismo `tenant_id`.
- ES MULTISERVICIOS puede responder sobre IZZY, ZYNKO, NIVO y CAMI desde cualquiera de sus sitios autorizados, sin copiar conocimiento por dominio.
- Los tenants de clientes continúan totalmente aislados: nunca reciben conocimiento de ES MULTISERVICIOS ni de otros clientes.
- Se agregan reglas base y catálogo de soluciones para el tenant principal ES MULTISERVICIOS mediante `ZYNKO_UPDATE_DB_COMPLETO.sql`.
- `WS_PUBLIC_URL` queda administrable desde Configuración y tiene prioridad cuando existe un reverse proxy/TLS para WebSocket.
- `.env.example` es solo una plantilla. El `.env` real nunca se incluye en los ZIP y el instalador lo crea en cada servidor.

## V2.31.96 · NIVO Web Chat + NIVO IA: comunicación bilateral y handoff real
- Cada mensaje del visitante se persiste en la Bandeja antes de ejecutar NIVO y los eventos WebSocket son aceleradores: si el realtime falla, el chat sigue operativo mediante polling.
- NIVO ya no transfiere por desconocimiento por defecto; continúa conversando, usando reglas y Fuentes web aprobadas, y registra dudas para aprendizaje supervisado.
- Cuando el visitante pide atención humana, ZYNKO intenta asignar inmediatamente al agente disponible con menor carga; si no hay uno, deja la conversación en cola sin perder mensajes.
- El widget reconecta WebSocket automáticamente y soporta `WS_PUBLIC_URL` / `WS_PUBLIC_PORT` para producción HTTPS detrás de proxy TLS.
- Aprendizaje supervisado ahora permite **Responder y enseñar** directamente desde la pregunta pendiente; la respuesta se publica como conocimiento exclusivo de la empresa.
- La identidad de NIVO se resuelve desde el tenant real, evitando respuestas con la empresa equivocada.

## V2.31.95 · NIVO Web Chat + NIVO IA: respuesta garantizada, handoff persistente y Bandeja en tiempo real

- Corrige el silencio del Web Chat cuando una consulta válida llega a NIVO IA y una consulta interna de conocimiento falla.
- Las preguntas sobre ES MULTISERVICIOS, IZZY, CAMI y ZYNKO tienen una ruta de resolución garantizada para el tenant principal, manteniendo prioridad para reglas y conocimiento aprobado.
- Las búsquedas en `knowledge_sources` son defensivas: una incompatibilidad heredada ya no tumba todo el motor ni deja al visitante esperando.
- El estado `pending` de atención humana ya no se sobrescribe a `open` cuando el visitante sigue escribiendo. El banner de handoff permanece hasta que un agente resuelva/reabra la conversación.
- Cada mensaje válido se persiste antes de ejecutar NIVO y publica evento realtime con vista previa.
- La Bandeja actualiza la lista de conversaciones por WebSocket y también por polling de respaldo cada 5 segundos sin recargar toda la página.
- La encuesta continúa apareciendo únicamente al finalizar realmente el chat (visitante, agente o cierre por inactividad), no durante un handoff.
- Se conservan Finalizar chat, encuesta 1–5 estrellas, comentario, nuevo chat, Inicio/Último, perfil editable, inactividad, anti-spam, aprendizaje supervisado y aislamiento por tenant.
- No requiere cambios estructurales de base de datos.

## V2.31.94 · NIVO IA: conocimiento confiable y handoff seguro

- NIVO resuelve preguntas sobre la empresa y soluciones del tenant usando reglas, catálogo y fuentes web aprobadas.
- Las fuentes web se consultan aunque una instalación heredada tenga el flag `knowledge_enabled` desactualizado.
- Preguntas como “qué es ES MULTISERVICIOS”, “qué es IZZY”, “qué es CAMI” y “qué es ZYNKO” tienen resolución por entidad antes del fallback.
- Las soluciones configuradas en `nivo_solutions` se usan como conocimiento estructurado cuando corresponde.
- La transferencia automática ya no ocurre por un único fallo aislado: requiere al menos 3 fallos consecutivos, salvo solicitud explícita de un humano.
- El aislamiento por `tenant_id` se mantiene en reglas, fuentes, soluciones y aprendizaje.
- La encuesta se solicita al finalizar el chat o por cierre de inactividad; una transferencia humana no finaliza la conversación ni dispara encuesta por sí sola.

## V2.31.93 · Historial cronológico estable de NIVO

- El saludo inicial se crea una sola vez al nacer la conversación.
- Widget y Bandeja fuerzan el saludo como primer mensaje del historial.
- La Bandeja ya no inserta ni reconstruye saludos al abrir conversaciones antiguas.
- Se agregó normalización defensiva en el widget para evitar saludos duplicados heredados.
- Inicio / Último conservan la navegación del historial y el chat abre en el último mensaje.
- La edición de nombre/correo mediante el chip de perfil permanece disponible durante la conversación.
- Se preservan cierre manual, encuesta, nuevo chat, inactividad, handoff, anti-spam y NIVO IA.
- No hay cambios estructurales de base de datos.

## V2.31.92 · Restauración definitiva de Finalizar chat + encuesta

- Se mantiene visible el bloque premium **Finalizar chat** mientras el widget esté abierto y la sesión no esté cerrada.
- Se restaura el flujo completo: confirmación → cierre real → mensaje final → encuesta 1–5 estrellas → comentario opcional → iniciar nuevo chat.
- Si el visitante todavía no ha enviado ningún mensaje, Finalizar chat simplemente cierra la sesión visual sin crear una conversación vacía ni una encuesta falsa.
- Después del primer mensaje, el cierre usa la conversación persistida y conserva todo el historial.
- Se mantienen Inicio / Último, handoff humano, perfil, anti-spam, NIVO IA y el resto de mejoras existentes.
- No requiere cambios estructurales de base de datos.

## V2.31.90 · Finalización visible y handoff comunicado al visitante

- NIVO Web Chat mantiene **Finalizar chat** visible incluso cuando el visitante abre la edición de su perfil.
- La acción de finalización conserva confirmación, historial, encuesta y creación de un nuevo chat.
- Cuando NIVO solicita atención humana, el visitante recibe una explicación dentro del chat y un estado persistente de **Atención humana solicitada**.
- Una conversación en estado `pending` conserva nuevos mensajes para el agente sin continuar respuestas automáticas de NIVO.
- El correo de handoff y el estado visible para el visitante quedan sincronizados.
- No requiere cambios estructurales de base de datos.

## V2.31.89 · Continuidad real de NIVO Web Chat

- Corrige re-presentaciones/saludos duplicados durante refrescos del chat.
- Conserva contexto para preguntas de seguimiento como “explícame las funciones”.
- Inicio / Último navegan el historial real sin que polling robe la posición.
- Finalizar chat vuelve a mantenerse visible y conserva encuesta + nuevo chat.
- El formulario de perfil no desplaza las acciones cuando ya existe conversación.
- No requiere cambios estructurales de base de datos.

## V2.31.88 · Formulario público protegido y validación real de correo

- Validación frontend + backend del correo.
- Detección de errores comunes como `gmail.con`, `gmial.com`, `hotmal.com` y similares.
- Sugerencias visibles sin corregir automáticamente la dirección.
- Validación de dominio y registros MX con fallback ante fallos temporales del resolver DNS.
- Bloqueo de dominios de correo temporal/desechable.
- Verificador externo opcional configurable desde `.env` con fallback local.
- Honeypot, rate limit por sesión/IP, detección de duplicados y scoring anti-spam.
- Cloudflare Turnstile sigue disponible como capa adicional.
- El visitante no recibe correos de confirmación; la consulta se envía únicamente al administrador.
- Nueva auditoría `public_contact_security_events`.
- Documentación en `docs/PUBLIC_CONTACT_SECURITY.md`.

## V2.31.87 · NIVO Web Chat anti-spam + trazabilidad

- Protección anti-spam multiempresa antes de crear conversaciones.
- Honeypot invisible, rate limit por huella anónima, repetición, enlaces y user-agent automatizado.
- Registro de eventos de seguridad sin guardar IP en texto plano.
- Conversaciones sospechosas visibles en Bandeja con origen y estado de seguridad.
- Spam de alto riesgo se descarta antes de ensuciar la Bandeja.
- Mensajes humanos simples siguen llegando a NIVO IA.
- Cierre por inactividad reforzado también del lado servidor.
- Umbrales configurables desde NIVO Web Chat.
- Tablas de seguridad creadas defensivamente en runtime y documentadas en schema/update acumulativo.

## V2.31.86 · NIVO IA multiempresa + aprendizaje supervisado + documentación completa

### MULTIEMPRESA
- Se reforzó el aislamiento por `tenant_id` en reglas, conocimiento, soluciones, módulos, fuentes web y búsquedas de NIVO.
- NIVO Web Chat resuelve el tenant desde su instalación/conversación y utiliza únicamente el conocimiento de esa empresa.
- Se endurecieron joins y validaciones para evitar referencias cruzadas entre soluciones/módulos de tenants distintos.

### 10 MEJORAS DE NIVO IA
1. Contexto reciente de conversación para preguntas de seguimiento.
2. Recuperación de hasta 3 fuentes relevantes por respuesta.
3. Desduplicación de contenido web sincronizado.
4. Cola de aprendizaje por tenant para preguntas sin respuesta segura.
5. Priorización de dudas por número de ocurrencias.
6. Respuestas humanas candidatas desde la Bandeja.
7. Aprobación/rechazo humano antes de publicar conocimiento aprendido.
8. Conversación continua con `0 = sin límite fijo` de respuestas automáticas.
9. Fallback aclaratorio antes de transferir a humano.
10. Trazabilidad de fuentes y conocimiento aprobado por empresa.

### DOCUMENTACIÓN
- Se reescribió `docs/NIVO_IA.md` con arquitectura multiempresa, fuentes web, aprendizaje, contexto, recuperación y seguridad.
- Se reescribió `docs/NIVO_WEB_CHAT.md` con instalación, CORS, historial, cierre, encuestas, NIVO IA y aislamiento por tenant.
- Se agregó `docs/NIVO_MULTIEMPRESA_APRENDIZAJE.md`.
- La documentación pública explica cómo NIVO Web Chat identifica el tenant y usa únicamente el conocimiento de esa empresa.

### BASE DE DATOS
- `nivo_learning_queue` se crea defensivamente en runtime para instalaciones existentes.
- `database/schema.sql` incluye la tabla para instalaciones nuevas.
- El acumulativo contiene el bloque V2.31.86, pero esta versión no obliga a ejecutarlo para empezar a usar la mejora porque el runtime crea la estructura faltante.

## V2.31.85 · Fuentes web UTF8MB4 + modal limpio

- Corrige `knowledge_sources` para contenido web Unicode/emoji con `utf8mb4`.
- Refuerza la sincronización web en instalaciones antiguas.
- Al guardar una fuente web, el modal se cierra y el formulario se limpia antes de recargar.
- Mantiene `ZYNKO_UPDATE_DB_COMPLETO.sql` acumulativo para producción.

## V2.31.83 · UTF-8/emoji seguro en NIVO Web Chat

- Corrige producción cuando MySQL/MariaDB tenía `messages.body` heredado con charset distinto de `utf8mb4`.
- NIVO puede guardar saludos y mensajes con emojis sin lanzar `SQLSTATE[22007]/1366 Incorrect string value`.
- `webchat-api.php` verifica defensivamente la columna `messages.body` y la migra a `utf8mb4` si detecta una instalación antigua.
- Todas las conexiones principales fuerzan `SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci`.
- El paquete acumulativo `ZYNKO_UPDATE_DB_COMPLETO.sql` normaliza las columnas de texto críticas de chat y conocimiento.
- No se eliminan mensajes ni conversaciones existentes.

## V2.31.82 · NIVO continuo y controles premium del widget

- NIVO ya no deja en silencio consultas principales de IZZY/CAMI/ZYNKO por límites automáticos internos.
- Las consultas comerciales de ES MULTISERVICIOS se resuelven de forma determinística y guiada.
- Si se alcanza un límite de respuestas automáticas, NIVO informa claramente en lugar de quedar mudo.
- El widget estrena navegación de historial premium con controles compactos Inicio / Último.
- Finalizar chat ahora usa una acción premium con descripción clara y flujo de satisfacción.
- Iniciar nuevo chat también utiliza una acción premium consistente.
- Se conserva el historial completo, encuesta, cierre y nueva conversación de V2.31.79.
- No requiere cambios de base de datos respecto a V2.31.79.


- NIVO Web Chat y Bandeja conservan y consultan el historial completo en orden cronológico desde el saludo inicial.
- Se agregó navegación directa **Inicio / Último** para revisar conversaciones largas sin perder el punto actual.
- El saludo inicial de NIVO se persiste como mensaje `greeting`, evitando confundirlo con respuestas posteriores generadas en el mismo segundo.
- NIVO ya no silencia preguntas de Web Chat por `cooldown`; el rate limit sigue protegiendo contra abuso.
- Se reforzaron las respuestas sobre ES MULTISERVICIOS, IZZY, CAMI y ZYNKO con lenguaje más claro para clientes.
- El visitante puede **Finalizar chat**, calificar la atención de 1 a 5 estrellas, dejar comentario e iniciar una conversación nueva.
- Desde Bandeja, **Finalizar chat** conserva el historial y solicita encuesta automáticamente para conversaciones Web Chat.
- Cliente 360° muestra el estado/resultado de satisfacción cuando existe.
- Requiere ejecutar `ZYNKO_UPDATE_DB_COMPLETO.sql` una vez para crear `conversation_surveys`.

## V2.31.78 · Emoji picker estable

- Tamaño fijo en escritorio: 390 × 300 px.
- Mantiene posición al cambiar de categoría.
- El contenido excedente usa scroll vertical interno sin redimensionar el popup.
- En móvil conserva tamaño responsivo controlado.

## V2.31.78 · Switches activos visibles y consistentes

- Bandeja: Cliente 360° mantiene visibles los datos principales y agrupa Categorías, Seguimiento y Notas internas dentro de “Más opciones”.
- El panel adicional queda cerrado por defecto y se despliega únicamente cuando el agente lo necesita.
- Seguimiento deja de quedar pegado al borde inferior y conserva separación visual correcta.
- NIVO Web Chat mantiene la alineación de icono al lado de título/subtítulo aplicada en V2.31.70.
- No requiere cambios de base de datos.

## V2.31.70 · Bandeja a altura completa

- La Bandeja usa toda la altura disponible del viewport en escritorio.
- Se elimina el scroll vertical exterior de la página dentro de Bandeja.
- Lista de conversaciones, mensajes y Cliente 360 mantienen sus propios scrolls internos cuando son necesarios.
- Se elimina el espacio muerto debajo de las columnas de la Bandeja.
- El ajuste aplica también en modo Full Screen sin alterar la navegación persistente.
- En móvil se conserva el flujo vertical natural y responsive.
- No requiere cambios de base de datos.


## V2.31.70
- Bandeja: margen inferior adicional para Seguimiento dentro de Cliente 360°.
- NIVO Web Chat: cabeceras con icono alineado al lado del título/subtítulo en escritorio y móvil.
- Sin cambios de base de datos.


### V2.31.82
- El Widget NIVO y la Bandeja abren por defecto en el mensaje más reciente.
- Los controles Inicio / Último continúan disponibles para navegar el historial sin alterar el comportamiento manual posterior.

## ZYNKO

## V2.31.110 · WebSocket autorrecuperable + expansión visible
- Web Chat comprueba el daemon antes de anunciar WSS; si el proceso cayó tras un reemplazo manual, intenta reiniciarlo de forma segura.
- Si el hosting impide reinicio automático, el widget usa reconciliación HTTP sin bucle de errores WebSocket en consola.
- El botón Expandir queda visible también en viewport estrecho/DevTools y puede restaurar el tamaño.
- El script de reinicio valida que el puerto realmente quedó escuchando antes de reportar éxito.
 V2.31.110
- Inactividad estable: un solo aviso, cierre automático configurable y sin bucles de seguimiento.
- Scroll inteligente: respuestas largas se posicionan desde el inicio del mensaje; respuestas cortas permanecen al final.
- Salud integral amplía diagnóstico con hora/zona horaria PHP y MySQL, además de WebSocket, proxy y puertos.
- Producción e instalaciones nuevas usan WS_HOST=127.0.0.1 para evitar resolución IPv6 de localhost.
- El perfil del visitante se conserva tras cerrar si “Recordar nombre/correo” está activo; si se desactiva, se limpia al cerrar.


## ZYNKO V2.31.110
- NIVO Web Chat más alto, responsive y con expansión real a pantalla completa/restauración.
- WebSocket con configuración IPv4 consistente para local/producción, salud integral y reinicio controlado desde Configuración.
- Encuesta ampliada a 3 preguntas y resultados visibles en Cliente 360°.
- Eliminación autorizada de conversaciones con campo de contraseña nativo y auditable.
- Selector de emojis cerrable con X o clic fuera; filtros CRM alineados; categorías por conversación preservadas.
- Mantiene scroll inteligente, cierre por inactividad, reconciliación HTTP y comunicación bilateral durable.
