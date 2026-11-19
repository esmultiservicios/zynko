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

## Remitentes de salida por propósito (2.31.144)
Con Microsoft Graph activo, el administrador puede asignar notificaciones, acceso y seguridad, suscripciones, y soporte. Un campo vacío o pendiente de prueba conserva el buzón principal. La prueba aceptada por Graph habilita el remitente; la entrega efectiva debe revisarse en el buzón receptor. Al cambiar credenciales Graph se requieren nuevas pruebas. Los buzones deben existir y estar autorizados para Mail.Send; pruebe cada uno antes de usarlo. SMTP usa su propio remitente y no aplica estas rutas. La recepción es independiente y continúa usando el buzón principal o IMAP.
