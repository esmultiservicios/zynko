# ZYNKO API v1 — integración segura de sistemas externos

ZYNKO permite que IZZY, CAMI u otro sistema consuma la API sin conocer las credenciales privadas de Meta ni compartir secretos entre empresas. Cada credencial pertenece a un tenant y puede tener su propia política de seguridad.

## 1. Crear una clave API

En **Canales → Integraciones → Crear clave API** crea una clave diferente por sistema o ambiente. La clave completa se muestra una sola vez; ZYNKO almacena únicamente su hash.

Configura según el caso:

- **Orígenes permitidos:** dominios web desde los que se aceptará consumo en navegador.
- **IP / CIDR permitidos:** restringe consumo server-to-server a servidores o redes concretas.
- **HTTPS obligatorio:** recomendado para producción.
- **Rate limit:** máximo de solicitudes por minuto para esa credencial.
- **Vencimiento:** opcional.
- **Scopes:** `channels:read`, `messages:send`, `messages:receive`, `nivo:context`, `whatsapp:send`, `whatsapp:bulk`, `whatsapp:status`.

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


## WhatsApp especializado: envío individual, masivo, adjuntos y estados (2.31.142)

En **Canales → Integraciones**, seleccione un perfil o configure permisos individuales. `whatsapp:send` permite un destinatario; `whatsapp:bulk` permite una solicitud con varios; `whatsapp:status` permite consultar estados de WhatsApp de la empresa. Para ambos, seleccione WhatsApp completo. `channels:read` permite consultar canales y debe marcarse para descubrir sus ID. Los permisos anteriores se conservan hasta que el administrador los modifique desde Permisos. La API genérica individual `messages:send` permanece compatible.

**POST /api.php?r=v1/whatsapp/send**

```http
Authorization: Bearer TU_CLAVE_API
Content-Type: application/json
Idempotency-Key: OPERACION_UNICA
```

Individual: `{"channel_id":12,"to":"50499990000","message":"Su factura está disponible"}`.

Masivo: `{"channel_id":12,"to":["50499990000","50499990001"],"message":"Notificación autorizada","consent_confirmed":true}`. Máximo 100 destinatarios por solicitud; se normalizan y eliminan duplicados. Las solicitudes con múltiples destinatarios requieren el permiso masivo, incluso si hay números duplicados.

Adjunto remoto (Meta Cloud / Coexistence): `{"channel_id":12,"to":"50499990000","attachments":[{"name":"Factura.pdf","mime":"application/pdf","url":"https://ejemplo.com/factura.pdf"}]}`. Meta obtiene el contenido de la URL HTTPS accesible públicamente; ZYNKO no descarga URLs arbitrarias. No incluya credenciales en URL.

Adjunto Base64 (también QR): `{"channel_id":12,"to":"50499990000","attachments":[{"name":"Factura.pdf","data_base64":"BASE64_DEL_ARCHIVO"}]}`. Hasta 5 adjuntos, 10 MB por archivo decodificado y 20 MB en total. El MIME local se detecta desde los bytes.

Plantilla aprobada (Meta): `{"channel_id":12,"to":"50499990000","template_id":"123456789","template_values":["Cliente","F-001"]}`. No combine este payload con `message` o `attachments`; la plantilla se valida al despachar. Se aplican políticas de Meta, consentimiento y ventana de atención de 24 horas.

HTTP 202 devuelve `data.messages[]` con `to`, `message_uuid`, `conversation_id`, `message_id`, `status` (queued). En cola no significa entregado. Una misma clave Idempotency-Key con idéntico payload devuelve la respuesta original; con otro contenido, devuelve 409. El despachador existente procesa la cola.

**GET /api.php?r=v1/whatsapp/status&message_uuid=UUID**: devuelve `message_uuid`, `status`, `queue_state`, `last_error`. Requiere `whatsapp:status`. Nunca consulta la información de otra empresa.

401 autenticación, 403 permisos/plan, 404 canal/mensaje, 409 estado o idempotencia, 413 tamaño, 422 validación, 429 límite. Mantener la API key en el servidor. El canal debe estar conectado y el monitor de cola en funcionamiento; Cloud API de pruebas solo permite destinatarios verificados. Meta puede rechazar solicitudes fuera de la ventana o sin plantilla aprobada.
