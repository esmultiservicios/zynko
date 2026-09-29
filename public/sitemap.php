<?php
$root=dirname(__DIR__);
function seoEnvMap(string $path): array {$v=@parse_ini_file($path,false,INI_SCANNER_RAW);return is_array($v)?$v:[];}
$env=seoEnvMap($root.'/.env');$scheme=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';$host=$_SERVER['HTTP_HOST']??'localhost';$base=rtrim(trim((string)($env['APP_URL']??'')) ?: ($scheme.'://'.$host),'/');
header('Content-Type: application/xml; charset=utf-8');
$loc=htmlspecialchars($base.'/',ENT_XML1|ENT_QUOTES,'UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
echo "  <url><loc>{$loc}</loc><changefreq>weekly</changefreq><priority>1.0</priority></url>\n";
echo '</urlset>';
