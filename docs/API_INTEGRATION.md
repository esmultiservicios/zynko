# ZYNKO API v1 — integración de sistemas externos

ZYNKO permite que IZZY, CAMI u otro sistema solicite envíos sin conocer las credenciales de Meta.

## Seguridad
1. En **Integraciones**, crea una clave distinta por sistema.
2. La clave completa se muestra una sola vez. ZYNKO almacena únicamente SHA-256.
3. Enviar `Authorization: Bearer <clave>`.
4. Para operaciones de envío, enviar también `Idempotency-Key: <uuid-o-id-unico>` para impedir duplicados.
5. Una clave revocada deja de funcionar inmediatamente.
6. El tenant se obtiene de la clave; el cliente no puede elegir otro `tenant_id` en el payload.

## Enviar mensaje
`POST /api.php?r=v1/messages/send`

```json
{
  "channel_id": 12,
  "to": "+50499990000",
  "contact_name": "Cliente",
  "message": "Su factura está disponible."
}
```

El canal debe pertenecer al tenant autenticado y estar `connected`. ZYNKO crea/reutiliza contacto y conversación y deja el mensaje en `queued`. La entrega real al proveedor se realiza únicamente cuando la integración oficial del canal está autorizada.

## Contexto de NIVO
`POST /api.php?r=v1/nivo/context`

```json
{"query":"¿Cuál es la política de devoluciones?"}
```

Devuelve hasta cinco fuentes autorizadas relevantes. No inventa una respuesta de IA: la generación requiere configurar un proveedor/modelo en NIVO.

## Respuestas importantes
- `401`: clave faltante, inválida o revocada.
- `403`: scope o plan sin la función requerida.
- `409`: canal todavía no conectado/autorizado.
- `422`: parámetros incompletos.
- `202`: mensaje aceptado y puesto en cola.

## CORS y consumo desde aplicaciones web

Desde ZYNKO V2.31.52, la API pública responde preflight `OPTIONS` antes de autenticar la petición real y admite los headers `Authorization`, `Content-Type`, `Accept`, `Idempotency-Key` y `X-Requested-With`.

CORS únicamente habilita al navegador para realizar la solicitud. La seguridad continúa dependiendo de la API key, sus scopes, el plan activo y las validaciones de cada endpoint. No se utilizan cookies ni `Access-Control-Allow-Credentials` en la API pública.

Para NIVO Web Chat, el widget evita preflight innecesario enviando una solicitud CORS simple. El backend sigue validando estrictamente `installation_key` + dominio autorizado antes de entregar datos o aceptar mensajes. Por ello cada código de instalación continúa siendo exclusivo del sitio autorizado correspondiente.

Los webhooks de Meta / WhatsApp / Messenger son comunicaciones servidor-a-servidor y no dependen de CORS del navegador.
