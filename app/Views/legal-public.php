<?php
// Este archivo se incluye desde el controlador público después de preparar el esquema.
$catalog = zynkoLegalCatalog();
$definition = $catalog[$legalKey] ?? null;
if ($definition === null) {
    http_response_code(404);
    exit('Documento no encontrado.');
}
$doc = null;
try {
    $doc = zynkoFindLegalDocument(appDb(), $legalKey);
} catch (Throwable $e) {
    // No revelar errores de base de datos ni entregar contenido desactualizado.
}
$docHtml = $doc ? zynkoLegalContentToHtml((string)$doc['content']) : '';
$available = $doc !== null && trim($docHtml) !== '';
if (!$available) {
    http_response_code(503);
    header('Retry-After: 60');
}
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; script-src 'none'; object-src 'none'; base-uri 'none'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'");
$escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$documentTitle = $available ? (string)$doc['title'] : $definition['title'];
$published = $available && !empty($doc['published_at']) ? date('d/m/Y', strtotime($doc['published_at'])) : '';
$cssVersion = filemtime($root.'/public/assets/css/legal-public.css');
?>
<!doctype html>
<html lang="es-HN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?=$escape($definition['description'])?>">
    <meta name="theme-color" content="#08263b">
    <meta name="robots" content="<?=$available?'index,follow':'noindex,follow'?>">
    <title><?=$escape($documentTitle)?> · ZYNKO</title>
    <link rel="icon" href="assets/img/favicon.svg">
    <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/css/seo-home.css?v=<?=filemtime($root.'/public/assets/css/seo-home.css')?>">
    <link rel="stylesheet" href="assets/css/legal-public.css?v=<?=$cssVersion?>">
</head>
<body class="zynko-legal-public">
    <header class="legal-site-header">
        <div class="wrap legal-site-nav">
            <a class="logo" href="./" aria-label="ZYNKO inicio"><span class="brandmark">ZY</span><span><b>ZYNKO</b><small>Omnichannel SaaS</small></span></a>
            <a class="btn legal-home-button" href="./"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Volver al inicio</a>
        </div>
    </header>
    <main class="wrap legal-public-layout">
        <aside class="legal-public-aside" aria-label="Documentos legales">
            <span class="legal-aside-label">CENTRO LEGAL</span>
            <nav class="legal-public-links">
                <?php foreach ($catalog as $key => $item): ?>
                    <a href="<?=$escape($item['path'])?>" <?=$key===$legalKey?'aria-current="page"':''?>>
                        <i class="fa-solid <?=$escape($item['icon'])?>" aria-hidden="true"></i>
                        <span><?=$escape($item['title'])?></span>
                        <i class="fa-solid fa-chevron-right legal-nav-arrow" aria-hidden="true"></i>
                    </a>
                <?php endforeach; ?>
            </nav>
            <p>Una solución de ES MULTISERVICIOS.</p>
            <a class="legal-contact-link" href="./#contacto"><i class="fa-regular fa-envelope" aria-hidden="true"></i> Contactar soporte</a>
        </aside>
        <article class="legal-public-card">
            <div class="legal-public-heading">
                <span class="legal-public-icon"><i class="fa-solid <?=$escape($definition['icon'])?>" aria-hidden="true"></i></span>
                <div><span class="legal-kicker">DOCUMENTO LEGAL · ZYNKO</span>
                    <h1><?=$escape($documentTitle)?></h1>
                    <p><?=$escape($definition['description'])?></p>
                </div>
            </div>
            <?php if ($available): ?>
                <div class="legal-public-meta"><span><i class="fa-solid fa-code-branch" aria-hidden="true"></i> Versión <?=$escape($doc['version'])?></span><?php if ($published): ?><span><i class="fa-regular fa-calendar" aria-hidden="true"></i> Publicado el <?=$escape($published)?></span><?php endif; ?></div>
                <div class="legal-public-content"><?=$docHtml?></div>
            <?php else: ?>
                <div class="legal-public-content"><h2>Documento temporalmente no disponible</h2><p>Vuelva a intentarlo más tarde o contacte con soporte. No es necesario iniciar sesión.</p></div>
            <?php endif; ?>
        </article>
    </main>
    <footer class="legal-site-footer"><div class="wrap"><span>© <?=date('Y')?> ZYNKO · ES MULTISERVICIOS · Honduras</span><nav aria-label="Enlaces legales"><?php foreach ($catalog as $item): ?><a href="<?=$escape($item['path'])?>"><?=$escape($item['title'])?></a><?php endforeach; ?></nav></div></footer>
</body>
</html>
