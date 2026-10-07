<?php
final class InboundEmailService {
    public function __construct(private PDO $pdo, private string $root) {}

    private function decrypt(?string $cipher): string {
        if(!$cipher)return '';
        if(!str_starts_with($cipher,'enc:v1:'))return $cipher;
        $env=@parse_ini_file($this->root.'/.env',false,INI_SCANNER_RAW)?:[];
        $hex=$env['APP_KEY']??''; if(!preg_match('/^[a-f0-9]{64}$/i',$hex))return '';
        $raw=base64_decode(substr($cipher,7),true); if($raw===false||strlen($raw)<29)return '';
        $iv=substr($raw,0,12);$tag=substr($raw,12,16);$data=substr($raw,28);
        $plain=openssl_decrypt($data,'aes-256-gcm',hex2bin($hex),OPENSSL_RAW_DATA,$iv,$tag);
        return $plain===false?'':$plain;
    }

    public function test(array $cfg): array {
        $mode=strtoupper((string)($cfg['inbound_method']??'NONE'));
        if($mode==='NONE')return ['ok'=>false,'message'=>'Selecciona IMAP o Microsoft Graph para la recepción entrante.'];
        if($mode==='IMAP')return $this->testImap($cfg);
        if($mode==='GRAPH')return $this->testGraph($cfg);
        return ['ok'=>false,'message'=>'Método de recepción no reconocido.'];
    }

    private function testImap(array $cfg): array {
        if(!function_exists('imap_open'))return ['ok'=>false,'message'=>'La extensión PHP IMAP no está habilitada en este servidor. Actívala en cPanel / Select PHP Version antes de usar recepción IMAP.'];
        $host=trim((string)($cfg['imap_host']??''));$port=max(1,(int)($cfg['imap_port']??993));$security=strtolower((string)($cfg['imap_secure']??'ssl'));$user=trim((string)($cfg['imap_username']??''));$pass=$this->decrypt($cfg['imap_password']??null);$folder=trim((string)($cfg['imap_folder']??'INBOX'))?:'INBOX';
        if($host===''||$user===''||$pass==='')return ['ok'=>false,'message'=>'IMAP requiere servidor, usuario y contraseña/App Password.'];
        $flags=$security==='ssl'?'/imap/ssl':'/imap/tls';$mailbox='{'.$host.':'.$port.$flags.'}'.$folder;
        $stream=@imap_open($mailbox,$user,$pass,0,1,['DISABLE_AUTHENTICATOR'=>'GSSAPI']);
        if(!$stream){$err=imap_last_error()?:'No fue posible autenticar el buzón IMAP.';return ['ok'=>false,'message'=>'IMAP: '.$err];}
        $count=imap_num_msg($stream);imap_close($stream);
        return ['ok'=>true,'message'=>'Conexión IMAP correcta. Buzón accesible ('.$count.' mensaje(s) visibles en '.$folder.').'];
    }

