<?php
declare(strict_types=1);
$root=dirname(__DIR__);
require_once $root.'/app/Services/ChannelProviderGateway.php';
ini_set('session.use_strict_mode','1');session_start();
$user=$_SESSION['user']??[];$sid=(int)($_SESSION['zynko_session_row_id']??0);$impersonating=!empty($_SESSION['zynko_platform_context']);session_write_close();
$tid=(int)($user['tenant_id']??0);$uid=(int)($user['id']??0);$mid=(int)($_GET['message_id']??0);$index=(int)($_GET['index']??0);
header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');
if(!$tid||!$uid||!$mid||$index<0){http_response_code(403);exit;}
try{
 $env=@parse_ini_file($root.'/.env',false,INI_SCANNER_RAW)?:[];
 $pdo=new PDO('mysql:host='.($env['DB_HOST']??'127.0.0.1').';port='.($env['DB_PORT']??3306).';dbname='.($env['DB_DATABASE']??'zynko').';charset=utf8mb4',$env['DB_USERNAME']??'root',$env['DB_PASSWORD']??'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $check=$pdo->prepare("SELECT 1 FROM tenant_users tu JOIN users u ON u.id=tu.user_id JOIN tenants t ON t.id=tu.tenant_id WHERE tu.tenant_id=? AND tu.user_id=? AND u.status='active' AND t.status NOT IN ('suspended','closed')");$check->execute([$tid,$uid]);if(!$check->fetchColumn())throw new RuntimeException();if(!$impersonating){$check=$pdo->prepare('SELECT 1 FROM user_sessions WHERE id=? AND user_id=? AND revoked_at IS NULL AND expires_at>NOW()');$check->execute([$sid,$uid]);if(!$check->fetchColumn())throw new RuntimeException();}
 $q=$pdo->prepare('SELECT ch.*,m.media_json FROM messages m JOIN conversations c ON c.id=m.conversation_id AND c.tenant_id=m.tenant_id JOIN channels ch ON ch.id=c.channel_id AND ch.tenant_id=c.tenant_id WHERE m.id=? AND m.tenant_id=? AND c.deleted_at IS NULL');$q->execute([$mid,$tid]);$ch=$q->fetch();if(!$ch)throw new RuntimeException();
 $f=(json_decode((string)$ch['media_json'],true)?:[])[$index]??null;if(!is_array($f))throw new RuntimeException();
 if(!empty($f['provider_id'])){$r=(new ChannelProviderGateway($pdo,$root))->downloadProviderMedia($ch,(string)$f['provider_id']);}
 else{$u=(string)($f['url']??'');if(!preg_match('#^uploads/(?:chat|quick-replies|quick_replies)/'.preg_quote((string)$tid,'#').'/[A-Za-z0-9_./-]+$#D',$u)||str_contains($u,'..'))throw new RuntimeException();$base=realpath($root.'/public/uploads');$path=realpath($root.'/public/'.$u);if(!$base||!$path||!str_starts_with($path,$base.DIRECTORY_SEPARATOR)||!is_file($path)||filesize($path)>20*1024*1024)throw new RuntimeException();$r=['mime'=>(new finfo(FILEINFO_MIME_TYPE))->file($path),'bytes'=>file_get_contents($path)];}
 $mime=(string)$r['mime'];if(!preg_match('#^(image/(jpeg|png|gif|webp)|audio/[a-z0-9.+-]+|video/[a-z0-9.+-]+|application/pdf)$#iD',$mime))$mime='application/octet-stream';
 $name=preg_replace('/[^A-Za-z0-9_.-]/','_',basename((string)($f['name']??'attachment')))?:'attachment';
 header('Content-Type: '.$mime);header('Content-Disposition: '.((str_starts_with($mime,'image/')||$mime==='application/pdf')&&!isset($_GET['download'])?'inline':'attachment').'; filename="'.$name.'"');header('Content-Length: '.strlen($r['bytes']));echo $r['bytes'];
}catch(Throwable $e){http_response_code(404);}
