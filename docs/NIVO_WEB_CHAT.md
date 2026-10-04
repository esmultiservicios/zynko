# NIVO Web Chat — instalación, experiencia, seguridad y NIVO IA

NIVO Web Chat es el canal web propio de ZYNKO. Cada empresa configura su widget, autoriza sus dominios y atiende las conversaciones desde su Bandeja. Cuando NIVO IA está activo, el mismo chat utiliza reglas y conocimiento **del tenant propietario**.

## Flujo completo

1. El visitante abre el widget en un dominio autorizado.
2. ZYNKO valida `installation_key` + origen real.
3. Se identifica el tenant dueño de esa instalación.
4. Se crea o recupera la conversación del visitante.
5. El mensaje entra a la Bandeja del tenant.
6. NIVO IA evalúa reglas, contexto y conocimiento de ese mismo tenant.
7. La respuesta se guarda en el historial completo.
8. Si NIVO no tiene suficiente confianza, pide contexto adicional o transfiere a humano según la política.
9. El visitante o el agente pueden finalizar el chat.
10. Al cierre puede solicitarse una encuesta de satisfacción.

## Modelo de seguridad

Cada sitio externo autorizado recibe una `installation_key` única. ZYNKO valida la clave junto con el dominio real que hace la petición. Una clave copiada a otro dominio no funciona.

CORS es dinámico: no existe una lista global hardcodeada de clientes. `webchat-api.php` valida la instalación y solo devuelve `Access-Control-Allow-Origin` para el origen autorizado correspondiente.

## Instalación

1. En **NIVO Web Chat**, configura apariencia y comportamiento.
2. En **Sitios autorizados**, registra el dominio exacto.
3. Copia el script único generado para ese sitio.
4. Pégalo antes de `</body>` o en el gestor global de scripts del CMS.
5. Recarga el sitio y verifica **Último uso**.

```html
<script src="https://TU-DOMINIO-ZYNKO/nivo-widget.js" data-zynko-key="CLAVE_UNICA_DEL_SITIO" async></script>
```

## Relación con NIVO IA

NIVO Web Chat no tiene una base de conocimiento independiente. Cuando NIVO IA está activo, el widget utiliza:

- reglas del tenant;
- conocimiento manual aprobado;
- archivos aprobados;
- fuentes web sincronizadas del tenant;
- aprendizaje supervisado aprobado;
- IA externa, únicamente cuando está permitida.

El conocimiento de otras empresas queda fuera de la consulta.

## Experiencia conversacional

- indicador “NIVO está escribiendo…” antes del saludo inicial;
- identidad contextual de la empresa;
- nombre opcional del visitante y perfil editable;
- historial completo desde el primer saludo;
- apertura por defecto en el último mensaje;
- controles Inicio / Último sin tapar la conversación;
- estado online y NIVO IA activo;
- sonido y contador de mensajes;
- persistencia abierto/minimizado;
- inactividad y cierre automático configurables;
- Finalizar chat;
- encuesta de satisfacción de 1 a 5 estrellas;
- Iniciar nuevo chat sin mezclar el historial anterior.

## Sesiones e historial

El historial se guarda por tenant, conversación y visitante. El polling/realtime no debe forzar al visitante al final si está leyendo mensajes antiguos. Al abrir inicialmente el chat sí se posiciona en el mensaje más reciente.

## Transferencia humana

NIVO puede transferir cuando:

- el visitante pide una persona;
- una regla lo exige;
- se superan los intentos de aclaración configurados;
- el canal/política impide respuesta automática;
- un agente toma la conversación.

Cuando existe un agente asignado y la política `pause_when_assigned` está activa, NIVO deja de intervenir automáticamente.

## Aprendizaje desde la Bandeja

Si NIVO no sabe responder una pregunta, la registra para revisión. Si después un agente responde al cliente, esa respuesta puede quedar como candidata de aprendizaje. El administrador decide si se publica. El widget se beneficia de ese conocimiento en conversaciones futuras del mismo tenant.

## Controles premium

- activar/desactivar widget;
- autoapertura y autoapertura única;
- typing y retraso configurable;
- respuestas rápidas;
- timestamps;
- sonido y contador;
- persistencia abierto/minimizado;
- caducidad de sesión;
- ocultar en móvil;
- rutas permitidas/bloqueadas;
- polling y reconexión;
- WebSocket cuando esté disponible;
- estado online;
- alerta mediante título del navegador;
- animación del launcher;
- respeto a `prefers-reduced-motion`;
- perfil de visitante;
- seguimiento por inactividad;
- cierre seguro;
- encuesta de satisfacción.

## Multiempresa

Cada empresa administra únicamente:

- sus widgets;
- sus sitios autorizados;
- sus visitantes y conversaciones;
- su NIVO IA;
- sus reglas;
- su conocimiento;
- sus fuentes web;
- su aprendizaje supervisado.

La empresa principal de ZYNKO puede administrar su propio tenant, pero eso no convierte su conocimiento en conocimiento global de clientes.

## Diagnóstico

Si el script carga pero el widget no aparece, revisar:

- sitio activo;
- clave correcta;
- dominio normalizado;
- CORS/preflight;
- plan y límite de sitios;
- consola del navegador;
- respuesta de `webchat-api.php`;
- última detección en Admin.

Si NIVO no responde, revisar:

- NIVO IA activo;
- canal Web Chat habilitado;
- conocimiento/reglas aprobados;
- confianza mínima;
- agente humano asignado;
- política de horario;
- cola de aprendizaje para preguntas que aún no tienen respuesta.
