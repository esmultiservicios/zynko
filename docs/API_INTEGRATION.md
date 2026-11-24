# ZYNKO API v1 — Guía de integración y diagnóstico (v060)

> Esta documentación describe los endpoints del archivo `public/api.php` del proyecto incluido. Las pruebas estáticas no reemplazan la verificación autenticada contra la instalación de producción, la base de datos ni los servicios de Meta.

## A. Lista de verificación ANTES de programar el cliente

1. **URL exacta:** `https://zynkocloud.app/api.php?r=v1/channels`. El parámetro `r` selecciona la ruta; `api.php` no es una API que se consuma mediante `/api.php/v1/channels` en esta instalación. No invente otras URLs.
2. **Una API Key por integración y ambiente.** La administra el titular en **Canales → Integraciones**. Guarde el secreto en el backend, cifrado o en un almacén de secretos. No en navegador, APK, repositorio ni logs.
3. **Scopes:** para descubrir canales `channels:read`; envíos WhatsApp individuales `whatsapp:send`, lotes `whatsapp:bulk`, historial/estados `whatsapp:status`. Otros endpoints tienen scopes diferentes (tabla abajo).
4. **Canal autorizado:** seleccione el canal WhatsApp real vinculado a la API Key. Si la clave está restringida a un canal, otros `channel_id` no funcionarán.
5. **IP/CIDR:** la IP permitida es la **IP pública de salida del proceso que hace HTTP**, no la IP del sitio web ni la IP del servidor destino. Un PHP que se ejecuta en Laragon (`newizzy.test`) sale por la conexión del equipo/red local; NO necesariamente por WEB01 (`13.140.230.254`). Si el cliente es desplegado después a WEB01, puede cambiar su IP de salida. Pregunte al responsable de infraestructura o confirme mediante la respuesta de diagnóstico del servidor. No cambie la allowlist automáticamente.
6. **Origen vs. IP:** la allowlist de orígenes (`Origin`) se aplica si se recibe el encabezado `Origin` (típico de navegadores). Un PHP/cURL backend normalmente no lo envía. Configurar `https://newizzy.test` como origen NO equivale a permitir la IP de Laragon. Para llamadas servidor-a-servidor se recomienda no enviar `Origin` salvo que realmente aplique.
7. **HTTPS y permisos:** confirme plan con API habilitada, clave activa/no vencida, canal conectado, salida HTTPS y cuota disponible. **No** coloque `Authorization` en un parámetro de URL.
8. **Redirecciones:** `CURLOPT_FOLLOWLOCATION=false`; usar siempre el endpoint HTTPS final. Compruebe que cURL prepara `Authorization: Bearer TU_CLAVE_API`.

## B. Primera prueba real, sin enviar WhatsApp

```bash
curl --fail-with-body --silent --show-error --request GET \
  'https://zynkocloud.app/api.php?r=v1/channels' \
  --header 'Authorization: Bearer TU_CLAVE_API' \
  --header 'Accept: application/json'
```

La respuesta correcta es HTTP **200** con `ok:true` y `data.channels`. Si la lista queda vacía, significa que la clave autenticó pero no tiene canales visibles; no invente un `channel_id`.

**Ejemplo de PHP/cURL (servidor, no JavaScript):**

```php
<?php
$token = getenv('ZYNKO_API_KEY'); // cargar desde almacén seguro
$url = 'https://zynkocloud.app/api.php?r=v1/channels';
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Accept: application/json',
    ],
]);
$body = curl_exec($ch);
$http = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$transportError = curl_error($ch);
curl_close($ch);
if ($body === false) { throw new RuntimeException('Error de transporte cURL: ' . $transportError); }
$response = json_decode($body, true);
if (!is_array($response)) { throw new RuntimeException('El servidor no respondió JSON. HTTP ' . $http); }
if ($http >= 400) {
    // No imprimir el Bearer. Mostrar solo código, mensaje y datos no secretos.
    throw new RuntimeException('ZYNKO HTTP ' . $http . ' / ' . ($response['error']['code'] ?? 'UNKNOWN') . ': ' . ($response['message'] ?? 'Error'));
}
```

