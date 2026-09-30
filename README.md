## V2.31.40 · NIVO Web Chat restaurado, sitio oficial automático y código único visible

- Corrige instalaciones existentes que aún no tenían la columna o la clave `installation_key` sin exigir un UPDATE manual previo.
- El sitio público utiliza el host real solicitado (`zynko.test`, producción u otro ambiente) para localizar/generar su instalación oficial.
- El widget institucional vuelve a cargar tanto en localhost como en producción.
- El Admin genera automáticamente claves faltantes y vuelve a mostrar el bloque **Código único / Copiar código**.
- El sitio oficial queda identificado como automático y no puede editarse, apagarse ni borrarse.
- Los sitios externos muestran su script únicamente después de autorizar su dominio.
- La validación de dominio normaliza `www`, puertos y URLs para evitar falsos bloqueos entre ambientes.
- Se mantiene `ZYNKO_UPDATE_DB_COMPLETO.sql` como único UPDATE acumulativo.

## V2.31.39 · NIVO Web Chat por sitio, código visible y sitio oficial automático

- El sitio principal de ZYNKO se registra y mantiene automáticamente con una sola instalación.
- Se consolidan automáticamente variantes duplicadas `www` / sin `www` del dominio principal de ZYNKO.
- El sitio oficial de ZYNKO no necesita que el administrador pegue manualmente su script: la portada pública carga su clave única automáticamente.
- Cada sitio externo autorizado muestra siempre su código único dentro de su propia tarjeta.
- Instalaciones antiguas sin `installation_key` reciben una clave automáticamente al abrir NIVO Web Chat.
- `www.dominio.com` y `dominio.com` se consideran el mismo sitio para evitar duplicados accidentales.
- El sitio oficial de ZYNKO no puede desactivarse, editarse ni eliminarse desde las acciones normales.
- Los límites de sitios por plan siguen aplicándose a los sitios externos; la instalación oficial interna de ZYNKO no consume ese cupo.
- El widget vuelve a validarse correctamente en el dominio principal mediante la misma regla segura de clave + dominio.
- Se mantiene `ZYNKO_UPDATE_DB_COMPLETO.sql` como único script acumulativo.

## V2.31.38 · Vista detalle de planes legible y límites NIVO por plan

- Se eliminó el scroll interno que recortaba características en la vista Detalle de Suscripciones.
- Los límites y características ahora crecen con la tarjeta, permiten salto de línea y se leen completos.
- Se reforzó el layout responsive de la vista Detalle sin alterar la vista Miniatura.
- Se mantiene la aplicación real de `max_webchat_sites`: no se pueden autorizar ni reactivar más sitios NIVO Web Chat que los permitidos por el plan asignado.
- Cada sitio autorizado conserva su código único de instalación.
- `ZYNKO_UPDATE_DB_COMPLETO.sql` sigue siendo el único UPDATE acumulativo.

## V2.31.37 · Código único por sitio para NIVO Web Chat

- Cada dominio autorizado genera su propio código de instalación.
- El código se muestra únicamente después de autorizar el sitio.
- El backend valida clave de instalación + dominio real de origen.
- Se corrigió la validación anterior que podía aceptar el host de ZYNKO como sustituto del dominio visitante.
- Los códigos antiguos basados en la clave global del widget siguen funcionando únicamente si el dominio está autorizado, para no romper instalaciones existentes.
- El sitio público principal de ZYNKO mantiene autorización automática del dominio propio, pero usa su clave de instalación única.

# ZYNKO

## V2.31.36 · Planes comerciales oficiales
- Catálogo oficial: Gratis, Starter, Pro y Business.
- API externa disponible desde planes de pago.
- Límites mensuales de chats para planes pagados.
- Planes destacados administrables en el sitio público.
- Un único `ZYNKO_UPDATE_DB_COMPLETO.sql` acumulativo e idempotente.
 — Plataforma SaaS omnicanal

**Nombre oficial de la plataforma:** **ZYNKO**  
**Asistente inteligente oficial:** **NIVO**  

Plataforma SaaS multiempresa para centralizar WhatsApp Business, Messenger y futuras fuentes de conversación, con API propia para IZZY, CAMI y otros sistemas. Cada empresa conserva aislamiento estricto de usuarios, canales, conversaciones, branding, bot, plan y configuración.

> Identidad aprobada para el proyecto: **ZYNKO** es la plataforma y **NIVO** es el asistente inteligente. El branding visual (logos) se diseñará posteriormente.

## Qué significa la IA
Meta transporta mensajes y expone las APIs/canales autorizados. **Meta no convierte por sí sola este panel en un chatbot inteligente.** ZYNKO separa tres capas: reglas deterministas, IA conectable y handoff humano. El administrador podrá elegir modo `rules`, `ai` o `hybrid`, definir instrucciones, base de conocimiento, horarios, límites y reglas de transferencia. Ninguna clave de proveedor IA se guarda en frontend.

## Stack
PHP 8.3+, MySQL 8+, HTML5, CSS3, JavaScript ES2022, REST JSON, Webhooks de Meta, WebSocket para tiempo real y Redis opcional para colas/cache. Select2 + jQuery/AJAX se incorporan en los formularios productivos que requieren búsqueda/selección avanzada.

## Reglas UI/UX obligatorias
- Full responsive desde 320px hasta ultrawide, portrait y landscape.
- Sin controles pegados: labels, inputs, ayudas, tarjetas y acciones mantienen espaciado consistente.
- Formularios alineados por grid; la ayuda nunca rompe la altura visual de una fila.
- Sidebar principal + submenú lateral flotante hacia la derecha en escritorio; drawer en móvil.
- Búsqueda global `Ctrl/Cmd + K` para conversaciones, contactos, usuarios, canales, integraciones y configuración.
- Select2 en selectores de datos productivos; switches visuales en lugar de checkbox/radio crudo cuando aplique.
- Upload premium: drag & drop, pegar desde portapapeles, selector de archivos, preview, validación de tipo/tamaño y progreso.
- Claro/oscuro/sistema, branding por empresa, logo claro/oscuro, favicon e imagen de login administrables.
- ES/EN por usuario con catálogo de traducciones: el administrador **no** tendrá que escribir cada texto dos veces.
- Ayuda contextual consistente y centro de ayuda/onboarding.

## Canales
**Fase actual:** WhatsApp Business + Messenger. La arquitectura deja Instagram Messaging como canal posterior. La conexión real utilizará los mecanismos oficiales que Meta tenga habilitados para cada producto y las credenciales/permisos de la app; no se simula un QR como si fuera autorización real.

## Multiempresa y comercial
Cada tenant tiene plan, estado de suscripción, mensualidad y `tenant_channel_entitlements`. Así un cliente puede contratar solo WhatsApp, otro WhatsApp + Messenger, etc. Estados previstos: trial, active, past_due, suspended y cancelled. La suspensión conserva datos y configuración; no borra conversaciones.

## Módulos
Autenticación/remember me/MFA; tenants; usuarios/roles/equipos; canales; inbox omnicanal; contactos; NIVO; automatizaciones; base de conocimiento; plantillas; API/Webhooks; analytics; auditoría; branding; idiomas; suscripción/canales; salud de integraciones.

## 5 mejoras nuevas añadidas
1. **Command Palette / búsqueda global:** navegación y búsqueda transversal con teclado.
2. **Onboarding guiado:** checklist de empresa → branding → canal → usuario → bot → prueba de mensaje.
3. **Centro de salud:** permisos, webhooks, tokens, latencia, último evento y reintentos por canal.
4. **Feature entitlements:** habilitación por plan/canal sin tocar código ni mostrar funciones no contratadas.
5. **Media Library multiempresa:** logos y archivos reutilizables, con propósito, propietario y aislamiento por tenant.

## Seguridad
Tenant ID validado en backend; RBAC; secretos cifrados; CSRF; rate limit; sesiones seguras; webhooks idempotentes; verificación de firma; auditoría; hashes de API keys; sanitización de uploads; MIME real; límites de tamaño; rutas no públicas para secretos.

## Base de datos
`database/schema.sql` incluye tenants, usuarios, roles, equipos, planes, suscripciones, derechos por canal, canales, contactos, conversaciones, mensajes, media library, NIVO/bot profiles, flujos, knowledge sources, API keys, webhooks, branding, auditoría y sesiones.

## Flujo de mensajes
Meta → webhook HTTPS → verificación → `webhook_events` → worker → conversación/mensaje → WebSocket → navegador.  
Agente/NIVO/IZZY/CAMI → API backend → proveedor del canal → persistencia → WebSocket.

## Estado real de esta entrega
Starter funcional de interfaz y arquitectura ampliado. Tiene navegación, login demo, dashboard/inbox existentes, NIVO, branding, canales y suscripción a nivel de interfaz + esquema SQL. **No contiene credenciales de Meta ni proveedor IA y todavía no debe presentarse como integración productiva terminada.** La conexión productiva requiere implementar los controladores/servicios y obtener las autorizaciones correspondientes.

## Instalador web automático
En una instalación nueva, ZYNKO detecta que `storage/installed.lock` no existe y abre automáticamente `public/install.php`.

El asistente solicita únicamente los datos necesarios de MySQL y la cuenta principal. Luego prueba la conexión, crea la base de datos si el usuario MySQL tiene permisos, ejecuta `database/schema.sql`, crea la primera empresa, el usuario propietario, el branding inicial y el perfil de NIVO, genera `APP_KEY`, escribe `.env` y bloquea el instalador al finalizar.

No es necesario importar las tablas manualmente. Para reinstalar deliberadamente en un entorno limpio, elimina primero la base de datos/datos correspondientes y `storage/installed.lock`; no hagas esto en producción con información existente.

## Correo y notificaciones
ZYNKO incluye esquema para `correo`, `correo_tipo`, preferencias y bitácora de notificaciones. La UI contempla SMTP y Microsoft Graph y una prueba antes de activar el envío. Se incluyen como referencia los servicios entregados por ES MULTISERVICIOS en `app/Services/` para conservar la lógica probada (SMTP/Graph, BCC, adjuntos y plantillas) mientras se integra con el bootstrap definitivo de ZYNKO.

Eventos iniciales: sistema, seguridad, empresas/suscripciones, canales, usuarios, conversaciones, NIVO/IA, facturación/cobros, reportes y pruebas.

jQuery 3.7.1 y Select2 4.1.0 están incluidos localmente en `public/assets/vendor/`; ZYNKO no depende de CDN para estas librerías.

## Plantillas profesionales de correo
ZYNKO incluye `app/Services/EmailTemplates.php`, una plantilla transaccional responsive y sin dependencias remotas. El branding se recibe por empresa en tiempo de ejecución (`company_name`, `logo_url`, `support_email`, `app_url`, `app_title`). Incluye plantillas base para prueba de correo, alta de empresa, seguridad, facturación y notificaciones genéricas. El panel **Correo y notificaciones** incorpora una vista previa visual de la plantilla.

