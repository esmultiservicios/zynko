<?php
declare(strict_types=1);

final class ChannelProviderGateway
{
    private PDO $pdo;
    private string $root;
    private array $env;

    public function __construct(PDO $pdo,string $root){$this->pdo=$pdo;$this->root=$root;$this->env=$this->env($root.'/.env');}

    public function dispatchByConversation(int $tenantId,int $conversationId,int $messageId): array
    {
        $q=$this->pdo->prepare("SELECT m.id,m.body,m.type,m.media_json,c.channel_id,ci.external_id destination,ch.* FROM messages m JOIN conversations c ON c.id=m.conversation_id AND c.tenant_id=m.tenant_id JOIN channels ch ON ch.id=c.channel_id AND ch.tenant_id=c.tenant_id LEFT JOIN contact_identities ci ON ci.contact_id=c.contact_id AND ci.channel_id=ch.id AND ci.tenant_id=c.tenant_id WHERE m.id=? AND m.tenant_id=? LIMIT 1");
        $q->execute([$messageId,$tenantId]);$row=$q->fetch();if(!$row)return [false,'Mensaje o conversación no encontrada.'];
        $destination=trim((string)($row['destination']??''));if($destination==='')return [false,'El contacto no tiene identidad válida para este canal.'];
        return $this->dispatch($row,$destination,(string)($row['body']??''),json_decode((string)($row['media_json']??''),true)?:[],$messageId);
    }

    public function dispatch(array $channel,string $destination,string $body,array $media=[],?int $messageId=null): array
    {
        if(($channel['status']??'')!=='connected')return [false,'El canal aún no está conectado.'];
        $settings=json_decode((string)($channel['settings_json']??''),true)?:[];$type=(string)$channel['type'];
        $provider=(string)($settings['provider_mode']??($type==='whatsapp'?'meta_cloud':$type));
        try{
            if($type==='whatsapp'&&$provider==='qr_bridge')$result=$this->sendQr($channel,$settings,$destination,$body,$media);
            elseif($type==='whatsapp')$result=$this->sendMetaWhatsApp($channel,$settings,$destination,$body);
            elseif($type==='messenger'||$type==='instagram')$result=$this->sendMetaMessaging($channel,$settings,$destination,$body,$type);
            elseif($type==='telegram')$result=$this->sendTelegram($channel,$settings,$destination,$body);
            else return [false,'El canal no tiene despachador externo configurado.'];
            if($messageId){$this->pdo->prepare("UPDATE messages SET status=? WHERE id=? AND tenant_id=?")->execute([$result[0]?'sent':'failed',$messageId,(int)$channel['tenant_id']]);}
            return $result;
        }catch(Throwable $e){if($messageId)$this->pdo->prepare("UPDATE messages SET status='failed' WHERE id=? AND tenant_id=?")->execute([$messageId,(int)$channel['tenant_id']]);return [false,$e->getMessage()];}
    }

    public function qrStart(array $channel): array
    {
        $settings=json_decode((string)($channel['settings_json']??''),true)?:[];if(($settings['provider_mode']??'')!=='qr_bridge')return [false,'Este canal no usa WhatsApp por QR.',[]];$base=rtrim((string)($settings['bridge_url']??''),'/');$session=(string)($settings['session_id']??'');if($base===''||$session==='')return [false,'Falta URL del bridge o Session ID.',[]];$r=$this->http('POST',$base.'/api/sessions/'.rawurlencode($session).'/start',[],$this->bridgeHeaders($channel));$ok=$r['status']>=200&&$r['status']<300&&!empty($r['json']['ok']);return [$ok,$r['json']['message']??($ok?'Sesión iniciada.':'No fue posible iniciar la sesión.'),$r['json']];
    }

    public function dispatchWhatsAppCampaign(array $channel,string $destination,string $body,?string $templateName=null,string $language='es'): array
    {
        $settings=json_decode((string)($channel['settings_json']??''),true)?:[];$provider=(string)($settings['provider_mode']??'meta_cloud');
        if($provider==='qr_bridge')return $this->dispatch($channel,$destination,$body);
        if($templateName===null||trim($templateName)==='')return $this->dispatch($channel,$destination,$body);
        $phone=trim((string)($channel['external_phone_id']??''));$token=$this->token($channel);if($phone===''||$token==='')return [false,'WhatsApp Cloud API requiere Phone Number ID y token.'];
        $payload=['messaging_product'=>'whatsapp','to'=>$destination,'type'=>'template','template'=>['name'=>trim($templateName),'language'=>['code'=>$language]]];
        $r=$this->http('POST','https://graph.facebook.com/v23.0/'.rawurlencode($phone).'/messages',$payload,['Authorization: Bearer '.$token]);
        if($r['status']<200||$r['status']>=300)return [false,$r['json']['error']['message']??'Meta rechazó la plantilla.'];return [true,'Plantilla enviada por WhatsApp Cloud API.',$r['json']];
    }

