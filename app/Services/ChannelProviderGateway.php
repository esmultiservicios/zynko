<?php
declare(strict_types=1);
require_once __DIR__.'/../Support/ChannelSecurity.php';
require_once __DIR__.'/../Support/Realtime.php';
require_once __DIR__.'/../Support/Plan.php';
require_once __DIR__.'/../Support/ChannelIdentity.php';
require_once __DIR__.'/ChannelTemplateComponents.php';
require_once __DIR__.'/ChannelStructuredMessage.php';

/** An explicit retryable refusal is different from an unknown delivery result. */
final class ChannelDispatchException extends RuntimeException
{
    public function __construct(string $message,public string $deliveryState='failed',public int $retryAfter=60){parent::__construct($message);}
}

final class ChannelProviderGateway
{
    private array $env;
    public function __construct(private PDO $pdo,private string $root){$e=@parse_ini_file($root.'/.env',false,INI_SCANNER_RAW);$this->env=is_array($e)?$e:[];}
    public function enqueue(int $tenantId,int $conversationId,int $messageId): bool
    {
        $q=$this->pdo->prepare("INSERT IGNORE INTO channel_outbox(message_id,tenant_id,conversation_id,channel_id) SELECT m.id,m.tenant_id,c.id,c.channel_id FROM messages m JOIN conversations c ON c.id=m.conversation_id AND c.tenant_id=m.tenant_id JOIN channels ch ON ch.id=c.channel_id AND ch.tenant_id=c.tenant_id WHERE m.id=? AND m.tenant_id=? AND c.id=? AND m.direction='out' AND ch.type NOT IN ('webchat','email')");$q->execute([$messageId,$tenantId,$conversationId]);return $q->rowCount()>0;
    }
    public function dispatchByConversation(int $tenantId,int $conversationId,int $messageId): array
    {
        $this->enqueue($tenantId,$conversationId,$messageId);return $this->dispatchQueued($tenantId,$messageId,$conversationId);
    }
    public function dispatch(array $channel,string $destination,string $body,array $media=[],?int $messageId=null): array
    {
        if($messageId){$q=$this->pdo->prepare('SELECT m.conversation_id FROM messages m JOIN conversations c ON c.id=m.conversation_id AND c.tenant_id=m.tenant_id WHERE m.id=? AND m.tenant_id=? AND c.channel_id=?');$q->execute([$messageId,(int)$channel['tenant_id'],(int)$channel['id']]);$cid=(int)$q->fetchColumn();if(!$cid)return [false,'Mensaje no pertenece al canal.'];return $this->dispatchByConversation((int)$channel['tenant_id'],$cid,$messageId);}
        try{$responses=$this->sendParts($channel,$destination,$body,$media);return [true,'Mensaje aceptado por el proveedor.',end($responses)?:[]];}catch(Throwable $e){return [false,$e instanceof ChannelDispatchException?$e->getMessage():'No se pudo despachar el mensaje.'];}
    }
    public function dispatchQueued(int $tenantId,int $messageId,?int $conversationId=null): array
    {
        $params=[$messageId,$tenantId];$where='message_id=? AND tenant_id=?';if($conversationId!==null){$where.=' AND conversation_id=?';$params[]=$conversationId;}
        $q=$this->pdo->prepare("UPDATE channel_outbox SET state='sending',attempts=attempts+1,claimed_at=NOW() WHERE $where AND state IN ('queued','retrying') AND next_attempt_at<=NOW()");$q->execute($params);
        if(!$q->rowCount()){$q=$this->pdo->prepare("SELECT state,last_error FROM channel_outbox WHERE $where");$q->execute($params);$r=$q->fetch();$state=(string)($r['state']??'failed');return [in_array($state,['sent','queued','retrying','sending'],true),$r['last_error']??'Estado: '.$state,['delivery_state'=>$state]];}
        $state='failed';$detail='No se pudo enviar el mensaje.';$retry=60;$accepted=0;$attempts=1;
        try{
            $q=$this->pdo->prepare("SELECT ch.*,m.id AS local_message_id,m.body,m.media_json,m.sender_type,c.assigned_user_id,c.id AS local_conversation_id,o.attempts,ci.external_id AS destination FROM channel_outbox o JOIN messages m ON m.id=o.message_id AND m.tenant_id=o.tenant_id JOIN conversations c ON c.id=m.conversation_id AND c.tenant_id=m.tenant_id AND c.id=o.conversation_id AND c.channel_id=o.channel_id JOIN channels ch ON ch.id=c.channel_id AND ch.tenant_id=c.tenant_id JOIN contact_identities ci ON ci.contact_id=c.contact_id AND ci.channel_id=ch.id AND ci.tenant_id=c.tenant_id WHERE o.message_id=? AND o.tenant_id=? AND m.direction='out' AND c.deleted_at IS NULL ORDER BY ci.id LIMIT 1");$q->execute([$messageId,$tenantId]);$row=$q->fetch();if(!$row)throw new ChannelDispatchException('Mensaje o destino no disponible.');$attempts=(int)$row['attempts'];if($row['sender_type']==='bot'&&((int)$row['assigned_user_id']>0||(ChannelSecurity::settings($row)['attention_mode']??'nivo')!=='nivo'))throw new ChannelDispatchException('La conversación pasó a atención humana o externa.');
            $prior=$this->pdo->prepare('SELECT COUNT(*) FROM channel_message_receipts WHERE tenant_id=? AND message_id=?');$prior->execute([$tenantId,$messageId]);if((int)$prior->fetchColumn()>0)throw new ChannelDispatchException('Existe un envío previo; no se repetirá automáticamente.','uncertain');
            $media=json_decode((string)($row['media_json']??''),true)?:[];
            $record=function(array $response)use($row,$messageId,$tenantId,&$accepted){$accepted++;$external=$this->providerId($response);if($external==='')throw new ChannelDispatchException('El proveedor no devolvió un identificador de mensaje.','uncertain');$this->rememberReceipt($row,$messageId,$external,(string)$row['destination']);};
            $sq=$this->pdo->prepare('SELECT payload_json FROM channel_structured_outbox WHERE message_id=? AND tenant_id=? AND channel_id=?');$sq->execute([$messageId,$tenantId,(int)$row['id']]);$structured=$sq->fetchColumn();
            if($structured!==false){$record($this->sendStructured($row,(string)$row['destination'],json_decode((string)$structured,true,32,JSON_THROW_ON_ERROR),$messageId));}
            else $this->sendParts($row,(string)$row['destination'],(string)($row['body']??''),$media,$record);
            $state='sent';$detail='Mensaje aceptado por el proveedor.';
        }catch(Throwable $e){$state=$e instanceof ChannelDispatchException?$e->deliveryState:'uncertain';$detail=$e instanceof ChannelDispatchException?$e->getMessage():'Resultado del proveedor no verificable.';$retry=$e instanceof ChannelDispatchException?$e->retryAfter:60;if($accepted>0){$state='partial';$detail='El proveedor aceptó parte del mensaje. No se reenviará automáticamente.';}elseif($state==='retrying'&&$attempts>=5){$state='failed';$detail='Se agotaron los reintentos de envío.';}}
        $this->pdo->prepare("UPDATE channel_outbox SET state=?,last_error=?,next_attempt_at=DATE_ADD(NOW(),INTERVAL ? SECOND),claimed_at=NULL WHERE message_id=? AND tenant_id=?")->execute([$state,$state==='sent'?null:mb_substr($detail,0,500),max($retry,min(900,30*(2**$attempts))),$messageId,$tenantId]);
        // Do not regress a status which a webhook has already advanced.
        $this->pdo->prepare("UPDATE messages SET status=? WHERE id=? AND tenant_id=? AND COALESCE(status,'') NOT IN ('delivered','read')")->execute([$state,$messageId,$tenantId]);
        if($state==='sent')$this->aggregateReceipts($tenantId,$messageId);
        $msg=zynkoRealtimeMessage($this->pdo,$tenantId,$messageId);if($msg)zynkoRealtimePublishSafe($this->pdo,$tenantId,'message.status',['conversation_id'=>(int)$msg['conversation_id'],'message_id'=>$messageId,'message'=>$msg],'message',(string)$messageId);
        return [in_array($state,['sent','retrying'],true),$detail,['delivery_state'=>$state]];
    }
    public function processQueue(int $limit=30): array
    {
        $limit=max(1,min(100,$limit));$this->pdo->exec("UPDATE channel_outbox o JOIN messages m ON m.id=o.message_id AND m.tenant_id=o.tenant_id SET o.state='uncertain',o.last_error='Proceso interrumpido; revisar antes de reenviar.',m.status=IF(m.status IN ('delivered','read','sent'),m.status,'uncertain') WHERE o.state='sending' AND o.claimed_at<DATE_SUB(NOW(),INTERVAL 10 MINUTE)");
        $q=$this->pdo->query("SELECT tenant_id,message_id FROM channel_outbox WHERE state IN ('queued','retrying') AND next_attempt_at<=NOW() ORDER BY next_attempt_at,message_id LIMIT $limit");$stats=[];foreach($q->fetchAll() as $r){$x=$this->dispatchQueued((int)$r['tenant_id'],(int)$r['message_id']);$s=$x[2]['delivery_state']??'failed';$stats[$s]=($stats[$s]??0)+1;}return $stats;
    }
    private function providerId(array $r): string {return (string)($r['messages'][0]['id']??$r['message_id']??$r['result']['message_id']??$r['data']['dm_event_id']??'');}
    private function receiptKey(array $ch,string $id,string $destination): string {return hash('sha256',$id.(($ch['type']??'')==='telegram'?'|'.$destination:''));}
    private function rememberReceipt(array $ch,int $mid,string $id,string $destination): void
    {
        $key=$this->receiptKey($ch,$id,$destination);$tid=(int)$ch['tenant_id'];$cid=(int)$ch['id'];
        $this->pdo->prepare("INSERT INTO channel_message_receipts(channel_id,tenant_id,provider_key,provider_id,destination,message_id) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE provider_id=VALUES(provider_id)")->execute([$cid,$tid,$key,$id,$destination,$mid]);
        $p=$this->pdo->prepare('SELECT delivery_status FROM channel_pending_status WHERE tenant_id=? AND channel_id=? AND provider_key=?');$p->execute([$tid,$cid,$key]);if($st=$p->fetchColumn())$this->applyStatus($ch,$id,(string)$st,$destination);
    }
    public function applyStatus(array $ch,string $id,string $status,string $destination=''): void
    {
        if($id===''||!in_array($status,['sent','delivered','read','failed'],true))return;$tid=(int)$ch['tenant_id'];$cid=(int)$ch['id'];$key=$this->receiptKey($ch,$id,$destination);
        $q=$this->pdo->prepare('SELECT message_id,delivery_status FROM channel_message_receipts WHERE tenant_id=? AND channel_id=? AND provider_key=?');$q->execute([$tid,$cid,$key]);$r=$q->fetch();
        if(!$r){$p=$this->pdo->prepare('SELECT delivery_status FROM channel_pending_status WHERE tenant_id=? AND channel_id=? AND provider_key=?');$p->execute([$tid,$cid,$key]);$status=ChannelSecurity::nextStatus((string)($p->fetchColumn()?:''),$status);$this->pdo->prepare('INSERT INTO channel_pending_status(channel_id,tenant_id,provider_key,delivery_status) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE delivery_status=VALUES(delivery_status)')->execute([$cid,$tid,$key,$status]);return;}
        $old=(string)$r['delivery_status'];$next=ChannelSecurity::nextStatus($old,$status);$this->pdo->prepare('UPDATE channel_message_receipts SET delivery_status=? WHERE tenant_id=? AND channel_id=? AND provider_key=? AND delivery_status=?')->execute([$next,$tid,$cid,$key,$old]);$this->pdo->prepare('DELETE FROM channel_pending_status WHERE tenant_id=? AND channel_id=? AND provider_key=?')->execute([$tid,$cid,$key]);$this->aggregateReceipts($tid,(int)$r['message_id']);
    }
    public function applyWatermark(array $ch,string $destination,int $watermark,string $status): void
    {
        if(($ch['type']??'')!=='messenger'||$destination===''||$watermark<1000000000000||!in_array($status,['read','delivered'],true))return;
        $at=date('Y-m-d H:i:s',(int)floor($watermark/1000));$q=$this->pdo->prepare("SELECT provider_id FROM channel_message_receipts WHERE tenant_id=? AND channel_id=? AND destination=? AND updated_at<=? AND delivery_status IN ('sent','delivered') LIMIT 500");$q->execute([(int)$ch['tenant_id'],(int)$ch['id'],$destination,$at]);foreach($q->fetchAll(PDO::FETCH_COLUMN) as $id)$this->applyStatus($ch,(string)$id,$status,$destination);
    }
    private function aggregateReceipts(int $tid,int $mid): void
    {
        $q=$this->pdo->prepare('SELECT delivery_status FROM channel_message_receipts WHERE tenant_id=? AND message_id=?');$q->execute([$tid,$mid]);$states=$q->fetchAll(PDO::FETCH_COLUMN);if(!$states)return;
        $q=$this->pdo->prepare('SELECT state FROM channel_outbox WHERE tenant_id=? AND message_id=?');$q->execute([$tid,$mid]);if($q->fetchColumn()!=='sent')return;
        $status=in_array('failed',$states,true)?(count(array_unique($states))===1?'failed':'partial'):(count(array_filter($states,fn($v)=>$v==='read'))===count($states)?'read':(count(array_filter($states,fn($v)=>in_array($v,['delivered','read'],true)))===count($states)?'delivered':'sent'));
        $this->pdo->prepare('UPDATE messages SET status=? WHERE tenant_id=? AND id=?')->execute([$status,$tid,$mid]);$m=zynkoRealtimeMessage($this->pdo,$tid,$mid);if($m)zynkoRealtimePublishSafe($this->pdo,$tid,'message.status',['conversation_id'=>(int)$m['conversation_id'],'message_id'=>$mid,'message'=>$m],'message',(string)$mid);
    }
    public function isOwnEcho(array $channel,string $id,string $destination=''): bool
    {
        $q=$this->pdo->prepare('SELECT 1 FROM channel_message_receipts WHERE tenant_id=? AND channel_id=? AND provider_key=?');$q->execute([(int)$channel['tenant_id'],(int)$channel['id'],$this->receiptKey($channel,$id,$destination)]);return (bool)$q->fetchColumn();
    }
    private function requireConnected(array $ch): void
    {
        if(!empty(ChannelSecurity::settings($ch)['phone_conflict']))throw new ChannelDispatchException('Existe un conflicto de titularidad del número.');
        if(($ch['status']??'')!=='connected')throw new ChannelDispatchException('El canal no está conectado.');
        $q=$this->pdo->prepare('SELECT status FROM tenants WHERE id=?');$q->execute([(int)$ch['tenant_id']]);if(in_array((string)$q->fetchColumn(),['suspended','closed'],true))throw new ChannelDispatchException('La empresa no está habilitada.');
        $main=(int)$this->pdo->query("SELECT id FROM tenants WHERE LOWER(REPLACE(TRIM(name),' ','')) IN ('esmultiservicios','esmultsiervicios') OR LOWER(slug) LIKE 'es-multiservicios%' OR LOWER(slug) LIKE 'es-multsiervicios%' ORDER BY id LIMIT 1")->fetchColumn();if(!$main)$main=(int)$this->pdo->query('SELECT MIN(id) FROM tenants')->fetchColumn();if(!zynkoPlanAllowsChannel(zynkoPlanContext($this->pdo,(int)$ch['tenant_id'],(int)$ch['tenant_id']===$main),(string)$ch['type']))throw new ChannelDispatchException('Canal no incluido en el plan activo.');
        if(ChannelSecurity::tokenExpired($ch)){$this->markWarning($ch);throw new ChannelDispatchException('La autorización venció. Vuelva a vincular o renovar el canal.');}
    }
    private function markWarning(array $ch): void {$this->pdo->prepare("UPDATE channels SET status='warning' WHERE id=? AND tenant_id=? AND status='connected'")->execute([(int)$ch['id'],(int)$ch['tenant_id']]);}
    private function sendStructured(array $ch,string $to,array $data,int $messageId): array
    {
        $this->requireConnected($ch);$p=ChannelStructuredMessage::build($ch,$data);$kind=$p['kind'];$token=$this->token($ch);if($to===''||$token==='')throw new ChannelDispatchException('Destino o autorización no disponible.');
        if($kind==='template')return $this->sendApprovedTemplate($ch,$p['template_id'],$to,$p['values']);
        if($ch['type']==='telegram'){$r=$this->http('POST','https://api.telegram.org/bot'.$token.'/sendLocation',['chat_id'=>$to,'latitude'=>$p['latitude'],'longitude'=>$p['longitude']]);$this->check($ch,$r);if(empty($r['json']['ok']))throw new ChannelDispatchException('Telegram no confirmó el envío.');return $r['json'];}
        $wa=['messaging_product'=>'whatsapp','to'=>$to];if($kind==='location'){$wa['type']='location';$wa['location']=['latitude'=>$p['latitude'],'longitude'=>$p['longitude']];}else{$buttons=[];foreach($p['buttons'] as $i=>$label)$buttons[]=['type'=>'reply','reply'=>['id'=>'z_'.$messageId.'_'.($i+1),'title'=>$label]];$wa['type']='interactive';$wa['interactive']=['type'=>'button','body'=>['text'=>$p['body']],'action'=>['buttons'=>$buttons]];}
        $r=$this->http('POST',$this->graph().'/'.rawurlencode(ChannelSecurity::asset($ch)).'/messages',$wa,$this->auth($token));$this->check($ch,$r);return $r['json'];
    }
    private function sendParts(array $ch,string $to,string $body,array $media,?callable $accepted=null): array
    {
        $type=(string)$ch['type'];if($type==='x'){require_once __DIR__.'/ChannelXOAuth.php';if(ChannelSecurity::enabled($this->env,'X_DM_ENABLED'))$ch=(new ChannelXOAuth($this->pdo,$this->root))->refresh($ch);}$this->requireConnected($ch);$s=ChannelSecurity::settings($ch);$mode=ChannelSecurity::mode($ch);$responses=[];
        if(trim($to)==='')throw new ChannelDispatchException('Destino no disponible.');
        if($media&&$type==='x')throw new ChannelDispatchException('Este conector aún no admite adjuntos.');
        $files=[];foreach($media as $f){$files[]=$this->localAttachment($ch,$f);} // validate all before sending any part
        $emit=function(array $response)use(&$responses,$accepted){$responses[]=$response;if($accepted)$accepted($response);};
        if(trim($body)!=='')$emit($this->sendPart($ch,$s,$mode,$to,$body,null));
        foreach($files as $f)$emit($this->sendPart($ch,$s,$mode,$to,'',$f));
        if(!$responses)throw new ChannelDispatchException('Escribe un mensaje o adjunta un archivo.');return $responses;
    }
    private function localAttachment(array $ch,array $f): array
    {
        $url=(string)($f['url']??'');$tid=(int)$ch['tenant_id'];$base=realpath($this->root.'/public/uploads');
        if(!preg_match('#^uploads/(?:chat|quick-replies|quick_replies)/'.preg_quote((string)$tid,'#').'/[A-Za-z0-9_./-]+$#D',$url)||str_contains($url,'..')||!$base)throw new ChannelDispatchException('Adjunto fuera del espacio de la empresa.');
        $path=realpath($this->root.'/public/'.$url);if(!$path||!str_starts_with($path,$base.DIRECTORY_SEPARATOR)||!is_file($path)||filesize($path)>10*1024*1024)throw new ChannelDispatchException('Adjunto no disponible o superior a 10 MB.');
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($path)?:'application/octet-stream';$type=str_starts_with($mime,'image/')?'image':(str_starts_with($mime,'audio/')?'audio':(str_starts_with($mime,'video/')?'video':'document'));
        return ['path'=>$path,'mime'=>$mime,'name'=>basename((string)($f['name']??basename($path))),'type'=>$type,'url'=>$url];
    }
    private function sendPart(array $ch,array $s,string $mode,string $to,string $text,?array $f): array
    {
        $type=(string)$ch['type'];$token=$this->token($ch);if($token==='')throw new ChannelDispatchException('Falta autorización del proveedor.');
        if($type==='whatsapp'&&$mode==='qr_bridge'){$base=$this->bridgeBase($ch);$payload=['to'=>$to,'text'=>$text];if($f)$payload['attachment']=['name'=>$f['name'],'mime'=>$f['mime'],'type'=>$f['type'],'data'=>base64_encode(file_get_contents($f['path']))];$r=$this->http('POST',$base.'/api/sessions/'.rawurlencode((string)$s['session_id']).'/send',$payload,$this->bridgeHeaders($ch));$this->check($ch,$r);if(empty($r['json']['ok']))throw new ChannelDispatchException('El bridge no confirmó el envío.','uncertain');return $r['json'];}
        if($type==='whatsapp'){$phone=ChannelSecurity::asset($ch);if($phone==='')throw new ChannelDispatchException('Falta Phone Number ID.');$p=['messaging_product'=>'whatsapp','to'=>$to,'type'=>'text','text'=>['body'=>$text]];if($f){$up=$this->http('POST',$this->graph().'/'.rawurlencode($phone).'/media',['messaging_product'=>'whatsapp','file'=>new CURLFile($f['path'],$f['mime'],$f['name'])],$this->auth($token),true);$this->check($ch,$up,false);$id=$up['json']['id']??'';if($id==='')throw new ChannelDispatchException('Meta no devolvió el ID del adjunto.');$p=['messaging_product'=>'whatsapp','to'=>$to,'type'=>$f['type'],$f['type']=>['id'=>$id]];if($f['type']==='document')$p['document']['filename']=$f['name'];}$r=$this->http('POST',$this->graph().'/'.rawurlencode($phone).'/messages',$p,$this->auth($token));$this->check($ch,$r);return $r['json'];}
        if(in_array($type,['messenger','instagram'],true)){$page=ChannelSecurity::asset($ch);if($page==='')throw new ChannelDispatchException('Falta ID de página/cuenta.');$msg=['text'=>$text];if($f){$app=rtrim((string)($this->env['APP_URL']??''),'/');if(!str_starts_with($app,'https://'))throw new ChannelDispatchException('Se necesita APP_URL HTTPS para adjuntos de Meta.');$msg=['attachment'=>['type'=>$f['type']==='document'?'file':$f['type'],'payload'=>['url'=>$app.'/'.$f['url']]]];} // URLs of existing uploaded attachments, no user arbitrary remote URLs
            $p=['recipient'=>['id'=>$to],'message'=>$msg];if($type==='messenger')$p['messaging_type']='RESPONSE';$r=$this->http('POST',$this->graph().'/'.rawurlencode($page).'/messages',$p,$this->auth($token));$this->check($ch,$r);return $r['json'];}
        if($type==='telegram'){$method='sendMessage';$p=['chat_id'=>$to,'text'=>$text];if($f){$field=['image'=>'photo','audio'=>'audio','video'=>'video','document'=>'document'][$f['type']];$method='send'.ucfirst($field);$p=['chat_id'=>$to,$field=>new CURLFile($f['path'],$f['mime'],$f['name'])];}$r=$this->http('POST','https://api.telegram.org/bot'.$token.'/'.$method,$p,[],(bool)$f);$this->check($ch,$r);if(empty($r['json']['ok']))throw new ChannelDispatchException('Telegram no confirmó el envío.');return $r['json'];}
        if($type==='x'){$r=$this->http('POST','https://api.x.com/2/dm_conversations/with/'.rawurlencode($to).'/messages',['text'=>$text],$this->auth($token));$this->check($ch,$r);return $r['json'];}
        throw new ChannelDispatchException('Este canal no tiene un despachador autorizado.');
    }
    public function replyToInstagramComment(array $ch,string $commentId,string $text): array
    {
        $this->requireConnected($ch);if(($ch['type']??'')!=='instagram'||!preg_match('/^[0-9]+$/D',$commentId))throw new ChannelDispatchException('Comentario inválido.');
        $r=$this->http('POST',$this->graph().'/'.rawurlencode(ChannelSecurity::asset($ch)).'/messages',['recipient'=>['comment_id'=>$commentId],'message'=>['text'=>$text]],$this->auth($this->token($ch)));$this->check($ch,$r);return $r['json'];
    }
    public function qrStart(array $channel): array
    {
        try{if(ChannelSecurity::mode($channel)!=='qr_bridge')return [false,'Este canal no usa WhatsApp por QR.',[]];$base=$this->bridgeBase($channel);$s=ChannelSecurity::settings($channel);$r=$this->http('POST',$base.'/api/sessions/'.rawurlencode($s['session_id']).'/start',[],$this->bridgeHeaders($channel));$ok=$r['status']===200&&!empty($r['json']['ok']);return [$ok,$ok?'Sesión iniciada.':'No fue posible iniciar la sesión.',$r['json']];}catch(Throwable $e){return [false,$e instanceof ChannelDispatchException?$e->getMessage():'Bridge no disponible.',[]];}
    }
    public function qrPair(array $channel,string $phone): array
    {
        if(ChannelSecurity::mode($channel)!=='qr_bridge')throw new ChannelDispatchException('El canal no pertenece al bridge QR.');$phone=ChannelIdentity::phone($phone);if($phone==='')throw new ChannelDispatchException('Introduzca el número internacional completo.');
        $s=ChannelSecurity::settings($channel);$r=$this->http('POST',$this->bridgeBase($channel).'/api/sessions/'.rawurlencode((string)$s['session_id']).'/pair',['phone'=>$phone],$this->bridgeHeaders($channel));$this->check($channel,$r,false);$code=(string)($r['json']['code']??'');if(!preg_match('/^[A-Z0-9-]{6,16}$/D',$code))throw new ChannelDispatchException('El bridge aún no devolvió un código de vinculación.');return ['code'=>$code];
    }
    public function qrUnlink(array $channel): bool
    {
        if(ChannelSecurity::mode($channel)!=='qr_bridge')throw new ChannelDispatchException('El canal no pertenece al bridge QR.');$s=ChannelSecurity::settings($channel);$r=$this->http('DELETE',$this->bridgeBase($channel).'/api/sessions/'.rawurlencode((string)$s['session_id']),null,$this->bridgeHeaders($channel));$this->check($channel,$r,false);return !empty($r['json']['ok']);
    }
    public function dispatchWhatsAppCampaign(array $ch,string $to,string $body,?string $templateName=null,string $language='es'): array
    {
        try{$this->requireConnected($ch);if(ChannelSecurity::mode($ch)==='qr_bridge'||!$templateName)return $this->dispatch($ch,$to,$body);$r=$this->http('POST',$this->graph().'/'.ChannelSecurity::asset($ch).'/messages',['messaging_product'=>'whatsapp','to'=>$to,'type'=>'template','template'=>['name'=>trim($templateName),'language'=>['code'=>$language]]],$this->auth($this->token($ch)));$this->check($ch,$r);return [true,'Plantilla aceptada por Meta.',$r['json']];}catch(Throwable $e){return [false,$e instanceof ChannelDispatchException?$e->getMessage():'No se pudo verificar el envío.'];}
    }
    public function validate(array $ch): array
    {
        try{$type=(string)$ch['type'];$s=ChannelSecurity::settings($ch);$mode=ChannelSecurity::mode($ch);$token=$this->token($ch);if($token==='')throw new ChannelDispatchException('Falta token de acceso.');if(ChannelSecurity::tokenExpired($ch))return [false,'Autorización vencida.'];
            if($mode==='qr_bridge'){$r=$this->qrStart($ch);return [$r[0]&&!empty($r[2]['connected']),$r[0]?'Estado real consultado.':$r[1],$r[2]];}
            if($type==='telegram'){$r=$this->http('GET','https://api.telegram.org/bot'.$token.'/getMe');$this->check($ch,$r,false);if(empty($r['json']['ok']))return [false,'Telegram no validó el bot.'];return [true,'Telegram validado.',$r['json']['result']??[]];}
            if($type==='x'){$r=$this->http('GET','https://api.x.com/2/users/me',null,$this->auth($token));$this->check($ch,$r,false);return [!empty($r['json']['data']['id'])&&ChannelSecurity::asset($ch)===(string)($r['json']['data']['id']??''),'Identidad X consultada.',$r['json']['data']??[]];}
            if(in_array($type,['whatsapp','messenger','instagram'],true)){$asset=ChannelSecurity::asset($ch);if($asset==='')return [false,'Falta el identificador del activo.'];$fields=$type==='whatsapp'?'id,display_phone_number,verified_name':'id';$r=$this->http('GET',$this->graph().'/'.rawurlencode($asset).'?fields='.$fields,null,$this->auth($token));$this->check($ch,$r,false);return [(string)($r['json']['id']??'')===$asset,'Identidad de Meta validada.',$r['json']];}
            return [false,'No hay validación remota para este canal.'];
        }catch(Throwable $e){return [false,$e instanceof ChannelDispatchException?$e->getMessage():'No se pudo validar la autorización.'];}
    }
    public function templates(array $ch,string $after=''): array
    {
        $waba=$this->waba($ch);$params=['fields'=>'id,name,status,category,language,components','limit'=>50];if($after!=='')$params['after']=$after;
        $r=$this->http('GET',$this->graph().'/'.$waba.'/message_templates?'.http_build_query($params),null,$this->auth($this->token($ch)));$this->check($ch,$r,false);return ['templates'=>$r['json']['data']??[],'after'=>!empty($r['json']['paging']['next'])?(string)($r['json']['paging']['cursors']['after']??''):''];
    }
    public function createTemplate(array $ch,string $name,string $language,string $category,string $body,array $examples=[],string $header='',string $footer='',array $buttons=[]): array
    {
        if(!preg_match('/^[a-z0-9_]{1,128}$/D',$name)||!preg_match('/^[a-z]{2,3}(?:_[A-Z]{2})?$/D',$language)||!in_array($category,['UTILITY','MARKETING'],true))throw new ChannelDispatchException('Datos de plantilla inválidos.');
        $components=ChannelTemplateComponents::build($body,$examples,$header,$footer,$buttons);
        $r=$this->http('POST',$this->graph().'/'.$this->waba($ch).'/message_templates',['name'=>$name,'language'=>$language,'category'=>$category,'components'=>$components],$this->auth($this->token($ch)));$this->check($ch,$r);return $r['json'];
    }
    /** Lookup only via the tenant's WABA; prevents ID-based cross-account edit/delete. */
    public function ownedTemplate(array $ch,string $id): array
    {
        if(!preg_match('/^[0-9]{1,30}$/D',$id))throw new ChannelDispatchException('Plantilla inválida.');$cursor='';$seen=[];
        for($page=0;$page<200;$page++){$rows=$this->templates($ch,$cursor);foreach($rows['templates'] as $t){if((string)($t['id']??'')===$id)return $t;}$cursor=(string)($rows['after']??'');if($cursor===''||isset($seen[$cursor]))break;$seen[$cursor]=true;}
        throw new ChannelDispatchException('La plantilla no pertenece a la cuenta autorizada o no se encontró.');
    }
    public function updateTemplate(array $ch,string $id,string $category,string $body,array $examples=[],string $header='',string $footer='',array $buttons=[]): array
    {
        $old=$this->ownedTemplate($ch,$id);if(!in_array($category,['UTILITY','MARKETING'],true)||!in_array((string)($old['category']??''),['UTILITY','MARKETING'],true))throw new ChannelDispatchException('Categoría no disponible en el editor.');
        foreach($old['components']??[] as $c){$type=(string)($c['type']??'');if(!in_array($type,['HEADER','BODY','FOOTER','BUTTONS'],true)||($type==='HEADER'&&(($c['format']??'')!=='TEXT'||ChannelTemplateComponents::variables((string)($c['text']??'')))))throw new ChannelDispatchException('Edite componentes especiales desde Meta.');foreach($c['buttons']??[] as $b)if(!in_array($b['type']??'',['QUICK_REPLY','URL','PHONE_NUMBER'],true)||str_contains((string)($b['url']??''),'{{'))throw new ChannelDispatchException('Edite botones especiales desde Meta.');}
        $components=ChannelTemplateComponents::build($body,$examples,$header,$footer,$buttons);
        $r=$this->http('POST',$this->graph().'/'.$id,['category'=>$category,'components'=>$components],$this->auth($this->token($ch)));$this->check($ch,$r);return $r['json'];
    }
    public function deleteTemplate(array $ch,string $id): array
    {
        $old=$this->ownedTemplate($ch,$id);$name=(string)($old['name']??'');if($name==='')throw new ChannelDispatchException('No se pudo comprobar el nombre de la plantilla.');
        $r=$this->http('DELETE',$this->graph().'/'.$this->waba($ch).'/message_templates?'.http_build_query(['hsm_id'=>$id,'name'=>$name]),null,$this->auth($this->token($ch)));$this->check($ch,$r);return $r['json'];
    }
    public function sendApprovedTemplate(array $ch,string $id,string $to,array $values): array
    {
        $this->requireConnected($ch);$t=$this->ownedTemplate($ch,$id);if(($t['status']??'')!=='APPROVED')throw new ChannelDispatchException('Meta todavía no ha aprobado la plantilla.');
        if(!preg_match('/^\+?[1-9][0-9]{6,14}$/D',$to))throw new ChannelDispatchException('Número de destino inválido.');
        $p=['name'=>$t['name'],'language'=>['code'=>$t['language']]];$components=ChannelTemplateComponents::forSend($t,$values);if($components)$p['components']=$components;
        $r=$this->http('POST',$this->graph().'/'.rawurlencode(ChannelSecurity::asset($ch)).'/messages',['messaging_product'=>'whatsapp','to'=>ltrim($to,'+'),'type'=>'template','template'=>$p],$this->auth($this->token($ch)));$this->check($ch,$r);return $r['json'];
    }
    public function registerCloud(array $ch,string $pin): array
    {
        if(ChannelSecurity::mode($ch)!=='meta_cloud'||!preg_match('/^\d{6}$/D',$pin))throw new ChannelDispatchException('Solo números Cloud API; PIN de seis dígitos obligatorio.');
        $r=$this->http('POST',$this->graph().'/'.ChannelSecurity::asset($ch).'/register',['messaging_product'=>'whatsapp','pin'=>$pin],$this->auth($this->token($ch)));$this->check($ch,$r);return $r['json'];
    }
    public function configureTelegramWebhook(array $ch,string $url,string $secret): bool
    {
        if(($ch['type']??'')!=='telegram'||!preg_match('/^[A-Za-z0-9_-]{1,256}$/D',$secret)||!str_starts_with($url,'https://'))return false;
        $r=$this->http('POST','https://api.telegram.org/bot'.$this->token($ch).'/setWebhook',['url'=>$url,'secret_token'=>$secret,'allowed_updates'=>['message','edited_message']]);$this->check($ch,$r,false);return !empty($r['json']['ok']);
    }
    public function downloadWhatsAppMedia(array $ch,string $id): array
    {
        if(($ch['type']??'')!=='whatsapp'||ChannelSecurity::mode($ch)==='qr_bridge'||!preg_match('/^[0-9]{1,30}$/D',$id))throw new ChannelDispatchException('Adjunto no disponible.');$token=$this->token($ch);$q=$this->http('GET',$this->graph().'/'.$id.'?phone_number_id='.rawurlencode(ChannelSecurity::asset($ch)),null,$this->auth($token));$this->check($ch,$q,false);$url=(string)($q['json']['url']??'');$u=parse_url($url);$host=strtolower((string)($u['host']??''));if(($u['scheme']??'')!=='https'||(!str_ends_with($host,'.fbsbx.com')&&!str_ends_with($host,'.fbcdn.net')&&$host!=='lookaside.fbsbx.com')||isset($u['user'])||isset($u['pass']))throw new ChannelDispatchException('Origen de adjunto inválido.');
        if((int)($q['json']['file_size']??0)>15*1024*1024)throw new ChannelDispatchException('Adjunto superior a 15 MB.');$r=$this->http('GET',$url,null,$this->auth($token),false,15*1024*1024);if($r['status']!==200)throw new ChannelDispatchException('Meta no devolvió el archivo.');return ['bytes'=>$r['body'],'mime'=>(string)($q['json']['mime_type']??'application/octet-stream')];
    }
    public function downloadProviderMedia(array $ch,string $id): array
    {
        $mode=ChannelSecurity::mode($ch);$type=(string)$ch['type'];
        if($type==='whatsapp'&&$mode!=='qr_bridge')return $this->downloadWhatsAppMedia($ch,$id);
        if($type==='whatsapp'&&$mode==='qr_bridge'){
            if(!preg_match('/^[a-f0-9]{64}$/D',$id))throw new ChannelDispatchException('Adjunto inválido.');
            $s=ChannelSecurity::settings($ch);$r=$this->http('GET',$this->bridgeBase($ch).'/api/sessions/'.rawurlencode((string)$s['session_id']).'/media/'.$id,null,$this->bridgeHeaders($ch),false,16*1024*1024);
            if($r['status']!==200)throw new ChannelDispatchException('Adjunto no disponible.');$bytes=$r['body'];
        }elseif($type==='telegram'){
            $token=$this->token($ch);$r=$this->http('POST','https://api.telegram.org/bot'.$token.'/getFile',['file_id'=>$id]);$file=(string)($r['json']['result']['file_path']??'');
            if(!preg_match('#^[a-zA-Z0-9_./-]+$#D',$file)||str_contains($file,'..'))throw new ChannelDispatchException('Adjunto no disponible.');
            $r=$this->http('GET','https://api.telegram.org/file/bot'.$token.'/'.$file,null,[],false,16*1024*1024);if($r['status']!==200)throw new ChannelDispatchException('Adjunto no disponible.');$bytes=$r['body'];
        }elseif(in_array($type,['messenger','instagram'],true)){
            if(!preg_match('/^[a-f0-9]{64}$/D',$id))throw new ChannelDispatchException('Adjunto inválido.');$tid=(int)$ch['tenant_id'];$cid=(int)$ch['id'];
            $q=$this->pdo->prepare('SELECT source_ciphertext,mime FROM channel_media_assets WHERE tenant_id=? AND channel_id=? AND asset_key=?');$q->execute([$tid,$cid,$id]);$row=$q->fetch();if(!$row)throw new ChannelDispatchException('Adjunto no disponible.');
            $dir=$this->root.'/storage/channel-media/'.$tid.'/'.$cid;$path=$dir.'/'.$id;
            if(is_file($path)&&!is_link($path)&&filesize($path)<=15*1024*1024){$bytes=file_get_contents($path);if($bytes===false)throw new ChannelDispatchException('No se pudo leer el adjunto.');}
            else{$url=ChannelSecurity::secret((string)$row['source_ciphertext'],$this->env);if(!ChannelSecurity::metaMediaUrl($url))throw new ChannelDispatchException('Origen de adjunto no autorizado.');$r=$this->http('GET',$url,null,[],false,15*1024*1024);if($r['status']!==200)throw new ChannelDispatchException('El adjunto ya no está disponible en Meta.');$bytes=$r['body'];if($bytes==='')throw new ChannelDispatchException('Adjunto vacío.');if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new ChannelDispatchException('No se pudo conservar el adjunto.');$tmp=$path.'.'.bin2hex(random_bytes(8));if(file_put_contents($tmp,$bytes,LOCK_EX)!==false){chmod($tmp,0600);rename($tmp,$path);$this->pdo->prepare('UPDATE channel_media_assets SET cached_at=NOW(),mime=? WHERE tenant_id=? AND channel_id=? AND asset_key=?')->execute([(new finfo(FILEINFO_MIME_TYPE))->buffer($bytes)?:'application/octet-stream',$tid,$cid,$id]);}else @unlink($tmp);}
        }else throw new ChannelDispatchException('Canal sin descarga de adjuntos.');
        return ['bytes'=>$bytes,'mime'=>(new finfo(FILEINFO_MIME_TYPE))->buffer($bytes)?:'application/octet-stream'];
    }
    private function waba(array $ch): string {$s=ChannelSecurity::settings($ch);$id=trim((string)($s['business_id']??''));if($id==='')$id=(string)($ch['external_account_id']??'');if(($ch['type']??'')!=='whatsapp'||ChannelSecurity::mode($ch)==='qr_bridge'||!preg_match('/^\d{5,30}$/D',$id))throw new ChannelDispatchException('Este canal no tiene una WABA oficial configurada.');return $id;}
    private function graph(): string {return 'https://graph.facebook.com/'.ChannelSecurity::version($this->env);}
    private function token(array $ch): string {return ChannelSecurity::secret((string)($ch['token_ciphertext']??''),$this->env);}
    private function auth(string $token): array {if($token==='')throw new ChannelDispatchException('Falta token de autorización.');return ['Authorization: Bearer '.$token];}
    private function bridgeHeaders(array $ch): array {return $this->auth($this->token($ch));}
    private function bridgeBase(array $ch): string {$s=ChannelSecurity::settings($ch);$base=rtrim((string)($s['bridge_url']??''),'/');if(!ChannelSecurity::bridgeAllowed($base,$this->env)||!ChannelSecurity::sessionValid((string)($s['session_id']??'')))throw new ChannelDispatchException('El bridge o sesión no está autorizado por la plataforma.');return $base;}
    private function check(array $ch,array $r,bool $sending=true): void
    {
        $status=(int)$r['status'];$err=$r['json']['error']??[];$code=(int)($err['code']??$r['json']['error_code']??0);if($status>=200&&$status<300&&!isset($r['json']['error']))return;
        if($status===401||$code===190){$this->markWarning($ch);throw new ChannelDispatchException('Autorización del proveedor vencida o revocada. Vuelva a conectar el canal.');}
        if($status===429||in_array($code,[4,17,32,613,130429,131056],true))throw new ChannelDispatchException('Límite temporal del proveedor (HTTP '.$status.').','retrying',max(60,min(3600,(int)($r['json']['parameters']['retry_after']??60))));
        if($sending&&$status>=500)throw new ChannelDispatchException('El proveedor devolvió un error temporal; el resultado del envío es desconocido.','uncertain');
        throw new ChannelDispatchException('El proveedor rechazó la operación (HTTP '.$status.($code?' / código '.$code:'').').');
    }
    private function http(string $method,string $url,?array $payload=null,array $headers=[],bool $multipart=false,int $maxBytes=4194304): array
    {
        $curl=curl_init($url);$h=array_merge(['Accept: application/json'],$headers);$body='';if($payload!==null){if(!$multipart)$h[]='Content-Type: application/json';curl_setopt($curl,CURLOPT_POSTFIELDS,$multipart?$payload:json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));}
        curl_setopt_array($curl,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$h,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>45,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS|CURLPROTO_HTTP,CURLOPT_WRITEFUNCTION=>function($c,string $chunk)use(&$body,$maxBytes){if(strlen($body)+strlen($chunk)>$maxBytes)return 0;$body.=$chunk;return strlen($chunk);}]);
        $ok=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$errno=curl_errno($curl);curl_close($curl);
        if($ok===false||$errno!==0){$safeRetry=in_array($errno,[5,6,7],true);throw new ChannelDispatchException($safeRetry?'No se pudo abrir conexión con el proveedor.':'La respuesta del proveedor no pudo confirmarse.',$safeRetry?'retrying':'uncertain');}
        $json=json_decode($body,true);return ['status'=>$status,'body'=>$body,'json'=>is_array($json)?$json:[]];
    }
}