## UI local
- jQuery 3.7.1: `public/assets/vendor/jquery/`
- Select2 4.1.0: `public/assets/vendor/select2/`
- Font Awesome Free 6.7.2 LTS: `public/assets/vendor/fontawesome/` (CSS + webfonts locales)
- Notificaciones/confirmaciones ZYNKO: `public/assets/js/zynko-ui.js` (`showNotify` y `Swal.fire`), sin `alert()`, `confirm()` ni `prompt()` nativos.
- El instalador permite probar SMTP o Microsoft Graph antes de guardar/finalizar; la prueba envía un correo real al destino interno o, si está vacío, al administrador principal.

## Integración externa segura (fase API/NIVO)
- Cada sistema externo usa su propia API key desde **Integraciones**.
- Las claves se almacenan como hash y pueden revocarse.
- `POST /api.php?r=v1/messages/send` recibe solicitudes de IZZY/CAMI/otros sistemas, valida tenant, scope, plan, canal e idempotencia y crea el mensaje en cola.
- `POST /api.php?r=v1/nivo/context` recupera contexto autorizado de la base de conocimiento de NIVO.
- Los límites de usuarios/canales se aplican desde el plan asignado a cada empresa.
- Solo la empresa principal puede crear planes y asignarlos.
- La entrega real por WhatsApp/Messenger exige autorización oficial y credenciales válidas de Meta. ZYNKO no marca un canal conectado ni simula un QR antes de esa fase.
- Ver `docs/API_INTEGRATION.md`.

### Premium Omnichannel V2.4 — Pantalla completa persistente
- El botón de pantalla completa mantiene ZYNKO en fullscreen al navegar entre Dashboard, Bandeja, Canales y demás módulos.
- La navegación se conserva dentro de un contenedor del mismo origen mientras el documento anfitrión permanece en Fullscreen API.
- Al pulsar nuevamente el botón de pantalla completa o salir con ESC, se limpia el modo persistente.

- V2.10: Channel catalog modal readability and switch alignment corrected globally.


## ZYNKO V2.13 — NIVO Web Chat + UI
- NIVO Web Chat aparece y se filtra explícitamente en la Bandeja omnicanal.
- Widget configurable como icono flotante cerrado o ventana abierta al cargar.
- Conversaciones Web Chat usan el mismo Inbox, agentes, NIVO y WebSocket.
- Badges consistentes para roles/estados.
- Modal de planes más ancho y estable.
- Acceso Experiencia enfoca correctamente la sección.


## NIVO Web Chat · identidad multiempresa
- NIVO se presenta por defecto como asistente virtual de la empresa/tenant que utiliza el widget.
- La identidad visible del asistente puede personalizarse por empresa sin cambiar la propiedad de la plataforma.
- El pie del widget identifica discretamente la plataforma como `Powered by ZYNKO · NIVO`.
- Se conserva el modo flotante cerrado o ventana abierta configurado por cada widget.

## ZYNKO V2.15 — NIVO Web Chat + NIVO IA Premium
### NIVO Web Chat
1. CTA opcional junto al launcher flotante.
2. Notificación sonora configurable para nuevas respuestas.
3. Aviso y enlace de privacidad configurable antes de enviar.
4. Captura de nombre/correo configurable y posibilidad de exigir los datos seleccionados.
5. Contador de respuestas no leídas cuando el widget está minimizado.

### NIVO IA
1. Palabras/frases de transferencia a humano configurables por empresa.
2. Horario de atención humana con días, horas y mensaje fuera de horario.
3. Tono operativo configurable: profesional, cercano o breve.
4. Longitud máxima de respuestas obtenidas desde conocimiento autorizado.
5. Transferencia segura configurable: confianza mínima + auto-handoff, conservando reglas y fuentes aprobadas.

Se conserva el comportamiento multiempresa, WebSocket, Bandeja omnicanal, dominios autorizados, branding por tenant y los estilos de switches establecidos en V2.14.

## ZYNKO V2.16 — Centro de notificaciones premium
- Notificación premium de seguridad al iniciar sesión, usando el SMTP o Microsoft Graph activo del tenant.
- Alertas configurables para nuevos mensajes, handoff de NIVO y eventos críticos.
- NIVO Web Chat notifica mensajes entrantes y solicitudes de atención humana.
- Anti-spam configurable por conversación: 0, 5, 10, 15, 30 o 60 minutos; 10 minutos recomendado.
- Plantillas HTML responsive con branding de empresa y CTA a ZYNKO.
- Registro de envíos/errores en notification_log.
- NotificationService reutilizable para que los conectores de WhatsApp, Messenger, Instagram y futuros canales invoquen el mismo flujo al procesar mensajes entrantes reales.

## ZYNKO V2.17 — NIVO Knowledge Hub
- Centro de conocimiento por Solución → Módulo → Fuente.
- CAMI e IZZY se crean automáticamente para la empresa principal de la plataforma cuando todavía no existen soluciones.
- Nuevas soluciones y módulos pueden agregarse sin reprogramar NIVO.
- Fuentes manuales pueden asociarse a una solución/módulo; archivos importados entran a revisión antes de publicarse.
- NIVO solo consulta conocimiento aprobado/publicado y el Playground permite probar por solución.
- Base preparada para relacionar contactos/clientes con soluciones y futuras APIs mediante `nivo_contact_solutions`.
- Se conserva el comportamiento, reglas, handoff, Web Chat, correo/notificaciones y estilos existentes.

## ZYNKO V2.18 — Premium UI consistency
- NIVO Knowledge Hub aligned with consistent spacing and equal-height cards.
- Select2 standardized for application selects, including knowledge solution/module selectors.
- Global premium hover movement for cards without changing functional behavior.
- Switches keep the login visual standard; rule switch markup normalized.
- Event checkboxes use one clean custom visual language.
- System version is shown discreetly in the sidebar company block.

## ZYNKO V2.19 — usuarios, correo y consistencia visual
- Launcher de NIVO centrado con icono vectorial estable.
- Acciones de usuario en dropdown premium: editar, restablecer contraseña por correo y cerrar sesiones.
- Formularios de edición/fotografía de usuario conectados a acciones reales.
- Correo y notificaciones reorganizado para evitar solapamientos y mantener jerarquía visual.


## ZYNKO V2.20 — Estándar global de interacción
- `showNotify` local unificado para success, error, info y warning.
- `Swal.fire` premium local para confirmaciones y mensajes de decisión.
- Eliminados los fallbacks a `alert()`, `confirm()` y `prompt()` nativos.
- API `ZynkoModal` y estilo global para modales centrados, responsive, ESC/X y sin cierre accidental por clic exterior.

## ZYNKO V2.21 — UX premium + sesiones + dashboard persistente
- Espaciado global reforzado entre tarjetas y secciones, incluido NIVO IA.
- Menús de acciones se cierran al ejecutar una opción.
- Administrador puede ver sesiones activas por usuario, cerrar una sesión o todas.
- Horario humano de NIVO reorganizado con separación consistente.
- Correo/notificaciones protegido contra desbordes y solapamientos al editar.
- Inicio guiado rediseñado como recorrido premium y accionable.
- Dashboard configurable por usuario; la selección se guarda en base de datos, no depende de cookies/localStorage.
- Enlaces de acción como Abrir bandeja usan CTA premium consistente.


## ZYNKO V2.21.5 — Correcciones de regresión
- Ver sesiones abre el modal de sesiones y permite revocar accesos individuales.
- Horario humano de NIVO con jerarquía y separación consistente.
- Correo y notificaciones evita compresión/solapamiento de textos y acciones.
- Versión visible sincronizada a 2.21.5.


## ZYNKO V2.22.0 — Premium consistency
- Switches alineados globalmente.
- Prueba de correo desde Correo y notificaciones usando la plantilla premium central.
- Sesiones activas con IP, navegador, sistema, tipo, inicio, expiración y cierre individual.
- Iconografía uniforme en modales personalizados.
- Versión administrable solo por el propietario de la plataforma.


## ZYNKO V2.23.0 — Modal system + isolated Web Chat
- Normalización estructural de modales premium, títulos, iconos, formularios y acciones.
- NIVO Web Chat reforzado con Shadow DOM y aislamiento del host para evitar colisiones con CSS del sitio cliente.
- Sesiones activas con layout de detalle robusto.
- Switches y checkboxes alineados dentro de modales NIVO e Integraciones.


## ZYNKO V2.23.1 — Email CTA compatibility
- CTA de correos transaccionales usa estructura compatible con Outlook/Microsoft 365.
- En entornos locales (.test/localhost) no se imprime una URL local inutilizable en el correo.
- La versión se actualiza automáticamente desde V2.23.0 a V2.23.1.


## ZYNKO V2.23.2 — Canales uniformes + sesiones reales
- Todas las tarjetas de Canales usan la misma altura en escritorio, incluso cuando el catálogo ocupa varias filas.
- Cada release del core eleva automáticamente `app_version` cuando la versión publicada es anterior a la release instalada.
- Todo inicio de sesión, con o sin «Recordarme», se registra en `user_sessions` con IP, navegador, sistema, inicio y expiración.
- «Ver sesiones» muestra las sesiones activas reales y permite revocar accesos individuales.


## ZYNKO V2.23.3 — Alineación de canales
- Todas las tarjetas del catálogo de canales parten de la misma línea superior y conservan el mismo alto en escritorio.
- Se elimina el margen heredado de `.panel` dentro del grid de canales para evitar desplazamientos visuales.


## ZYNKO V2.23.4
- Corrige el administrador de sesiones activas para sesiones ya abiertas antes de la actualización.
- Registra automáticamente la sesión PHP actual si aún no estaba inventariada.
- Permite revocar una sesión individual o todas desde el mismo modal.

## ZYNKO V2.23.5
- Sesiones activas ahora se consultan en vivo al abrir el modal; no dependen del snapshot cargado con la página.
- Corregido error JavaScript de escape de contenido que impedía renderizar sesiones aunque existieran.
- Modal de sesiones con alto fijo, scroll interno y estados premium de carga/vacío/error.
- Revocación individual y total mantiene el estado visual sincronizado.


## ZYNKO V2.23.6 — Mostrar todos los registros
- Los selectores “Mostrar X registros” incluyen ahora la opción **Todos**.
- La selección se aplica realmente a los listados del panel y se combina con la búsqueda existente.
- Usuarios incorpora 10, 25, 50 y Todos sin alterar el diseño premium ni Select2.


## V2.23.7
- Todos los diálogos de confirmación ZYNKO asignan iconos contextuales a botones Confirmar/Cancelar de forma centralizada.
- Se mantiene soporte para iconos explícitos cuando una acción requiere uno particular.


## V2.23.8
- Correo de prueba: se eliminó completamente el CTA/app_url del payload de prueba para impedir que clientes de correo rendericen URLs locales como texto o sintaxis tipo `[URL]Texto`.