    public function validate(array $channel): array
    {
        $settings=json_decode((string)($channel['settings_json']??''),true)?:[];$type=(string)$channel['type'];$provider=(string)($settings['provider_mode']??($type==='whatsapp'?'meta_cloud':$type));
        if($type==='whatsapp'&&$provider==='qr_bridge'){
            $base=rtrim((string)($settings['bridge_url']??''),'/');$session=(string)($settings['session_id']??'');if($base===''||$session==='')return [false,'Falta URL del bridge o Session ID.'];
            $r=$this->http('GET',$base.'/api/sessions/'.rawurlencode($session).'/status',null,$this->bridgeHeaders($channel));return [$r['status']>=200&&$r['status']<300&&!empty($r['json']['connected']),$r['json']['message']??($r['status']>=200?'Estado consultado.':'Bridge no disponible.')];
        }
        if($type==='telegram'){
            $token=$this->token($channel);if($token==='')return [false,'Falta token del bot.'];$r=$this->http('GET','https://api.telegram.org/bot'.$token.'/getMe');return [$r['status']===200&&!empty($r['json']['ok']),$r['json']['description']??'Telegram validado.'];
        }
        if(in_array($type,['whatsapp','messenger','instagram'],true)){
            $token=$this->token($channel);if($token==='')return [false,'Falta token de acceso.'];$target=$type==='whatsapp'?trim((string)($channel['external_phone_id']??'')):trim((string)($channel['external_phone_id']??$channel['external_account_id']??''));if($target==='')return [false,'Falta el identificador externo requerido por Meta.'];$fields=$type==='whatsapp'?'display_phone_number,verified_name':'id,name';$r=$this->http('GET','https://graph.facebook.com/v23.0/'.rawurlencode($target).'?fields='.rawurlencode($fields).'&access_token='.rawurlencode($token));return [$r['status']===200&&!empty($r['json']['id']),$r['json']['error']['message']??'Meta validado.'];
        }
        return [false,'No hay validación remota para este canal.'];
    }

    private function sendMetaWhatsApp(array $ch,array $s,string $to,string $body): array
    {
        $phone=trim((string)($ch['external_phone_id']??''));$token=$this->token($ch);if($phone===''||$token==='')throw new RuntimeException('WhatsApp Cloud API requiere Phone Number ID y token.');
        $payload=['messaging_product'=>'whatsapp','to'=>$to,'type'=>'text','text'=>['body'=>$body]];$r=$this->http('POST','https://graph.facebook.com/v23.0/'.rawurlencode($phone).'/messages',$payload,['Authorization: Bearer '.$token]);
        if($r['status']<200||$r['status']>=300)throw new RuntimeException($r['json']['error']['message']??'Meta rechazó el mensaje.');return [true,'Mensaje enviado por WhatsApp Cloud API.',$r['json']];
    }

    private function sendMetaMessaging(array $ch,array $s,string $to,string $body,string $type): array
    {
        $token=$this->token($ch);$page=trim((string)($ch['external_phone_id']??$ch['external_account_id']??''));if($token===''||$page==='')throw new RuntimeException('El canal Meta requiere identificador de página/cuenta y token.');
        $payload=['recipient'=>['id'=>$to],'message'=>['text'=>$body]];$url='https://graph.facebook.com/v23.0/'.rawurlencode($page).'/messages';$r=$this->http('POST',$url,$payload,['Authorization: Bearer '.$token]);if($r['status']<200||$r['status']>=300)throw new RuntimeException($r['json']['error']['message']??'Meta rechazó el mensaje.');return [true,'Mensaje enviado por '.($type==='instagram'?'Instagram Messaging':'Messenger').'.',$r['json']];
    }

    private function sendTelegram(array $ch,array $s,string $to,string $body): array
    {
        $token=$this->token($ch);if($token==='')throw new RuntimeException('Telegram requiere token del bot.');$r=$this->http('POST','https://api.telegram.org/bot'.$token.'/sendMessage',['chat_id'=>$to,'text'=>$body]);if($r['status']!==200||empty($r['json']['ok']))throw new RuntimeException($r['json']['description']??'Telegram rechazó el mensaje.');return [true,'Mensaje enviado por Telegram.',$r['json']];
    }

    private function sendQr(array $ch,array $s,string $to,string $body,array $media): array
    {
        $base=rtrim((string)($s['bridge_url']??''),'/');$session=(string)($s['session_id']??'');if($base===''||$session==='')throw new RuntimeException('WhatsApp QR requiere URL del bridge y Session ID.');$payload=['to'=>$to,'text'=>$body,'media'=>$media];$r=$this->http('POST',$base.'/api/sessions/'.rawurlencode($session).'/send',$payload,$this->bridgeHeaders($ch));if($r['status']<200||$r['status']>=300||empty($r['json']['ok']))throw new RuntimeException($r['json']['message']??'El bridge de WhatsApp QR rechazó el mensaje.');return [true,'Mensaje enviado por la sesión WhatsApp QR.',$r['json']];
    }

    private function bridgeHeaders(array $ch): array {$t=$this->token($ch);return $t!==''?['Authorization: Bearer '.$t]:[];}
    private function token(array $ch): string{return $this->decrypt((string)($ch['token_ciphertext']??''));}
    private function decrypt(string $cipher): string {if($cipher===''||!str_starts_with($cipher,'enc:v1:'))return $cipher;$hex=$this->env['APP_KEY']??'';if(!preg_match('/^[a-f0-9]{64}$/i',$hex))return ''; $raw=base64_decode(substr($cipher,7),true);if($raw===false||strlen($raw)<29)return '';$iv=substr($raw,0,12);$tag=substr($raw,12,16);$data=substr($raw,28);$plain=openssl_decrypt($data,'aes-256-gcm',hex2bin($hex),OPENSSL_RAW_DATA,$iv,$tag);return is_string($plain)?$plain:'';}
    private function env(string $p): array {$v=@parse_ini_file($p,false,INI_SCANNER_RAW);return is_array($v)?$v:[];}
    private function http(string $method,string $url,?array $payload=null,array $headers=[]): array {$ch=curl_init($url);$h=array_merge(['Accept: application/json'],$headers);if($payload!==null){$h[]='Content-Type: application/json';curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));}curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$h,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>25,CURLOPT_SSL_VERIFYPEER=>true]);$body=(string)curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=curl_error($ch);curl_close($ch);if($err!=='')throw new RuntimeException('Error de conexión con proveedor: '.$err);$json=json_decode($body,true);return ['status'=>$status,'body'=>$body,'json'=>is_array($json)?$json:[]];}
}
