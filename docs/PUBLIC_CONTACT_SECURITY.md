# Formulario público de contacto · validación y anti-spam

## Objetivo

El formulario público valida el correo antes de aceptar la consulta sin enviar correos de confirmación al visitante.

## Flujo

1. Validación HTML5 y JavaScript.
2. Validación backend obligatoria.
3. Detección de errores frecuentes en dominios y sugerencia explícita.
4. Bloqueo de dominios temporales/desechables.
5. Consulta de registros MX cuando el DNS está disponible.
6. Verificador externo opcional con fallback local.
7. Honeypot, rate limit por sesión/IP y puntuación anti-spam.
8. Cloudflare Turnstile opcional.
9. Si todo es válido, se registra la consulta y se envía únicamente al administrador.

## Validación de correo

Los estados visibles son:

- `Correo válido`.
- `Ingresa un correo electrónico válido. Ejemplo: nombre@empresa.com`.
- `El dominio de este correo no parece válido. Revisa la dirección e inténtalo nuevamente.`.
- `Utiliza un correo electrónico permanente para continuar.`.
- `¿Quisiste escribir nombre@gmail.com?` con acción para usar la sugerencia.

La sugerencia nunca modifica automáticamente el correo del usuario.

## DNS / MX

`PublicEmailValidationService` consulta registros MX. Si el resolver DNS informa un fallo temporal del servidor, ZYNKO aplica fallback y vuelve a validar al enviar en lugar de bloquear automáticamente a un cliente legítimo.

## API externa opcional

Puede configurarse desde `Configuración > Servidor y dominio · .env`:

- `EMAIL_VALIDATION_API_URL`
- `EMAIL_VALIDATION_API_KEY`
- `EMAIL_VALIDATION_API_TIMEOUT`

La URL puede incluir `{email}`. Si no lo incluye, ZYNKO agrega `?email=` automáticamente.

La API Key se envía como `Authorization: Bearer` y `X-API-Key`. Si el proveedor externo no responde, ZYNKO conserva la validación local y no bloquea por esa caída.

## Anti-spam

Se aplican:

- honeypot invisible;
- máximo de envíos por sesión;
- máximo de envíos por IP;
- detección de mensajes duplicados;
- análisis de enlaces excesivos;
- detección de contenido típico de spam;
- detección de ráfagas/envíos demasiado rápidos;
- detección de user-agents automatizados;
- Cloudflare Turnstile cuando esté habilitado.

Los eventos de seguridad se registran en `public_contact_security_events` usando hash de IP para auditoría anti-spam.

## Correos enviados

El formulario **no envía ningún correo de confirmación al visitante**. Solo se envía la consulta al destinatario administrativo configurado en ZYNKO.