## V2.24.0
- NIVO Web Chat refuerza aislamiento visual mediante Shadow DOM + reset interno para evitar CSS del sitio anfitrión.
- KPIs de uso del widget: sitios registrados, autorizados, detectados y visitantes.
- NIVO IA admite fechas cerradas y horarios especiales por fecha.
- Alineación global reforzada para contenedores de iconos.
- Instalador admite prefijo opcional del hosting para la base de datos y conserva detección automática de APP_URL.


## V2.24.1
- KPIs de NIVO Web Chat con entrada escalonada, elevación al hover, movimiento de icono y contador animado.
- Respeta `prefers-reduced-motion` por accesibilidad.


## V2.24.2
- Instalador: aviso visible para hosting que requieren crear previamente la base de datos y asignar el usuario MySQL con permisos antes de continuar.
- Se conserva la creación automática cuando el proveedor la permite.


## V2.25.8
- Paquete de distribución limpio: `.env` y `storage/installed.lock` ya no forman parte del ZIP de instalación.
- Instalador apto para instalación fresca local/hosting: genera `.env` y el lock al finalizar.
- Verificación previa de PHP, PDO MySQL, OpenSSL, cURL y permisos de escritura.
- Detección de URL mejorada para HTTPS y proxies (`X-Forwarded-Proto` / `X-Forwarded-Host`).
- Base de datos compatible con creación automática o bases precreadas por cPanel/hosting con prefijos administrables.
- Vista previa del nombre final evita duplicar el prefijo cuando ya forma parte del nombre.


## V2.25.8
- Front controller `index.php` en la raíz para hosting cuyo DocumentRoot apunta al proyecto.
- `.htaccess` raíz desactiva listado de directorios, enruta recursos públicos y protege carpetas privadas.
- Defensa adicional con `.htaccess` dentro de app/database/docs/routes/storage/websocket.
- Compatible con acceso limpio desde la raíz sin tener que escribir `/public`.


## V2.25.8
- `.gitignore` reforzado: `.env`, `.env.*`, `storage/installed.lock`, logs y cache runtime no se versionan.
- `.env.example` se conserva como plantilla versionable.
- El MASTER de distribución no contiene `.env` ni `storage/installed.lock`.
- Se conservan las carpetas runtime mediante `.gitkeep` sin subir su contenido local.


## V2.25.8
- Asistente de instalación compactado y alineado en todos sus pasos.
- Reduce espacios verticales, alturas de campos y márgenes sin cambiar el flujo funcional.
- Mantiene pares de campos alineados y adapta el formulario a tablet/móvil sin desbordes.
- Conserva instalación local/hosting, prefijos MySQL y protección de archivos runtime.


## V2.26.0
- Instalador: selección premium por tarjetas/radio para Configurar después, SMTP y Microsoft Graph.
- Configurar después queda seleccionado por defecto y no bloquea la instalación.
- Campos de correo aparecen únicamente según el método elegido.
- Prueba de correo disponible solo para SMTP/Graph.
- Controles y acciones del paso Correo reorganizados y responsive.


## V2.26.2
- El instalador inicia limpio al volver a Bienvenida: no conserva credenciales, prefijo ni datos de intentos anteriores.
- La base por defecto vuelve a ser `zynko` y el prefijo queda vacío.
- Se separa el nombre propio de la base del nombre final con prefijo para evitar duplicaciones como `esmultiservicios_esmultiservicios_zynko`.
- La conexión y el `.env` usan el nombre final real de la base, mientras la UI conserva los campos editables correctamente.


## V2.26.2 — Plantilla de correo unificada
- El instalador y el panel usan `app/Services/EmailTemplates.php` como única plantilla visual transaccional.
- Las pruebas SMTP y Microsoft Graph del instalador usan exactamente el mismo diseño premium que las pruebas del dashboard.
- Los correos conservan el mismo shell visual y cambian título, etiqueta, contenido y llamada a la acción según el evento.


## V2.26.5
- El correo de la cuenta creada por el instalador se precarga automáticamente en el login al pulsar Ir a ZYNKO.
- El valor de instalación se usa solo como ayuda de primer acceso y se limpia después de iniciar sesión correctamente.
- Versión runtime, UI y schema sincronizada en 2.26.5.


## V2.26.5
- Plantilla transaccional unificada con bloque informativo de NIVO.
- NIVO se presenta como asistente inteligente para chat e IA, con transferencia humana cuando corresponde.
- Correo de bienvenida de instalación usa una plantilla dedicada de cuenta creada, sin etiquetarlo como alerta crítica.
- El mismo lenguaje visual se conserva para pruebas, seguridad, facturación y notificaciones generales.
- Versión runtime, UI y schema sincronizada en 2.26.5.


## V2.26.5
- Ajuste final de plantilla de correo: badge sin saltos de línea y bloque NIVO horizontal.
- NIVO utiliza el robot institucional en correos cuando APP_URL es público; mantiene fallback seguro si no hay URL pública.
- Diseño transaccional unificado y compatible con Outlook mediante tablas HTML.


## V2.26.7
- Corrige la URL pública usada por plantillas de correo para que NIVO cargue su imagen real desde assets/img/nivo-email.png.
- La prueba de correo del dashboard ahora recibe app_url y muestra el robot de NIVO en lugar del fallback NI.
- Normaliza APP_URL eliminando /public cuando el dominio ya apunta al document root público.
- Unifica botones del instalador con acabados navy/teal, sin botones blancos.
- Versión runtime, UI y schema sincronizada en 2.26.7.

## V2.27.0 — Registro público, verificación y Plan Gratis
- El login incorpora **Crear cuenta gratis** para altas públicas de empresas y propietarios.
- Registro protegido con honeypot, límites por IP/correo, código de 6 dígitos hasheado, expiración de 10 minutos, reenvío controlado e intentos máximos.
- La cuenta y empresa se crean únicamente después de verificar el correo.
- Se activa automáticamente el **Plan Gratis** con NIVO Web Chat, 1 usuario, 1 sitio autorizado y hasta 5 chats nuevos por día; las conversaciones existentes pueden continuar sin límite de mensajes.
- Los demás canales y módulos permanecen visibles, pero bloqueados cuando el plan no los incluye.
- Administración de planes ampliada con canales permitidos, módulos, sitios Web Chat y límite diario de chats; NIVO Web Chat se incluye siempre.
- Al completar el registro, ZYNKO envía correo premium de bienvenida al cliente y notificación de nuevo cliente al administrador principal.
- La capa de servidor aplica límites de plan en Web Chat, sitios autorizados, canales, usuarios y módulos avanzados.
- Runtime, interfaz y schema sincronizados en **2.27.0**.



## V2.27.2 — Ciclo comercial y notificaciones bidireccionales
- Los propietarios/administradores de empresas pueden solicitar un plan superior desde Suscripción.
- Cada solicitud queda auditada y envía confirmación al cliente y aviso al administrador principal.
- El administrador principal puede aprobar/asignar o rechazar solicitudes desde el mismo panel.
- La asignación manual o aprobación de un plan notifica al cliente y al administrador principal, incluyendo plan anterior, plan actual y estado.
- Cambios de estado `active`, `grace`, `suspended` y `cancelled` generan notificación comercial.
- Crear, editar o eliminar planes genera aviso de auditoría al administrador principal.
- Se agregan correos de seguridad cuando un usuario es creado, cambia su rol/estado o se revocan sus sesiones.
- La configuración de un canal genera correo informativo al propietario de la empresa.
- `notification_log` clasifica los nuevos eventos dentro de Empresas y suscripciones, Seguridad y Canales.
- La entrega de correo usa siempre la plantilla premium central de ZYNKO/NIVO.
- Runtime, interfaz y schema sincronizados en **2.27.2**.


### V2.27.2 · Foco inteligente de formularios
- El cursor se coloca automáticamente en el primer campo utilizable de formularios y modales.
- Se excluye el buscador global/público del dashboard y controles ocultos, deshabilitados o readonly.
- Registro público inicia directamente en Empresa; login, recuperación y verificación respetan su primer campo.
- Los modales usan el mismo criterio sin provocar scroll ni enfocar campos inválidos.


### V2.27.3 · Términos y Condiciones administrables
- El registro público incluye acceso visible a Términos y Condiciones junto al consentimiento.
- Los términos se muestran en un modal ancho, responsive y consistente con la interfaz premium de ZYNKO.
- El administrador principal puede editar y publicar el documento desde el Dashboard.
- Cada publicación incrementa la versión automáticamente.
- La solicitud de registro guarda la versión aceptada y la fecha/hora de aceptación.
- Si los términos cambian mientras un usuario completa el formulario, ZYNKO exige recargar y aceptar la versión vigente.
- Runtime, interfaz y schema sincronizados en **2.27.3**.


### V2.27.5 · Header legal premium y composición refinada
- Rediseña el encabezado del modal legal para aprovechar correctamente el ancho disponible.
- Integra versión y fecha de publicación en tarjetas compactas dentro del header.
- Mejora jerarquía visual, alineación, cierre y comportamiento responsive sin alterar la administración del documento.

### V2.27.4 · Modal legal premium y términos ampliados
- Modal público reconstruido con header y footer siempre visibles; solo el contenido legal tiene scroll.
- Botón de confirmación visible y estable en desktop, tablet y móvil.
- Cabecera, metadatos, estado del documento y pie legal refinados visualmente.
- Términos base ampliados a 18 secciones para cubrir cuenta, usuarios, uso aceptable, canales, planes, facturación, datos, privacidad, integraciones, disponibilidad, suspensión, propiedad intelectual y cambios del documento.
- El administrador principal puede seguir modificando título y contenido desde Dashboard → Términos y Condiciones → Administrar términos.
- Si la instalación conserva exactamente el texto base V2.27.3 sin editar, se actualiza automáticamente al nuevo texto; contenido previamente personalizado no se sobrescribe.
- Runtime, interfaz y schema sincronizados en **2.27.5**.


### V2.27.8 · Corrección de espaciado del encabezado legal
- Corrige el encabezado del modal legal que visualmente quedaba pegado al borde superior.
- Aumenta el aire vertical real del header y centra correctamente icono, título y subtítulo.
- Reubica el botón de cierre con separación uniforme respecto a los bordes.
- Mantiene versión y fecha alineadas en escritorio sin comprimir el título.
- Ajusta el espaciado de tablet y móvil sin provocar desbordes.
- Mantiene footer fijo, scroll exclusivo en el cuerpo y edición de términos desde el Dashboard.
- Runtime, interfaz y schema sincronizados en **2.27.8**.


### V2.27.9 · Corrección visual del botón Cancelar en Términos
- Corrige el botón Cancelar del footer administrativo para que conserve ancho, alto, icono y texto correctamente alineados.
- Evita que las reglas del botón X del header afecten al botón Cancelar del footer.
- Iguala la altura visual de Cancelar y Publicar nueva versión.
- Mantiene responsive, editor WYSIWYG, versionado y modal público sin cambios funcionales.