## C. Error 403 por IP: diagnóstico definitivo

Si **el Bearer es válido** pero la petición procede de una IP no incluida en la política, ZYNKO v060 responde:

```json
{
  "ok": false,
  "message": "La IP pública de salida no está autorizada para esta clave API.",
  "data": {
    "received_ip": "198.51.100.23",
    "allowed_ips": ["13.140.230.254"],
    "source": "REMOTE_ADDR",
    "next_step": "Autorice la IP/CIDR real en la política de esta clave o ejecute desde un servidor previamente autorizado."
  },
  "error": {"code": "IP_NOT_ALLOWED", "http_status": 403}
}
```

**Las IP de este ejemplo son ilustrativas**, no observaciones de su petición. Para conocer la IP real de IZZY, consulte el `data.received_ip` que devuelve ZYNKO tras desplegar v060. La allowlist autorizada se devuelve en `data.allowed_ips` solo cuando el Bearer ya fue validado. Para IP se usa `REMOTE_ADDR`: no se confía en `X-Forwarded-For`, `X-Real-IP` ni `CF-Connecting-IP` enviados por solicitantes externos, porque pueden falsificarse. Si la instalación usa un proxy inverso **administrado y confiable**, el administrador debe configurar el módulo de IP real en el servidor; no se corrige tomando ciegamente encabezados en PHP.

Si su entorno local cambia de IP pública, deberá autorizarla de forma deliberada o ejecutar la prueba desde un servidor con IP estable. No desactive restricciones ni use `0.0.0.0/0` como solución provisional.

## D. Endpoints y permisos disponibles

| Método | `r=` | Scope requerido | Uso / claves principales |
|---|---|---|---|
| GET | `v1/health` | Bearer válido y política permitida | Salud de la API autenticada; no es pública |
| GET | `v1/channels` | `channels:read` (o scopes genéricos compatibles) | IDs y estado de canales visibles |
| GET | `v1/whatsapp/history` | `whatsapp:status` | `page`, `per_page` (1-100), `q`; historial limitado a esa API Key |
| GET | `v1/whatsapp/status` | `whatsapp:status` | `message_uuid` retornado por **esa misma API Key** |
| POST | `v1/whatsapp/send` | `whatsapp:send` individual / `whatsapp:bulk` lote | `channel_id`, `to`, `message`/`template_id`/`attachments`, `Idempotency-Key` |
| GET | `v1/messages/status` | `messages:send` o `messages:bulk` | `message_uuid`; ruta genérica heredada |
| POST | `v1/messages/send` | `messages:send` | `channel_id`, `to`, `message` |
| POST | `v1/messages/bulk` | `messages:bulk` | `channel_id`, `to[]` (2–100), `message`, `consent_confirmed`, `Idempotency-Key` |
| POST | `v1/messages/receive` | `messages:receive` o compatibilidad `messages:send` | `channel_id`, `from`, `message`, `contact_name` opcional |
| POST | `v1/nivo/context` | `nivo:context` | `query`; consulta contexto autorizado, no garantiza generación IA |

**Para POST**: `Content-Type: application/json`, JSON válido, Bearer y los scopes necesarios. Para `v1/whatsapp/send`, `Idempotency-Key` es obligatorio y debe ser diferente para cada operación; reintentar la *misma* operación con la misma clave/payload evita duplicados. HTTP **202** significa **en cola**, NO entregado. Consulte `status` y `history` posteriormente; el resultado real depende del proveedor, plantillas aprobadas, consentimiento y ventana de Meta.

## E. Contrato de errores para integradores

Las respuestas de error normales devuelven **`ok:false`, `message` legible, `data` y `error.code` + `error.http_status`**. No muestre mensajes HTTP genéricos si hay un error específico de ZYNKO.

