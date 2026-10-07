# Correo entrante en ZYNKO

ZYNKO separa envío y recepción:

- SMTP: envío saliente.
- Microsoft Graph: envío saliente o recepción entrante, según permisos.
- IMAP: recepción entrante estándar.

Para IMAP se requiere servidor, puerto, seguridad, usuario, App Password/contraseña y carpeta (normalmente INBOX). El servidor PHP debe tener habilitada la extensión IMAP.

Para Microsoft Graph entrante se requiere Tenant ID, Client ID, Client Secret, buzón y permiso de aplicación `Mail.Read` con consentimiento administrativo.

La opción **Permitir vincular** del canal Correo solo queda disponible después de que la recepción entrante esté activa y una prueba real resulte correcta.

Para procesar correo entrante periódicamente se puede programar:

`php /ruta/al/proyecto/bin/email-inbound-poll.php`

Una frecuencia de 5 minutos es adecuada para hosting cPanel sin webhooks push.