### V2.28.0 · SEO técnico y portada pública indexable
- Nueva portada pública optimizada para buscadores sin exponer el dashboard.
- `robots.txt` y `sitemap.xml` dinámicos basados en `APP_URL`.
- Metadatos SEO, canonical, Open Graph, Twitter Card y JSON-LD.
- Login, registro, recuperación, verificación y área privada quedan con `noindex,nofollow`.
- `site.webmanifest`, imagen social y guía `SEO-README.txt`.
- El dominio canónico se controla desde `.env`, evitando URLs hardcodeadas.


### V2.28.2 · SEO físico + administrador de posicionamiento
- Agrega `robots.txt` y `sitemap.xml` visibles físicamente en la raíz del proyecto y en `public/`.
- Mantiene generación dinámica en Apache para que ambos endpoints usen el dominio real configurado.
- Agrega `SEO-APLICAR.txt` con guía de Search Console y publicación.
- Incorpora **Configuración > SEO y posicionamiento** para la cuenta administradora principal.
- Permite administrar nombre del sitio, URL canonical, meta description, locale, Twitter/X y tokens de Google/Bing.
- La portada pública consume la configuración SEO guardada y mantiene fallback al `.env`.
- Conserva `noindex,nofollow` en login, registro, recuperación, verificación y todas las áreas privadas.


### V2.28.3 · Rail NIVO restaurado al ancho completo
- Recupera el ancho completo de la columna derecha de NIVO Web Chat.
- Evita que la vista previa y la tarjeta de acompañamiento se encojan al ancho del contenido.
- Restaura una altura visual premium para la vista previa y la tarjeta de NIVO.
- Mantiene el comportamiento responsive en escritorio, tablet y móvil.
- Conserva intactos SEO, Search Console, Términos y la identidad visual de NIVO.


### V2.28.4 · NIVO limpio, altura sincronizada y animación restaurada
- Corrige la colisión del `aside` interno de NIVO Web Chat con el sidebar global; elimina el fondo navy accidental.
- Sincroniza en escritorio la altura del rail derecho con el card **Diseño y comportamiento**.
- Mantiene Vista previa y la tarjeta de NIVO dentro de esa misma altura, sin desbordes.
- Restaura movimiento sutil de la mascota en la tarjeta NIVO de **NIVO Web Chat** y **NIVO · IA**.
- Añade animación suave también a la mascota acompañante, respetando `prefers-reduced-motion`.
- Conserva comportamiento responsive en escritorio, tablet y móvil sin tocar SEO, Términos ni funciones existentes.


### V2.28.5 · KPIs NIVO interactivos y estado inactivo corregido
- Corrige la animación de entrada de las métricas para que no bloquee el movimiento al pasar el mouse.
- Restaura hover premium en las cards de uso de NIVO Web Chat: elevación, sombra, borde, icono y valor.
- Cambia el estado Inactivo de NIVO IA a una señal ámbar profesional, reservando el verde únicamente para Activo/Conectado.
- Mantiene responsive, identidad NIVO, SEO, Search Console y Términos ya aprobados.


### V2.28.6 · Sitio público premium + ES MULTISERVICIOS + redes administrables
- Refuerza a ZYNKO como solución desarrollada y respaldada por ES MULTISERVICIOS.
- Mejora la portada pública con microanimaciones, hover premium y aparición progresiva al hacer scroll.
- Corrige FAQ para que abrir una pregunta no estire visualmente la tarjeta vecina.
- Agrega acceso de regreso al sitio público desde Login y Crear cuenta.
- Agrega redes sociales flotantes y en el footer con colores oficiales y alto contraste.
- Incorpora administración de redes sociales, orden, URL y estado desde Configuración.
- Restringe la administración pública, SEO, Términos y planes a la empresa principal mediante validación del servidor y de la interfaz.
- Conserva NIVO, SEO técnico, Search Console, Terms y funciones anteriores.


### V2.28.7 · Redes posicionables + Login premium compacto
- Permite elegir desde Configuración si las redes flotantes aparecen a la izquierda o derecha.
- Permite elegir posición vertical superior, centro o inferior en escritorio.
- Permite activar o desactivar de forma independiente redes flotantes y redes del footer.
- En tablet y móvil las redes flotantes se convierten automáticamente en una barra inferior compacta para no cubrir contenido.
- Rediseña el acceso “Volver al sitio web” como botón secundario coherente con la UI de ZYNKO.
- Hace el Login ligeramente más ancho y reduce espacios verticales para una composición más compacta, premium y legible.
- Conserva permisos globales exclusivamente para la empresa principal y mantiene intactos NIVO, SEO, Search Console, Términos y planes.


### V2.28.8 · Política global de botones sin blanco
- Elimina botones blancos del sitio público y de la aplicación.
- Las acciones primarias conservan teal; las secundarias usan navy y los controles utilitarios usan superficies teal/navy suaves.
- `Iniciar sesión` y demás CTAs secundarios de la portada dejan de usar fondo blanco.
- `Volver al sitio web` adopta botón navy premium y consistente en autenticación/registro.
- Paginación, botones de icono, controles del header, editor legal y utilidades usan fondos tintados en lugar de blanco.
- El botón/etiqueta opcional del launcher de NIVO Web Chat también deja de usar fondo blanco.
- Mantiene contraste correcto en modo oscuro y responsive.


### V2.29.1 · Reparación portada pública y showcase premium
- Corrige la carga de estilos y scripts de la portada pública usando las rutas probadas del proyecto.
- Elimina la referencia al logo inexistente que causaba imagen rota.
- Mantiene y mejora la galería de capturas del sistema con zoom y modal.
- Refuerza NIVO Web Chat, NIVO IA, canales y autorización oficial de Meta.
- Mejora la tarjeta de Plan Gratis y conserva login/registro premium.
- Mantiene SEO, robots, sitemap, redes sociales administrables y configuración global.


### V2.29.2 · Orden visual, navegación, FAQ y NIVO
- Restaura el menú público en una sola línea en escritorio y evita saltos de texto.
- Mejora espaciados para que botones, avisos y tarjetas no queden pegados.
- Numera las preguntas frecuentes con una UI premium.
- Refuerza hover y movimiento en tarjetas públicas.
- Restaura la mascota NIVO de forma visible en el sitio principal.
- Compacta Crear cuenta para reducir altura sin perder información.
- Mantiene login, registro, SEO, redes, términos, NIVO Web Chat, NIVO IA y panel interno.


### V2.29.4 · Contacto público y navegación más legible
- Aumenta el tamaño de las etiquetas del menú público manteniendo la navegación en una sola línea en escritorio.
- Agrega sección Contacto premium con nombre, empresa, correo, teléfono, motivo, origen y mensaje.
- Registra las consultas públicas en `public_contact_inquiries` para conservar trazabilidad básica.
- Reutiliza el proveedor de correo principal configurado en ZYNKO (SMTP o Microsoft Graph).
- Envía notificación al destinatario interno configurado y confirmación automática al visitante.
- Incorpora protección CSRF, honeypot y límite básico de frecuencia por IP.
- Usa `showNotify` en el sitio público para confirmar o reportar errores de envío.
- Mantiene responsive, NIVO, showcase, SEO, redes sociales y funciones aprobadas.


### V2.29.5 · Select2 + Turnstile + campos obligatorios
- Formulario público de contacto migra sus selects a Select2 local.
- Los campos obligatorios muestran asterisco rojo y conservan validación del navegador y del servidor.
- Agrega Cloudflare Turnstile configurable desde la empresa principal.
- Site Key, activación y hostname se administran desde Configuración > Sitio público y redes sociales.
- Secret Key se guarda cifrada con APP_KEY y no se vuelve a mostrar en texto plano.
- Turnstile se ejecuta en segundo plano con appearance interaction-only y validación obligatoria contra Siteverify en el servidor.
- Se conservan honeypot y rate limit existentes como capas adicionales anti-spam.


### V2.29.7 · Responsive total, analítica pública, contactos flotantes y confirmación final
- Navegación móvil real con botón hamburguesa y menú adaptado a pantallas pequeñas.
- Refuerzo responsive del sitio público y panel administrador.
- Analítica de visitas del sitio público para la empresa principal: hoy, 7 días, mes, histórico, días, semanas, meses, dispositivos y referidos.
- NIVO Web Chat integrado en la portada usando el widget administrable de la empresa principal.
- WhatsApp público flotante configurable desde el panel, por defecto +504 8913-6844, colocado en el lado opuesto a NIVO para evitar superposición.
- Confirmación premium antes de finalizar la instalación, con resumen de base de datos, administrador, URL, correo, NIVO y seguridad.
- El instalador acepta correctamente la opción Configurar correo después y crea installed.lock solo después de confirmar.


### V2.29.8 · Reparación responsive del administrador y accesos públicos
- Corrige el selector responsive que afectaba por error a cualquier elemento `aside` dentro del administrador y desplazaba el detalle de analítica fuera del layout.
- El comportamiento móvil del sidebar queda limitado exclusivamente a `#sidebar`.
- Conserva intacto el módulo de analítica dentro del flujo normal del Dashboard.
- Los accesos públicos de Iniciar sesión y Crear cuenta se abren en una pestaña nueva para no sacar al visitante del sitio principal.
- Conserva analítica, NIVO Web Chat, WhatsApp flotante, Turnstile, contacto, SEO, Términos y configuración existentes.


### V2.29.8 · Dashboard reparado y accesos públicos en nueva pestaña
- Corrige el conflicto de CSS que trataba el panel lateral de analítica como si fuera el sidebar principal.
- Limita todas las reglas globales del menú lateral exclusivamente a `#sidebar`.
- Mantiene la analítica dentro del Dashboard, con su layout normal y responsive.
- Convierte el bloque lateral de detalle de analítica a un contenedor normal para evitar futuras colisiones.
- Los enlaces públicos de Iniciar sesión y Crear cuenta se abren en una pestaña nueva desde header, menú móvil, hero, Plan Gratis, CTA y footer.
- Conserva NIVO Web Chat, WhatsApp flotante, analítica, Turnstile, formulario de contacto, SEO, redes, Términos y el instalador premium.


### V2.29.9 · Dashboard y acceso público corregidos
- Restaura los KPIs del Dashboard con tarjetas premium, iconos y espaciado correcto.
- El dominio raíz vuelve a mostrar siempre el sitio público, incluso cuando existe una sesión administrativa activa.
- Login y Crear cuenta continúan abriendo en pestaña nueva desde el sitio público.
- Mantiene intactos NIVO Web Chat, WhatsApp flotante, analítica, contacto, Turnstile, SEO y configuración existente.


