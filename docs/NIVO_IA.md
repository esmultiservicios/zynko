# NIVO IA — multiempresa, conocimiento y aprendizaje supervisado

NIVO IA se configura **por empresa (tenant)**. Cada tenant mantiene aislados sus reglas, fuentes web, archivos, conocimiento aprobado, configuración, aprendizaje pendiente, conversaciones y permisos. Una empresa cliente nunca debe consultar ni aprender automáticamente del conocimiento de otra empresa.

## Aislamiento por tenant

Cada operación de NIVO se resuelve con el `tenant_id` de la conversación/canal autenticado. El motor consulta únicamente registros que pertenecen a ese tenant:

- `bot_profiles`;
- `nivo_rules`;
- `nivo_solutions` y `nivo_solution_modules`;
- `knowledge_sources`;
- `nivo_knowledge_websites`;
- `nivo_learning_queue`;
- políticas de IA externa y consumo.

Ejemplo: si **ES MULTISERVICIOS** sincroniza `esmultiservicios.com`, `zynkocloud.app`, IZZY y CAMI, ese conocimiento queda disponible solo para NIVO de ES MULTISERVICIOS. Si **Juan y Asociados** agrega `juanyasociados.com`, su NIVO usa únicamente el conocimiento de Juan y Asociados.

## Cómo responde NIVO

Orden de decisión recomendado y aplicado:

1. valida tenant, canal y políticas;
2. detecta identidad, saludo, intención comercial o solicitud de humano;
3. evalúa reglas determinísticas del tenant;
4. arma contexto con los mensajes recientes de la conversación;
5. busca conocimiento aprobado del tenant;
6. combina hasta tres fuentes relevantes cuando ayudan a contestar mejor;
7. opcionalmente usa IA externa solo si está habilitada y permitida;
8. si aún no tiene suficiente información, pide un dato adicional y registra la pregunta para aprendizaje supervisado;
9. transfiere a humano únicamente cuando corresponde por política o solicitud del usuario.

## Fuentes web de conocimiento

En **NIVO IA → Fuentes web** cada empresa puede registrar sus propios sitios públicos. Una fuente web contiene:

- nombre visible;
- URL base;
- alcance: una página o todo el dominio público;
- máximo de páginas;
- rutas privadas excluidas;
- sincronización manual o automática;
- frecuencia de actualización;
- estado y última sincronización.

El sincronizador:

- bloquea redes privadas/reservadas en producción;
- respeta el dominio autorizado;
- elimina contenido duplicado antes de publicarlo;
- guarda cada página con trazabilidad por URL;
- publica únicamente dentro del tenant dueño de la fuente.

**Fuentes web** no es lo mismo que **Sitios autorizados de NIVO Web Chat**. Una fuente web indica de dónde puede aprender NIVO. Un sitio autorizado indica dónde puede ejecutarse el widget.

## Aprendizaje supervisado

NIVO no se autoentrena sin control. El aprendizaje funciona así:

1. una pregunta no alcanza la confianza mínima;
2. NIVO la registra en la cola de aprendizaje del tenant;
3. preguntas repetidas aumentan su contador de ocurrencias;
4. si un agente humano responde esa pregunta en la Bandeja, la respuesta queda como candidata;
5. un administrador revisa la candidata en **NIVO IA → Aprendizaje**;
6. al aprobarla, se publica como conocimiento exclusivo de esa empresa;
7. al rechazarla, no se usa para futuras respuestas.

Esto permite mejorar continuamente sin contaminar el conocimiento ni inventar información.

## Conversaciones largas

El límite de respuestas automáticas acepta `0 = conversación continua`. NIVO puede atender conversaciones largas sin detenerse por un contador arbitrario. Se mantienen protecciones independientes para longitud de entrada, canales permitidos, rate limit, palabras bloqueadas y seguridad.

## Contexto conversacional

Para preguntas de seguimiento, NIVO utiliza los mensajes recientes del visitante junto con la pregunta actual. Así puede interpretar consultas como “¿y cuánto cuesta?” o “¿eso sirve para restaurantes?” tomando en cuenta lo hablado inmediatamente antes, sin mezclar conversaciones ni tenants.

## Recuperación de conocimiento

NIVO puntúa el conocimiento por:

- coincidencia del título;
- términos relevantes del mensaje;
- contexto reciente;
- frases relacionadas;
- actualidad de la fuente.

Puede combinar hasta tres fuentes relevantes y evita repetir contenido idéntico.

## Controles disponibles

- activación por empresa;
- canales permitidos;
- orígenes web autorizados;
- rate limit;
- redacción de datos sensibles antes de IA externa;
- conocimiento aprobado;
- reglas determinísticas prioritarias;
- conocimiento manual;
- archivos aprobados;
- fuentes web administrables;
- exclusión de rutas privadas;
- separación por solución/módulo;
- aprendizaje supervisado;
- cola de preguntas no resueltas;
- respuestas humanas candidatas;
- aprobación/rechazo del aprendizaje;
- auto-handoff configurable;
- palabras de handoff;
- umbral de confianza;
- conversación continua o límite configurable;
- cooldown por canales externos;
- pausa con agente humano;
- contexto conversacional reciente;
- máximo de caracteres por pregunta;
- palabras bloqueadas;
- horario comercial y fechas especiales;
- protección de datos y enlaces;
- fallback externo opcional.

## IA externa

OpenAI es una segunda fase opcional. NIVO intenta primero reglas y conocimiento interno del tenant. Solo puede utilizar IA externa cuando:

- la plataforma tiene proveedor configurado;
- el tenant lo tiene habilitado;
- el plan lo permite;
- el canal está autorizado;
- se cumplen las políticas de privacidad, rate limit y presupuesto.

El conocimiento que se envía como contexto también se filtra por `tenant_id`.

## Integración con NIVO Web Chat

Cuando llega un mensaje desde NIVO Web Chat:

`installation_key → sitio autorizado → tenant → conversación → NIVO IA → reglas/conocimiento del tenant → respuesta/handoff`

Por eso el Web Chat de una empresa utiliza exactamente el motor NIVO IA y la base de conocimiento de esa empresa.

## Recomendaciones

- usar fuentes públicas y excluir `/admin`, `/login`, `/checkout` y demás paneles privados;
- activar sincronización automática solo en fuentes confiables;
- revisar la cola de aprendizaje periódicamente;
- aprobar únicamente respuestas humanas correctas y vigentes;
- mantener reglas para información exacta y conocimiento para preguntas abiertas;
- probar NIVO desde el simulador antes de publicar cambios sensibles.

## Resolución de conocimiento por entidad (V2.31.94)

- NIVO detecta preguntas como “qué es…”, “para qué sirve…”, “funciones…” y “cómo funciona…” sobre la empresa y las soluciones del tenant.
- La resolución sigue este orden: reglas exactas → entidad/solución → fuentes web y conocimiento aprobado → respaldo externo opcional → aclaración/fallback.
- `nivo_solutions`, `knowledge_sources`, reglas y aprendizaje siempre se filtran por `tenant_id`.
- Las fuentes `ready` + `approved` se consideran activas para el motor aun cuando una instalación heredada tenga desactualizado el flag `knowledge_enabled`.
- La transferencia automática por baja confianza requiere al menos 3 fallos consecutivos; una petición explícita de atención humana puede transferir inmediatamente.