| HTTP | Código de error | Qué debe hacer el cliente |
|---|---|---|
| 400 | `BAD_REQUEST` | Corregir JSON inválido |
| 401 | `UNAUTHORIZED` | Revisar Bearer faltante/inválido, clave vencida o revocada; no generar otra sin diagnosticar |
| 403 | `IP_NOT_ALLOWED` | Comparar `data.received_ip` con `data.allowed_ips`; solicitar autorización manual |
| 403 | `ORIGIN_NOT_ALLOWED` | Revisar `Origin` recibido y orígenes de la clave |
| 403 | `API_KEY_POLICY_DISABLED` | Reactivar política de clave solo con autorización administrativa |
| 403 | `HTTPS_REQUIRED` | Utilizar HTTPS en la integración del navegador |
| 403 | `FORBIDDEN` | Consultar mensaje: scope, canal, plan u otra política insuficiente |
| 404 | `NOT_FOUND` | Revisar ruta, UUID o pertenencia a clave/canal |
| 409 | `CONFLICT` | Revisar estado del canal o `Idempotency-Key` reutilizada con distinto contenido |
| 413 | `PAYLOAD_TOO_LARGE` | Reducir solicitud/adjuntos |
| 422 | `VALIDATION_ERROR` | Corregir los parámetros indicados en `message` |
| 429 | `RATE_LIMITED` | Reducir frecuencia y aplicar reintento con backoff |
| 500 | `INTERNAL_ERROR` | Registrar referencia de error sin capturar secretos; investigar servidor |

No reintente automáticamente respuestas 401/403/409/422. No pruebe la ruta de envío hasta obtener HTTP 200 en canales y, si corresponde, HTTP 200 en historial. Nunca pida ni registre la clave completa en soporte o capturas.

---

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

## Actualización 2.31.143 — API v1

Los endpoints, permisos, autenticación Bearer y contratos introducidos en 2.31.142 permanecen compatibles. En Canales → Integraciones puede crear claves API para envío y webhooks para recibir eventos de ZYNKO. Un webhook no es necesario para enviar mensajes. La documentación pública y administrativa comparte los ejemplos de WhatsApp.

## Actualización 2.31.144 — API v1

Los endpoints y scopes de v1 se mantienen. El plan Gratis no incluye API, WhatsApp ni NIVO IA. Los planes de pago incluyen WhatsApp y API según el catálogo; la disponibilidad real de los conectores requiere autorización. La mensualidad de ZYNKO no incluye las tarifas de Meta ni otros proveedores; el cliente debe asumirlas mediante su cuenta o un mecanismo de facturación acordado.

### Correo de ZYNKO
En Correo y notificaciones, Microsoft Graph permite guardar remitentes por propósito y probar cada buzón. El envío requiere Mail.Send y permisos de acceso al buzón. Esto no modifica la API REST v1 ni habilita recepción automáticamente.


## Actualización 2.31.145 — API v1

Esta versión conserva los endpoints, permisos, claves y formatos de la API REST v1. Los cambios se concentran en el catálogo de planes, la galería pública, el historial de visitas y la coherencia de los scripts de base de datos.

## Actualización 2.31.146 — API REST v1