### V2.30.0 · Cierre visual y funcional premium
- Alinea los filtros Días / Semanas / Meses de analítica en una sola fila.
- Habilita búsqueda real en Select2 del formulario público.
- Permite editar nombre/etiqueta y dominio de sitios autorizados de NIVO Web Chat.
- Sustituye la referencia técnica a `</body>` por una instrucción amigable.
- Rediseña el modal Crear/Editar plan para mantenerse dentro del viewport y ordenar canales/módulos.
- Integra la mascota de NIVO en launcher, encabezado y vista previa real del widget.
- Muestra estado de NIVO IA dentro del Web Chat y explica su integración con reglas, conocimiento y transferencia humana.
- Corrige contraste y presentación de redes sociales del footer.
- Refuerza alturas consistentes de tarjetas que comparten una misma fila.
- Conserva SEO, Turnstile, analítica, contacto, WhatsApp, permisos y funcionalidades anteriores.


### V2.30.1 · Ajustes finales de cierre premium
- Mantiene Días, Semanas y Meses alineados en una sola fila.
- Separa claramente Conversaciones que requieren atención del bloque superior del Dashboard.
- Permite leer completa la etiqueta de NIVO Web Chat en dos líneas.
- Centra la mascota e imágenes de NIVO en sus contenedores principales.
- Sustituye el textarea simple de características del plan por un editor visual con agregar, ordenar y eliminar.
- Alinea Plan activo y Plan Gratis predeterminado en una misma fila y elimina el rótulo redundante Estado del plan.


### V2.30.2 · Vista ampliada sin cubrir la barra de tareas
- Sustituye el fullscreen nativo del navegador por una vista ampliada interna de ZYNKO.
- Mantiene visibles las pestañas/barra del navegador y la barra de tareas de Windows.
- Conserva la navegación entre módulos dentro de la vista ampliada.
- El mismo botón permite entrar y salir, actualizando icono, tooltip y estado accesible.
- No modifica NIVO, analítica, planes, SEO, contactos ni otras funciones aprobadas.


## V2.30.3 · Pantalla completa nativa
- Restaura el modo de pantalla completa real mediante la Fullscreen API del navegador.
- Oculta la interfaz del navegador y la barra de tareas mientras el modo está activo.
- El mismo botón permite entrar/salir y ESC sale de forma nativa.
- No modifica módulos, NIVO, planes, analítica ni el resto de la interfaz.


## V2.30.7 · NIVO autónomo + WhatsApp universal

- Corrige el acceso flotante de WhatsApp para escritorio, tablet y móvil con apertura robusta y fallback.
- Refuerza NIVO Web Chat para responder inmediatamente cuando NIVO IA está activo.
- Agrega autocorrección de la tabla de reglas de NIVO en runtime para instalaciones existentes.
- Agrega reglas iniciales seguras para la empresa principal cuando NIVO está activo y todavía no existe conocimiento configurado.
- Mejora coincidencia de reglas y conocimiento normalizando acentos y palabras irrelevantes.
- NIVO utiliza únicamente conocimiento aprobado y publicado.
- Mantiene transferencia automática a humano cuando el asistente no tiene información suficiente.
- Agrega respuesta inmediata en el widget, indicador “Escribiendo…” y sondeo de respaldo además de WebSocket.


## V2.31.1
- Bandeja omnicanal premium: acciones alineadas, conversaciones y panel Cliente 360° mejorados.
- Seguimientos con campos y botón Programar estilizados.
- Composer de mensajes reorganizado y responsive.
- NIVO Web Chat refuerza compatibilidad con instalaciones existentes reparando columnas faltantes de bot_profiles en runtime.
- Respuestas de NIVO se distinguen visualmente en la Bandeja.


## V2.31.2

- Bandeja omnicanal final: las tres columnas quedan alineadas desde el mismo borde superior y ocupan la misma altura útil.
- El compositor de respuesta permanece visible, con textarea y botón Enviar de tamaño cómodo.
- Cliente 360° se compacta para evitar scrollbar vertical en escritorio sin eliminar sus funciones.
- Conversaciones reciben una UI más limpia, sin subrayados de enlace y con jerarquía visual consistente.
- Se preservan NIVO, asignación, resumen, categorías, seguimiento, notas y filtros existentes.

## V2.31.3
- Bandeja omnicanal con filtro buscable por categorías.
- Vistas Activas, Resueltas y Archivadas.
- Filtros de atención: sin leer, seguimiento pendiente y espera superior a 15 minutos.
- Acciones de resolver/reabrir, archivar/restaurar y marcado no leído.
- Eliminación segura con autorización por contraseña para Owner/Admin y auditoría.
- Acciones masivas para asignar, resolver y archivar.
- Ctrl/Cmd + Enter para envío rápido.

## V2.31.4
- Cliente 360° con tipografía legible, campos ordenados y scroll vertical únicamente cuando el contenido excede el alto disponible.
- Alineación de las tres columnas de la Bandeja preservada en escritorio.
- Herramientas del compositor centradas vertical y horizontalmente.
- Selector de emojis ampliado con categorías: frecuentes, caras, gestos, corazones y objetos.
- Responsive reforzado para la Bandeja y el selector de emojis.



## V2.31.5
- Login móvil compactado para reducir scroll sin eliminar NIVO, accesos ni opciones del formulario.
- Ajustados espacios, tipografía, campos, botón, tarjeta NIVO y acciones en pantallas pequeñas.


# HISTORIAL CONSOLIDADO DE CAMBIOS


## Historial consolidado — README_ENTREGA_V2306

ZYNKO V2.30.6 - SHOWCASE PRINCIPAL CORREGIDO

Cambios aplicados:
- Se corrigió la sección "Vista del sistema" del sitio principal.
- Se reemplazaron y actualizaron las capturas mostradas en el sitio principal con las imágenes nuevas compartidas por el usuario.
- Se amplió el showcase para incluir capturas actuales de:
  * Dashboard
  * Login
  * Registro
  * Bandeja omnicanal
  * Canales
  * NIVO Web Chat
  * Usuarios
  * NIVO IA
  * Integraciones
  * Suscripciones
  * Correo y notificaciones
  * Configuración y marca
  * Inicio guiado
- Se mantuvo la estructura y funcionalidad existente sin tocar la lógica principal del sistema.

Archivo principal modificado:
- app/Views/home.php


## Historial consolidado — README_ENTREGA_V2307

ZYNKO V2.30.7 - NIVO AUTONOMO + WHATSAPP UNIVERSAL

CAMBIOS FUNCIONALES

1. WHATSAPP PUBLICO
- Se mantiene el botón flotante administrable.
- Se usa URL universal de WhatsApp.
- En móvil abre correctamente WhatsApp mediante navegación directa.
- En escritorio abre WhatsApp Web/navegador en pestaña nueva.
- Se agregó fallback si el navegador bloquea la nueva pestaña.
- Se reforzó z-index, pointer-events y touch-action para evitar bloqueos por superposición.

2. NIVO WEB CHAT + NIVO IA
- Se agregó autocorrección runtime de la tabla nivo_rules para bases existentes.
- Si NIVO está activo en la empresa principal y todavía no tiene reglas ni conocimiento, crea reglas iniciales seguras.
- NIVO responde en la misma petición del visitante; el widget no depende exclusivamente del WebSocket para mostrar la respuesta.
- Se agregó estado visual “Escribiendo…” mientras procesa la respuesta.
- Se mantiene WebSocket y además se agregó polling de respaldo cada 5 segundos.
- Se mejoró la normalización de texto y coincidencia de reglas/conocimiento.
- Solo utiliza conocimiento aprobado y publicado.
- Si no tiene información suficiente, responde de forma segura y puede transferir a humano según la configuración existente.
- La transferencia marca la conversación como pendiente y mantiene la notificación al equipo.

5 MEJORAS SIN COSTO APLICADAS
- Runtime self-healing para NIVO.
- Reglas iniciales seguras.
- Matching local mejorado sin API de pago.
- Respuesta inmediata + polling de respaldo.
- Handoff humano seguro con notificación y estado pendiente.

No requiere proveedor externo de IA ni gasto adicional para estas mejoras locales.


## Historial consolidado — README_ENTREGA_V2308

ZYNKO V2.30.8 - WHATSAPP SIN APERTURA DUPLICADA

Correccion aplicada sobre V2.30.7:
- Se elimina la interceptacion JavaScript que abria WhatsApp manualmente y podia provocar dos navegaciones.
- Se mantiene un unico enlace nativo con target _blank.
- Se cambia el destino al enlace oficial wa.me para un comportamiento mas directo en escritorio, tablet y movil.
- Se conserva el mensaje prellenado configurado desde el panel.
- No se modifica NIVO Web Chat, NIVO IA ni el resto de la logica funcional.

Archivos modificados:
- app/Views/home.php
- public/assets/js/seo-home.js


## Historial consolidado — README_ENTREGA_V2309

ZYNKO V2.30.9 - NIVO CENTRADO EN MOVIL

Cambio aplicado sobre V2.30.8 funcional:
- Se centra la mascota NIVO dentro de la tarjeta NIVO IA en pantallas moviles.
- La mascota mantiene proporciones, animacion y espacio interno limpio.
- Se ajusta el alto del contenedor para evitar que la imagen quede pegada o desalineada.
- Se conserva el layout de escritorio/tablet y toda la funcionalidad existente.
- No se modifica NIVO Web Chat, NIVO IA, WhatsApp, Dashboard ni logica del sistema.


## Historial consolidado — README_ENTREGA_V2310

ZYNKO V2.31.0 - NIVO RESPUESTA INMEDIATA + WHATSAPP CORRECTO

Correcciones:
- NIVO responde de inmediato a saludos desde NIVO Web Chat.
- Si el visitante indicó su nombre, el saludo se personaliza: "¡Hola, Edwin!".
- Se garantiza que el nombre/email del visitante estén disponibles también en mensajes posteriores.
- El runtime del Web Chat crea reglas iniciales seguras si NIVO está activo y no hay reglas ni conocimiento publicado.
- NIVO mantiene reglas, conocimiento aprobado, fallback seguro y transferencia humana.
- Se actualiza last_message_at cuando NIVO responde.
- Número público de WhatsApp corregido a +504 8913-6844 en defaults, panel, portada, schema e instalación.

No se elimina ni reemplaza la configuración administrable existente.


## Historial consolidado — README_ENTREGA_V2311

ZYNKO V2.31.1 — Bandeja Premium + NIVO Runtime

Cambios principales:
- Autoasignar, Asignar y Resumir alineados en una sola fila.
- Cliente 360° legible y responsivo.
- Seguimiento con fecha/hora, motivo y botón Programar completamente estilizados.
- Tarjetas de conversaciones premium con hover, estados y SLA más claros.
- Composer de respuestas reorganizado.
- Respuestas de NIVO identificadas visualmente dentro de la conversación.
- Reparación automática de columnas antiguas de bot_profiles para evitar que NIVO reciba el mensaje pero falle antes de responder.
- Compatibilidad con instalaciones existentes sin migración manual obligatoria.


## Historial consolidado — README_ENTREGA_V2312

