# WebSocket de ZYNKO en producción

## 1. Qué archivo contiene la configuración

ZYNKO lee la configuración real desde `.env` en la raíz del proyecto. Ese archivo contiene credenciales y valores propios del servidor, por eso **no se distribuye dentro de los ZIP**.

El ZIP incluye `.env.example` únicamente como plantilla. En instalaciones nuevas, `public/install.php` crea el `.env`. En una instalación existente, el propietario de la plataforma puede editar los valores permitidos desde **Configuración**.

Valores relacionados con WebSocket:

```env
WS_HOST=127.0.0.1
WS_PORT=8080
WS_PUBLIC_HOST=zynkocloud.app
WS_PUBLIC_PORT=8080
WS_PUBLIC_SCHEME=wss
WS_PUBLIC_URL=
```

- `WS_HOST` y `WS_PORT`: interfaz y puerto internos donde escucha `websocket/server.php`.
- `WS_PUBLIC_HOST`, `WS_PUBLIC_PORT` y `WS_PUBLIC_SCHEME`: se usan para construir la URL pública cuando `WS_PUBLIC_URL` está vacío.
- `WS_PUBLIC_URL`: URL pública completa. Si tiene valor, tiene prioridad sobre los tres valores anteriores.

## 2. Producción HTTPS

El servidor PHP de `websocket/server.php` atiende WebSocket normal (`ws://`) y no termina TLS por sí solo. Por eso, si la web pública funciona con HTTPS, la opción recomendada es:

1. Ejecutar internamente el socket en `127.0.0.1:8080`.
2. Publicarlo mediante un reverse proxy con TLS en el mismo dominio, por ejemplo `/ws`.
3. Configurar:

```env
WS_PUBLIC_URL=wss://zynkocloud.app/ws
```

Esto evita exponer directamente el puerto 8080 a Internet cuando el hosting permite proxy WebSocket.

## 3. Diagnóstico en el servidor

Desde Terminal/SSH:

```bash
cd /ruta/de/zynko
php websocket/server.php
```

Debe mostrar algo equivalente a:

```text
ZYNKO WebSocket activo en ws://127.0.0.1:8080
```

En otra sesión comprueba que el proceso escucha:

```bash
ss -lntp | grep 8080
```

Si `ss` no existe:

```bash
netstat -lntp 2>/dev/null | grep 8080
```

Para saber si el proceso está vivo:

```bash
ps aux | grep '[w]ebsocket/server.php'
```

## 4. Imunify360 / firewall

No abras el puerto 8080 automáticamente. Primero determina qué arquitectura tiene el servidor:

- **Reverse proxy en 443:** el puerto 8080 puede permanecer privado/localhost. Esta es la opción recomendada.
- **Conexión pública directa a `:8080`:** el firewall debe permitir TCP entrante al puerto 8080 y el servidor WebSocket tendría que escuchar en una interfaz accesible. Además, para una página HTTPS necesitarías TLS real en ese endpoint; el servidor PHP incluido no lo proporciona directamente.

Si Imunify360 bloquea una conexión, revisa sus eventos/logs y agrega una regla solo después de confirmar qué IP/puerto necesita el proxy o servicio. No desactives Imunify360 ni abras puertos amplios como solución general.

## 5. Prueba desde el navegador

Abre DevTools > Network > WS en el sitio que contiene NIVO. Debe existir una conexión con estado `101 Switching Protocols`.

Si ves:

- `ERR_CONNECTION_REFUSED`: no hay servicio escuchando o el firewall/proxy no permite llegar.
- `Mixed Content`: la página usa HTTPS pero intenta conectar con `ws://`; debe ser `wss://`.
- `404` o `502` en `/ws`: el reverse proxy no está apuntando correctamente a `127.0.0.1:8080`.
- conexión que abre y se cierra: revisar token, proxy timeout, proceso WebSocket y logs del servidor.

## 6. Regla funcional de NIVO

WebSocket transporta los eventos en tiempo real, pero los mensajes se guardan primero en base de datos. Si el socket cae temporalmente, el widget conserva la conversación y al reconectar recupera el estado canónico; un fallo del socket no debe borrar mensajes.


## 7. Arquitectura realtime actual

La ruta recomendada en producción es:

```text
Navegador HTTPS
  -> wss://tu-dominio/ws
  -> Apache mod_proxy_wstunnel
  -> ws://127.0.0.1:8080
  -> websocket/server.php
```

El proyecto incluye una regla protegida por `mod_proxy`/`mod_proxy_wstunnel` en `.htaccess`. Si esos módulos están disponibles, no necesitas abrir el puerto 8080 al Internet ni crear una regla pública en Imunify360 para ese puerto.

Después de cada deploy de cPanel, `.cpanel.yml` ejecuta `bin/restart-websocket.sh`. El script reinicia el daemon y deja log en `storage/logs/websocket.log`.

La comunicación normal ya no depende de polling periódico. Cada mensaje se guarda primero en `messages` y su evento completo se escribe en `realtime_events`; el daemon lo transmite al Widget y a la Bandeja. Si la conexión se cae, la reconexión hace una resincronización canónica para recuperar cualquier evento ocurrido durante el corte.

Para verificar producción abre DevTools > Network > WS y confirma una conexión a `/ws` con respuesta `101 Switching Protocols`. En la Bandeja el indicador debe mostrar **Tiempo real**, no **Auto**.


## V2.31.108 · Binding IPv4 estable

ZYNKO normaliza `WS_HOST=localhost` a `127.0.0.1` al iniciar `websocket/server.php`. Esto evita que Linux resuelva `localhost` a `::1` mientras el proxy Apache `/ws` apunta a `127.0.0.1`. Para producción se recomienda guardar explícitamente `WS_HOST=127.0.0.1`. La pantalla **Configuración → Salud integral del servidor** comprueba daemon, puerto, proxy y endpoint WSS público.
