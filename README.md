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
