# Administración de dominio, .env, SEO y anti-spam

## Servidor y dominio
Desde el Admin propietario se pueden administrar variables permitidas del `.env`, incluyendo `APP_URL`, `WS_PUBLIC_HOST`, ambiente, debug, WebSocket y parámetros controlados de base de datos.

Antes de guardar, ZYNKO crea un respaldo en `storage/env-backups`. `APP_KEY` no se expone ni se modifica desde navegador. La contraseña actual de la base de datos tampoco se vuelve a mostrar.

Para migrar de dominio, normalmente actualizar:

```text
APP_URL=https://nuevo-dominio.app
WS_PUBLIC_HOST=nuevo-dominio.app
```

Después revisar DNS, SSL, canonical/SEO, Turnstile y callbacks externos.

## SEO
Desde Configuración se puede administrar:
- URL pública/canonical;
- título/nombre;
- descripción SEO;
- Google Search Console;
- Bing;
- Open Graph;
- Twitter Card;
- Schema.org.

ZYNKO expone/genera `robots.txt`, `sitemap.xml` y `site.webmanifest` utilizando la URL pública configurada.

## Cloudflare Turnstile
El formulario público puede protegerse con Turnstile. Configura Site Key, Secret Key y, opcionalmente, hostname esperado. La validación se realiza en servidor antes de aceptar el formulario.

Al cambiar de dominio, actualizar el dominio permitido en Cloudflare y el hostname esperado dentro de ZYNKO.
