<?php
// Este archivo se incluye desde el controlador público después de preparar el esquema.
$publicLanguageChoice=in_array($_COOKIE['zynko_public_lang']??'',['es','en'],true)?$_COOKIE['zynko_public_lang']:(str_starts_with(strtolower((string)($_SERVER['HTTP_ACCEPT_LANGUAGE']??'')),'en')?'en':'es');
$lt=fn($es,$en)=>$publicLanguageChoice==='en'?$en:$es;
$catalog = zynkoLegalLocalizedCatalog($publicLanguageChoice);
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
$localized=$doc?zynkoLegalLocalizedDocument($legalKey,$doc,$publicLanguageChoice):null;
$docHtml = $localized ? zynkoLegalContentToHtml($localized['content']) : '';
$publicLanguageReturn=pathinfo($definition['path'],PATHINFO_FILENAME);
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
$documentTitle = $available ? $localized['title'] : $definition['title'];
$published = $available && !empty($doc['published_at']) ? date('d/m/Y', strtotime($doc['published_at'])) : '';
$cssVersion = filemtime($root.'/public/assets/css/legal-public.css');
?>
<!doctype html>
<html lang="<?=$publicLanguageChoice?>">
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
            <div class="legal-nav-actions"><?php require __DIR__.'/partials/public-language.php';?><a class="btn legal-home-button" href="./"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> <?=$lt('Volver al inicio','Back to home')?></a></div>
        </div>
    </header>
    <main class="wrap legal-public-layout">
        <aside class="legal-public-aside" aria-label="Documentos legales">
            <span class="legal-aside-label"><?=$lt('CENTRO LEGAL','LEGAL CENTER')?></span>
            <nav class="legal-public-links">
                <?php foreach ($catalog as $key => $item): ?>
                    <a href="<?=$escape($item['path'])?>" <?=$key===$legalKey?'aria-current="page"':''?>>
                        <i class="fa-solid <?=$escape($item['icon'])?>" aria-hidden="true"></i>
                        <span><?=$escape($item['title'])?></span>
                        <i class="fa-solid fa-chevron-right legal-nav-arrow" aria-hidden="true"></i>
                    </a>
                <?php endforeach; ?>
            </nav>
            <p><?=$lt('Una solución de ES MULTISERVICIOS.','A solution by ES MULTISERVICIOS.')?></p>
            <a class="legal-contact-link" href="./#contacto"><i class="fa-regular fa-envelope" aria-hidden="true"></i> <?=$lt('Contactar soporte','Contact support')?></a>
        </aside>
        <article class="legal-public-card">
            <div class="legal-public-heading">
                <span class="legal-public-icon"><i class="fa-solid <?=$escape($definition['icon'])?>" aria-hidden="true"></i></span>
                <div><span class="legal-kicker"><?=$lt('DOCUMENTO LEGAL · ZYNKO','LEGAL DOCUMENT · ZYNKO')?></span>
                    <h1><?=$escape($documentTitle)?></h1>
                    <p><?=$escape($definition['description'])?></p>
                </div>
            </div>
            <?php if ($available): ?>
                <div class="legal-public-meta"><span><i class="fa-solid fa-code-branch" aria-hidden="true"></i> <?=$lt('Versión','Version')?> <?=$escape($doc['version'])?></span><?php if ($published): ?><span><i class="fa-regular fa-calendar" aria-hidden="true"></i> <?=$lt('Publicado el','Published on')?> <?=$escape($published)?></span><?php endif; ?></div>
                <?php if(!empty($localized['fallback'])):?><p class="legal-translation-notice" lang="en">The current published version is available in Spanish. An English translation has not been verified for this revision.</p><?php endif?><div class="legal-public-content" lang="<?=!empty($localized['fallback'])?'es':$publicLanguageChoice?>"><?=$docHtml?></div>
            <?php else: ?>
                <div class="legal-public-content"><h2><?=$lt('Documento temporalmente no disponible','Document temporarily unavailable')?></h2><p><?=$lt('Vuelva a intentarlo más tarde o contacte con soporte. No es necesario iniciar sesión.','Please try again later or contact support. You do not need to sign in.')?></p></div>
            <?php endif; ?>
        </article>
    </main>
    <footer class="legal-site-footer"><div class="wrap"><span>© <?=date('Y')?> ZYNKO · ES MULTISERVICIOS · Honduras</span><nav aria-label="Enlaces legales"><?php foreach ($catalog as $item): ?><a href="<?=$escape($item['path'])?>"><?=$escape($item['title'])?></a><?php endforeach; ?></nav></div></footer>
</body>
</html>