    private function testGraph(array $cfg): array {
        if(!function_exists('curl_init'))return ['ok'=>false,'message'=>'cURL no está disponible en PHP.'];
        $tenant=trim((string)($cfg['tenant_graph_id']??''));$client=trim((string)($cfg['client_id']??''));$secret=$this->decrypt($cfg['client_secret']??null);$user=trim((string)($cfg['graph_user']??''));
        if($tenant===''||$client===''||$secret===''||$user==='')return ['ok'=>false,'message'=>'Graph entrante requiere Tenant ID, Client ID, Client Secret y buzón.'];
        $ch=curl_init('https://login.microsoftonline.com/'.rawurlencode($tenant).'/oauth2/v2.0/token');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query(['client_id'=>$client,'client_secret'=>$secret,'scope'=>'https://graph.microsoft.com/.default','grant_type'=>'client_credentials']),CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12]);
        $raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
        $token=json_decode((string)$raw,true)['access_token']??'';if($code<200||$code>=300||$token==='')return ['ok'=>false,'message'=>'Graph OAuth no pudo autenticarse'.($err!==''?': '.$err:'. Revisa permisos Mail.Read de aplicación y consentimiento de administrador.')];
        $ch=curl_init('https://graph.microsoft.com/v1.0/users/'.rawurlencode($user).'/mailFolders/inbox?$select=id,displayName,totalItemCount');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Accept: application/json'],CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12]);$body=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        if($code<200||$code>=300)return ['ok'=>false,'message'=>'Graph autenticó, pero no pudo leer el buzón. Verifica Mail.Read de aplicación y acceso al usuario configurado.'];
        $box=json_decode((string)$body,true);return ['ok'=>true,'message'=>'Microsoft Graph entrante está listo. Bandeja INBOX accesible con '.(int)($box['totalItemCount']??0).' mensaje(s).'];
    }

    public function pollTenant(int $tenantId): array {
        $q=$this->pdo->prepare('SELECT * FROM correo WHERE tenant_id=? AND is_default=1 AND inbound_enabled=1 ORDER BY correo_id DESC LIMIT 1');$q->execute([$tenantId]);$cfg=$q->fetch();
        if(!$cfg)return ['ok'=>true,'processed'=>0,'message'=>'Recepción no habilitada.'];
        $mode=strtoupper((string)($cfg['inbound_method']??'NONE'));
        return $mode==='IMAP'?$this->pollImap($tenantId,$cfg):($mode==='GRAPH'?$this->pollGraph($tenantId,$cfg):['ok'=>true,'processed'=>0,'message'=>'Recepción desactivada.']);
    }

    private function uuid4(): string {$d=random_bytes(16);$d[6]=chr((ord($d[6])&0x0f)|0x40);$d[8]=chr((ord($d[8])&0x3f)|0x80);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($d),4));}
    private function emailChannel(int $tenantId,string $display): int {
        $q=$this->pdo->prepare("SELECT id FROM channels WHERE tenant_id=? AND type='email' ORDER BY id LIMIT 1");$q->execute([$tenantId]);$id=(int)$q->fetchColumn();
        if(!$id){$this->pdo->prepare("INSERT INTO channels(tenant_id,uuid,type,name,display_address,status,settings_json,last_event_at) VALUES(?,?, 'email','Correo',?,'connected','{}',NOW())")->execute([$tenantId,$this->uuid4(),$display]);$id=(int)$this->pdo->lastInsertId();}
        else $this->pdo->prepare("UPDATE channels SET status='connected',display_address=COALESCE(NULLIF(?,''),display_address),last_event_at=NOW() WHERE id=?")->execute([$display,$id]);
        return $id;
    }
    private function ingest(int $tenantId,int $channelId,string $externalId,string $email,string $name,string $subject,string $body,string $sentAt): bool {
        $externalId=mb_substr(trim($externalId),0,190);if($externalId==='')$externalId='email:'.sha1($email.'|'.$subject.'|'.$sentAt.'|'.$body);
        $q=$this->pdo->prepare('SELECT id FROM messages WHERE tenant_id=? AND external_message_id=? LIMIT 1');$q->execute([$tenantId,$externalId]);if($q->fetchColumn())return false;
        $q=$this->pdo->prepare('SELECT id FROM contacts WHERE tenant_id=? AND email=? ORDER BY id LIMIT 1');$q->execute([$tenantId,$email]);$contactId=(int)$q->fetchColumn();
        if(!$contactId){$this->pdo->prepare('INSERT INTO contacts(tenant_id,uuid,name,email) VALUES(?,?,?,?)')->execute([$tenantId,$this->uuid4(),$name?:$email,$email]);$contactId=(int)$this->pdo->lastInsertId();}
        $q=$this->pdo->prepare("SELECT id FROM conversations WHERE tenant_id=? AND channel_id=? AND contact_id=? AND status IN ('open','pending') AND archived_at IS NULL AND deleted_at IS NULL ORDER BY id DESC LIMIT 1");$q->execute([$tenantId,$channelId,$contactId]);$convId=(int)$q->fetchColumn();
        if(!$convId){$this->pdo->prepare("INSERT INTO conversations(tenant_id,uuid,channel_id,contact_id,status,priority,unread_count,last_message_at) VALUES(?,?,?,?,'open','normal',0,?)")->execute([$tenantId,$this->uuid4(),$channelId,$contactId,$sentAt]);$convId=(int)$this->pdo->lastInsertId();}
        $message=trim(($subject!==''?'Asunto: '.$subject."\n\n":'').$body);if($message==='')$message='[Correo sin contenido de texto]';
        $this->pdo->prepare("INSERT INTO messages(tenant_id,conversation_id,uuid,external_message_id,direction,sender_type,type,body,status,sent_at) VALUES(?,?,?,?, 'in','contact','text',?,'received',?)")->execute([$tenantId,$convId,$this->uuid4(),$externalId,mb_substr($message,0,50000),$sentAt]);
        $this->pdo->prepare('UPDATE conversations SET unread_count=unread_count+1,last_message_at=?,status=IF(status="closed","open",status) WHERE id=?')->execute([$sentAt,$convId]);
        return true;
    }
    private function pollImap(int $tenantId,array $cfg): array {
        $test=$this->testImap($cfg);if(!$test['ok'])return ['ok'=>false,'processed'=>0,'message'=>$test['message']];
        $host=trim((string)$cfg['imap_host']);$port=(int)$cfg['imap_port'];$security=strtolower((string)$cfg['imap_secure']);$folder=trim((string)$cfg['imap_folder'])?:'INBOX';$flags=$security==='ssl'?'/imap/ssl':($security==='tls'?'/imap/tls':'/imap/notls');$mailbox='{'.$host.':'.$port.$flags.'}'.$folder;$stream=@imap_open($mailbox,trim((string)$cfg['imap_username']),$this->decrypt($cfg['imap_password']??null),0,1,['DISABLE_AUTHENTICATOR'=>'GSSAPI']);if(!$stream)return ['ok'=>false,'processed'=>0,'message'=>'No se pudo abrir IMAP.'];
        $ids=imap_search($stream,'UNSEEN')?:[];$ids=array_slice(array_reverse($ids),0,25);$channel=$this->emailChannel($tenantId,(string)($cfg['correo']??$cfg['imap_username']??''));$processed=0;
        foreach(array_reverse($ids) as $n){$h=imap_headerinfo($stream,$n);$from=$h->from[0]??null;$email=$from?trim(($from->mailbox??'').'@'.($from->host??'')):'';if(!filter_var($email,FILTER_VALIDATE_EMAIL))continue;$name=isset($from->personal)?imap_utf8((string)$from->personal):$email;$subject=isset($h->subject)?imap_utf8((string)$h->subject):'';$msgid=trim((string)($h->message_id??('imap:'.$tenantId.':'.$n)));$body=imap_fetchbody($stream,$n,'1.1');if(trim((string)$body)==='')$body=imap_fetchbody($stream,$n,'1');if(trim((string)$body)==='')$body=imap_body($stream,$n);$enc=(int)($h->encoding??0);if($enc===3)$body=base64_decode((string)$body)?:$body;elseif($enc===4)$body=quoted_printable_decode((string)$body);$body=trim(strip_tags((string)$body));$sent=date('Y-m-d H:i:s',(int)($h->udate??time()));if($this->ingest($tenantId,$channel,$msgid,$email,$name,$subject,$body,$sent)){$processed++;imap_setflag_full($stream,(string)$n,'\\Seen');}}
        imap_close($stream);return ['ok'=>true,'processed'=>$processed,'message'=>'IMAP procesó '.$processed.' correo(s) nuevo(s).'];
    }
    private function graphToken(array $cfg): string {
        $tenant=trim((string)$cfg['tenant_graph_id']);$client=trim((string)$cfg['client_id']);$secret=$this->decrypt($cfg['client_secret']??null);if($tenant===''||$client===''||$secret==='')return '';$ch=curl_init('https://login.microsoftonline.com/'.rawurlencode($tenant).'/oauth2/v2.0/token');curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query(['client_id'=>$client,'client_secret'=>$secret,'scope'=>'https://graph.microsoft.com/.default','grant_type'=>'client_credentials']),CURLOPT_TIMEOUT=>15]);$raw=curl_exec($ch);curl_close($ch);return (string)(json_decode((string)$raw,true)['access_token']??'');
    }
    private function pollGraph(int $tenantId,array $cfg): array {
        if(!function_exists('curl_init'))return ['ok'=>false,'processed'=>0,'message'=>'cURL no disponible.'];$token=$this->graphToken($cfg);if($token==='')return ['ok'=>false,'processed'=>0,'message'=>'No se pudo obtener token de Microsoft Graph.'];$user=trim((string)$cfg['graph_user']);$url='https://graph.microsoft.com/v1.0/users/'.rawurlencode($user).'/mailFolders/inbox/messages?$top=25&$orderby=receivedDateTime%20asc&$select=id,subject,from,receivedDateTime,bodyPreview,isRead';$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Accept: application/json'],CURLOPT_TIMEOUT=>20]);$raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);if($code<200||$code>=300)return ['ok'=>false,'processed'=>0,'message'=>'Microsoft Graph no pudo leer INBOX (HTTP '.$code.').'];$items=json_decode((string)$raw,true)['value']??[];$channel=$this->emailChannel($tenantId,$user);$processed=0;foreach($items as $m){$email=trim((string)($m['from']['emailAddress']['address']??''));if(!filter_var($email,FILTER_VALIDATE_EMAIL))continue;$name=trim((string)($m['from']['emailAddress']['name']??$email));$sent=date('Y-m-d H:i:s',strtotime((string)($m['receivedDateTime']??'now')));if($this->ingest($tenantId,$channel,'graph:'.(string)($m['id']??''),$email,$name,(string)($m['subject']??''),(string)($m['bodyPreview']??''),$sent))$processed++;}return ['ok'=>true,'processed'=>$processed,'message'=>'Microsoft Graph procesó '.$processed.' correo(s) nuevo(s).'];
    }

}
