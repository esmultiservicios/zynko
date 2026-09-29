# ZYNKO — Plataforma SaaS omnicanal

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
