# Inbox y seguridad | 2.31.153

## Uso
- Nueva conversación: elegir cuenta conectada, país (Honduras por defecto) y número local, o una identidad del canal ya conocida. Seleccionar si se desea guardar el contacto, y abrir el chat. WhatsApp oficial puede exigir plantilla aprobada cuando no existe ventana de atención. No se promete envío libre a números nuevos.
- El contacto no guardado necesita una identidad y fila técnica para entregar el mensaje, asociar respuestas y mantener historial. No se marca como guardado en la agenda del Inbox. Cliente 360 permite guardarlo después; los registros heredados sin marca se pueden guardar explícitamente. El nombre/alias de ficha no altera la identidad oficial del proveedor.
- Selector de países: 246 países y territorios con prefijo telefónico. Los planes de numeración compartidos pueden requerir selección manual del país al editar. El servidor valida formato internacional y longitud hondureña, no la existencia real de cualquier número mundial. Para los conectores sociales se listan hasta 2000 identidades conocidas de la empresa; no se permite iniciar mensajes a identificadores arbitrarios.
- Vista multimedia: miniaturas, icono PDF, visor, descarga, archivos de la conversación, búsqueda local en mensajes cargados, copiado y hora. El visor PDF depende del soporte del navegador; la descarga permanece disponible. Las URL externas no se descargan en el servidor para previsualizar.
- Se reutilizan las restricciones y el despachador de canales. Threads ya está en el catálogo v022 y sigue sin conector de mensajes directos.

## Seguridad revisada en el código
- Las consultas nuevas usan parámetros PDO y filtros de empresa. Se usa la identidad del proveedor, no el teléfono editable, como destino del chat. Se reutilizan conversaciones activas y se serializa la creación bajo bloqueo de empresa.
- Se verifica CSRF en crear conversación, guardar contacto y enviar desde Inbox. Se conserva CSRF de mensajes estructurados. Se rechazan conversaciones eliminadas y canales no autorizados. Nuevo visor de adjuntos usa sesión, pertenencia a empresa, validación de ruta, MIME y `nosniff`.
- Se corrige HTML sin escape en nombres de adjuntos y resumen local, y se escapan atributos del render en tiempo real. Se evita duplicación por clic concurrente en el mismo formulario; no equivale a idempotencia global ante reintentos de red.
- Controles existentes observados: PDO preparado en flujos revisados, sesiones estrictas/HttpOnly/SameSite, firmas de webhooks, AES-GCM de credenciales, políticas de API y alcance por empresa.

## Fase 3 recomendada (no completada)
Auditar exhaustivamente SQL dinámico, XSS almacenado y DOM, CSRF de todos los POST, autorización de cada recurso y rol, aislamiento entre empresas, rutas públicas de adjuntos antiguos, almacenamiento privado o URLs firmadas compatibles con proveedores, SSRF y salidas de red, límites de carga y rate limiting, sesiones y revocación, secretos y registros, dependencias, CSP, pruebas de integración y penetración. Los adjuntos históricos de `public/uploads` siguen teniendo rutas directas que requieren una estrategia compatible con proveedores; el visor autenticado no elimina ese acceso. Revisar scripts inline con JSON de datos de usuario, por ejemplo en el encabezado, y los demás usos de innerHTML. No se declara la plataforma libre de vulnerabilidades.

No hay nuevas tablas. Se utiliza contacts.custom_fields y las identidades existentes. Actualizar los archivos juntos, conservar copia/backup, abrir sesión nueva o recargar para incluir CSRF en los formularios. Pruebas de producción necesarias: MariaDB, sesión real, envíos por canal, historial y visualización en móvil.
