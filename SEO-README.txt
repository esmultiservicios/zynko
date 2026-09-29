ZYNKO - SEO TECNICO V2.28.0

ARCHIVOS / FUNCIONES
- /robots.txt se genera dinámicamente desde public/robots.php.
- /sitemap.xml se genera dinámicamente desde public/sitemap.php.
- APP_URL del .env define el dominio canónico usado en robots, sitemap, Open Graph y datos estructurados.
- La portada pública / es indexable.
- Login, registro, recuperación, verificación y todo el dashboard llevan noindex/nofollow.
- APIs, instalador y directorios privados se excluyen de rastreo.
- La portada incluye title, description, canonical, Open Graph, Twitter Card y JSON-LD (Organization, SoftwareApplication y WebSite).
- site.webmanifest y una imagen social 1200x630 están incluidos.

ANTES DE PUBLICAR
1. En .env configura APP_URL con el dominio HTTPS real, sin slash final. Ejemplo: APP_URL=https://zynko.tudominio.com
2. Opcional: ajusta SEO_SITE_NAME, SEO_DESCRIPTION y SEO_LOCALE.
3. Abre /robots.txt y /sitemap.xml en producción y confirma que muestran el dominio correcto.
4. Registra /sitemap.xml en Google Search Console y Bing Webmaster Tools.
5. No indexes el dashboard: ZYNKO ya lo bloquea intencionalmente.
