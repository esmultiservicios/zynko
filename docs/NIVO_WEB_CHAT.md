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
