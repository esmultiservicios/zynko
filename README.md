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
