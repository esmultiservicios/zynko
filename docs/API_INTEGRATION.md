# ZYNKO API v1 — integración segura de sistemas externos

ZYNKO permite que IZZY, CAMI u otro sistema consuma la API sin conocer las credenciales privadas de Meta ni compartir secretos entre empresas. Cada credencial pertenece a un tenant y puede tener su propia política de seguridad.

## 1. Crear una clave API

En **Integraciones → Crear clave API** crea una clave diferente por sistema o ambiente. La clave completa se muestra una sola vez; ZYNKO almacena únicamente su hash.

Configura según el caso:

- **Orígenes permitidos:** dominios web desde los que se aceptará consumo en navegador.
- **IP / CIDR permitidos:** restringe consumo server-to-server a servidores o redes concretas.
- **HTTPS obligatorio:** recomendado para producción.
- **Rate limit:** máximo de solicitudes por minuto para esa credencial.
- **Vencimiento:** opcional.
- **Scopes:** `channels:read`, `messages:send`, `messages:receive`, `nivo:context`.

Una política puede editarse después sin regenerar la clave. Una clave revocada deja de funcionar inmediatamente.

## 2. Autenticación

Enviar siempre:

```http
Authorization: Bearer TU_CLAVE_API
```

El tenant se obtiene de la clave. El cliente nunca puede seleccionar otro `tenant_id` en el payload.

Para operaciones que puedan repetirse por error o reintento, usa:

```http
Idempotency-Key: identificador-unico
```

## 3. Consultar canales

`GET /api.php?r=v1/channels`

Requiere scope `channels:read`. La respuesta contiene únicamente canales de la empresa autenticada.

## 4. Enviar mensaje

`POST /api.php?r=v1/messages/send`

```json
{
  "channel_id": 12,
  "to": "+50499990000",
  "contact_name": "Cliente",
  "message": "Su factura está disponible."
}
```

Requiere scope `messages:send`. El canal debe pertenecer al tenant autenticado y estar disponible/conectado. ZYNKO registra el mensaje y responde HTTP 202 cuando queda aceptado en cola. La entrega final depende del conector oficial del proveedor.

## 5. Recibir mensaje hacia ZYNKO

`POST /api.php?r=v1/messages/receive`

Requiere scope `messages:receive`. Registra contacto, conversación y mensaje dentro del tenant correspondiente y puede ejecutar NIVO IA cuando la empresa lo tenga permitido/configurado.

## 6. Contexto de NIVO IA

`POST /api.php?r=v1/nivo/context`

```json
{
  "query": "¿Cuál es la política de devoluciones?"
}
```

Requiere scope `nivo:context`. Además de la política de la API key, NIVO IA puede aplicar su propia lista de orígenes autorizados y límites configurados por empresa.

La respuesta devuelve contexto/fuentes autorizadas relevantes. La generación mediante proveedor externo ocurre únicamente cuando NIVO está configurado para utilizarlo.

## 7. Seguridad por origen, IP y tenant

ZYNKO valida en servidor:

1. clave válida y no revocada;
2. fecha de vencimiento;
3. tenant propietario de la credencial;
4. scope requerido;
5. origen web permitido, cuando aplica;
6. IP/CIDR permitido, cuando aplica;
7. HTTPS obligatorio, cuando aplica;
8. rate limit de la credencial;
9. canal perteneciente al mismo tenant;
10. disponibilidad del módulo según plan/configuración.

CORS por sí solo no concede acceso. Una aplicación web necesita pasar todas las validaciones anteriores.

## 8. CORS y preflight

La API pública responde `OPTIONS` antes de autenticar la petición real y admite, según la solicitud, los headers `Authorization`, `Content-Type`, `Accept`, `Idempotency-Key` y `X-Requested-With`.

Cuando una API key tiene orígenes permitidos, el navegador solo recibe autorización CORS para esos orígenes. No se utiliza `Access-Control-Allow-Credentials` para la API pública.

NIVO Web Chat utiliza un flujo independiente: cada sitio recibe una `installation_key` y el backend valida **clave + dominio autorizado**. El widget no requiere una API key de Integraciones.

Los webhooks de Meta / WhatsApp / Messenger son comunicaciones servidor-a-servidor y no dependen de CORS del navegador.

## 9. Respuestas importantes

- `200`: consulta procesada.
- `202`: operación aceptada / mensaje en cola.
- `401`: clave faltante, inválida, vencida o revocada.
- `403`: origen, IP, scope, HTTPS, plan o política no permitida.
- `409`: canal todavía no conectado/autorizado o conflicto de operación.
- `422`: parámetros incompletos o inválidos.
- `429`: rate limit alcanzado.

## 10. Recomendaciones de producción

- Una clave por sistema y ambiente.
- Restringir origen para aplicaciones web.
- Restringir IP/CIDR para integraciones server-to-server cuando sea posible.
- Mantener HTTPS obligatorio.
- Otorgar solo los scopes necesarios.
- Revocar credenciales que ya no se utilicen.
- No colocar claves privadas dentro de JavaScript público cuando la integración pueda resolverse desde backend.

## V2.31.112
- La documentación administrativa y pública muestra historial de versión para mantener API, NIVO y conectores sincronizados con cada release funcional.
- Correo SMTP/Microsoft Graph configurado actualmente cubre envío y notificaciones; una bandeja entrante de correo requiere un conector IMAP/Graph webhook real y no se marca como vinculada de forma ficticia.
