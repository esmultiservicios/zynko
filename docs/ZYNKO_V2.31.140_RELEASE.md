# ZYNKO v2.31.140

- Corrige el fallback físico de `sitemap.xml` para que Google Search Console reciba una URL indexable aun cuando Apache no ejecute el rewrite hacia `public/sitemap.php`.
- Replica el fallback válido en `public/sitemap.xml`.
- Agrega `Sitemap: https://zynkocloud.app/sitemap.xml` a los `robots.txt` físicos.
- Conserva la generación dinámica existente mediante `public/sitemap.php`, `public/robots.php` y `.htaccess`.
- No requiere actualización de base de datos.
