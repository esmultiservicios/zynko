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
