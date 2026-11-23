<?php
declare(strict_types=1);
$root=dirname(__DIR__);require_once $root.'/app/Support/Cors.php';require_once $root.'/app/Support/Realtime.php';require_once $root.'/app/Support/AccessPolicy.php';require_once $root.'/app/Services/NivoEngine.php';require_once $root.'/app/Services/AutomationEngine.php';require_once $root.'/app/Services/ChannelProviderGateway.php';require_once $root.'/app/Support/ChannelSchema.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow, nosnippet', true);

$apiCorsOrigin = ZynkoCors::requestOrigin();
ZynkoCors::handleAuthenticatedApiPreflight();
if ($apiCorsOrigin !== '') {
    ZynkoCors::send(
        $apiCorsOrigin,
        ['GET', 'POST', 'OPTIONS'],
        ['Authorization', 'Content-Type', 'Accept', 'Idempotency-Key', 'X-Requested-With']
    );
}

function out(int $status,bool $ok,string $message,array $data=[]): never { http_response_code($status); echo json_encode(['ok'=>$ok,'message'=>$message,'data'=>$data],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function envCfg(string $p): array { $v=@parse_ini_file($p,false,INI_SCANNER_RAW); return is_array($v)?$v:[]; }
function db(): PDO { static $pdo; global $root; if($pdo)return $pdo;$e=envCfg($root.'/.env');$dsn='mysql:host='.($e['DB_HOST']??'127.0.0.1').';port='.($e['DB_PORT']??'3306').';dbname='.($e['DB_DATABASE']??'zynko').';charset=utf8mb4';$pdo=new PDO($dsn,$e['DB_USERNAME']??'root',$e['DB_PASSWORD']??'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");return $pdo; }
function uuid4(): string {$d=random_bytes(16);$d[6]=chr((ord($d[6])&15)|64);$d[8]=chr((ord($d[8])&63)|128);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($d),4));}
function bearer(): string {
 // Apache/cPanel puede exponer Authorization como REDIRECT_HTTP_AUTHORIZATION.
 $h=trim((string)($_SERVER['HTTP_AUTHORIZATION']??''));
 if($h==='')$h=trim((string)($_SERVER['REDIRECT_HTTP_AUTHORIZATION']??''));
 if($h===''){
  foreach(function_exists('getallheaders')?(getallheaders()?:[]):[] as $name=>$value){
   if(strcasecmp((string)$name,'Authorization')===0){$h=trim((string)$value);break;}
  }
 }
 if(!preg_match('/^Bearer\s+([^\s]+)\s*$/i',$h,$m))out(401,false,'Falta Authorization: Bearer <API_KEY>.');
 return $m[1];
}
function auth(): array { ZynkoAccessPolicy::ensureTables(db()); $key=bearer();$prefix=explode('.',$key,2)[0]??'';if(!str_starts_with($prefix,'zk_'))out(401,false,'Clave API inválida.');$q=db()->prepare('SELECT * FROM api_keys WHERE key_prefix=? AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at>NOW())');$q->execute([$prefix]);$r=$q->fetch();if(!$r||!hash_equals($r['key_hash'],hash('sha256',$key)))out(401,false,'Clave API inválida o revocada.');try{ZynkoAccessPolicy::enforce(db(),$r);}catch(OverflowException $e){out(429,false,$e->getMessage());}catch(RuntimeException $e){out(403,false,$e->getMessage());}db()->prepare('UPDATE api_keys SET last_used_at=NOW() WHERE id=?')->execute([$r['id']]);return $r; }
function hasScope(array $k,string $scope): void {$s=json_decode($k['scopes_json']??'[]',true)?:[];if(!in_array($scope,$s,true))out(403,false,'La clave no tiene el permiso '.$scope.'.');}
function hasAnyScope(array $k,array $scopes): void {$s=json_decode($k['scopes_json']??'[]',true)?:[];foreach($scopes as $scope)if(in_array($scope,$s,true))return;out(403,false,'La clave no tiene ninguno de los permisos requeridos.');}
function body(): array {$raw=file_get_contents('php://input')?:'';$j=json_decode($raw,true);if(!is_array($j))out(400,false,'JSON inválido.');return $j;}
function planAllows(int $tid,string $feature): bool {try{$q=db()->prepare('SELECT sp.module_access_json,sp.features_json,sp.active,ts.status FROM tenant_subscriptions ts JOIN subscription_plans sp ON sp.id=ts.plan_id WHERE ts.tenant_id=? LIMIT 1');$q->execute([$tid]);$row=$q->fetch();if(!$row)return false;if((int)($row['active']??0)!==1||!in_array((string)($row['status']??''),['active','grace','trial'],true))return false;$m=json_decode($row['module_access_json']??'{}',true)?:[];if(array_key_exists($feature,$m))return (bool)$m[$feature];$f=json_decode($row['features_json']??'[]',true)?:[];foreach($f as $k=>$v){if(is_string($k)&&$k===$feature)return (bool)$v;if(is_string($v)&&$v===$feature)return true;}return false;}catch(Throwable $e){return false;}}
function monthlyChatLimit(int $tid): ?int {try{$q=db()->prepare('SELECT sp.max_monthly_chats FROM tenant_subscriptions ts JOIN subscription_plans sp ON sp.id=ts.plan_id WHERE ts.tenant_id=? AND ts.status IN ("active","grace","trial") LIMIT 1');$q->execute([$tid]);$v=$q->fetchColumn();if($v===false||$v===null)return null;$n=(int)$v;return $n>0?$n:null;}catch(Throwable $e){return null;}}
function enforceMonthlyChatLimit(int $tid): void {$limit=monthlyChatLimit($tid);if($limit===null)return;try{$q=db()->prepare("SELECT COUNT(*) FROM conversations WHERE tenant_id=? AND api_only=0 AND created_at>=DATE_FORMAT(CURDATE(),'%Y-%m-01') AND created_at<DATE_ADD(LAST_DAY(CURDATE()),INTERVAL 1 DAY)");$q->execute([$tid]);$used=(int)$q->fetchColumn();if($used>=$limit)out(429,false,'La empresa alcanzó el límite de '.number_format($limit).' chats nuevos de este mes.',['limit'=>$limit,'used'=>$used,'period'=>'month']);}catch(Throwable $e){}}
function logReq(int $tid,int $keyId,string $endpoint,string $idem,int $status,array $payload): void {try{db()->prepare('INSERT INTO api_request_logs(tenant_id,api_key_id,endpoint,idempotency_key,http_status,payload_hash,created_at) VALUES(?,?,?,?,?,?,NOW())')->execute([$tid,$keyId,$endpoint,$idem?:null,$status,hash('sha256',json_encode($payload))]);}catch(Throwable $e){}}
try{
 $key=auth();$tid=(int)$key['tenant_id'];$keyChannel=(int)($key['channel_id']??0);$apiOnly=(string)($key['delivery_mode']??'inbox')==='independent'||(zynkoPlanContext(db(),$tid,false)['plan_code']??'')==='api_only';$method=$_SERVER['REQUEST_METHOD']??'GET';$path=trim($_GET['r']??($_SERVER['PATH_INFO']??''),'/');
 if($method==='GET'&&($path===''||$path==='v1/health'))out(200,true,'ZYNKO API disponible.',['version'=>'v1','tenant_id'=>$tid]);
 if(!planAllows($tid,'api'))out(403,false,'El plan o estado de suscripción de esta empresa no permite usar la API de ZYNKO.');if($method==='POST')ChannelSchema::ensure(db());
 if($method==='GET'&&$path==='v1/channels'){
  hasAnyScope($key,['channels:read','messages:send','messages:bulk','messages:receive']);if(!planAllows($tid,'api'))out(403,false,'El plan de esta empresa no incluye acceso API.');$q=db()->prepare('SELECT id,uuid,type,name,display_address,status,created_at FROM channels WHERE tenant_id=?'.($keyChannel>0?' AND id=?':'').' ORDER BY name,id');$q->execute($keyChannel>0?[$tid,$keyChannel]:[$tid]);out(200,true,'Canales disponibles.',['channels'=>$q->fetchAll()]);
 }
 if($method==='GET'&&$path==='v1/messages/status'){
  hasAnyScope($key,['messages:send','messages:bulk']);
  $uuid=(string)($_GET['message_uuid']??'');
  if(!preg_match('/^[a-f0-9-]{36}$/D',$uuid))out(422,false,'message_uuid inválido.');
  $sql="SELECT m.uuid message_uuid,m.status,o.state queue_state,o.last_error,ch.type channel_type,ch.name channel_name FROM messages m JOIN conversations c ON c.id=m.conversation_id AND c.tenant_id=m.tenant_id JOIN channels ch ON ch.id=c.channel_id AND ch.tenant_id=c.tenant_id LEFT JOIN channel_outbox o ON o.message_id=m.id AND o.tenant_id=m.tenant_id WHERE m.uuid=? AND m.tenant_id=? AND m.direction='out'".($keyChannel>0?' AND ch.id=?':'')." LIMIT 1";
  $q=db()->prepare($sql);$q->execute($keyChannel>0?[$uuid,$tid,$keyChannel]:[$uuid,$tid]);$record=$q->fetch();
  if(!$record)out(404,false,'Mensaje no disponible para esta clave.');
  out(200,true,'Estado consultado.',$record);
 }
 if($method==='GET'&&$path==='v1/whatsapp/history'){
  hasScope($key,'whatsapp:status');
  ZynkoAccessPolicy::ensureTables(db());
  $page=max(1,min(100000,(int)($_GET['page']??1)));
  $per=max(1,min(100,(int)($_GET['per_page']??20)));
  $search=mb_substr(trim((string)($_GET['q']??'')),0,80);
  $where=' WHERE h.tenant_id=? AND h.api_key_id=? AND ch.type=\'whatsapp\'';
  $args=[$tid,(int)$key['id']];
  if(!empty($key['channel_id'])){$where.=' AND h.channel_id=?';$args[]=(int)$key['channel_id'];}
  if($search!==''){$where.=' AND (h.destination LIKE ? OR m.uuid LIKE ?)';$like='%'.$search.'%';$args[]=$like;$args[]=$like;}
  $join=' FROM api_message_history h JOIN messages m ON m.id=h.message_id AND m.tenant_id=h.tenant_id JOIN channels ch ON ch.id=h.channel_id AND ch.tenant_id=h.tenant_id LEFT JOIN channel_outbox o ON o.message_id=m.id AND o.tenant_id=h.tenant_id';
  $cq=db()->prepare('SELECT COUNT(*)'.$join.$where);$cq->execute($args);$total=(int)$cq->fetchColumn();
  $query=db()->prepare('SELECT h.created_at,h.destination,h.delivery_mode,ch.name channel_name,m.uuid message_uuid,m.body,m.status,o.state queue_state,o.last_error'.$join.$where.' ORDER BY h.id DESC LIMIT '.(int)$per.' OFFSET '.(int)(($page-1)*$per));
  $query->execute($args);
  out(200,true,'Historial de WhatsApp API.',['page'=>$page,'per_page'=>$per,'total'=>$total,'pages'=>max(1,(int)ceil($total/$per)),'messages'=>$query->fetchAll()]);
 }
 if($method==='GET'&&$path==='v1/whatsapp/status'){
  hasScope($key,'whatsapp:status');$uuid=(string)($_GET['message_uuid']??'');if(!preg_match('/^[a-f0-9-]{36}$/D',$uuid))out(422,false,'message_uuid inválido.');
  $q=db()->prepare("SELECT m.uuid message_uuid,m.status,o.state queue_state,o.last_error FROM messages m JOIN conversations c ON c.id=m.conversation_id AND c.tenant_id=m.tenant_id JOIN channels ch ON ch.id=c.channel_id AND ch.tenant_id=c.tenant_id LEFT JOIN channel_outbox o ON o.message_id=m.id AND o.tenant_id=m.tenant_id WHERE m.uuid=? AND m.tenant_id=? AND m.direction='out' AND ch.type='whatsapp'");$q->execute([$uuid,$tid]);$r=$q->fetch();if(!$r)out(404,false,'Mensaje no encontrado.');if($keyChannel>0){$verify=db()->prepare('SELECT h.channel_id FROM api_message_history h JOIN messages m ON m.id=h.message_id AND m.tenant_id=h.tenant_id WHERE m.uuid=? AND h.tenant_id=? LIMIT 1');$verify->execute([$uuid,$tid]);$recordChannel=(int)$verify->fetchColumn();if($recordChannel!==$keyChannel)out(404,false,'Mensaje no encontrado para esta clave.');}out(200,true,'Estado consultado.',$r);
 }
 if($method==='POST'&&$path==='v1/whatsapp/send'){
  hasAnyScope($key,['whatsapp:send','whatsapp:bulk']);if((int)($_SERVER['CONTENT_LENGTH']??0)>30*1024*1024)out(413,false,'Solicitud superior a 30 MB.');$j=body();$idem=trim($_SERVER['HTTP_IDEMPOTENCY_KEY']??'');if($idem===''||strlen($idem)>150)out(422,false,'Idempotency-Key obligatorio (máximo 150 caracteres).');
  $channel=filter_var($j['channel_id']??null,FILTER_VALIDATE_INT);if(!$channel||$channel<1)out(422,false,'channel_id inválido.');if($keyChannel>0&&$channel!==$keyChannel)out(403,false,'Esta clave API solo permite el canal seleccionado.');$q=db()->prepare("SELECT * FROM channels WHERE id=? AND tenant_id=? AND type='whatsapp'");$q->execute([$channel,$tid]);$ch=$q->fetch();if(!$ch)out(404,false,'Canal WhatsApp no encontrado.');if($ch['status']!=='connected')out(409,false,'Canal WhatsApp no conectado.');if(!zynkoPlanAllowsChannel(zynkoPlanContext(db(),$tid),'whatsapp'))out(403,false,'El plan no permite WhatsApp.');
  $raw=$j['to']??null;if(is_string($raw))$raw=[$raw];if(!is_array($raw)||!array_is_list($raw)||count($raw)<1||count($raw)>100)out(422,false,'Indique entre 1 y 100 destinatarios.');$numbers=[];foreach($raw as $number){if(!is_string($number)||!($n=ChannelIdentity::phone($number)))out(422,false,'Número inválido; utilice formato internacional.');$numbers[$n]=$n;}$numbers=array_values($numbers);hasScope($key,count($raw)>1?'whatsapp:bulk':'whatsapp:send');
  if(count($raw)>1&&($j['consent_confirmed']??false)!==true)out(422,false,'Confirme autorización de los destinatarios mediante consent_confirmed=true.');
  if(isset($j['message'])&&!is_string($j['message']))out(422,false,'message debe ser texto.');$text=trim($j['message']??'');if(mb_strlen($text)>4096)out(422,false,'Mensaje demasiado largo.');$structured=null;
  if(isset($j['template_id'])){try{$structured=ChannelStructuredMessage::build($ch,['kind'=>'template','template_id'=>$j['template_id'],'values'=>$j['template_values']??[]]);}catch(InvalidArgumentException $e){out(422,false,$e->getMessage());}}
  $input=$j['attachments']??[];if(!is_array($input)||!array_is_list($input)||count($input)>5)out(422,false,'Máximo cinco adjuntos.');if($structured&&($input||$text!==''))out(422,false,'Envíe la plantilla sin texto ni adjuntos adicionales.');if(!$structured&&$text===''&&!$input)out(422,false,'Incluya mensaje, plantilla o adjunto.');
  $mimeMap=['image/jpeg'=>'jpg','image/png'=>'png','application/pdf'=>'pdf','video/mp4'=>'mp4','audio/mpeg'=>'mp3','audio/ogg'=>'ogg','application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'=>'xlsx'];$files=[];$total=0;
  foreach($input as $a){if(!is_array($a)||!is_string($a['name']??null))out(422,false,'Adjunto inválido.');$name=basename(str_replace('\\','/',$a['name']));if($name===''||strlen($name)>180)out(422,false,'Nombre de adjunto inválido.');
   if(isset($a['url'])){if(isset($a['data_base64']))out(422,false,'Use url o data_base64, no ambas.');$url=$a['url'];$mime=$a['mime']??'';if(ChannelSecurity::mode($ch)==='qr_bridge')out(422,false,'Para QR utilice data_base64 en lugar de URL.');if(!is_string($url)||!is_string($mime)||!isset($mimeMap[$mime])||!ChannelProviderGateway::validPublicMediaUrl($url))out(422,false,'URL HTTPS pública o MIME inválido.');$files[]=['name'=>$name,'url'=>$url,'mime'=>$mime,'remote'=>true];continue;}
   $b64=$a['data_base64']??null;if(!is_string($b64)||strlen($b64)>14*1024*1024)out(422,false,'Adjunto demasiado grande o inválido.');$bytes=base64_decode($b64,true);if($bytes===false||$bytes===''||strlen($bytes)>10*1024*1024)out(422,false,'Adjunto inválido o superior a 10 MB.');$total+=strlen($bytes);if($total>20*1024*1024)out(422,false,'Máximo 20 MB por solicitud.');$mime=(new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);if(!isset($mimeMap[$mime]))out(422,false,'Formato de archivo no permitido.');$files[]=['name'=>$name,'bytes'=>$bytes,'mime'=>$mime,'ext'=>$mimeMap[$mime]];
  }
  $p=db();$idemKey='wa:'.hash('sha256',$key['id'].':'.$idem);$payloadHash=hash('sha256',json_encode($j,JSON_THROW_ON_ERROR));$saved=[];$created=[];$results=[];$p->beginTransaction();
  try{ // lock per tenant serializes admission and idempotency, not provider I/O
   $q=$p->prepare('SELECT id FROM tenants WHERE id=? FOR UPDATE');$q->execute([$tid]);if(!$q->fetchColumn())throw new RuntimeException('Empresa no encontrada.');$lockedPlan=zynkoPlanContext($p,$tid,false);if(!zynkoPlanAllowsChannel($lockedPlan,'whatsapp'))throw new RuntimeException('Canal no incluido en el plan.');
   $q=$p->prepare('SELECT response_json,http_status FROM api_idempotency WHERE tenant_id=? AND idempotency_key=?');$q->execute([$tid,$idemKey]);if($old=$q->fetch()){$oldData=json_decode($old['response_json'],true);$p->commit();if(($oldData['data']['request_hash']??'')!==$payloadHash)out(409,false,'La clave ya se utilizó con otra solicitud.');http_response_code((int)$old['http_status']);echo $old['response_json'];exit;}
   $media=[];foreach($files as $f){if(!empty($f['remote'])){$media[]=$f;continue;}$rel='uploads/chat/'.$tid.'/api';$dir=$root.'/public/'.$rel;if(!is_dir($dir)&&!mkdir($dir,0750,true)&&!is_dir($dir))throw new RuntimeException('No se pudo crear almacenamiento.');$fn=bin2hex(random_bytes(20)).'.'.$f['ext'];$absolute=$dir.'/'.$fn;$saved[]=$absolute;if(file_put_contents($absolute,$f['bytes'],LOCK_EX)!==strlen($f['bytes']))throw new RuntimeException('No se pudo guardar adjunto.');chmod($absolute,0640);$media[]=['name'=>$f['name'],'url'=>$rel.'/'.$fn,'mime'=>$f['mime']];}
   $limit=monthlyChatLimit($tid);$used=0;if($limit!==null){$q=$p->prepare("SELECT COUNT(*) FROM conversations WHERE tenant_id=? AND api_only=0 AND created_at>=DATE_FORMAT(CURDATE(),'%Y-%m-01') AND created_at<DATE_ADD(LAST_DAY(CURDATE()),INTERVAL 1 DAY)");$q->execute([$tid]);$used=(int)$q->fetchColumn();}
   $gateway=new ChannelProviderGateway($p,$root);foreach($numbers as $to){$to=(string)$to;$q=$p->prepare('SELECT contact_id FROM contact_identities WHERE tenant_id=? AND channel_id=? AND external_id=? LIMIT 1');$q->execute([$tid,$channel,$to]);$contact=(int)$q->fetchColumn();if(!$contact){$q=$p->prepare('SELECT id FROM contacts WHERE tenant_id=? AND phone=? LIMIT 1');$q->execute([$tid,$to]);$contact=(int)$q->fetchColumn();}if(!$contact){$p->prepare('INSERT INTO contacts(tenant_id,uuid,name,phone) VALUES(?,?,?,?)')->execute([$tid,uuid4(),$to,$to]);$contact=(int)$p->lastInsertId();}
    $p->prepare('INSERT INTO contact_identities(tenant_id,contact_id,channel_id,external_id) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE contact_id=VALUES(contact_id)')->execute([$tid,$contact,$channel,$to]);
    $q=$p->prepare("SELECT id FROM conversations WHERE tenant_id=? AND channel_id=? AND contact_id=? AND status IN ('open','pending') AND api_only=? AND archived_at IS NULL AND deleted_at IS NULL ORDER BY id DESC LIMIT 1");$q->execute([$tid,$channel,$contact,$apiOnly?1:0]);$cid=(int)$q->fetchColumn();if(!$cid){if(!$apiOnly){zynkoPlanRequireChatCapacity($p,$tid,$lockedPlan,'whatsapp');if($limit!==null&&$used>=$limit)throw new OverflowException('Límite mensual de conversaciones alcanzado.');$used++;}$p->prepare("INSERT INTO conversations(tenant_id,uuid,channel_id,contact_id,status,last_message_at,api_only) VALUES(?,?,?,?,'open',NOW(),?)")->execute([$tid,uuid4(),$channel,$contact,$apiOnly?1:0]);$cid=(int)$p->lastInsertId();$created[]=$cid;}
    $uuid=uuid4();$msgBody=$structured?ChannelStructuredMessage::body($structured):$text;$p->prepare("INSERT INTO messages(tenant_id,conversation_id,uuid,direction,sender_type,type,body,media_json,status,sent_at) VALUES(?,?,?,'out','system',?,?,?,'queued',NOW())")->execute([$tid,$cid,$uuid,$media?'media':'text',$msgBody?:null,$media?json_encode($media,JSON_THROW_ON_ERROR):null]);$mid=(int)$p->lastInsertId();$queuePayload=$structured??['kind'=>'api_send'];$queuePayload['api_destination']=$to;$p->prepare('INSERT INTO channel_structured_outbox(message_id,tenant_id,channel_id,payload_json) VALUES(?,?,?,?)')->execute([$mid,$tid,$channel,json_encode($queuePayload,JSON_THROW_ON_ERROR)]);if(!$gateway->enqueue($tid,$cid,$mid))throw new RuntimeException('No se pudo registrar la cola.');$p->prepare('INSERT INTO api_message_history(tenant_id,api_key_id,channel_id,message_id,destination,delivery_mode) VALUES(?,?,?,?,?,?)')->execute([$tid,(int)$key['id'],$channel,$mid,$to,$apiOnly?'independent':'inbox']);$p->prepare('UPDATE conversations SET last_message_at=NOW() WHERE id=? AND tenant_id=?')->execute([$cid,$tid]);$results[]=['to'=>$to,'message_uuid'=>$uuid,'conversation_id'=>$cid,'message_id'=>$mid,'status'=>'queued'];
   }
   $resp=['ok'=>true,'message'=>'Mensajes registrados en cola.','data'=>['request_hash'=>$payloadHash,'count'=>count($results),'messages'=>$results]];$json=json_encode($resp,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$p->prepare('INSERT INTO api_idempotency(tenant_id,idempotency_key,response_json,http_status,created_at) VALUES(?,?,?,?,NOW())')->execute([$tid,$idemKey,$json,202]);$p->commit();
  }catch(Throwable $e){if($p->inTransaction())$p->rollBack();foreach($saved as $f)@unlink($f);if($e instanceof OverflowException)out(429,false,$e->getMessage());throw $e;}
  if(!$apiOnly)foreach($created as $cid)zynkoRealtimePublishSafe($p,$tid,'conversation.created',['conversation_id'=>$cid,'channel'=>'whatsapp'],'conversation',(string)$cid);if(!$apiOnly)foreach($results as $r){try{zynkoRealtimePublishMessage($p,$tid,$r['message_id'],['channel'=>'whatsapp','sender'=>'system']);}catch(Throwable $e){}}
  logReq($tid,(int)$key['id'],'v1/whatsapp/send',$idem,202,$j);http_response_code(202);echo $json;exit;
 }

 if($method==='POST'&&$path==='v1/messages/bulk'){
  hasScope($key,'messages:bulk');
  $j=body();$idem=trim((string)($_SERVER['HTTP_IDEMPOTENCY_KEY']??''));
  if($idem===''||strlen($idem)>150)out(422,false,'Idempotency-Key obligatorio (máximo 150 caracteres).');
  $channel=(int)($j['channel_id']??0);
  if($channel<1||($keyChannel>0&&$channel!==$keyChannel))out(403,false,'Canal no autorizado para esta clave.');
  $q=db()->prepare('SELECT * FROM channels WHERE id=? AND tenant_id=?');$q->execute([$channel,$tid]);$ch=$q->fetch();
  if(!$ch)out(404,false,'Canal no encontrado.');
  if(!in_array((string)$ch['type'],['messenger','instagram','telegram','x'],true))out(422,false,'Use v1/whatsapp/send para WhatsApp. Este endpoint es para canales sociales compatibles.');
  if($ch['status']!=='connected')out(409,false,'El canal no está conectado.');
  if(!zynkoPlanAllowsChannel(zynkoPlanContext(db(),$tid),'whatsapp'===($ch['type']??'')?'whatsapp':(string)$ch['type']))out(403,false,'Canal no incluido en el plan.');
  if(($j['consent_confirmed']??false)!==true)out(422,false,'Confirme el consentimiento de todos los destinatarios.');
  $targets=$j['to']??null;
  if(!is_array($targets)||!array_is_list($targets)||count($targets)<2||count($targets)>100)out(422,false,'Indique entre 2 y 100 destinatarios.');
  $text=trim((string)($j['message']??''));
  if($text===''||mb_strlen($text)>4096)out(422,false,'Indique un texto de máximo 4096 caracteres.');
  $recipients=[];
  foreach($targets as $item){
   if(!is_string($item)||!preg_match('/^[A-Za-z0-9_.:\-]{1,190}$/D',trim($item)))out(422,false,'Identificador de destinatario inválido.');
   $recipient=trim($item);$recipients[$recipient]=$recipient;
  }
  $recipients=array_values($recipients);
  $pdo=db();$idemKey='bulk:'.hash('sha256',(int)$key['id'].':'.$idem);
  $payloadHash=hash('sha256',json_encode($j,JSON_THROW_ON_ERROR));$result=[];$created=[];
  $pdo->beginTransaction();
  try{
   $tenantLock=$pdo->prepare('SELECT id FROM tenants WHERE id=? FOR UPDATE');$tenantLock->execute([$tid]);
   if(!$tenantLock->fetchColumn())throw new RuntimeException('Empresa no encontrada.');
   $q=$pdo->prepare('SELECT response_json,http_status FROM api_idempotency WHERE tenant_id=? AND idempotency_key=?');$q->execute([$tid,$idemKey]);
   if($old=$q->fetch()){
    $previous=json_decode((string)$old['response_json'],true)?:[];$pdo->commit();
    if(($previous['data']['request_hash']??'')!==$payloadHash)out(409,false,'La clave ya corresponde a otra solicitud.');
    http_response_code((int)$old['http_status']);echo $old['response_json'];exit;
   }
   $ctx=zynkoPlanContext($pdo,$tid,false);$gateway=new ChannelProviderGateway($pdo,$root);
   foreach($recipients as $destination){
    $q=$pdo->prepare('SELECT contact_id FROM contact_identities WHERE tenant_id=? AND channel_id=? AND external_id=? LIMIT 1');
    $q->execute([$tid,$channel,$destination]);$contactId=(int)$q->fetchColumn();
    if(!$contactId){
     $pdo->prepare('INSERT INTO contacts(tenant_id,uuid,name) VALUES(?,?,?)')->execute([$tid,uuid4(),$destination]);$contactId=(int)$pdo->lastInsertId();
     $pdo->prepare('INSERT INTO contact_identities(tenant_id,contact_id,channel_id,external_id) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE contact_id=VALUES(contact_id)')->execute([$tid,$contactId,$channel,$destination]);
    }
    $q=$pdo->prepare("SELECT id FROM conversations WHERE tenant_id=? AND channel_id=? AND contact_id=? AND api_only=? AND status IN ('open','pending') AND archived_at IS NULL AND deleted_at IS NULL ORDER BY id DESC LIMIT 1");
    $q->execute([$tid,$channel,$contactId,$apiOnly?1:0]);$conversationId=(int)$q->fetchColumn();
    if(!$conversationId){
     if(!$apiOnly)zynkoPlanRequireChatCapacity($pdo,$tid,$ctx,(string)$ch['type']);
     $pdo->prepare("INSERT INTO conversations(tenant_id,uuid,channel_id,contact_id,status,last_message_at,api_only) VALUES(?,?,?,?,'open',NOW(),?)")->execute([$tid,uuid4(),$channel,$contactId,$apiOnly?1:0]);
     $conversationId=(int)$pdo->lastInsertId();$created[]=$conversationId;
    }
    $uuid=uuid4();
    $pdo->prepare("INSERT INTO messages(tenant_id,conversation_id,uuid,direction,sender_type,type,body,status,sent_at) VALUES(?,?,?,'out','system','text',?,'queued',NOW())")->execute([$tid,$conversationId,$uuid,$text]);
    $messageId=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO channel_structured_outbox(message_id,tenant_id,channel_id,payload_json) VALUES(?,?,?,?)')->execute([$messageId,$tid,$channel,json_encode(['kind'=>'api_send','api_destination'=>$destination],JSON_THROW_ON_ERROR)]);
    if(!$gateway->enqueue($tid,$conversationId,$messageId))throw new RuntimeException('No se pudo registrar el despacho en cola.');
    $pdo->prepare('INSERT INTO api_message_history(tenant_id,api_key_id,channel_id,message_id,destination,delivery_mode) VALUES(?,?,?,?,?,?)')->execute([$tid,(int)$key['id'],$channel,$messageId,$destination,$apiOnly?'independent':'inbox']);
    $pdo->prepare('UPDATE conversations SET last_message_at=NOW() WHERE id=? AND tenant_id=?')->execute([$conversationId,$tid]);
    $result[]=['to'=>$destination,'message_uuid'=>$uuid,'message_id'=>$messageId,'status'=>'queued'];
   }
   $reply=['ok'=>true,'message'=>'Mensajes registrados en cola.','data'=>['request_hash'=>$payloadHash,'count'=>count($result),'messages'=>$result]];
   $encoded=json_encode($reply,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
   $pdo->prepare('INSERT INTO api_idempotency(tenant_id,idempotency_key,response_json,http_status,created_at) VALUES(?,?,?,?,NOW())')->execute([$tid,$idemKey,$encoded,202]);
   $pdo->commit();
  }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
  if(!$apiOnly)foreach($created as $cid)zynkoRealtimePublishSafe($pdo,$tid,'conversation.created',['conversation_id'=>$cid,'channel'=>$ch['type']],'conversation',(string)$cid);
  if(!$apiOnly)foreach($result as $item)zynkoRealtimePublishMessage($pdo,$tid,(int)$item['message_id'],['channel'=>$ch['type'],'sender'=>'system']);
  logReq($tid,(int)$key['id'],'v1/messages/bulk',$idem,202,$j);
  http_response_code(202);echo $encoded;exit;
 }

 if($method==='POST'&&$path==='v1/messages/send'){
  hasScope($key,'messages:send');if(!planAllows($tid,'api'))out(403,false,'El plan de esta empresa no incluye acceso API.');$j=body();$idem=trim($_SERVER['HTTP_IDEMPOTENCY_KEY']??'');if($apiOnly&&$idem==='')out(422,false,'Idempotency-Key obligatorio para envío independiente.');if($idem!==''){$q=db()->prepare('SELECT response_json,http_status FROM api_idempotency WHERE tenant_id=? AND idempotency_key=?');$q->execute([$tid,$idem]);if($old=$q->fetch()){http_response_code((int)$old['http_status']);echo $old['response_json'];exit;}}
  $channel=(int)($j['channel_id']??0);if($keyChannel>0&&$channel!==$keyChannel)out(403,false,'Esta clave API solo permite el canal seleccionado.');if(!is_string($j['to']??null))out(422,false,'to debe ser un número o identificador de texto; para varios números use v1/whatsapp/send.');$to=trim($j['to']);$name=trim((string)($j['contact_name']??$to));$text=trim((string)($j['message']??''));if($channel<1||$to===''||$text==='')out(422,false,'Requiere channel_id, to y message.');$q=db()->prepare('SELECT * FROM channels WHERE id=? AND tenant_id=?');$q->execute([$channel,$tid]);$ch=$q->fetch();if($ch&&!zynkoPlanAllowsChannel(zynkoPlanContext(db(),$tid,false),(string)$ch['type']))out(403,false,'Canal no incluido en el plan.');if(!$ch)out(404,false,'Canal no encontrado.');if($ch['status']!=='connected')out(409,false,'El canal existe pero aún no está conectado/autorizado con el proveedor.');
  $q=db()->prepare('SELECT contact_id FROM contact_identities WHERE tenant_id=? AND channel_id=? AND external_id=? LIMIT 1');$q->execute([$tid,$channel,$to]);$contact=(int)($q->fetchColumn()?:0);if(!$contact&&$ch['type']==='whatsapp'){$q=db()->prepare('SELECT id FROM contacts WHERE tenant_id=? AND phone=? LIMIT 1');$q->execute([$tid,$to]);$contact=(int)($q->fetchColumn()?:0);}if(!$contact){db()->prepare('INSERT INTO contacts(tenant_id,uuid,name,phone,email) VALUES(?,?,?,?,?)')->execute([$tid,uuid4(),$name,$ch['type']==='whatsapp'?$to:null,$ch['type']==='email'?$to:null]);$contact=(int)db()->lastInsertId();}db()->prepare('INSERT INTO contact_identities(tenant_id,contact_id,channel_id,external_id,profile_json) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE contact_id=VALUES(contact_id),profile_json=VALUES(profile_json)')->execute([$tid,$contact,$channel,$to,json_encode(['name'=>$name],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
  $q=db()->prepare("SELECT id FROM conversations WHERE tenant_id=? AND channel_id=? AND contact_id=? AND status IN ('open','pending') AND api_only=? ORDER BY id DESC LIMIT 1");$q->execute([$tid,$channel,$contact,$apiOnly?1:0]);$cid=(int)($q->fetchColumn()?:0);$newConversation=false;if(!$cid){$cid=zynkoPlanCreateChat(db(),$tid,$channel,$contact,(string)$ch['type'],uuid4(),false,$apiOnly);$newConversation=true;if(!$apiOnly)zynkoRealtimePublish(db(),$tid,'conversation.created',['conversation_id'=>$cid,'channel'=>$ch['type'],'contact_name'=>$name],'conversation',(string)$cid);}
  $uuid=uuid4();db()->prepare("INSERT INTO messages(tenant_id,conversation_id,uuid,direction,sender_type,type,body,status,sent_at) VALUES(?,?,?,'out','system','text',?,'queued',NOW())")->execute([$tid,$cid,$uuid,$text]);$messageId=(int)db()->lastInsertId();db()->prepare('INSERT INTO api_message_history(tenant_id,api_key_id,channel_id,message_id,destination,delivery_mode) VALUES(?,?,?,?,?,?)')->execute([$tid,(int)$key['id'],$channel,$messageId,$to,$apiOnly?'independent':'inbox']);db()->prepare('UPDATE conversations SET last_message_at=NOW() WHERE id=?')->execute([$cid]);if(!$apiOnly)zynkoRealtimePublishMessage(db(),$tid,$messageId,['channel'=>$ch['type'],'sender'=>'system']);$dispatch=[true,'Canal interno'];if(!in_array((string)$ch['type'],['webchat','email'],true))$dispatch=(new ChannelProviderGateway(db(),$root))->dispatch($ch,$to,$text,[],$messageId);$resp=['ok'=>$dispatch[0],'message'=>$dispatch[0]?'Mensaje aceptado y despachado por el canal.':'El proveedor rechazó el despacho.','data'=>['message_uuid'=>$uuid,'conversation_id'=>$cid,'status'=>$dispatch[2]['delivery_state']??($dispatch[0]?'sent':'failed'),'provider_status'=>$dispatch[2]['delivery_state']??($dispatch[0]?'sent':'failed'),'provider_message'=>$dispatch[1]]];$json=json_encode($resp,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if($idem!=='')db()->prepare('INSERT INTO api_idempotency(tenant_id,idempotency_key,response_json,http_status,created_at) VALUES(?,?,?,?,NOW())')->execute([$tid,$idem,$json,202]);logReq($tid,(int)$key['id'],'v1/messages/send',$idem,202,$j);http_response_code(202);echo $json;exit;
 }
 if($method==='POST'&&$path==='v1/messages/receive'){
  if($apiOnly)out(403,false,'Esta clave es exclusiva para envíos independientes.');
  hasAnyScope($key,['messages:receive','messages:send']);if(!planAllows($tid,'api'))out(403,false,'El plan de esta empresa no incluye acceso API.');$j=body();$idem=trim($_SERVER['HTTP_IDEMPOTENCY_KEY']??'');if($idem!==''){$q=db()->prepare('SELECT response_json,http_status FROM api_idempotency WHERE tenant_id=? AND idempotency_key=?');$q->execute([$tid,$idem]);if($old=$q->fetch()){http_response_code((int)$old['http_status']);echo $old['response_json'];exit;}}$channel=(int)($j['channel_id']??0);if($keyChannel>0&&$channel!==$keyChannel)out(403,false,'Esta clave API solo permite el canal seleccionado.');$from=trim((string)($j['from']??''));$name=trim((string)($j['contact_name']??$from));$text=trim((string)($j['message']??''));if($channel<1||$from===''||$text==='')out(422,false,'Requiere channel_id, from y message.');$q=db()->prepare('SELECT * FROM channels WHERE id=? AND tenant_id=?');$q->execute([$channel,$tid]);$ch=$q->fetch();if($ch&&!zynkoPlanAllowsChannel(zynkoPlanContext(db(),$tid,false),(string)$ch['type']))out(403,false,'Canal no incluido en el plan.');if(!$ch)out(404,false,'Canal no encontrado.');if($ch['status']!=='connected')out(409,false,'El canal todavía no está conectado/autorizado.');
  $q=db()->prepare('SELECT contact_id FROM contact_identities WHERE tenant_id=? AND channel_id=? AND external_id=? LIMIT 1');$q->execute([$tid,$channel,$from]);$contact=(int)($q->fetchColumn()?:0);if(!$contact&&$ch['type']==='whatsapp'){$q=db()->prepare('SELECT id FROM contacts WHERE tenant_id=? AND phone=? LIMIT 1');$q->execute([$tid,$from]);$contact=(int)($q->fetchColumn()?:0);}if(!$contact){db()->prepare('INSERT INTO contacts(tenant_id,uuid,name,phone,email) VALUES(?,?,?,?,?)')->execute([$tid,uuid4(),$name,$ch['type']==='whatsapp'?$from:null,$ch['type']==='email'?$from:null]);$contact=(int)db()->lastInsertId();}db()->prepare('INSERT INTO contact_identities(tenant_id,contact_id,channel_id,external_id,profile_json) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE contact_id=VALUES(contact_id),profile_json=VALUES(profile_json)')->execute([$tid,$contact,$channel,$from,json_encode(['name'=>$name],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
  $q=db()->prepare("SELECT id FROM conversations WHERE tenant_id=? AND channel_id=? AND contact_id=? AND status IN ('open','pending') AND archived_at IS NULL AND deleted_at IS NULL ORDER BY id DESC LIMIT 1");$q->execute([$tid,$channel,$contact]);$cid=(int)($q->fetchColumn()?:0);if(!$cid){$cid=zynkoPlanCreateChat(db(),$tid,$channel,$contact,(string)$ch['type'],uuid4());zynkoRealtimePublish(db(),$tid,'conversation.created',['conversation_id'=>$cid,'channel'=>$ch['type'],'contact_name'=>$name],'conversation',(string)$cid);}
  db()->prepare("INSERT INTO messages(tenant_id,conversation_id,uuid,direction,sender_type,type,body,status,sent_at) VALUES(?,?,?,'in','contact','text',?,'received',NOW())")->execute([$tid,$cid,uuid4(),$text]);$inboundMessageId=(int)db()->lastInsertId();db()->prepare("UPDATE conversations SET unread_count=unread_count+1,last_message_at=NOW(),status=IF(assigned_user_id IS NULL,'open',status),archived_at=NULL WHERE id=? AND tenant_id=?")->execute([$cid,$tid]);zynkoRealtimePublishMessage(db(),$tid,$inboundMessageId,['channel'=>$ch['type'],'sender'=>'contact']);zynkoRealtimePublish(db(),$tid,'conversation.updated',['conversation_id'=>$cid,'channel'=>$ch['type'],'reason'=>'inbound_message','message_id'=>$inboundMessageId],'conversation',(string)$cid);
  $ownership=db()->prepare('SELECT status,assigned_user_id FROM conversations WHERE id=? AND tenant_id=? LIMIT 1');$ownership->execute([$cid,$tid]);$ownerState=$ownership->fetch()?:[];$assignedUserId=(int)($ownerState['assigned_user_id']??0);
  $company=(string)(db()->query('SELECT name FROM tenants WHERE id='.(int)$tid)->fetchColumn()?:'la empresa');
  if($assignedUserId>0){$engine=['reply'=>null,'handoff'=>true,'source'=>'handoff:assigned','sources'=>[],'reason'=>'human_assigned'];}
  else{$automation=AutomationEngine::evaluate(db(),$tid,$cid,(string)$ch['type'],$text);$engine=($automation&&(!empty($automation['stop'])||!empty($automation['reply'])))?$automation:NivoEngine::evaluate(db(),$tid,$cid,$text,(string)$ch['type'],$name,$company);}
  $reply=$engine['reply']??null;$replyDelivery=null;if(!empty($engine['handoff'])&&$assignedUserId===0)db()->prepare("UPDATE conversations SET status='pending' WHERE id=? AND tenant_id=?")->execute([$cid,$tid]);
  if($reply&&$assignedUserId===0&&(ChannelSecurity::settings($ch)['attention_mode']??'nivo')==='nivo'){db()->prepare("INSERT INTO messages(tenant_id,conversation_id,uuid,direction,sender_type,type,body,status,sent_at) VALUES(?,?,?,'out','bot','text',?,'queued',NOW())")->execute([$tid,$cid,uuid4(),$reply]);$replyMessageId=(int)db()->lastInsertId();$replyDelivery=(new ChannelProviderGateway(db(),$root))->dispatch($ch,$from,$reply,[],$replyMessageId);db()->prepare("UPDATE messages SET status=? WHERE id=? AND tenant_id=?")->execute([$replyDelivery[2]['delivery_state']??($replyDelivery[0]?'sent':'failed'),$replyMessageId,$tid]);db()->prepare('UPDATE conversations SET last_message_at=NOW() WHERE id=? AND tenant_id=?')->execute([$cid,$tid]);zynkoRealtimePublishMessage(db(),$tid,$replyMessageId,['channel'=>$ch['type'],'sender'=>'bot']);zynkoRealtimePublish(db(),$tid,'conversation.updated',['conversation_id'=>$cid,'channel'=>$ch['type'],'reason'=>'bot_reply','message_id'=>$replyMessageId],'conversation',(string)$cid);}
  $resp=['ok'=>true,'message'=>'Mensaje recibido por ZYNKO.','data'=>['conversation_id'=>$cid,'channel'=>$ch['type'],'nivo'=>['reply'=>$reply,'handoff'=>(bool)($engine['handoff']??false),'source'=>$engine['source']??null,'sources'=>$engine['sources']??[],'reason'=>$engine['reason']??null],'delivery_note'=>$reply?($replyDelivery&&$replyDelivery[0]?'La respuesta de NIVO fue despachada por el canal conectado.':'La respuesta quedó registrada, pero el proveedor reportó un fallo de entrega.'):($assignedUserId>0?'La conversación está bajo atención humana; NIVO no respondió automáticamente.':null)]];$json=json_encode($resp,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if($idem!=='')db()->prepare('INSERT INTO api_idempotency(tenant_id,idempotency_key,response_json,http_status,created_at) VALUES(?,?,?,?,NOW())')->execute([$tid,$idem,$json,200]);logReq($tid,(int)$key['id'],'v1/messages/receive',$idem,200,$j);http_response_code(200);echo $json;exit;
 }
 if($method==='POST'&&$path==='v1/nivo/context'){
  hasScope($key,'nivo:context');
  try{$svc=new OpenAIProviderService(db(),dirname(__DIR__));$aiPolicy=$svc->tenantSettings($tid);$origins=$aiPolicy['allowed_origins']??[];$origin=trim((string)($_SERVER['HTTP_ORIGIN']??''));if($origin!==''&&$origins&&!in_array(rtrim(strtolower($origin),'/'),array_map(static fn($v)=>rtrim(strtolower((string)$v),'/'),$origins),true))out(403,false,'Este origen no está autorizado para utilizar NIVO IA.');}catch(Throwable $ignore){}
  $j=body();$query=trim((string)($j['query']??''));if($query==='')out(422,false,'Requiere query.');$words=array_values(array_filter(preg_split('/\W+/u',mb_strtolower($query)),fn($x)=>mb_strlen($x)>=4));$q=db()->prepare("SELECT id,name,content FROM knowledge_sources WHERE tenant_id=? AND status='ready' AND approval_status='approved' ORDER BY updated_at DESC LIMIT 100");$q->execute([$tid]);$hits=[];foreach($q->fetchAll() as $r){$hay=mb_strtolower(($r['name']??'').' '.($r['content']??''));$score=0;foreach($words as $w)if(str_contains($hay,$w))$score++;if($score)$hits[]=['id'=>$r['id'],'name'=>$r['name'],'content'=>mb_substr((string)$r['content'],0,2500),'score'=>$score];}usort($hits,fn($a,$b)=>$b['score']<=>$a['score']);out(200,true,'Contexto autorizado recuperado.',['query'=>$query,'sources'=>array_slice($hits,0,5),'note'=>'Este endpoint recupera conocimiento. La generación con IA se activa al configurar un proveedor/modelo en NIVO.']);
 }
 out(404,false,'Endpoint no encontrado.');
}catch(OverflowException $e){out(429,false,$e->getMessage());}catch(Throwable $e){out(500,false,'Error interno de ZYNKO API.',['reference'=>substr(hash('sha256',$e->getMessage().microtime()),0,12)]);}
