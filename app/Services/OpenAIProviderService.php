<?php
declare(strict_types=1);

final class OpenAIProviderService
{
    private PDO $pdo;
    private string $root;

    public function __construct(PDO $pdo,string $root){$this->pdo=$pdo;$this->root=$root;}

    public static function ensureSchema(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS ai_provider_settings(
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
            provider VARCHAR(30) NOT NULL DEFAULT 'openai',
            enabled TINYINT(1) NOT NULL DEFAULT 0,
            api_key_ciphertext TEXT NULL,
            admin_key_ciphertext TEXT NULL,
            model VARCHAR(120) NOT NULL DEFAULT 'gpt-6-luna',
            fallback_only TINYINT(1) NOT NULL DEFAULT 1,
            monthly_budget_usd DECIMAL(12,4) NULL,
            input_cost_per_million DECIMAL(12,6) NOT NULL DEFAULT 0.050000,
            cached_input_cost_per_million DECIMAL(12,6) NOT NULL DEFAULT 0.005000,
            output_cost_per_million DECIMAL(12,6) NOT NULL DEFAULT 0.250000,
            max_output_tokens INT UNSIGNED NOT NULL DEFAULT 700,
            remote_month_cost_usd DECIMAL(12,4) NULL,
            remote_cost_refreshed_at DATETIME NULL,
            last_test_at DATETIME NULL,
            last_test_status VARCHAR(20) NULL,
            last_test_message VARCHAR(500) NULL,
            updated_by BIGINT UNSIGNED NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS tenant_ai_settings(
            tenant_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
            enabled TINYINT(1) NOT NULL DEFAULT 0,
            allowed_channels_json JSON NULL,
            updated_by BIGINT UNSIGNED NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS ai_usage_logs(
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            conversation_id BIGINT UNSIGNED NULL,
            provider VARCHAR(30) NOT NULL DEFAULT 'openai',
            channel_type VARCHAR(50) NOT NULL,
            model VARCHAR(120) NOT NULL,
            request_id VARCHAR(190) NULL,
            input_tokens BIGINT UNSIGNED NOT NULL DEFAULT 0,
            cached_input_tokens BIGINT UNSIGNED NOT NULL DEFAULT 0,
            output_tokens BIGINT UNSIGNED NOT NULL DEFAULT 0,
            estimated_cost_usd DECIMAL(14,8) NOT NULL DEFAULT 0,
            status ENUM('ok','error','blocked') NOT NULL DEFAULT 'ok',
            error_message VARCHAR(1000) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ai_usage_tenant_month(tenant_id,created_at),
            INDEX idx_ai_usage_provider_month(provider,created_at),
            INDEX idx_ai_usage_conversation(conversation_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("INSERT IGNORE INTO ai_provider_settings(id,provider,enabled,model,fallback_only) VALUES(1,'openai',0,'gpt-6-luna',1)");
    }

    private function env(): array { $v=@parse_ini_file($this->root.'/.env',false,INI_SCANNER_RAW);return is_array($v)?$v:[]; }
    private function encrypt(string $plain): string
    {
        $hex=(string)($this->env()['APP_KEY']??'');if(!preg_match('/^[a-f0-9]{64}$/i',$hex))throw new RuntimeException('APP_KEY inválida para proteger la credencial de OpenAI.');
        $iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($plain,'aes-256-gcm',hex2bin($hex),OPENSSL_RAW_DATA,$iv,$tag);if($cipher===false)throw new RuntimeException('No se pudo cifrar la credencial de OpenAI.');
        return 'enc:v1:'.base64_encode($iv.$tag.$cipher);
    }
    private function decrypt(?string $cipher): string
    {
        if(!$cipher||!str_starts_with($cipher,'enc:v1:'))return '';$hex=(string)($this->env()['APP_KEY']??'');if(!preg_match('/^[a-f0-9]{64}$/i',$hex))return '';
        $raw=base64_decode(substr($cipher,7),true);if($raw===false||strlen($raw)<29)return '';$plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',hex2bin($hex),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));return $plain===false?'':$plain;
    }

    public function settings(): array
    {
        self::ensureSchema($this->pdo);$s=$this->pdo->query('SELECT * FROM ai_provider_settings WHERE id=1')->fetch()?:[];
        $s['api_key_configured']=$this->decrypt($s['api_key_ciphertext']??null)!=='';$s['admin_key_configured']=$this->decrypt($s['admin_key_ciphertext']??null)!=='';
        unset($s['api_key_ciphertext'],$s['admin_key_ciphertext']);return $s;
    }

    public function saveGlobal(array $data,int $userId): void
    {
        self::ensureSchema($this->pdo);$current=$this->pdo->query('SELECT * FROM ai_provider_settings WHERE id=1')->fetch()?:[];
        $api=trim((string)($data['api_key']??''));$admin=trim((string)($data['admin_key']??''));
        $apiCipher=$api!==''?$this->encrypt($api):($current['api_key_ciphertext']??null);$adminCipher=$admin!==''?$this->encrypt($admin):($current['admin_key_ciphertext']??null);if(!empty($data['enabled'])&&$this->decrypt($apiCipher)==='')throw new RuntimeException('Guarda una API Key válida antes de activar OpenAI.');
        $model=preg_replace('/[^a-zA-Z0-9._-]/','',trim((string)($data['model']??'gpt-6-luna')))?:'gpt-6-luna';
        $budget=max(0,(float)($data['monthly_budget_usd']??0));$in=max(0,(float)($data['input_cost_per_million']??0.05));$cached=max(0,(float)($data['cached_input_cost_per_million']??0.005));$out=max(0,(float)($data['output_cost_per_million']??0.25));$maxOut=max(100,min(4000,(int)($data['max_output_tokens']??700)));
        $q=$this->pdo->prepare("INSERT INTO ai_provider_settings(id,provider,enabled,api_key_ciphertext,admin_key_ciphertext,model,fallback_only,monthly_budget_usd,input_cost_per_million,cached_input_cost_per_million,output_cost_per_million,max_output_tokens,updated_by) VALUES(1,'openai',?,?,?,?,1,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),api_key_ciphertext=VALUES(api_key_ciphertext),admin_key_ciphertext=VALUES(admin_key_ciphertext),model=VALUES(model),fallback_only=1,monthly_budget_usd=VALUES(monthly_budget_usd),input_cost_per_million=VALUES(input_cost_per_million),cached_input_cost_per_million=VALUES(cached_input_cost_per_million),output_cost_per_million=VALUES(output_cost_per_million),max_output_tokens=VALUES(max_output_tokens),updated_by=VALUES(updated_by)");
        $q->execute([!empty($data['enabled'])?1:0,$apiCipher,$adminCipher,$model,$budget?:null,$in,$cached,$out,$maxOut,$userId]);
    }

    public function saveTenant(int $tenantId,array $data,int $userId,array $planCtx): void
    {
        self::ensureSchema($this->pdo);$planAllowed=!empty($planCtx['unrestricted'])||!empty($planCtx['external_ai_included']);$enabled=!empty($data['enabled'])&&$planAllowed?1:0;if($enabled){$g=$this->pdo->query('SELECT enabled,api_key_ciphertext FROM ai_provider_settings WHERE id=1')->fetch()?:[];if(empty($g['enabled'])||$this->decrypt($g['api_key_ciphertext']??null)==='')throw new RuntimeException('OpenAI todavía no está conectado y habilitado por el administrador principal.');}
        $channels=array_values(array_unique(array_filter(array_map(fn($v)=>preg_replace('/[^a-z0-9_-]/','',strtolower((string)$v)),(array)($data['channels']??[])))));
        $planChannels=$planCtx['external_ai_channels']??[];if(empty($planCtx['unrestricted'])&&$planChannels)$channels=array_values(array_intersect($channels,$planChannels));
        if(!$channels)$channels=['webchat'];
        $q=$this->pdo->prepare('INSERT INTO tenant_ai_settings(tenant_id,enabled,allowed_channels_json,updated_by) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),allowed_channels_json=VALUES(allowed_channels_json),updated_by=VALUES(updated_by)');
        $q->execute([$tenantId,$enabled,json_encode($channels,JSON_UNESCAPED_SLASHES),$userId]);
    }

    public function tenantSettings(int $tenantId): array
    {
        self::ensureSchema($this->pdo);$q=$this->pdo->prepare('SELECT * FROM tenant_ai_settings WHERE tenant_id=?');$q->execute([$tenantId]);$r=$q->fetch()?:[];$r['channels']=json_decode((string)($r['allowed_channels_json']??'[]'),true)?:[];return $r;
    }

    private function curlJson(string $url,string $key,string $method='GET',?array $payload=null): array
    {
        if(!function_exists('curl_init'))throw new RuntimeException('cURL no está disponible en el servidor.');$ch=curl_init($url);$headers=['Authorization: Bearer '.$key,'Content-Type: application/json'];$opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>35,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_HTTPHEADER=>$headers];if($method!=='GET'){$opts[CURLOPT_CUSTOMREQUEST]=$method;$opts[CURLOPT_POSTFIELDS]=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}curl_setopt_array($ch,$opts);$raw=curl_exec($ch);$errno=curl_errno($ch);$error=curl_error($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);if($errno)throw new RuntimeException('No fue posible conectar con OpenAI: '.$error);$json=json_decode((string)$raw,true);if($code<200||$code>=300){$msg=is_array($json)?(string)($json['error']['message']??'OpenAI rechazó la solicitud.'):'OpenAI rechazó la solicitud.';throw new RuntimeException($msg.' (HTTP '.$code.')');}return is_array($json)?$json:[];
    }

    public function testConnection(): array
    {
        self::ensureSchema($this->pdo);$raw=$this->pdo->query('SELECT * FROM ai_provider_settings WHERE id=1')->fetch()?:[];$key=$this->decrypt($raw['api_key_ciphertext']??null);if($key==='')throw new RuntimeException('Primero guarda una API Key de OpenAI.');
        try{$json=$this->curlJson('https://api.openai.com/v1/models',$key);$message='Conexión correcta con OpenAI. '.count($json['data']??[]).' modelo(s) visibles para la clave.';$this->pdo->prepare("UPDATE ai_provider_settings SET last_test_at=NOW(),last_test_status='ok',last_test_message=? WHERE id=1")->execute([$message]);return ['ok'=>true,'message'=>$message];}
        catch(Throwable $e){$this->pdo->prepare("UPDATE ai_provider_settings SET last_test_at=NOW(),last_test_status='error',last_test_message=? WHERE id=1")->execute([mb_substr($e->getMessage(),0,500)]);throw $e;}
    }

    public function refreshOrganizationCost(): float
    {
        self::ensureSchema($this->pdo);$raw=$this->pdo->query('SELECT * FROM ai_provider_settings WHERE id=1')->fetch()?:[];$key=$this->decrypt($raw['admin_key_ciphertext']??null);if($key==='')throw new RuntimeException('Agrega una Admin API Key para consultar el costo real de la organización.');
        $start=(new DateTimeImmutable('first day of this month 00:00:00',new DateTimeZone('UTC')))->getTimestamp();$end=(new DateTimeImmutable('first day of next month 00:00:00',new DateTimeZone('UTC')))->getTimestamp();$url='https://api.openai.com/v1/organization/costs?start_time='.$start.'&end_time='.$end.'&limit=31';$json=$this->curlJson($url,$key);$sum=0.0;foreach(($json['data']??[]) as $bucket)foreach(($bucket['results']??[]) as $r)$sum+=(float)($r['amount']['value']??0);$this->pdo->prepare('UPDATE ai_provider_settings SET remote_month_cost_usd=?,remote_cost_refreshed_at=NOW() WHERE id=1')->execute([$sum]);return $sum;
    }

    public function usageSummary(?int $tenantId=null): array
    {
        self::ensureSchema($this->pdo);$where="created_at>=DATE_FORMAT(CURDATE(),'%Y-%m-01')";$args=[];if($tenantId!==null){$where.=' AND tenant_id=?';$args[]=$tenantId;}$q=$this->pdo->prepare("SELECT COALESCE(SUM(input_tokens),0) input_tokens,COALESCE(SUM(cached_input_tokens),0) cached_input_tokens,COALESCE(SUM(output_tokens),0) output_tokens,COALESCE(SUM(estimated_cost_usd),0) estimated_cost_usd,COUNT(*) requests FROM ai_usage_logs WHERE $where AND status='ok'");$q->execute($args);return $q->fetch()?:['input_tokens'=>0,'cached_input_tokens'=>0,'output_tokens'=>0,'estimated_cost_usd'=>0,'requests'=>0];
    }

    private function canUse(int $tenantId,string $channelType): array
    {
        self::ensureSchema($this->pdo);$global=$this->pdo->query('SELECT * FROM ai_provider_settings WHERE id=1')->fetch()?:[];if(empty($global['enabled']))return [false,'provider_disabled',$global,[]];$key=$this->decrypt($global['api_key_ciphertext']??null);if($key==='')return [false,'missing_api_key',$global,[]];
        $tenant=$this->tenantSettings($tenantId);if(empty($tenant['enabled']))return [false,'tenant_disabled',$global,$tenant];$channels=$tenant['channels']??[];if($channels&&!in_array($channelType,$channels,true))return [false,'tenant_channel_disabled',$global,$tenant];
        if(function_exists('zynkoEnsurePlanSchema'))zynkoEnsurePlanSchema($this->pdo);$planCtx=function_exists('zynkoPlanContext')?zynkoPlanContext($this->pdo,$tenantId,false):['unrestricted'=>true];if(empty($planCtx['unrestricted'])){if(empty($planCtx['external_ai_included']))return [false,'plan_not_included',$global,$tenant];$pc=$planCtx['external_ai_channels']??[];if($pc&&!in_array($channelType,$pc,true))return [false,'plan_channel_disabled',$global,$tenant];$limit=(int)($planCtx['external_ai_monthly_tokens']??0);if($limit>0){$u=$this->usageSummary($tenantId);$used=(int)$u['input_tokens']+(int)$u['output_tokens'];if($used>=$limit)return [false,'plan_token_limit',$global,$tenant];}}
        $budget=(float)($global['monthly_budget_usd']??0);if($budget>0){$u=$this->usageSummary(null);if((float)$u['estimated_cost_usd']>=$budget)return [false,'global_budget_limit',$global,$tenant];}
        $global['_api_key']=$key;return [true,'ok',$global,$tenant];
    }

    public function fallback(int $tenantId,int $conversationId,string $channelType,string $message,string $contactName,string $companyName): ?array
    {
        [$allowed,$reason,$cfg]=$this->canUse($tenantId,$channelType);if(!$allowed){return null;}
        try{
            $context=[];$q=$this->pdo->prepare("SELECT direction,sender_type,body FROM messages WHERE tenant_id=? AND conversation_id=? AND body IS NOT NULL ORDER BY id DESC LIMIT 8");$q->execute([$tenantId,$conversationId]);$rows=array_reverse($q->fetchAll());foreach($rows as $r){$body=trim((string)$r['body']);if($body==='')continue;$context[]=(($r['direction']??'in')==='in'?'Cliente':'NIVO/Agente').': '.mb_substr($body,0,700);}
            $knowledge=[];$q=$this->pdo->prepare("SELECT name,content FROM knowledge_sources WHERE tenant_id=? AND status='ready' AND approval_status='approved' AND content IS NOT NULL ORDER BY updated_at DESC LIMIT 12");$q->execute([$tenantId]);$budgetChars=7000;foreach($q->fetchAll() as $r){$chunk=trim((string)$r['content']);if($chunk==='')continue;$chunk=mb_substr($chunk,0,1400);if($budgetChars-mb_strlen($chunk)<0)break;$knowledge[]='['.$r['name'].'] '.$chunk;$budgetChars-=mb_strlen($chunk);}
            $instructions="Eres NIVO, la inteligencia artificial de {$companyName}. Atiendes el canal {$channelType}. Nunca te presentes como ChatGPT ni como OpenAI; eres NIVO para el cliente. Usa primero y principalmente la información autorizada proporcionada. Si falta un dato crítico, dilo con claridad y pide lo necesario; no inventes políticas, precios, estados de cuenta ni datos de la empresa. Responde en el idioma del cliente, de forma profesional, útil y breve. Si te preguntan por tu identidad, responde que eres NIVO, la IA de {$companyName}.";
            $input="Cliente: ".($contactName!==''?$contactName:'Visitante')."\nCanal: {$channelType}\n\nContexto reciente:\n".implode("\n",$context)."\n\nConocimiento autorizado:\n".($knowledge?implode("\n\n",$knowledge):'No hay fuentes adicionales disponibles.')."\n\nMensaje actual:\n{$message}";
            $payload=['model'=>(string)($cfg['model']??'gpt-6-luna'),'instructions'=>$instructions,'input'=>$input,'max_output_tokens'=>(int)($cfg['max_output_tokens']??700)];$json=$this->curlJson('https://api.openai.com/v1/responses',(string)$cfg['_api_key'],'POST',$payload);
            $reply='';if(!empty($json['output_text'])&&is_string($json['output_text']))$reply=trim($json['output_text']);if($reply===''){foreach(($json['output']??[]) as $out)foreach(($out['content']??[]) as $c){if(($c['type']??'')==='output_text'&&isset($c['text']))$reply.=($reply!==''?"\n":'').trim((string)$c['text']);}}
            if($reply==='')throw new RuntimeException('OpenAI no devolvió texto utilizable.');$usage=$json['usage']??[];$in=(int)($usage['input_tokens']??0);$out=(int)($usage['output_tokens']??0);$cached=(int)($usage['input_tokens_details']['cached_tokens']??0);$billable=max(0,$in-$cached);$cost=($billable/1000000)*(float)$cfg['input_cost_per_million']+($cached/1000000)*(float)$cfg['cached_input_cost_per_million']+($out/1000000)*(float)$cfg['output_cost_per_million'];
            $this->pdo->prepare("INSERT INTO ai_usage_logs(tenant_id,conversation_id,provider,channel_type,model,request_id,input_tokens,cached_input_tokens,output_tokens,estimated_cost_usd,status) VALUES(?,?,'openai',?,?,?,?,?,?,?,'ok')")->execute([$tenantId,$conversationId,$channelType,(string)$cfg['model'],(string)($json['id']??''),$in,$cached,$out,$cost]);
            return ['reply'=>$reply,'source'=>'openai:'.($cfg['model']??'gpt-6-luna'),'confidence'=>'ai','usage'=>['input_tokens'=>$in,'cached_input_tokens'=>$cached,'output_tokens'=>$out,'estimated_cost_usd'=>$cost]];
        }catch(Throwable $e){try{$this->pdo->prepare("INSERT INTO ai_usage_logs(tenant_id,conversation_id,provider,channel_type,model,status,error_message) VALUES(?,?,'openai',?,?,'error',?)")->execute([$tenantId,$conversationId,$channelType,(string)($cfg['model']??'gpt-6-luna'),mb_substr($e->getMessage(),0,1000)]);}catch(Throwable $ignore){}return null;}
    }
}
