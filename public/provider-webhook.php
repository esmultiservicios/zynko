<?php
declare(strict_types=1);
$root=dirname(__DIR__);
require_once $root.'/app/Support/ChannelSecurity.php';
require_once $root.'/app/Support/ChannelSchema.php';
require_once $root.'/app/Services/ChannelIngress.php';
function webhookOut(int $code,array $data): never {http_response_code($code);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
$env=@parse_ini_file($root.'/.env',false,INI_SCANNER_RAW);$env=is_array($env)?$env:[];
try{
    $pdo=new PDO('mysql:host='.($env['DB_HOST']??'127.0.0.1').';port='.($env['DB_PORT']??3306).';dbname='.($env['DB_DATABASE']??'zynko').';charset=utf8mb4',$env['DB_USERNAME']??'root',$env['DB_PASSWORD']??'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>true]);
    $uuid=(string)($_GET['channel']??'');$hint=null;
    if($uuid!==''){if(!preg_match('/^[0-9a-f-]{36}$/iD',$uuid))webhookOut(404,['ok'=>false]);$q=$pdo->prepare('SELECT * FROM channels WHERE uuid=? LIMIT 1');$q->execute([$uuid]);$hint=$q->fetch();if(!$hint)webhookOut(404,['ok'=>false]);}
    $method=$_SERVER['REQUEST_METHOD']??'GET';
    if($method==='GET'){
        $expected=$hint?(string)(ChannelSecurity::settings($hint)['verify_token']??''):(string)($env['META_WEBHOOK_VERIFY_TOKEN']??'');
        if($expected!==''&&($_GET['hub_mode']??'subscribe')==='subscribe'&&hash_equals($expected,(string)($_GET['hub_verify_token']??''))){header('Content-Type: text/plain; charset=utf-8');echo (string)($_GET['hub_challenge']??'');exit;}
        webhookOut(403,['ok'=>false]);
    }
    if($method!=='POST')webhookOut(405,['ok'=>false]);
    $raw=file_get_contents('php://input',false,null,0,18*1024*1024+1)?:'';if(strlen($raw)>18*1024*1024)webhookOut(413,['ok'=>false]);$payload=json_decode($raw,true);if(!is_array($payload))webhookOut(400,['ok'=>false]);
    $ingress=new ChannelIngress($pdo,$root);$processed=0;
    if(isset($payload['session_id'],$payload['event'])&&isset($_SERVER['HTTP_X_ZYNKO_QR_TOKEN'])){
        $session=(string)$payload['session_id'];$rows=$ingress->findQr($session);if(count($rows)!==1)webhookOut(count($rows)>1?409:404,['ok'=>false]);$ch=$rows[0];$token=ChannelSecurity::secret((string)($ch['token_ciphertext']??''),$env);
        if($token===''||!hash_equals($token,(string)$_SERVER['HTTP_X_ZYNKO_QR_TOKEN']))webhookOut(403,['ok'=>false]);
        ChannelSchema::ensure($pdo);
        if($payload['event']==='status'){$ingress->recordHealth($ch,!empty($payload['connected'])?'connected':'warning',(string)($payload['phone']??''));webhookOut(200,['ok'=>true]);}
        if($payload['event']==='message'){
            $from=(string)($payload['from']??'');if(!preg_match('/^[0-9]+(?::[0-9]+)?@s\.whatsapp\.net$/D',$from))webhookOut(200,['ok'=>true,'processed'=>0]);$from=preg_replace('/[:@].*$/','',$from);$media=[];$m=$payload['attachment']??null;
            if(is_array($m)&&isset($m['id'])&&preg_match('/^[a-f0-9]{64}$/D',(string)$m['id']))$media[]=['provider_id'=>(string)$m['id'],'provider_type'=>(string)($m['type']??'document'),'name'=>(string)($m['name']??'Adjunto'),'mime'=>(string)($m['mime']??'')];
            $text=trim((string)($payload['text']??''));if($text===''&&$media)$text='['.$media[0]['provider_type'].']';
            $processed=$ingress->handle($ch,[ChannelWebhookEvents::message($from,$text,(string)($payload['message_id']??''),(string)($payload['push_name']??''),!empty($payload['from_me']),$media,(int)($payload['timestamp']??0))]);
        }
        webhookOut(200,['ok'=>true,'processed'=>$processed]);
    }
    if($hint&&($hint['type']??'')==='telegram'){
        $secret=(string)(ChannelSecurity::settings($hint)['telegram_webhook_secret']??ChannelSecurity::settings($hint)['verify_token']??'');
        if($secret===''||!hash_equals($secret,(string)($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN']??'')))webhookOut(403,['ok'=>false]);
        ChannelSchema::ensure($pdo);$m=$payload['message']??$payload['edited_message']??null;
        if(is_array($m)){$from=(string)($m['chat']['id']??'');$text=(string)($m['text']??$m['caption']??'');$media=[];foreach(['photo','video','voice','audio','document','sticker'] as $kind){$f=$m[$kind]??null;if($kind==='photo'&&is_array($f))$f=end($f);if(is_array($f)&&!empty($f['file_id'])){$media[]=['provider_id'=>(string)$f['file_id'],'provider_type'=>$kind,'name'=>(string)($f['file_name']??$kind),'mime'=>(string)($f['mime_type']??'')];if($text==='')$text='['.$kind.']';break;}}$processed=$ingress->handle($hint,[ChannelWebhookEvents::message($from,$text,(string)($m['message_id']??''),(string)($m['from']['first_name']??''),false,$media,(int)($m['date']??0))]);}
        webhookOut(200,['ok'=>true,'processed'=>$processed]);
    }
    $signature=(string)($_SERVER['HTTP_X_HUB_SIGNATURE_256']??'');$globalSigned=ChannelSecurity::verifiesMeta($raw,$signature,(string)($env['META_APP_SECRET']??''));
    $hintSigned=$hint&&ChannelSecurity::verifiesMeta($raw,$signature,ChannelSecurity::metaSecret($hint,$env));
    if(!$globalSigned&&!$hintSigned)webhookOut(403,['ok'=>false]);
    ChannelSchema::ensure($pdo);
    foreach(ChannelWebhookEvents::meta($payload) as $batch){
        if($batch['type']==='whatsapp_template'){
            $q=$pdo->prepare("SELECT ch.* FROM channels ch JOIN tenants t ON t.id=ch.tenant_id WHERE ch.type='whatsapp' AND (ch.external_account_id=? OR JSON_UNQUOTE(JSON_EXTRACT(ch.settings_json,'$.business_id'))=?) AND ch.status<>'disconnected' AND t.status NOT IN ('suspended','closed')");$q->execute([$batch['account'],$batch['account']]);$matches=[];foreach($q->fetchAll() as $ch){if(ChannelSecurity::mode($ch)==='qr_bridge'||!ChannelSecurity::verifiesMeta($raw,$signature,ChannelSecurity::metaSecret($ch,$env)))continue;$matches[(int)$ch['tenant_id']][]=$ch;}
            // A WABA shared across different tenants is not assigned implicitly.
            if(count($matches)===1)foreach(reset($matches) as $ch)foreach($batch['events'] as $event){if(!preg_match('/^[0-9]{1,30}$/D',(string)$event['external']))continue;$pdo->prepare('INSERT INTO channel_template_events(channel_id,tenant_id,provider_template_id,template_name,language,event_status) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE template_name=VALUES(template_name),language=VALUES(language),event_status=VALUES(event_status)')->execute([(int)$ch['id'],(int)$ch['tenant_id'],$event['external'],mb_substr($event['name'],0,160),mb_substr($event['language'],0,20),mb_substr($event['status'],0,40)]);$processed++;}continue;
        }
        $channels=$ingress->findMeta($batch['type'],$batch['asset'],$batch['account']);$authorized=[];
        foreach($channels as $channel){if(ChannelSecurity::verifiesMeta($raw,$signature,ChannelSecurity::metaSecret($channel,$env)))$authorized[]=$channel;}
        // Ambiguous legacy bindings are never routed to an arbitrary tenant.
        if(count($authorized)!==1){if(count($authorized)>1)foreach($authorized as $ch)$ingress->audit($ch,'error','Activo ambiguo: webhook aislado hasta resolver conexión duplicada.');continue;}
        $processed+=$ingress->handle($authorized[0],$batch['events']);
    }
    webhookOut(200,['ok'=>true,'processed'=>$processed]);
}catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();error_log('ZYNKO provider webhook processing failed ('.get_class($e).')');webhookOut(503,['ok'=>false,'message'=>'No se pudo procesar el evento.']);}
