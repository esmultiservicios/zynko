<?php
declare(strict_types=1);
$root=dirname(__DIR__);
require_once $root.'/app/Services/NivoConversationIntelligence.php';
$env=@parse_ini_file($root.'/.env',false,INI_SCANNER_RAW);$env=is_array($env)?$env:[];
$dsn='mysql:host='.($env['DB_HOST']??'127.0.0.1').';port='.($env['DB_PORT']??'3306').';dbname='.($env['DB_DATABASE']??'zynko').';charset=utf8mb4';
$pdo=new PDO($dsn,$env['DB_USERNAME']??'root',$env['DB_PASSWORD']??'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
$tenant=0;foreach($argv as $arg){if(str_starts_with($arg,'--tenant='))$tenant=(int)substr($arg,9);}
$ids=[];if($tenant>0)$ids=[$tenant];else{$q=$pdo->query('SELECT id FROM tenants ORDER BY id');$ids=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));}
foreach($ids as $tid){try{(new NivoConversationIntelligence($pdo,$root))->syncChunks($tid);echo "Tenant {$tid}: conocimiento fragmentado y listo para RAG.\n";}catch(Throwable $e){fwrite(STDERR,"Tenant {$tid}: {$e->getMessage()}\n");}}
