# NIVO Web Chat — instalación, permisos y operación

## Modelo de seguridad
Cada sitio externo autorizado recibe una `installation_key` única. ZYNKO valida la clave junto con el dominio real que hace la petición. Una clave copiada a otro dominio no debe funcionar.

## Instalación
1. En **NIVO Web Chat**, configura apariencia y comportamiento.
2. En **Sitios autorizados**, registra el dominio exacto del cliente.
3. Copia el script único generado para ese sitio.
4. Pégalo antes de `</body>` o en el gestor global de scripts del CMS.
5. Recarga el sitio y verifica **Último uso**.

Ejemplo:

```html
<script src="https://TU-DOMINIO-ZYNKO/nivo-widget.js" data-zynko-key="CLAVE_UNICA_DEL_SITIO" async></script>
```

## Controles premium
- activar/desactivar instalación sin borrar historial;
- autoapertura;
- autoapertura una sola vez;
- typing y retraso configurable;
- respuestas rápidas;
- timestamps;
- sonido y contador;
- persistencia abierto/minimizado;
- caducidad de sesión del visitante;
- ocultar en móvil;
- rutas permitidas;
- rutas bloqueadas;
- polling y reconexión;
- estado online;
- alerta mediante título del navegador;
- animación del launcher;
- respeto a `prefers-reduced-motion`.

## Multiempresa
Cada empresa administra únicamente sus propios sitios. Los límites de cantidad de sitios dependen del plan. El sitio principal de ZYNKO se administra automáticamente y no consume el cupo de sitios externos.

## CORS
NIVO Web Chat no debe usar una lista global de dominios hardcodeados. `webchat-api.php` valida dinámicamente la `installation_key` contra el sitio autorizado y responde CORS para el origen válido.

## Diagnóstico
Si el script carga pero el widget no aparece, revisar: sitio activo, clave correcta, dominio normalizado, plan/límite, consola del navegador, respuesta de `webchat-api.php` y última detección en Admin.

## Experiencia conversacional V2.31.62

NIVO Web Chat ya no muestra el saludo inicial como texto estático. En una conversación nueva, el widget muestra primero el estado **NIVO está escribiendo…** y después presenta un saludo dinámico.

### Identidad y contexto

- Para el tenant principal, NIVO mantiene la identidad propietaria de **ES MULTISERVICIOS**.
- Si el sitio autorizado corresponde a ZYNKO, IZZY o CAMI, el saludo y subtítulo contextualizan ese producto sin perder la marca propietaria.
- Para empresas cliente, NIVO utiliza automáticamente el nombre del tenant del cliente.
- El pie de marca del widget mantiene `NIVO Web Chat · Tecnología ZYNKO by ES MULTISERVICIOS`.

### Perfil del visitante

El nombre y correo son opcionales salvo que el administrador los marque como requeridos. Cuando existen, se conservan durante la sesión, pueden editarse desde el propio widget y se sincronizan con el contacto de la Bandeja. El motor NIVO utiliza el nombre para personalizar respuestas cuando la política de IA lo permite.

### Inactividad y cierre de sesión

Desde Admin se pueden configurar:

- minutos antes del mensaje de seguimiento;
- texto del seguimiento;
- minutos antes del cierre automático;
- texto del cierre por inactividad;
- duración máxima del token local del visitante.

Al cerrar por inactividad, la conversación se marca como cerrada y el visitante puede iniciar una nueva conversación sin arrastrar el estado anterior.

### Controles premium agregados

- saludo según horario;
- retardo del primer saludo;
- perfil persistente del visitante;
- perfil editable durante la conversación;
- identidad contextual por sitio;
- estado conversacional visible;
- seguimiento por inactividad;
- cierre automático seguro.
