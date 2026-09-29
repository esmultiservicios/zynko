<?php
$root=dirname(__DIR__);
function seoEnv(string $path): array {$v=@parse_ini_file($path,false,INI_SCANNER_RAW);return is_array($v)?$v:[];}
function seoSetting(string $key,string $fallback=''): string {global $root;try{$e=seoEnv($root.'/.env');$pdo=new PDO('mysql:host='.($e['DB_HOST']??'127.0.0.1').';port='.($e['DB_PORT']??'3306').';dbname='.($e['DB_DATABASE']??'zynko').';charset=utf8mb4',$e['DB_USERNAME']??'root',$e['DB_PASSWORD']??'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$q=$pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key=? LIMIT 1');$q->execute([$key]);$v=$q->fetchColumn();return is_string($v)&&trim($v)!==''?trim($v):$fallback;}catch(Throwable $x){return $fallback;}}
$env=seoEnv($root.'/.env');
$scheme=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';$host=$_SERVER['HTTP_HOST']??'localhost';
$base=seoSetting('seo_site_url',trim((string)($env['APP_URL']??'')));$base=rtrim($base!==''?$base:($scheme.'://'.$host),'/');
header('Content-Type: text/plain; charset=utf-8');
echo "User-agent: *\n";
echo "Allow: /\n";
echo "Disallow: /app/\nDisallow: /database/\nDisallow: /docs/\nDisallow: /routes/\nDisallow: /storage/\nDisallow: /websocket/\n";
echo "Disallow: /api.php\nDisallow: /webchat-api.php\nDisallow: /install.php\n";
echo "Disallow: /*?page=login\nDisallow: /*?page=register\nDisallow: /*?page=verify-email\nDisallow: /*?page=forgot-password\nDisallow: /*?page=reset-password\nDisallow: /*?page=dashboard\nDisallow: /*?page=inbox\nDisallow: /*?page=channels\nDisallow: /*?page=webchat\nDisallow: /*?page=users\nDisallow: /*?page=chatbot\nDisallow: /*?page=integrations\nDisallow: /*?page=billing\nDisallow: /*?page=email\nDisallow: /*?page=settings\nDisallow: /*?page=onboarding\n";
echo "Sitemap: {$base}/sitemap.xml\n";
