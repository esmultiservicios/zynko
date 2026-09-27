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
