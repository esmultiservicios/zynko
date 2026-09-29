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

