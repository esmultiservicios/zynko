<?php
declare(strict_types=1);
$root=dirname(__DIR__);require_once $root.'/app/Services/NivoWebsiteKnowledgeService.php';
$env=@parse_ini_file($root.'/.env',false,INI_SCANNER_RAW)?:[];
$pdo=new PDO('mysql:host='.($env['DB_HOST']??'127.0.0.1').';port='.($env['DB_PORT']??3306).';dbname='.($env['DB_DATABASE']??'zynko').';charset=utf8mb4',$env['DB_USERNAME']??'root',$env['DB_PASSWORD']??'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$svc=new NivoWebsiteKnowledgeService($pdo,$root);$result=$svc->syncDue(null,10);echo json_encode(['ok'=>true,'processed'=>count($result),'results'=>$result],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