La API conserva sus endpoints y permisos. Los ejemplos se muestran en formato compacto, con ajuste visual de línea. En Bash/POSIX `\` inmediatamente seguido de un salto de línea es una continuación válida; si se coloca todo en una sola línea debe eliminarse, no dejar `\ -H`. Ejemplo:

```sh
curl "https://zynkocloud.app/api.php?r=v1/channels" -H "Authorization: Bearer TU_CLAVE_API"
```

Las solicitudes deben salir de un servidor seguro y las claves no deben publicarse en el navegador. En el sitio público la documentación se expande en bloques y conserva los botones de copia.


## ZYNKO 2.31.155 — Plan exclusivamente API y trazabilidad por empresa

**Plan `api_only` (ZYNKO API):** se crea dentro del catálogo de planes ya existente, sin modificar los precios ni las capacidades de otros planes. Su precio inicial es **pendiente de definir** y se publica con etiqueta **Próximamente**; la administración debe fijar precio y disponibilidad antes de ofrecer contrataciones. Solo admite Dashboard reducido, Canales (WhatsApp propio), Integraciones API y Suscripción. Sin Bandeja, NIVO Web Chat, NIVO IA, automatizaciones ni CRM. La gestión de altas y cambios de plan sigue utilizando el motor existente.

**Nueva clave API:** Canales → Integraciones → Crear clave API. Debe indicar **un canal exclusivo** (ID existente de la empresa) y seleccionar:

- **Historial independiente, sin Bandeja** (predeterminado): los envíos API se registran en una conversación técnica oculta (`conversations.api_only=1`) para reutilizar la cola, la entrega y los estados ya implementados. No se muestran en la Bandeja ni en sus consultas visibles. Se anotan en `api_message_history`.
- **Historial y Bandeja:** disponible solo si el plan permite Bandeja. El mensaje también aparece como conversación normal. Se registra igualmente en `api_message_history`.

Las claves antiguas mantienen su modo original (`inbox`) y alcance heredado (sin canal fijado) para evitar incompatibilidades. **Si una empresa entra en el plan `api_only`, el API impone el modo independiente aun sobre claves heredadas; jamás se habilita la bandeja para dicho plan.** Las nuevas quedan ligadas a un `channel_id`, verificado en servidor. El modo es editable posteriormente desde **Permisos**, sin alterar mensajes antiguos. Cambios de plan superiores conservan las claves y configuraciones cuando no se revocan por la política vigente.

**Historial de envíos API:** visible desde Integraciones para el tenant autenticado. Incluye búsqueda por destino, canal, integración o UUID, tamaño 10/25/50/100/Todos (n), paginación numérica, estados reales desde la tabla de mensajes/cola y exportadores Excel/PDF con los modales estándar. Se registran los nuevos envíos a partir de la actualización; los envíos históricos sin relación de clave verificable no se atribuyen retroactivamente.

**Seguridad:** los orígenes permitidos (HTTP `Origin`) controlan principalmente solicitudes desde navegador y pueden ser falsificados por clientes no navegadores; **no sustituyen una clave secreta ni la lista de IP/CIDR**. Para IZZY, usar una clave exclusiva, el backend de IZZY como consumidor y restringir la IP pública fija de salida del servidor, validando su dirección real en ZYNKO. Nunca entregar la clave al navegador ni a un cliente final. Una IP autorizada no impide que un atacante utilice una clave robada desde el mismo servidor comprometido: revocar inmediatamente si hay sospecha.

**Webhook:** «Nuevo webhook» registra la URL HTTPS de otro sistema para recibir eventos que ZYNKO emite; **no se necesita para enviar WhatsApp**. Se puede agregar en el futuro un webhook de estados o respuestas entrantes específicamente para el API, con firma y validación, antes de habilitar recepción fuera de la Bandeja. No se debe prometer recepción automática por API en esta entrega.

**SQL:** las tres variantes existentes `ZYNKO_UPDATE_DB_COMPLETO*.sql` incorporan la migración aditiva de la versión 2.31.155. El runtime también crea las columnas/tablas necesarias. No ejecutar simultáneamente las tres variantes sobre una misma base.


### API por canal no WhatsApp (Messenger / Instagram)

Las nuevas claves se ligan a un único canal y pueden incluir `messages:send` (individual), `messages:bulk` (2–100 destinatarios con consentimiento) y `channels:read`.

`POST /api.php?r=v1/messages/bulk` requiere Bearer, `Idempotency-Key` y JSON:

```json
{"channel_id":14,"to":["IDENTIFICADOR_DESTINATARIO_1","IDENTIFICADOR_DESTINATARIO_2"],"message":"Mensaje autorizado","consent_confirmed":true}
```

El `channel_id` debe pertenecer a la empresa y coincidir con la clave; la conexión debe estar activa y autorizada por el proveedor. Para Meta, los destinatarios son los identificadores propios del canal, no números telefónicos. Los mensajes quedan `queued` (HTTP 202) hasta el despacho real. La API **no elimina las restricciones de ventana de mensajería y uso comercial del proveedor**; una solicitud aceptada puede fallar al despacharse. El estado se consulta con `GET /api.php?r=v1/messages/status&message_uuid=UUID`. Los envíos WhatsApp conservan sus endpoints específicos `v1/whatsapp/send` y `v1/whatsapp/status`.


### Historial de envíos API por credencial (v030)

`GET /api.php?r=v1/whatsapp/history&page=1&per_page=20&q=` con `Authorization: Bearer <API_KEY>` y permiso `whatsapp:status`.
Devuelve únicamente los mensajes generados mediante **esa misma API Key**, dentro de su empresa y del canal asociado a la clave. Admite hasta 100 registros por página e incluye UUID, destinatario, contenido, estado de cola, último error y modalidad de entrega. No incluye conversaciones normales de la bandeja.
El modo independiente mantiene el historial sin publicar la conversación en la bandeja; el modo integrado también conserva este historial. Las aplicaciones cliente deben mantener su API Key únicamente en el servidor.


## F. Configurar varios sitios y varias IP de salida (ZYNKO v064)

Los campos del formulario **no son equivalentes**:

- **Sitios / orígenes permitidos**: agregue una URL de origen por línea, como `https://sistema.izzycloud.app` y `https://demo.izzycloud.app`. Los orígenes aplican cuando el cliente envía la cabecera HTTP `Origin` (habitual en navegadores). No conceden acceso de red por IP; la URL no debe incluir rutas.
- **IP / CIDR permitidos**: agregue una dirección **pública de salida** por línea. Se admiten IPv4 (`13.140.230.254`), IPv6 (`2605:a144:2370:6446::`) o CIDR válidos (`203.0.113.0/24`, `2001:db8::/64`). Se permiten varias IP y redes simultáneamente, hasta 50 entradas únicas. También admite entradas separadas por comas o punto y coma.

