<?php
declare(strict_types=1);

/** Shared, side-effect-free provider identity and signature helpers. */
final class ChannelSecurity
{
    public static function settings(array $channel): array
    {
        $s=json_decode((string)($channel['settings_json']??''),true);
        return is_array($s)?$s:[];
    }
    public static function mode(array $channel): string
    {
        return (string)(self::settings($channel)['provider_mode']??(($channel['type']??'')==='whatsapp'?'meta_cloud':($channel['type']??'')));
    }
    public static function asset(array $channel): string
    {
        $phone=trim((string)($channel['external_phone_id']??''));
        if(($channel['type']??'')==='whatsapp')return $phone;
        return $phone!==''?$phone:trim((string)($channel['external_account_id']??''));
    }
    public static function version(array $env,string $fallback='v23.0'): string
    {
        $v=trim((string)($env['META_GRAPH_VERSION']??$fallback));
        return preg_match('/^v[0-9]{1,3}\.0$/D',$v)?$v:$fallback;
    }
    public static function secret(string $cipher,array $env): string
    {
        if(!str_starts_with($cipher,'enc:v1:'))return $cipher; // legacy tokens remain readable
        $key=(string)($env['APP_KEY']??'');$raw=base64_decode(substr($cipher,7),true);
        if(!preg_match('/^[a-f0-9]{64}$/iD',$key)||$raw===false||strlen($raw)<29)return '';
        $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',hex2bin($key),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));
        return is_string($plain)?$plain:'';
    }
    public static function encrypt(string $plain,array $env): string
    {
        $key=(string)($env['APP_KEY']??'');if($plain===''||!preg_match('/^[a-f0-9]{64}$/iD',$key))throw new RuntimeException('No se pudo proteger la autorización.');
        $iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($plain,'aes-256-gcm',hex2bin($key),OPENSSL_RAW_DATA,$iv,$tag);
        if($cipher===false)throw new RuntimeException('No se pudo cifrar la autorización.');return 'enc:v1:'.base64_encode($iv.$tag.$cipher);
    }
    public static function enabled(array $env,string $key): bool {return in_array(strtolower(trim((string)($env[$key]??''))),['1','true','yes','on'],true);}
    public static function metaMediaUrl(string $url): bool
    {
        $u=parse_url($url);if(!is_array($u)||($u['scheme']??'')!=='https'||isset($u['user'])||isset($u['pass'])||isset($u['fragment'])||isset($u['port'])&&$u['port']!==443||strlen($url)>4096||preg_match('/[\x00-\x20]/',$url))return false;
        $host=strtolower((string)($u['host']??''));foreach(['fbcdn.net','fbsbx.com','cdninstagram.com'] as $domain){if($host===$domain||str_ends_with($host,'.'.$domain))return true;}return false;
    }
    public static function metaSecret(array $channel,array $env): string
    {
        $s=self::settings($channel);$custom=self::secret((string)($s['meta_app_secret_ciphertext']??''),$env);
        if($custom!=='')return $custom;
        $app=trim((string)($s['meta_app_id']??''));$global=(string)($env['META_APP_ID']??'1466181682143463');
        return ($app===''||$app===$global)?trim((string)($env['META_APP_SECRET']??'')):'';
    }
    public static function verifiesMeta(string $body,string $signature,string $secret): bool
    {
        return $secret!==''&&preg_match('/^sha256=[a-f0-9]{64}$/D',$signature)===1&&hash_equals('sha256='.hash_hmac('sha256',$body,$secret),$signature);
    }
    public static function sessionValid(string $id): bool {return preg_match('/^[A-Za-z0-9_-]{1,120}$/D',$id)===1;}
    /** Only the server-configured bridge (or its historical loopback default) is reachable. */
    public static function bridgeAllowed(string $url,array $env): bool
    {
        $url=rtrim($url,'/');$configured=rtrim((string)($env['ZYNKO_QR_BRIDGE_URL']??'http://127.0.0.1:8091'),'/');
        if($url===''||!in_array($url,[$configured,'http://127.0.0.1:8091','http://localhost:8091'],true))return false;
        $p=parse_url($url);
        return is_array($p)&&isset($p['host'])&&in_array($p['scheme']??'',['http','https'],true)&&!isset($p['user'])&&!isset($p['pass'])&&!isset($p['query'])&&!isset($p['fragment']);
    }
    public static function externalKey(array $channel,string $id,string $contact=''): string
    {
        if($id==='')return '';
        // WhatsApp wamids are already globally unique; preserve existing history identifiers.
        if(($channel['type']??'')==='whatsapp'&&str_starts_with($id,'wamid.')&&strlen($id)<=190)return $id;
        return 'provider:'.hash('sha256',(string)$channel['id'].'|'.$contact.'|'.$id);
    }
    public static function nextStatus(string $old,string $next): string
    {
        $r=['queued'=>0,'retrying'=>0,'sending'=>0,'uncertain'=>0,'failed'=>0,'sent'=>1,'delivered'=>2,'read'=>3];
        if(!isset($r[$next]))return $old;
        if($next==='failed')return in_array($old,['delivered','read'],true)?$old:'failed';
        return ($r[$next]>=($r[$old]??0))?$next:$old;
    }
    public static function tokenExpired(array $channel): bool
    {
        $expiry=trim((string)($channel['token_expires_at']??''));return $expiry!==''&&($t=strtotime($expiry))!==false&&$t<=time();
    }
}
