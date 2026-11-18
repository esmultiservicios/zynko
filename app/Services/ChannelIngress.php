<?php
declare(strict_types=1);
require_once __DIR__.'/ChannelWebhookEvents.php';
require_once __DIR__.'/ChannelProviderGateway.php';
require_once __DIR__.'/AutomationEngine.php';
require_once __DIR__.'/NivoEngine.php';
require_once __DIR__.'/../Support/ChannelSecurity.php';
require_once __DIR__.'/../Support/ChannelIdentity.php';

final class ChannelIngress
{
    private ChannelProviderGateway $gateway;
    public function __construct(private PDO $pdo,private string $root){$this->gateway=new ChannelProviderGateway($pdo,$root);}
    public static function uuid(): string {$d=random_bytes(16);$d[6]=chr((ord($d[6])&15)|64);$d[8]=chr((ord($d[8])&63)|128);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($d),4));}
    public function findMeta(string $type,string $asset,string $account): array
    {
        if($asset===''||!in_array($type,['whatsapp','messenger','instagram'],true))return [];
        $stmt=$this->pdo->prepare("SELECT ch.* FROM channels ch JOIN tenants t ON t.id=ch.tenant_id WHERE ch.type=? AND (ch.external_phone_id=? OR (ch.type<>'whatsapp' AND (ch.external_phone_id IS NULL OR ch.external_phone_id='') AND ch.external_account_id=?)) AND t.status NOT IN ('suspended','closed') AND ch.status IN ('connected','pending','warning')");$stmt->execute([$type,$asset,$asset]);$rows=[];
        foreach($stmt->fetchAll() as $ch){if(ChannelSecurity::mode($ch)==='qr_bridge'||ChannelSecurity::asset($ch)!==$asset||!empty(ChannelSecurity::settings($ch)['phone_conflict']))continue;$s=ChannelSecurity::settings($ch);if($type==='whatsapp'&&$account!==''){$waba=(string)($s['business_id']??'');if($waba==='')$waba=(string)($ch['external_account_id']??'');if($waba!==''&&$waba!==$account)continue;}$rows[]=$ch;}return $rows;
    }
    public function findQr(string $session): array
    {
        if(!ChannelSecurity::sessionValid($session))return [];$q=$this->pdo->prepare("SELECT ch.* FROM channels ch JOIN tenants t ON t.id=ch.tenant_id WHERE ch.type='whatsapp' AND JSON_UNQUOTE(JSON_EXTRACT(ch.settings_json,'$.provider_mode'))='qr_bridge' AND JSON_UNQUOTE(JSON_EXTRACT(ch.settings_json,'$.session_id'))=? AND t.status NOT IN ('suspended','closed') AND ch.status<>'disconnected'");$q->execute([$session]);return $q->fetchAll();
    }
    public function recordHealth(array $ch,string $status,string $display=''): void
    {
        if(!in_array($status,['connected','pending','warning','disconnected'],true))return;if($status==='connected'&&$ch['type']==='whatsapp'&&$display!==''&&!ChannelIdentity::claim($this->pdo,$ch,$display)){$this->audit($ch,'warning','Número de WhatsApp ya asociado a otra conexión.');return;}$tid=(int)$ch['tenant_id'];$id=(int)$ch['id'];$this->pdo->prepare('UPDATE channels SET status=?,last_event_at=NOW() WHERE tenant_id=? AND id=?')->execute([$status,$tid,$id]);
        
        if(($ch['status']??'')!==$status)$this->audit($ch,'info','Estado de canal actualizado: '.$status);
    }
    public function audit(array $ch,string $level,string $message): void
    {
        try{$this->pdo->prepare('INSERT INTO system_event_logs(tenant_id,level,module,message,context_json) VALUES(?,?,?,?,?)')->execute([(int)$ch['tenant_id'],$level,'channel_provider',$message,json_encode(['channel_id'=>(int)$ch['id'],'provider'=>(string)$ch['type']])]);}catch(Throwable $e){}
    }
    public function handle(array $ch,array $events): int
    {
        $count=0;$tid=(int)$ch['tenant_id'];$id=(int)$ch['id'];$type=(string)$ch['type'];
        foreach($events as $ev){$kind=(string)($ev['kind']??'');if(!empty(ChannelSecurity::settings($ch)['phone_conflict']))continue;if($kind==='watermark'){$this->gateway->applyWatermark($ch,(string)($ev['from']??''),(int)($ev['watermark']??0),(string)($ev['status']??'read'));$count++;continue;}if($kind==='status'){$this->gateway->applyStatus($ch,(string)($ev['external']??''),(string)($ev['status']??''),(string)($ev['from']??''),(int)($ev['error_code']??0));$count++;continue;}
            $from=trim((string)($ev['from']??''));if($from===''||strlen($from)>190)continue;$external=trim((string)($ev['external']??''));if($kind!=='contact'&&$external==='')continue;
            // A single transaction serializes contact/conversation creation and receipt claim per channel.
            $cid=0;$mid=0;$outId=0;$historical=!empty($ev['historical']);$echo=!empty($ev['echo']);$text=trim((string)($ev['text']??''));$name=mb_substr(trim((string)($ev['name']??''))?:$from,0,120);
            $this->pdo->beginTransaction();
            try{
                $lock=$this->pdo->prepare('SELECT status,settings_json FROM channels WHERE id=? AND tenant_id=? FOR UPDATE');$lock->execute([$id,$tid]);$current=$lock->fetch();if(!$current||$current['status']==='disconnected'||!empty(ChannelSecurity::settings($current)['phone_conflict'])){$this->pdo->rollBack();continue;}
                if($kind!=='contact'){
                    if($echo&&!$historical&&!$this->gateway->isOwnEcho($ch,$external,$from)){$inflight=$this->pdo->prepare("SELECT 1 FROM channel_outbox o JOIN conversations c ON c.id=o.conversation_id AND c.tenant_id=o.tenant_id JOIN contact_identities ci ON ci.contact_id=c.contact_id AND ci.channel_id=o.channel_id AND ci.tenant_id=o.tenant_id WHERE o.tenant_id=? AND o.channel_id=? AND o.state='sending' AND o.claimed_at>DATE_SUB(NOW(),INTERVAL 2 MINUTE) AND ci.external_id=? LIMIT 1");$inflight->execute([$tid,$id,$from]);if($inflight->fetchColumn())throw new RuntimeException('Eco pendiente de confirmación del envío.');}
                    $key=hash('sha256',$kind.'|'.($echo?'out':'in').'|'.$from.'|'.$external);
                    $q=$this->pdo->prepare('INSERT IGNORE INTO channel_event_receipts(channel_id,tenant_id,event_key) VALUES(?,?,?)');$q->execute([$id,$tid,$key]);if(!$q->rowCount()){$this->pdo->commit();continue;}
                    // Preserve idempotency for messages ingested before this release.
                    $q=$this->pdo->prepare('SELECT m.id FROM messages m JOIN conversations c ON c.id=m.conversation_id AND c.tenant_id=m.tenant_id WHERE c.channel_id=? AND c.tenant_id=? AND m.external_message_id IN (?,?) LIMIT 1');$q->execute([$id,$tid,$external,ChannelSecurity::externalKey($ch,$external,$from)]);if($q->fetchColumn()||($echo&&$this->gateway->isOwnEcho($ch,$external,$from))){$this->pdo->commit();continue;}
                }
                if($kind==='comment'){$this->pdo->commit();if($type==='instagram'&&!$historical&&!$echo)$this->comment($ch,$from,$text,$external);$count++;continue;}
                $contact=$this->contact($ch,$from,$name);if($kind==='contact'){$this->pdo->commit();$count++;continue;}
                if($text===''){$this->pdo->commit();continue;}
                $q=$this->pdo->prepare("SELECT id,assigned_user_id FROM conversations WHERE tenant_id=? AND channel_id=? AND contact_id=? AND deleted_at IS NULL AND (status IN ('open','pending') OR ?=1) ORDER BY id DESC LIMIT 1");$q->execute([$tid,$id,$contact,$historical?1:0]);$conv=$q->fetch();$cid=(int)($conv['id']??0);
                $stamp=(int)($ev['timestamp']??0);$sentAt=$stamp>0&&$stamp<=time()+300?date('Y-m-d H:i:s',$stamp):date('Y-m-d H:i:s');
                if(!$cid){$this->pdo->prepare("INSERT INTO conversations(tenant_id,uuid,channel_id,contact_id,status,last_message_at) VALUES(?,?,?,?,?,?)")->execute([$tid,self::uuid(),$id,$contact,$historical?'resolved':'open',$sentAt]);$cid=(int)$this->pdo->lastInsertId();}
                $media=$ev['media']??[];if(in_array($type,['messenger','instagram'],true)){
                    $env=@parse_ini_file($this->root.'/.env',false,INI_SCANNER_RAW)?:[];
                    foreach($media as $i=>&$f){$url=(string)($f['source_url']??'');unset($f['source_url']);if(!ChannelSecurity::metaMediaUrl($url))continue;$key=hash('sha256',$external.'|'.$i);$cipher=ChannelSecurity::encrypt($url,$env);$this->pdo->prepare('INSERT IGNORE INTO channel_media_assets(channel_id,tenant_id,asset_key,source_ciphertext) VALUES(?,?,?,?)')->execute([$id,$tid,$key,$cipher]);$f['provider_id']=$key;}unset($f);
                }$sender=$echo?'system':'contact';$direction=$echo?'out':'in';$status=$echo?'sent':'received';$typeStored=$media?'media':(string)($ev['message_type']??'text');
                $this->pdo->prepare('INSERT INTO messages(tenant_id,conversation_id,uuid,external_message_id,direction,sender_type,type,body,media_json,status,sent_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)')->execute([$tid,$cid,self::uuid(),ChannelSecurity::externalKey($ch,$external,$from),$direction,$sender,$typeStored,$text,$media?json_encode($media,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR):null,$status,$sentAt]);$mid=(int)$this->pdo->lastInsertId();
                if($media){foreach($media as $i=>&$f){if(!empty($f['provider_id'])&&$type==='whatsapp'&&ChannelSecurity::mode($ch)!=='qr_bridge')$f['url']='channel-media.php?message_id='.$mid.'&index='.$i;elseif(!empty($f['provider_id'])&&in_array($type,['telegram','whatsapp','messenger','instagram'],true))$f['url']='channel-media.php?message_id='.$mid.'&index='.$i;else unset($f['url']);}unset($f);$this->pdo->prepare('UPDATE messages SET media_json=? WHERE tenant_id=? AND id=?')->execute([json_encode($media,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$tid,$mid]);}
                if($historical)$this->pdo->prepare('UPDATE conversations SET last_message_at=GREATEST(COALESCE(last_message_at,?),?) WHERE id=? AND tenant_id=?')->execute([$sentAt,$sentAt,$cid,$tid]);
                else{$this->pdo->prepare("UPDATE conversations SET last_message_at=GREATEST(COALESCE(last_message_at,?),?),unread_count=unread_count+?,archived_at=NULL WHERE id=? AND tenant_id=?")->execute([$sentAt,$sentAt,$echo?0:1,$cid,$tid]);}
                zynkoRealtimePublishMessage($this->pdo,$tid,$mid,['channel'=>$type,'sender'=>$sender]);zynkoRealtimePublish($this->pdo,$tid,'conversation.updated',['conversation_id'=>$cid,'channel'=>$type,'reason'=>$historical?'history_import':'provider_message','message_id'=>$mid],'conversation',(string)$cid);
                $this->pdo->commit();$count++;
            }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
            // Never respond to a historical message, an echo, a reaction, or media without a text caption.
            if($echo||$historical||!$mid||$kind!=='message'||in_array($ev['message_type']??'text',['reaction','location','contacts'],true)||(!empty($ev['media'])&&preg_match('/^\[[^\]]+\]$/D',$text)))continue;
            $this->automate($ch,$cid,$text,$name);
        }
        $this->pdo->prepare('UPDATE channels SET last_event_at=NOW() WHERE id=? AND tenant_id=?')->execute([$id,$tid]);return $count;
    }
    private function contact(array $ch,string $from,string $name): int
    {
        $tid=(int)$ch['tenant_id'];$id=(int)$ch['id'];$q=$this->pdo->prepare('SELECT contact_id FROM contact_identities WHERE tenant_id=? AND channel_id=? AND external_id=? LIMIT 1');$q->execute([$tid,$id,$from]);$contact=(int)$q->fetchColumn();if($contact)return $contact;
        $phone=$ch['type']==='whatsapp'?$from:null;if($phone!==null){$q=$this->pdo->prepare('SELECT id FROM contacts WHERE tenant_id=? AND phone=? ORDER BY id LIMIT 1');$q->execute([$tid,$phone]);$contact=(int)$q->fetchColumn();}
        if(!$contact){$this->pdo->prepare('INSERT INTO contacts(tenant_id,uuid,name,phone) VALUES(?,?,?,?)')->execute([$tid,self::uuid(),$name,$phone]);$contact=(int)$this->pdo->lastInsertId();}
        $this->pdo->prepare('INSERT INTO contact_identities(tenant_id,contact_id,channel_id,external_id,profile_json) VALUES(?,?,?,?,JSON_OBJECT())')->execute([$tid,$contact,$id,$from]);return $contact;
    }
    private function automate(array $ch,int $cid,string $text,string $name): void
    {
        $tid=(int)$ch['tenant_id'];$mode=(string)(ChannelSecurity::settings($ch)['attention_mode']??'nivo');if($mode!=='nivo'||$ch['status']!=='connected')return;
        try{
            // Serializes automation evaluation within one conversation. A manual assignment is rechecked before enqueue.
            $key='zynko_auto_'.$tid.'_'.$cid;$q=$this->pdo->prepare('SELECT GET_LOCK(?,2)');$q->execute([$key]);if((int)$q->fetchColumn()!==1)return;
            try{
                $q=$this->pdo->prepare('SELECT assigned_user_id,deleted_at FROM conversations WHERE id=? AND tenant_id=?');$q->execute([$cid,$tid]);$r=$q->fetch();if(!$r||!empty($r['assigned_user_id'])||!empty($r['deleted_at']))return;
                $company=$this->pdo->prepare('SELECT name FROM tenants WHERE id=?');$company->execute([$tid]);$companyName=(string)($company->fetchColumn()?:'la empresa');$auto=AutomationEngine::evaluate($this->pdo,$tid,$cid,(string)$ch['type'],$text);$engine=($auto&&(!empty($auto['stop'])||!empty($auto['reply'])))?$auto:NivoEngine::evaluate($this->pdo,$tid,$cid,$text,(string)$ch['type'],$name,$companyName);$reply=trim((string)($engine['reply']??''));if($reply==='')return;
                $this->pdo->beginTransaction();$q=$this->pdo->prepare('SELECT assigned_user_id,deleted_at FROM conversations WHERE id=? AND tenant_id=? FOR UPDATE');$q->execute([$cid,$tid]);$r=$q->fetch();if(!$r||!empty($r['assigned_user_id'])||!empty($r['deleted_at'])){$this->pdo->rollBack();return;}
                $this->pdo->prepare("INSERT INTO messages(tenant_id,conversation_id,uuid,direction,sender_type,type,body,status,sent_at) VALUES(?,?,?,'out','bot','text',?,'queued',NOW())")->execute([$tid,$cid,self::uuid(),$reply]);$out=(int)$this->pdo->lastInsertId();$this->gateway->enqueue($tid,$cid,$out);$this->pdo->prepare('UPDATE conversations SET last_message_at=NOW() WHERE id=? AND tenant_id=?')->execute([$cid,$tid]);zynkoRealtimePublishMessage($this->pdo,$tid,$out,['channel'=>$ch['type'],'sender'=>'bot']);zynkoRealtimePublish($this->pdo,$tid,'conversation.updated',['conversation_id'=>$cid,'message_id'=>$out],'conversation',(string)$cid);$this->pdo->commit();$this->gateway->dispatchQueued($tid,$out,$cid);
            }finally{$this->pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$key]);}
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();$this->audit($ch,'warning','No se completó una respuesta automática; mensaje entrante conservado.');}
    }
    private function comment(array $ch,string $from,string $text,string $eventId): void
    {
        try{$r=$this->pdo->prepare("SELECT * FROM social_automation_rules WHERE tenant_id=? AND channel_type='instagram' AND rule_type='comment_to_dm' AND active=1 ORDER BY id");$r->execute([(int)$ch['tenant_id']]);foreach($r->fetchAll() as $rule){$match=trim((string)$rule['keywords'])==='';foreach(preg_split('/[,\n]+/u',mb_strtolower((string)$rule['keywords']))?:[] as $k){if(trim($k)!==''&&mb_strpos(mb_strtolower($text),trim($k))!==false)$match=true;}if($match){$this->gateway->replyToInstagramComment($ch,$eventId,(string)$rule['response_text']);break;}}}catch(Throwable $e){$this->audit($ch,'warning','No se autorizó la respuesta privada al comentario.');}
    }
}
