ZYNKO - SEO TECNICO V2.28.1

IMPLEMENTADO
- /robots.txt físico visible en la raíz y copia en /public.
- /sitemap.xml físico visible en la raíz y copia en /public.
- En Apache, ambos endpoints son servidos dinámicamente por robots.php y sitemap.php para usar el dominio configurado.
- Administrador SEO en Dashboard > Configuración > SEO y posicionamiento.
- URL pública/canonical administrable con fallback a APP_URL.
- Meta title/description, robots, canonical, Open Graph, Twitter Card y JSON-LD.
- Tokens HTML para Google Search Console y Bing Webmaster Tools.
- La portada pública / es indexable.
- Login, registro, recuperación, verificación y dashboard llevan noindex/nofollow.
- APIs, instalador y directorios privados quedan fuera del rastreo.
- site.webmanifest e imagen social 1200x630 incluidos.

ANTES DE PUBLICAR
1. Configura la URL pública desde Dashboard > Configuración > SEO y posicionamiento o APP_URL en .env.
2. Abre /robots.txt y /sitemap.xml en producción.
3. Verifica Search Console preferiblemente con propiedad de Dominio/DNS.
4. Envía /sitemap.xml en Google Search Console y Bing Webmaster Tools.
5. Cuando ZYNKO tenga nuevas páginas públicas reales, agrégalas al generador public/sitemap.php; no agregues pantallas privadas.
