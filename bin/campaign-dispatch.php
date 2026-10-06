<?php
declare(strict_types=1);
$root=dirname(__DIR__);require_once $root.'/app/Services/CampaignDispatcher.php';
$env=@parse_ini_file($root.'/.env',false,INI_SCANNER_RAW)?:[];$pdo=new PDO('mysql:host='.($env['DB_HOST']??'127.0.0.1').';port='.($env['DB_PORT']??'3306').';dbname='.($env['DB_DATABASE']??'zynko').';charset=utf8mb4',$env['DB_USERNAME']??'root',$env['DB_PASSWORD']??'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$q=$pdo->query("SELECT id,tenant_id FROM outbound_campaigns WHERE status IN('scheduled','sending') AND (scheduled_at IS NULL OR scheduled_at<=NOW()) ORDER BY id LIMIT 20");foreach($q->fetchAll() as $c){try{$r=CampaignDispatcher::dispatch($pdo,$root,(int)$c['tenant_id'],(int)$c['id'],100);echo 'Campaign '.$c['id'].': '.json_encode($r).PHP_EOL;}catch(Throwable $e){echo 'Campaign '.$c['id'].' error: '.$e->getMessage().PHP_EOL;}}
