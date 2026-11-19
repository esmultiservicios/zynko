<?php
declare(strict_types=1);
require_once __DIR__.'/../Support/ChannelSecurity.php';
require_once __DIR__.'/../Support/Plan.php';
require_once __DIR__.'/ChannelIngress.php';

/** OAuth 2.0 Authorization Code + PKCE and DM polling for a single authorized X user. */
final class ChannelXOAuth
{
    private array $env;
    public const SCOPES='tweet.read users.read dm.read dm.write offline.access';
    public function __construct(private PDO $pdo,private string $root){$this->env=@parse_ini_file($root.'/.env',false,INI_SCANNER_RAW)?:[];}
    public static function ready(array $e): bool {return ChannelSecurity::enabled($e,'X_DM_ENABLED')&&!empty($e['X_CLIENT_ID'])&&self::redirectUri($e)!=='';}
    public static function redirectUri(array $e): string
    {
        $url=rtrim(trim((string)($e['APP_URL']??'')),'/');$p=parse_url($url);return is_array($p)&&($p['scheme']??'')==='https'&&!empty($p['host'])&&!isset($p['query'])&&!isset($p['fragment'])&&!isset($p['user'])&&!isset($p['pass'])?$url.'/?action=channel_x_callback':'';
    }
    private static function b64(string $v): string {return rtrim(strtr(base64_encode($v),'+/','-_'),'=');}
    public function start(int $tid,int $user): array
    {
        if(!self::ready($this->env))throw new RuntimeException('X todavía no está habilitado por el administrador.');
        $verifier=self::b64(random_bytes(48));$state=bin2hex(random_bytes(32));$session=['tenant'=>$tid,'user'=>$user,'state'=>$state,'verifier'=>$verifier,'until'=>time()+600];
        $query=['response_type'=>'code','client_id'=>$this->env['X_CLIENT_ID'],'redirect_uri'=>self::redirectUri($this->env),'scope'=>self::SCOPES,'state'=>$state,'code_challenge'=>self::b64(hash('sha256',$verifier,true)),'code_challenge_method'=>'S256'];
        return ['session'=>$session,'url'=>'https://x.com/i/oauth2/authorize?'.http_build_query($query,'','&',PHP_QUERY_RFC3986)];
    }
    private function request(string $method,string $path,array $params=[],string $bearer='',bool $token=false): array
    {
        // Fixed provider host and paths only; no caller supplied URLs.
        $url='https://api.x.com'.$path;$headers=['Accept: application/json'];
        if($token){$client=(string)($this->env['X_CLIENT_ID']??'');$secret=(string)($this->env['X_CLIENT_SECRET']??'');$params['client_id']=$client;if($secret!=='')$headers[]='Authorization: Basic '.base64_encode(urlencode($client).':'.urlencode($secret));$headers[]='Content-Type: application/x-www-form-urlencoded';}
        elseif($bearer!=='')$headers[]='Authorization: Bearer '.$bearer;
        if($method==='GET'&&$params)$url.='?'.http_build_query($params,'','&',PHP_QUERY_RFC3986);
        $ch=curl_init($url);$raw='';curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>25,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_WRITEFUNCTION=>function($c,string $chunk)use(&$raw){if(strlen($raw)+strlen($chunk)>4*1024*1024)return 0;$raw.=$chunk;return strlen($chunk);}]);
        if($method==='POST')curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($params,'','&',PHP_QUERY_RFC3986));$ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$j=json_decode($raw,true);
        if($ok===false||!is_array($j)||$status<200||$status>=300||isset($j['error']))throw new ChannelDispatchException('X no confirmó la operación (HTTP '.$status.').',$status===429||$status>=500||$status===0?'retrying':'failed');return $j;
    }
    private function token(array $data): array
    {
        $access=(string)($data['access_token']??'');if($access==='')throw new RuntimeException('X no devolvió una autorización válida.');$expires=max(1,(int)($data['expires_in']??7200));return ['access'=>$access,'refresh'=>(string)($data['refresh_token']??''),'expires'=>date('Y-m-d H:i:s',time()+$expires)];
    }
    public function finish(array $flow,int $tid,int $uid,string $state,string $code,array $plan): int
    {
        if(!self::ready($this->env)||($flow['tenant']??0)!==$tid||($flow['user']??0)!==$uid||($flow['until']??0)<time()||empty($flow['state'])||!hash_equals((string)$flow['state'],$state)||$code===''||strlen($code)>4096||!zynkoPlanAllowsChannel($plan,'x'))throw new RuntimeException('La vinculación venció o no está autorizada.');
        $auth=$this->token($this->request('POST','/2/oauth2/token',['grant_type'=>'authorization_code','code'=>$code,'redirect_uri'=>self::redirectUri($this->env),'code_verifier'=>$flow['verifier']],'',true));
        $r=$this->request('GET','/2/users/me',[],$auth['access']);$account=(string)($r['data']['id']??'');$username=(string)($r['data']['username']??'');if(!preg_match('/^[0-9]{1,30}$/D',$account))throw new RuntimeException('No se pudo comprobar la identidad de X.');
        // Confirm DM read access before exposing the connection as operational.
        $this->request('GET','/2/dm_events',['max_results'=>10,'dm_event.fields'=>'id,event_type'], $auth['access']);
        $this->pdo->beginTransaction();$locked=false;
        try{$this->pdo->prepare('SELECT id FROM tenants WHERE id=? FOR UPDATE')->execute([$tid]);$locked=(int)$this->pdo->query("SELECT GET_LOCK('zynko_channel_assets',10)")->fetchColumn()===1;if(!$locked)throw new RuntimeException('Otra vinculación está en curso.');
            $q=$this->pdo->prepare("SELECT * FROM channels WHERE type='x' AND COALESCE(NULLIF(external_phone_id,''),external_account_id)=? FOR UPDATE");$q->execute([$account]);$rows=$q->fetchAll();if(count($rows)>1)throw new RuntimeException('La cuenta tiene vínculos duplicados.');$old=$rows[0]??null;
            if($old&&(int)$old['tenant_id']!==$tid)throw new RuntimeException('La cuenta de X ya pertenece a otra empresa.');
            zynkoPlanRequireConnection($this->pdo,$tid,zynkoPlanContext($this->pdo,$tid,($plan['plan_code']??'')==='platform'),'x',(int)($old['id']??0));
            $s=$old?ChannelSecurity::settings($old):[];$s['provider_mode']='x';$s['oauth_provider']='x';$s['verified_account']=$account;$cipher=ChannelSecurity::encrypt($auth['access'],$this->env);$json=json_encode($s,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
            if($old){$id=(int)$old['id'];$this->pdo->prepare("UPDATE channels SET token_ciphertext=?,token_expires_at=?,settings_json=?,status='connected',last_event_at=NOW() WHERE id=? AND tenant_id=?")->execute([$cipher,$auth['expires'],$json,$id,$tid]);}
            else{$this->pdo->prepare("INSERT INTO channels(tenant_id,uuid,type,name,external_account_id,external_phone_id,display_address,token_ciphertext,token_expires_at,settings_json,status,last_event_at) VALUES(?,?,'x',?,?,?,?,?,?,?,'connected',NOW())")->execute([$tid,ChannelIngress::uuid(),'X · @'.mb_substr($username,0,100),$account,$account,'@'.$username,$cipher,$auth['expires'],$json]);$id=(int)$this->pdo->lastInsertId();}
            $refresh=$auth['refresh']!==''?ChannelSecurity::encrypt($auth['refresh'],$this->env):null;$this->pdo->prepare("INSERT INTO channel_oauth_accounts(channel_id,tenant_id,provider,refresh_ciphertext) VALUES(?,?,'x',?) ON DUPLICATE KEY UPDATE refresh_ciphertext=VALUES(refresh_ciphertext),next_poll_at=NOW(),last_error=NULL")->execute([$id,$tid,$refresh]);$this->pdo->commit();return $id;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}finally{if($locked)$this->pdo->query("SELECT RELEASE_LOCK('zynko_channel_assets')");}
    }
    public function refresh(array $channel): array
    {
        if(!self::ready($this->env))return $channel;
        $expiry=trim((string)($channel['token_expires_at']??''));if($expiry===''||strtotime($expiry)>time()+180)return $channel;
        $id=(int)$channel['id'];$tid=(int)$channel['tenant_id'];$key='zynko_x_refresh_'.$id;$q=$this->pdo->prepare('SELECT GET_LOCK(?,5)');$q->execute([$key]);if((int)$q->fetchColumn()!==1)throw new ChannelDispatchException('Renovación de X en curso.','retrying');
        try{$q=$this->pdo->prepare('SELECT * FROM channels WHERE id=? AND tenant_id=?');$q->execute([$id,$tid]);$channel=array_replace($channel,$q->fetch()?:[]);if(!empty($channel['token_expires_at'])&&strtotime($channel['token_expires_at'])>time()+180)return $channel;
            $q=$this->pdo->prepare("SELECT refresh_ciphertext FROM channel_oauth_accounts WHERE tenant_id=? AND channel_id=? AND provider='x'");$q->execute([$tid,$id]);$refresh=ChannelSecurity::secret((string)$q->fetchColumn(),$this->env);if($refresh==='')throw new ChannelDispatchException('Vuelva a autorizar X.');
            $auth=$this->token($this->request('POST','/2/oauth2/token',['grant_type'=>'refresh_token','refresh_token'=>$refresh],'',true));$cipher=ChannelSecurity::encrypt($auth['access'],$this->env);$rotated=ChannelSecurity::encrypt($auth['refresh']?:$refresh,$this->env);
            $this->pdo->beginTransaction();$this->pdo->prepare('UPDATE channels SET token_ciphertext=?,token_expires_at=? WHERE id=? AND tenant_id=?')->execute([$cipher,$auth['expires'],$id,$tid]);$this->pdo->prepare('UPDATE channel_oauth_accounts SET refresh_ciphertext=?,last_error=NULL WHERE channel_id=? AND tenant_id=?')->execute([$rotated,$id,$tid]);$this->pdo->commit();$channel['token_ciphertext']=$cipher;$channel['token_expires_at']=$auth['expires'];return $channel;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();if(!($e instanceof ChannelDispatchException)||$e->deliveryState!=='retrying')$this->pdo->prepare("UPDATE channels SET status='warning' WHERE id=? AND tenant_id=? AND status='connected'")->execute([$id,$tid]);throw $e;}finally{$this->pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$key]);}
    }
    public static function normalize(array $ev,string $self,int $baseline): ?array
    {
        if(($ev['event_type']??'')!=='MessageCreate'||empty($ev['id'])||empty($ev['sender_id']))return null;
        $sender=(string)$ev['sender_id'];$echo=$sender===$self;$from=$sender;
        // Only one-to-one conversations; never guess the recipient of a group.
        $members=explode('-',(string)($ev['dm_conversation_id']??''));if(count($members)!==2||!in_array($self,$members,true)||($echo&&$members[0]===$members[1]))return null;
        if($echo)$from=$members[0]===$self?$members[1]:$members[0];if(!preg_match('/^[0-9]{1,30}$/D',$from))return null;
        $stamp=strtotime((string)($ev['created_at']??''));if($stamp===false)return null;$text=trim((string)($ev['text']??''));if($text===''&&!empty($ev['attachments']))$text='[Adjunto de X]';if($text==='')return null;
        $out=ChannelWebhookEvents::message($from,$text,(string)$ev['id'],'',$echo,[],$stamp);$out['historical']=$stamp<$baseline;return $out;
    }
    public function poll(int $limit=3): array
    {
        if(!self::ready($this->env))return [];$limit=max(1,min(10,$limit));$q=$this->pdo->query("SELECT ch.*,o.baseline_at,o.cursor_value FROM channel_oauth_accounts o JOIN channels ch ON ch.id=o.channel_id AND ch.tenant_id=o.tenant_id JOIN tenants t ON t.id=ch.tenant_id WHERE o.provider='x' AND ch.type='x' AND ch.status='connected' AND t.status NOT IN ('suspended','closed') AND o.next_poll_at<=NOW() ORDER BY o.next_poll_at LIMIT $limit");$out=[];
        foreach($q->fetchAll() as $ch){$id=(int)$ch['id'];$tid=(int)$ch['tenant_id'];$claim=$this->pdo->prepare('UPDATE channel_oauth_accounts SET next_poll_at=DATE_ADD(NOW(),INTERVAL 5 MINUTE) WHERE channel_id=? AND tenant_id=? AND next_poll_at<=NOW()');$claim->execute([$id,$tid]);if(!$claim->rowCount())continue;
            try{$ch=$this->refresh($ch);$token=ChannelSecurity::secret((string)$ch['token_ciphertext'],$this->env);$cursor=(string)($ch['cursor_value']??'');$pageToken='';$events=[];$newest='';$done=false;
                for($page=0;$page<10;$page++){$param=['max_results'=>100,'event_types'=>'MessageCreate','dm_event.fields'=>'id,event_type,text,created_at,sender_id,dm_conversation_id,attachments'];if($pageToken!=='')$param['pagination_token']=$pageToken;$r=$this->request('GET','/2/dm_events',$param,$token);foreach($r['data']??[] as $ev){$eid=(string)($ev['id']??'');if($newest==='')$newest=$eid;if($cursor!==''&&$eid===$cursor){$done=true;break;}$n=self::normalize($ev,ChannelSecurity::asset($ch),strtotime((string)$ch['baseline_at'])?:time());if($n)$events[]=$n;}if($done)break;$pageToken=(string)($r['meta']['next_token']??'');if($pageToken===''){$done=true;break;}}
                // Keep the old cursor if the volume exceeds this poll; dedup handles retries.
                if(!$done)throw new ChannelDispatchException('Hay más eventos de los procesables en un lote; se requiere sincronización ampliada.','retrying');
                $ingress=new ChannelIngress($this->pdo,$this->root);$n=$ingress->handle($ch,array_reverse($events));$this->pdo->prepare('UPDATE channel_oauth_accounts SET cursor_value=COALESCE(NULLIF(?,\'\'),cursor_value),last_poll_at=NOW(),next_poll_at=DATE_ADD(NOW(),INTERVAL 2 MINUTE),last_error=NULL WHERE channel_id=? AND tenant_id=?')->execute([$newest,$id,$tid]);$out[$id]=$n;
            }catch(Throwable $e){$this->pdo->prepare('UPDATE channel_oauth_accounts SET last_error=?,next_poll_at=DATE_ADD(NOW(),INTERVAL 10 MINUTE) WHERE channel_id=? AND tenant_id=?')->execute([$e instanceof ChannelDispatchException?mb_substr($e->getMessage(),0,255):'No se pudo sincronizar X.',$id,$tid]);$out[$id]=-1;}}
        return $out;
    }
}