ZYNKO V2.31.2 — Bandeja final alineada y compacta

Cambios:
- Las tres tarjetas principales de Bandeja comparten borde superior y altura útil.
- Área de respuesta siempre visible y más cómoda.
- Cliente 360° compacto, sin scrollbar vertical en escritorio.
- Conversaciones sin subrayados y con acabado premium.
- Responsive conservado para escritorio, tablet y móvil.
- No se eliminó funcionalidad existente.


## Historial consolidado — README_ENTREGA_V2313

ZYNKO V2.31.3 — Bandeja Premium · Gestión avanzada de conversaciones

Base: ZYNKO V2.31.2_BANDEJA_ALINEADA_FINAL

Mejoras funcionales incorporadas:
1. Filtro buscable por categorías de contacto directamente desde la Bandeja.
2. Filtro de atención: sin leer, con seguimiento y esperando más de 15 minutos.
3. Ciclo de conversación: resolver/reabrir y archivar/restaurar sin perder historial.
4. Eliminación segura solo para Owner/Admin con reautenticación por contraseña y registro de auditoría; no elimina físicamente mensajes.
5. Gestión de lectura: al abrir explícitamente una conversación se marca como leída y puede volver a marcarse como no leída.

Extras:
- Acciones masivas: asignarme, resolver y archivar.
- Ctrl/Cmd + Enter para enviar mensajes desde el compositor.
- Categorías visibles en cada conversación y búsqueda textual por categoría.
- Conversaciones archivadas se reactivan automáticamente si el visitante vuelve a escribir por NIVO Web Chat.
- Conversaciones resueltas vuelven a estado activo cuando llega un nuevo mensaje web.
- Vistas y filtros se guardan por usuario.

Base de datos:
- conversations: archived_at, deleted_at, deleted_by.
- inbox_preferences: category_id, state_filter, attention_filter.
- conversation_audit_logs: auditoría de acciones sensibles y de ciclo de vida.
- Las columnas/tablas se crean automáticamente por ensureRuntimeSchema() al actualizar una instalación existente.

Seguridad:
- Eliminar conversación requiere rol owner/admin + contraseña actual válida.
- La eliminación es lógica (soft delete) para conservar trazabilidad y evitar pérdida accidental de datos.


## Historial consolidado — README_ENTREGA_V2314

ZYNKO V2.31.4 — Bandeja Premium · Cliente 360 y emojis

Cambios:
- Cliente 360 legible y ordenado.
- Scroll vertical solo cuando el contenido no cabe en la tarjeta.
- Botones del compositor completamente centrados.
- Emoji picker premium por categorías con más opciones.
- Ajustes responsive sin alterar la lógica funcional existente.


## Historial consolidado — README_ENTREGA_V2315

ZYNKO V2.31.5 — Login móvil compacto

- Reduce altura total del login en celulares.
- Mantiene NIVO visible pero en formato compacto.
- Reduce espacios verticales, tamaños y paddings únicamente en móvil.
- Mantiene escritorio y tablet sin cambios funcionales.
- Conserva Recordarme, recuperación, registro y metadatos.


## Historial consolidado — README_CAMBIO_SHOWCASE_V2304

ZYNKO V2.30.4 - ACTUALIZACION DE CAPTURAS DEL SITIO PRINCIPAL

Se reemplazaron las capturas usadas en la seccion "Vista del sistema" del sitio principal con las nuevas imagenes proporcionadas por el usuario.

Capturas publicas actualizadas:
- dashboard.png
- inbox.png
- channels.png
- webchat.png
- nivo-ai.png
- integrations.png
- branding.png
- onboarding.png

Capturas adicionales guardadas para futuro uso:
public/assets/img/showcase/extras/
- login.png
- register.png
- users.png
- billing.png
- email.png
- profile-menu.png
- webchat-bottom.png

No se modifico la logica del sistema.
Solo se actualizaron assets visuales para no afectar el codigo funcional existente.

## V2.31.7 — README único + menú contextual premium
- Se consolidó el historial de entregas en este único `README.md`.
- Se eliminaron los `README_ENTREGA_V*.txt` y `README_CAMBIO_*.txt` de la raíz para evitar acumulación de archivos.
- La Bandeja incorpora menú contextual propio al hacer clic derecho sobre una conversación o sobre la conversación abierta.
- Acciones disponibles: abrir, marcar como no leída, resolver/reabrir, archivar/restaurar y eliminar con autorización cuando el rol lo permite.
- El menú respeta las confirmaciones existentes, auditoría y seguridad de eliminación.
- Diseño responsive, premium y con cierre por clic externo, ESC, scroll, resize o pérdida de foco.


## V2.31.7 — NIVO Web Chat + NIVO IA omnicanal

Esta entrega parte de V2.31.6 y conserva el diseño, la Bandeja, el menú contextual y el README único. Añade configuración avanzada sin depender de proveedores de IA de pago.

### NIVO Web Chat — 10 mejoras funcionales
1. Apertura automática configurable con retraso.
2. Indicador visual de que NIVO está escribiendo.
3. Retardo visual configurable para una respuesta más natural.
4. Hora opcional en cada mensaje.
5. Respuestas rápidas administrables (hasta 8).
6. Memoria local del estado abierto/cerrado del widget.
7. Límite de caracteres por mensaje validado en cliente y servidor.
8. Límite de mensajes por minuto para reducir abuso y spam.
9. Protección contra doble envío mientras una petición está en proceso.
10. Marca NIVO/ZYNKO administrable y cierre con ESC.

### NIVO IA — 10 mejoras funcionales
1. Selección de canales donde NIVO puede responder.
2. Identidad automática como asistente de la empresa activa.
3. Saludo personalizado usando el nombre del cliente.
4. Detección básica ES/EN para saludos y fallbacks.
5. Contexto operativo configurable por cantidad de mensajes.
6. Protección contra respuestas duplicadas consecutivas.
7. Transferencia después de una cantidad configurable de respuestas desconocidas.
8. Máximo configurable de respuestas automáticas por conversación.
9. Pausa configurable entre respuestas automáticas.
10. Pausa automática de NIVO cuando la conversación ya está asignada a una persona.

### Omnicanal
`app/Services/NivoEngine.php` centraliza la evaluación de reglas, conocimiento aprobado, confianza, fallback y transferencia. NIVO Web Chat usa directamente este motor. La API agrega `POST /api.php?r=v1/messages/receive` para que conectores autorizados de WhatsApp, Messenger, Instagram, Telegram, correo o integraciones externas ingresen mensajes al mismo flujo de NIVO. La entrega real hacia proveedores externos sigue requiriendo que cada canal esté conectado y autorizado con su proveedor oficial.

### Administración
El Dashboard incorpora acceso directo **Visitar sitio público**, abierto en una pestaña nueva.


## Documentación SEO consolidada

ZYNKO - SEO TECNICO V2.28.1

IMPLEMENTADO
- /robots.txt físico visible en la raíz y copia en /public.
- /sitemap.xml físico visible en la raíz y copia en /public.
- En Apache, ambos endpoints son servidos dinámicamente por robots.php y sitemap.php para usar el dominio configurado.
- Administrador SEO en Dashboard > Configuración > SEO y posicionamiento.
- URL pública/canonical administrable con fallback a APP_URL.
- Meta title/description, robots, canonical, Open Graph, Twitter Card y JSON-LD.
- Tokens HTML para Google Search Console y Bing Webmaster Tools.
- La portada pública / es indexable.
- Login, registro, recuperación, verificación y dashboard llevan noindex/nofollow.
- APIs, instalador y directorios privados quedan fuera del rastreo.
- site.webmanifest e imagen social 1200x630 incluidos.

ANTES DE PUBLICAR
1. Configura la URL pública desde Dashboard > Configuración > SEO y posicionamiento o APP_URL en .env.
2. Abre /robots.txt y /sitemap.xml en producción.
3. Verifica Search Console preferiblemente con propiedad de Dominio/DNS.
4. Envía /sitemap.xml en Google Search Console y Bing Webmaster Tools.
5. Cuando ZYNKO tenga nuevas páginas públicas reales, agrégalas al generador public/sitemap.php; no agregues pantallas privadas.


## V2.31.8 — NIVO IA híbrida + OpenAI opcional por plan

Esta versión conserva NIVO interno como motor principal. OpenAI nunca sustituye la primera fase: reglas y conocimiento aprobado se evalúan primero; únicamente cuando NIVO interno no resuelve y todos los controles están habilitados se usa OpenAI como segunda fase.

### Flujo funcional
1. NIVO interno evalúa saludo, transferencia, reglas y conocimiento aprobado.
2. Si existe respuesta local suficiente, no se consume OpenAI.
3. Si el motor local no puede resolver, ZYNKO verifica proveedor global, plan, tenant, canal, límite mensual y presupuesto global.
4. Solo entonces llama a OpenAI mediante Responses API.
5. La respuesta se presenta al cliente como NIVO, la IA de la empresa activa.
6. Si OpenAI no responde o está deshabilitado, se conserva el fallback y la transferencia humana de NIVO.

### Administración de OpenAI
- Conexión global exclusiva del administrador principal.
- API Key cifrada con APP_KEY; nunca se muestra completa al cliente.
- Admin API Key opcional únicamente para consultar costos reales de la organización.
- Botón de prueba de conexión.
- Modelo configurable; valor inicial `gpt-6-luna`.
- Presupuesto mensual global configurable.
- Tarifas por millón de tokens editables para mantener el cálculo de costo actualizado.
- Registro local de tokens de entrada, tokens cacheados, tokens de salida, solicitudes y costo estimado.
- Consulta opcional del costo real mensual de la organización mediante la API de Costs cuando existe Admin API Key.
- El “saldo operativo” es presupuesto configurado menos consumo; no se presenta como saldo prepago oficial de OpenAI.

### Control por empresa y canal
- Cada empresa puede tener el respaldo externo apagado aunque OpenAI esté conectado globalmente.
- Cada plan decide si incluye o no IA externa.
- Cada plan puede limitar tokens mensuales.
- Cada plan decide en qué canales puede utilizarse IA externa.
- Cada empresa selecciona dentro de los canales permitidos dónde activar el respaldo.
- El cliente nunca recibe ni visualiza las credenciales del proveedor.

### Planes
- Nuevo control “Incluir IA externa” al crear o editar un plan.
- Límite opcional de tokens mensuales por plan.
- Canales de IA externa configurables por plan.
- Al incluir IA externa, NIVO · IA queda habilitado automáticamente como módulo del plan.
- El Plan Gratis fuerza IA externa desactivada.
- Las tarjetas de planes muestran cuándo OpenAI está incluido.
- El catálogo de planes se puede descargar en CSV desde Suscripciones.

### Base de datos
- `subscription_plans.external_ai_included`
- `subscription_plans.external_ai_monthly_tokens`
- `subscription_plans.external_ai_channels_json`
- nueva tabla `ai_provider_settings`
- nueva tabla `tenant_ai_settings`
- nueva tabla `ai_usage_logs`

