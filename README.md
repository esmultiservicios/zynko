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