La API verifica `REMOTE_ADDR`, la IP observada al recibir la conexión HTTPS. No confía en cabeceras `X-Forwarded-For` aportadas por el cliente. **Si `GET ?r=v1/channels` responde `403 IP_NOT_ALLOWED`, compare `data.received_ip` con `data.allowed_ips`.** La IP de salida observada desde Laragon, demo alojado y producción puede ser distinta (también puede alternar IPv4/IPv6).

En la prueba reportada por IZZY, `received_ip` fue `2605:a144:2370:6446::` y la política solo contenía `13.140.230.254`; por ello el rechazo es esperado. Verifique que la IPv6 pertenece a su entorno autorizado antes de incorporarla. Para producción, autorice únicamente las IP estables o el CIDR mínimo asignado oficialmente, nunca `0.0.0.0/0` ni `::/0`. Si usa un proveedor/proxy inverso, configure la IP del servidor/proxy de confianza de forma explícita; no copie sin validar una IP informada por un encabezado del cliente.

**Procedimiento de comprobación**: 1) En Integraciones → Acciones → Editar, conserve la entrada existente y añada la IP validada en una línea nueva; 2) Guardar permisos y recargar «Consultar» para comprobar la lista; 3) en IZZY, «Probar conexión» otra vez; 4) exigir HTTP 200 antes de consultar historial; 5) no enviar WhatsApp en esta prueba. Guardar el sitio demo por sí solo NO corrige `IP_NOT_ALLOWED`.

Las nuevas validaciones de administrador devuelven un error explícito con la entrada mal escrita en lugar de ignorarla silenciosamente; aceptan IPv4, IPv6 y máscaras CIDR del rango correspondiente. Cambiar IP/origen no exige regenerar la clave API.