### Seguridad y compatibilidad
- Las credenciales se cifran con AES-256-GCM usando APP_KEY.
- OpenAI es fallback, no reemplazo del motor local.
- Si el proveedor falla, NIVO continúa con su fallback/transferencia actual.
- El motor compartido aplica el mismo flujo a Web Chat y a mensajes omnicanal recibidos por la API.
- No se modifica la estructura visual general del proyecto; los controles nuevos reutilizan los estilos actuales.


## V2.31.9 — Administración central de empresas

- Nueva opción **Empresas** exclusiva para el administrador principal de la plataforma.
- Listado visual con DIVs, búsqueda y filtros por estado y plan; no se usan tablas HTML.
- Las empresas creadas desde el registro público aparecen automáticamente en este módulo.
- Creación manual de empresa con usuario Owner, plan inicial y NIVO Web Chat preparado.
- Vista consolidada de todos los usuarios de cada empresa sin mezclar sus datos con la gestión normal del tenant.
- Gestión segura de rol y estado de los usuarios desde la ficha de la empresa.
- Restablecimiento mediante contraseña temporal de una sola visualización; las contraseñas actuales nunca se muestran porque ZYNKO almacena hashes.
- Modo asistencia para ingresar temporalmente como un usuario de otra empresa y regresar a la administración principal desde una barra visible.
- Asignación de planes directamente a la empresa; todos los usuarios heredan las capacidades y límites del plan.
- Auditoría de acciones administrativas sensibles mediante `platform_admin_audit`.
- El snippet de NIVO Web Chat se muestra y copia con barras `/` normales, sin secuencias `\/` innecesarias.
- Se mantiene un único `README.md` para el historial de versiones.

### API externa por empresa

Cada empresa usa su propia API Key y solo puede operar sobre sus canales. Flujo recomendado:

1. `GET /api.php?r=v1/channels` para obtener `channel_id`, tipo y estado.
2. `POST /api.php?r=v1/messages/receive` para registrar mensajes entrantes desde WhatsApp, Messenger, Instagram u otro conector y ejecutar NIVO.
3. `POST /api.php?r=v1/messages/send` para registrar un mensaje saliente en ZYNKO.
4. Enviar `Authorization: Bearer TU_CLAVE` y un `Idempotency-Key` único por mensaje para evitar duplicados.

Las identidades externas se guardan en `contact_identities`, por lo que Messenger/Instagram no se mezclan con correos o teléfonos. La entrega final hacia Meta/Telegram/etc. depende del conector/proveedor autorizado del canal; la API de ZYNKO no inventa una autorización del proveedor.


## V2.31.11 — Documentación, exportaciones y limpieza visual

### Cómo usar ZYNKO desde otro sistema

Primero ve a **Integraciones → Crear clave API**. La llave pertenece únicamente a la empresa que la genera.

La URL de los ejemplos dentro de ZYNKO se construye automáticamente con la URL publicada del sistema, por lo que no es necesario cambiar manualmente el dominio al mover ZYNKO entre ambientes.

#### Consultar canales

```bash
curl "https://TU-ZYNKO.com/api.php?r=v1/channels" \
  -H "Authorization: Bearer TU_CLAVE_API"
```

Respuesta de ejemplo:

```json
{
  "ok": true,
  "data": {
    "channels": [
      {
        "id": 12,
        "type": "whatsapp",
        "name": "WhatsApp Business",
        "status": "connected"
      }
    ]
  }
}
```

#### Ingresar un mensaje recibido desde IZZY, CAMI u otro sistema

```bash
curl -X POST "https://TU-ZYNKO.com/api.php?r=v1/messages/receive" \
  -H "Authorization: Bearer TU_CLAVE_API" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: whatsapp-msg-987654" \
  -d '{
    "channel_id": 12,
    "from": "+50499999999",
    "contact_name": "Juan Pérez",
    "message": "Hola, necesito información"
  }'
```

Flujo:

**Sistema externo → ZYNKO → canal correspondiente → conversación → NIVO IA → respuesta/handoff.**

Respuesta de NIVO:

```json
{
  "nivo": {
    "reply": "¡Hola Juan! Soy NIVO...",
    "handoff": false,
    "source": "rule"
  }
}
```

#### Enviar un mensaje saliente

```bash
curl -X POST "https://TU-ZYNKO.com/api.php?r=v1/messages/send" \
  -H "Authorization: Bearer TU_CLAVE_API" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: izzy-000123" \
  -d '{
    "channel_id": 12,
    "to": "+50499999999",
    "contact_name": "Juan Pérez",
    "message": "Su solicitud ya fue procesada."
  }'
```

`messages/send` registra el mensaje para el canal correspondiente. La entrega final por WhatsApp, Messenger, Instagram u otro proveedor requiere que el conector oficial de ese canal esté autorizado y operativo.

### Cambios visuales y funcionales de esta versión

- Se agregó una sección pública **Documentación e integración** al sitio web de ZYNKO.
- La documentación pública y la documentación del panel usan automáticamente la URL publicada de ZYNKO.
- Empresas incluye descarga directa del directorio en **Excel** y **PDF**.
- El directorio de Empresas permanece visible aunque no existan registros e incluye búsqueda, botón X, cantidad de registros y paginación.
- Se ajustó la alineación del botón **Restablecer** en la Bandeja omnicanal.
- Se reorganizó **Experiencia avanzada** de NIVO Web Chat con márgenes, separación y tarjetas internas uniformes.
- Se reorganizó **NIVO IA omnicanal** para mantener el mismo lenguaje visual limpio del bloque Comportamiento de NIVO.
- Se mantuvo intacta la lógica funcional existente de NIVO, Web Chat, empresas, planes, API y bandeja.

## Corrección final NIVO Web Chat

- Se corrigió la salida visible de JavaScript al final de NIVO Web Chat protegiendo el snippet incrustado dentro del script de administración.
- La mascota NIVO del panel lateral ahora tiene una animación visible y continua con movimiento, sombra y halo sutil.
- El espacio lateral de NIVO Web Chat ahora incluye estado del motor NIVO IA, sitios autorizados, actividad del canal y recordatorio de operación centralizada.
- Se mantiene intacta la lógica de instalaciones, copia de snippet, edición de dominios, autorización/desautorización, respuestas rápidas y configuración del widget.


## V2.31.13 · Experiencia visual y edición
- Preferencia de pantalla completa persistente: ZYNKO intenta restaurarla al navegar y muestra un control de reingreso cuando el navegador exige interacción.
- Acceso **Visitar sitio** disponible desde el header superior.
- Todos los textarea visibles usan un RTE reutilizable con almacenamiento plano compatible para no alterar la lógica existente.
- Rail de NIVO Web Chat balanceado con Vista previa, NIVO Web Chat + NIVO IA y Centro operativo dentro del alto de Diseño y comportamiento.
- Mascota NIVO animada y Centro operativo con interacción visual limpia.
- Hover premium y sutil aplicado a cards principales del panel.


## ZYNKO V2.31.15
- Corrige la documentación API del panel y del sitio público para reflejar la estructura real de `messages/receive` (`data.nivo`) y el estado `queued`/HTTP 202 de `messages/send`.
- Los ejemplos del panel de Integraciones toman `seo_site_url` o `APP_URL` como URL publicada, en lugar del host temporal del navegador.
- Fullscreen persistente: al navegar entre módulos, el documento principal permanece en pantalla completa y la navegación ocurre dentro de un iframe del mismo origen. Al salir con ESC o con el botón, se conserva el módulo actual.


## V2.31.16 · Integraciones compactas y copiar
- Reorganiza la documentación de Integraciones para eliminar espacios muertos dentro de las tarjetas.
- Agrega botones Copiar en comandos cURL y respuestas de ejemplo dentro del panel y sitio público.
- Todas estas acciones muestran confirmación mediante showNotify.
- Alinea Restablecer con Canal, Asignación, Prioridad, Categoría, Vista y Atención en Bandeja.


## V2.31.17
- Reglas de NIVO ahora pueden editarse además de eliminarse.
- Copiado global con confirmación visual y fallback compatible con HTTP/local.
- NIVO Web Chat confirma la copia del código de inserción y mejora su encabezado.
- Directorio de Usuarios agrega exportación ejecutiva Excel/PDF.
- Exportes de Empresas/Usuarios reciben formato visual administrativo mejorado.
- Se normaliza el espaciado de iconos en uploads, búsquedas y acciones.


## ZYNKO V2.31.18 — Ajustes NIVO IA
- Movimiento premium al hover en las 4 etapas de NIVO IA + Web Chat.
- Switch de OpenAI alineado horizontalmente con su label y descripción.
- Probar NIVO reorganizado: solución en primera fila, RTE en segunda y resultado debajo.
- Notificaciones showNotify reforzadas para acciones de NIVO y prueba del asistente.
- Switch de Regla activa alineado al inicio del modal.


## ZYNKO V2.31.19 — NIVO IA, directorios, Open Graph, Integraciones y Planes

- IA externa: animación premium en flujo NIVO → OpenAI → Humano y tarjetas de métricas.
- Usuarios: toolbar en una sola línea en escritorio, vistas Detalle/Miniatura, búsqueda con estado “No hay resultados”, Excel/PDF conservados.
- Empresas: vistas Detalle/Miniatura persistentes y estado vacío profesional conservando filtros, paginación y exportaciones.
- Configuración SEO: Locale Open Graph convertido a Select2 con idiomas/regiones comunes y ayuda contextual; Honduras usa es_HN.
- Integraciones: movimiento premium en las cuatro tarjetas de “Cómo usar ZYNKO desde otro sistema”.
- Suscripciones: Planes disponibles distribuye 1 plan al 100%, 2 planes al 50% y hasta 3 planes por fila; responsive en resoluciones menores.
- Sin cambios adicionales de base de datos.


## V2.31.20 · Dashboard, directorios y canales premium
- Corrige separación visual en todos los inputs de archivo y fotografías.
- Agrega KPIs y filtros al directorio de Usuarios, respetando KPI → filtros → directorio.
- Mantiene Mostrar X registros con Todos (total), búsqueda, Detalle/Miniatura y Excel/PDF en una línea de directorio.
- Mejora los menús de acciones en vistas miniatura para Usuarios y Empresas.
- Rediseña el bloque Canales del Dashboard y agrega iconos a Días/Semanas/Meses.
- Uniforma los cards de Canales a tres por fila en escritorio y mayor altura para leer correctamente su contenido.


## V2.31.21 · Excel real, PDF por vista y directorios consistentes

