<?php
$root=dirname(__DIR__);
function seoEnv(string $path): array {$v=@parse_ini_file($path,false,INI_SCANNER_RAW);return is_array($v)?$v:[];}
$env=seoEnv($root.'/.env');
$scheme=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';$host=$_SERVER['HTTP_HOST']??'localhost';
$base=rtrim(trim((string)($env['APP_URL']??'')) ?: ($scheme.'://'.$host),'/');
header('Content-Type: text/plain; charset=utf-8');
echo "User-agent: *\n";
echo "Allow: /\n";
echo "Disallow: /app/\nDisallow: /database/\nDisallow: /docs/\nDisallow: /routes/\nDisallow: /storage/\nDisallow: /websocket/\n";
echo "Disallow: /api.php\nDisallow: /webchat-api.php\nDisallow: /install.php\n";
echo "Disallow: /*?page=login\nDisallow: /*?page=register\nDisallow: /*?page=verify-email\nDisallow: /*?page=forgot-password\nDisallow: /*?page=reset-password\nDisallow: /*?page=dashboard\nDisallow: /*?page=inbox\nDisallow: /*?page=channels\nDisallow: /*?page=webchat\nDisallow: /*?page=users\nDisallow: /*?page=chatbot\nDisallow: /*?page=integrations\nDisallow: /*?page=billing\nDisallow: /*?page=email\nDisallow: /*?page=settings\nDisallow: /*?page=onboarding\n";
echo "Sitemap: {$base}/sitemap.xml\n";
