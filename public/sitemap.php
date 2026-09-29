<?php
$root=dirname(__DIR__);
function seoEnvMap(string $path): array {$v=@parse_ini_file($path,false,INI_SCANNER_RAW);return is_array($v)?$v:[];}
function seoMapSetting(string $key,string $fallback=''): string {global $root;try{$e=seoEnvMap($root.'/.env');$pdo=new PDO('mysql:host='.($e['DB_HOST']??'127.0.0.1').';port='.($e['DB_PORT']??'3306').';dbname='.($e['DB_DATABASE']??'zynko').';charset=utf8mb4',$e['DB_USERNAME']??'root',$e['DB_PASSWORD']??'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$q=$pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key=? LIMIT 1');$q->execute([$key]);$v=$q->fetchColumn();return is_string($v)&&trim($v)!==''?trim($v):$fallback;}catch(Throwable $x){return $fallback;}}
$env=seoEnvMap($root.'/.env');$scheme=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';$host=$_SERVER['HTTP_HOST']??'localhost';$base=seoMapSetting('seo_site_url',trim((string)($env['APP_URL']??'')));$base=rtrim($base!==''?$base:($scheme.'://'.$host),'/');
header('Content-Type: application/xml; charset=utf-8');
$loc=htmlspecialchars($base.'/',ENT_XML1|ENT_QUOTES,'UTF-8');
$today=date('Y-m-d');
echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
echo "  <url><loc>{$loc}</loc><lastmod>{$today}</lastmod><changefreq>weekly</changefreq><priority>1.0</priority></url>\n";
echo '</urlset>';