- Los reportes Excel de Usuarios, Empresas y Planes ahora son archivos XLSX Open XML reales, eliminando la advertencia de formato/extensión de Microsoft Excel.
- La generación XLSX no depende de librerías externas ni de ZipArchive: ZYNKO construye un paquete Office Open XML válido directamente desde PHP.
- Los PDF de Usuarios, Empresas y Planes respetan la vista activa: Detalle genera tarjetas amplias y Miniatura genera un directorio compacto en dos columnas.
- Los reportes PDF incluyen encabezado corporativo, total, fecha/hora, paginación y pie administrativo.
- Suscripciones adopta la misma regla visual del directorio de Usuarios: KPI → filtros → listado, con Mostrar X/Todos, búsqueda, Detalle/Miniatura, Excel y PDF.
- La vista Miniatura de Usuarios y Empresas muestra un botón visible «Acciones» con dropdown, sin depender del icono de tres puntos.
- Se mantiene la vista seleccionada y los enlaces PDF se sincronizan con Detalle/Miniatura.


## ZYNKO V2.31.23
- Refuerza visualmente la advertencia de autorización oficial de Meta en Canales.
- Agrega compatibilidad automática para columnas de empresas en instalaciones existentes.
- Corrige los XLSX para incluir estilos predeterminados completos y evitar reparaciones de Excel.
- Mantiene exportaciones de Empresas/Usuarios/Planes compatibles incluso cuando los listados están vacíos.

## V2.31.23 · Usuarios: XLSX estable y menú de acciones inteligente

- Se reemplazó la generación XLSX frágil por un paquete Office Open XML válido y compatible con Excel.
- Si PHP tiene `ZipArchive`, ZYNKO lo utiliza; si no está disponible, usa el empaquetador ZIP interno sin cambiar el formato XLSX.
- Se validó el libro generado con un lector Office Open XML y contiene correctamente encabezados y filas de usuarios.
- La vista Miniatura de Usuarios mantiene el mismo dropdown de acciones de la vista Detalle.
- El menú calcula automáticamente si debe abrir arriba o abajo y ajusta su posición horizontal al viewport para no cortarse.
- No hay cambios de estructura de base de datos en esta versión; `schema.sql` solo actualiza la versión del proyecto.


## V2.31.24 · Empresas + Usuarios + XLSX estable
- Empresas: Vista Detalle ahora es un registro de ancho completo; Miniatura conserva tarjetas compactas.
- Usuarios y Empresas: menú Acciones unificado con posicionamiento inteligente arriba/abajo/izquierda/derecha según espacio disponible.
- Excel: corregido el empaquetado XLSX con ZipArchive usando addFromString; elimina archivos vacíos/corruptos en servidores con extensión ZIP activa.
- No hay cambios estructurales de base de datos en esta versión. El script de actualización no requiere ejecución.


## V2.31.25 — Usuarios y Empresas: acciones inteligentes
- Unifica el tamaño del botón Acciones en vista detalle y miniatura.
- Los menús se renderizan como portal sobre el documento para evitar cortes por overflow o scroll del directorio.
- Calcula apertura arriba, abajo, izquierda o derecha según el espacio disponible.
- Refuerza la vista detalle de Empresas a ancho completo.
- No requiere cambios de base de datos.

## V2.31.25 · Directorios uniformes (Usuarios, Empresas y Suscripciones)
- Empresas: la vista Detalle ahora usa una fila completa y horizontal con empresa, propietario, indicadores, plan y acciones claramente separados.
- Empresas: la vista Miniatura mantiene tarjetas compactas, con el mismo botón Acciones de tamaño fijo usado en Detalle.
- Empresas: se corrigió el icono de Editar empresa por uno compatible con Font Awesome disponible en el proyecto.
- Usuarios y Empresas: los menús Acciones se renderizan como menús flotantes sobre el documento y calculan automáticamente si deben abrir arriba, abajo, izquierda o derecha, sin crear scroll dentro del directorio.
- Suscripciones: la vista Detalle ahora es un directorio horizontal real; la vista Miniatura conserva tarjetas de hasta 3 por fila.
- Suscripciones: las acciones del plan se agrupan en el mismo patrón Acciones usado por Usuarios y Empresas.
- No requiere cambios de base de datos.


## V2.31.26 · Directorios uniformes + acceso directo a Canales

- Empresas y Suscripciones mantienen el mismo patrón visual de Usuarios: vista Detalle realmente amplia, vista Miniatura compacta y menú Acciones consistente.
- Los menús de acciones se posicionan de forma inteligente según el espacio disponible para evitar cortes por overflow.
- El indicador `Canales conectados/configurados · Auto` del header ahora es un acceso directo al módulo Canales.
- El valor mostrado representa `canales con estado connected / total de canales configurados` para la empresa activa.
- Se mantiene el esquema responsive, estilos premium y comportamiento de fullscreen ya existente.

## V2.31.27 · Planes públicos dinámicos + etiqueta editable de NIVO Web Chat

- El sitio público deja de tener una tarjeta de Plan Gratis hardcodeada.
- Todos los planes activos creados desde Suscripciones se publican automáticamente en el sitio, con Plan Gratis primero y los demás ordenados por precio.
- El catálogo público se adapta a 1, 2 o 3+ planes con tarjetas responsive, límites, características y CTA consistentes.
- Los metadatos Schema.org del software exponen también el catálogo activo de planes.
- La etiqueta flotante de NIVO Web Chat deja de ser estática: el administrador puede cambiar todo el texto.
- Se agregó un editor compacto con negrita parcial o total y vista previa en tiempo real.
- El widget interpreta únicamente formato seguro de negrita y conserva aislamiento Shadow DOM.
- El valor predeterminado queda como **NIVO Web Chat** · ¿Necesitas ayuda?, manteniendo compatibilidad con configuraciones anteriores.
- No requiere cambios estructurales de base de datos.


## V2.31.29 · Planes públicos premium + etiqueta NIVO con rich text
- Cuando existe un único plan activo, el sitio público conserva la composición amplia original: contenido a la izquierda y tarjeta del plan a la derecha.
- Cuando existen dos o más planes activos, se muestran en una cuadrícula premium de 2 columnas; los planes adicionales continúan en filas de 2.
- En móvil los planes pasan automáticamente a una sola columna.
- Se evita repetir características como “NIVO Web Chat incluido” cuando ya vienen dentro del catálogo del plan.
- El texto flotante de NIVO Web Chat ahora tiene editor enriquecido compacto con negrita, cursiva, limpiar formato y hasta 2 líneas.
- La vista previa del administrador interpreta el formato real en lugar de mostrar los marcadores de formato.
- La etiqueta pública de NIVO responde al hover con desplazamiento y realce suave, manteniendo compatibilidad con reduced-motion.
- `launcher_label` se amplía a `VARCHAR(255)` y el `ZYNKO_UPDATE_DB_COMPLETO.sql` acumulativo único queda actualizado con todos los cambios vigentes.


## V2.31.29 · Sitio público limpio + UPDATE_DB estable

- Se eliminan del sitio público mensajes internos sobre Suscripciones, catálogo y sincronización administrativa.
- La sección pública de planes usa únicamente textos orientados a visitantes y clientes.
- El archivo acumulativo de actualización de base de datos queda con nombre estable `ZYNKO_UPDATE_DB_COMPLETO.sql`, sin versión en el nombre.
- `database/schema.sql` permanece como esquema completo de instalación y se mantiene actualizado con la estructura vigente.


## V2.31.30 · Preferencias sincronizadas + UPDATE_DB portable

- Las preferencias visuales que antes dependían solo del navegador ahora se sincronizan por usuario en `user_preferences.ui_preferences_json`.
- Se sincronizan entre equipos las vistas Detalle/Miniatura de Usuarios, Empresas y Suscripciones, además del estado contraído/oculto del menú lateral.
- El tema claro/oscuro continúa persistido por usuario en base de datos; `localStorage` queda únicamente como caché/fallback para evitar parpadeos durante la carga.
- Se agregó una acción genérica y validada `ui_preference` para futuras preferencias de interfaz sin crear cookies ni columnas por cada ajuste.
- `ZYNKO_UPDATE_DB_COMPLETO.sql` ya no contiene `USE zynko` ni referencias rígidas al nombre de la base: trabaja con `DATABASE()` y la base que esté seleccionada en phpMyAdmin/cliente SQL.
- Esto corrige el error `#1044 - Access denied ... to database 'zynko'` en producción cuando el hosting usa un nombre como `esmultiservicios_zynko`.
- `database/schema.sql` permanece como esquema completo de instalación limpia y el UPDATE acumulativo conserva solo los cambios necesarios para instalaciones existentes.


## V2.31.31 · Responsive integral de botones

- Corregidos los botones Copiar del sitio público para que el texto permanezca visible en móvil.
- Los encabezados de ejemplos de API se reorganizan sin romper el diseño en pantallas angostas.
- En teléfonos muy estrechos, las acciones pasan a ancho completo para conservar icono y texto.
- Aplicada la misma corrección a la documentación de Integraciones del panel administrativo.
- Reforzado el comportamiento responsive de grupos de acciones, modales y barras de herramientas sin cambiar la estructura visual de escritorio.
- Se conserva `ZYNKO_UPDATE_DB_COMPLETO.sql` como único UPDATE acumulativo y `database/schema.sql` como esquema completo.


## V2.31.33 · Responsive final de botones Copiar
- Sitio público: los encabezados de documentación ya no compiten por espacio con el botón Copiar en móvil.
- En móvil, el título conserva su ancho completo y el botón Copiar pasa a una línea propia alineado a la derecha.
- Las respuestas de ejemplo conservan el patrón compacto: texto a la izquierda y botón Copiar completo a la derecha.
- Admin / Integraciones: se aplica el mismo comportamiento responsive sin ocultar el texto del botón.
- Se reforzó la adaptación para pantallas de 360 px y menores sin alterar desktop, tablet ni la lógica de copiado.


## V2.31.36 — Cumplimiento real de límites por plan

Los límites configurados en Suscripciones se aplican en backend, no solo en la interfaz: módulos, canales permitidos, conexiones externas, sitios autorizados de NIVO Web Chat, chats diarios/mensuales, API y NIVO IA. Al cambiar o reducir un plan, ZYNKO reaplica los permisos del tenant y desconecta o desactiva capacidades que ya no están incluidas. La API valida plan y estado de suscripción en cada solicitud.

### V2.31.36 · Notificaciones administrativas transaccionales
- Se centralizaron correos para cambios materiales de cuenta/configuración: empresa, usuarios administrados, NIVO IA, API, webhooks, configuración, NIVO Web Chat y conocimiento de NIVO.
- Cada cambio material notifica al propietario de la empresa afectada y al administrador principal de ZYNKO, evitando duplicados cuando ambos correos coinciden.
- No se envían correos por acciones operativas de alta frecuencia (mensajes, asignaciones, búsquedas, notas, preferencias o consultas) para evitar spam.
- Los correos nunca incluyen secretos, contraseñas temporales, claves API ni secretos de webhook.
- Se conserva el flujo especializado existente para solicitudes/asignaciones de plan, altas de cuenta y eventos de seguridad.
