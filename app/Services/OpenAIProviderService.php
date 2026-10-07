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
            allowed_origins_json JSON NULL,
            max_requests_per_minute INT UNSIGNED NOT NULL DEFAULT 60,
            redact_sensitive TINYINT(1) NOT NULL DEFAULT 1,
            require_approved_knowledge TINYINT(1) NOT NULL DEFAULT 1,
            log_decisions TINYINT(1) NOT NULL DEFAULT 1,
            updated_by BIGINT UNSIGNED NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        foreach([
            'allowed_origins_json'=>'JSON NULL',
            'max_requests_per_minute'=>'INT UNSIGNED NOT NULL DEFAULT 60',
            'redact_sensitive'=>'TINYINT(1) NOT NULL DEFAULT 1',
            'require_approved_knowledge'=>'TINYINT(1) NOT NULL DEFAULT 1',
            'log_decisions'=>'TINYINT(1) NOT NULL DEFAULT 1'
        ] as $column=>$definition){try{$c=$pdo->query("SHOW COLUMNS FROM tenant_ai_settings LIKE ".$pdo->quote($column))->fetch();if(!$c)$pdo->exec("ALTER TABLE tenant_ai_settings ADD `{$column}` {$definition}");}catch(Throwable $ignore){}}
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
        $origins=[];foreach(preg_split('/[\r\n,;]+/',(string)($data['allowed_origins']??''))?:[] as $origin){$origin=trim($origin);if($origin==='')continue;if(!preg_match('#^https?://#i',$origin))$origin='https://'.$origin;$p=parse_url($origin);if(is_array($p)&&!empty($p['scheme'])&&!empty($p['host'])){$normalized=strtolower((string)$p['scheme']).'://'.strtolower((string)$p['host']).(!empty($p['port'])?':'.(int)$p['port']:'');if(!in_array($normalized,$origins,true))$origins[]=$normalized;}}$origins=array_slice($origins,0,50);
        $rate=max(5,min(600,(int)($data['max_requests_per_minute']??60)));$redact=!empty($data['redact_sensitive'])?1:0;$approved=!empty($data['require_approved_knowledge'])?1:0;$log=!empty($data['log_decisions'])?1:0;
        $q=$this->pdo->prepare('INSERT INTO tenant_ai_settings(tenant_id,enabled,allowed_channels_json,allowed_origins_json,max_requests_per_minute,redact_sensitive,require_approved_knowledge,log_decisions,updated_by) VALUES(?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),allowed_channels_json=VALUES(allowed_channels_json),allowed_origins_json=VALUES(allowed_origins_json),max_requests_per_minute=VALUES(max_requests_per_minute),redact_sensitive=VALUES(redact_sensitive),require_approved_knowledge=VALUES(require_approved_knowledge),log_decisions=VALUES(log_decisions),updated_by=VALUES(updated_by)');
        $q->execute([$tenantId,$enabled,json_encode($channels,JSON_UNESCAPED_SLASHES),json_encode($origins,JSON_UNESCAPED_SLASHES),$rate,$redact,$approved,$log,$userId]);
    }

    public function tenantSettings(int $tenantId): array
    {
        self::ensureSchema($this->pdo);$q=$this->pdo->prepare('SELECT * FROM tenant_ai_settings WHERE tenant_id=?');$q->execute([$tenantId]);$r=$q->fetch()?:[];$r['channels']=json_decode((string)($r['allowed_channels_json']??'[]'),true)?:[];$r['allowed_origins']=json_decode((string)($r['allowed_origins_json']??'[]'),true)?:[];return $r;
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
        $tenant=$this->tenantSettings($tenantId);if(empty($tenant['enabled']))return [false,'tenant_disabled',$global,$tenant];$channels=$tenant['channels']??[];if($channels&&!in_array($channelType,$channels,true))return [false,'tenant_channel_disabled',$global,$tenant];$rate=max(5,min(600,(int)($tenant['max_requests_per_minute']??60)));try{$rq=$this->pdo->prepare("SELECT COUNT(*) FROM ai_usage_logs WHERE tenant_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL 1 MINUTE)");$rq->execute([$tenantId]);if((int)$rq->fetchColumn()>=$rate)return [false,'tenant_rate_limit',$global,$tenant];}catch(Throwable $ignore){}
        if(function_exists('zynkoEnsurePlanSchema'))zynkoEnsurePlanSchema($this->pdo);$planCtx=function_exists('zynkoPlanContext')?zynkoPlanContext($this->pdo,$tenantId,false):['unrestricted'=>true];if(empty($planCtx['unrestricted'])){if(empty($planCtx['external_ai_included']))return [false,'plan_not_included',$global,$tenant];$pc=$planCtx['external_ai_channels']??[];if($pc&&!in_array($channelType,$pc,true))return [false,'plan_channel_disabled',$global,$tenant];$limit=(int)($planCtx['external_ai_monthly_tokens']??0);if($limit>0){$u=$this->usageSummary($tenantId);$used=(int)$u['input_tokens']+(int)$u['output_tokens'];if($used>=$limit)return [false,'plan_token_limit',$global,$tenant];}}
        $budget=(float)($global['monthly_budget_usd']??0);if($budget>0){$u=$this->usageSummary(null);if((float)$u['estimated_cost_usd']>=$budget)return [false,'global_budget_limit',$global,$tenant];}
        $global['_api_key']=$key;return [true,'ok',$global,$tenant];
    }


    /** Embeddings semánticos aislados por tenant. Si OpenAI no está habilitado, devuelve []. */
    public function embeddingVectors(int $tenantId,array $texts): array
    {
        $texts=array_values(array_filter(array_map(static fn($v)=>trim((string)$v),$texts),static fn($v)=>$v!==''));
        if(!$texts)return [];
        self::ensureSchema($this->pdo);
        $global=$this->pdo->query('SELECT * FROM ai_provider_settings WHERE id=1')->fetch()?:[];
        if(empty($global['enabled']))return [];
        $key=$this->decrypt($global['api_key_ciphertext']??null);if($key==='')return [];
        $tenant=$this->tenantSettings($tenantId);if(empty($tenant['enabled']))return [];
        $texts=array_map(static fn($v)=>mb_substr($v,0,8000),array_slice($texts,0,64));
        try{$json=$this->curlJson('https://api.openai.com/v1/embeddings',$key,'POST',['model'=>'text-embedding-3-small','input'=>$texts]);$out=[];foreach(($json['data']??[]) as $row){$out[(int)($row['index']??count($out))]=$row['embedding']??[];}ksort($out);return array_values($out);}catch(Throwable $e){return [];}
    }

    /**
     * Orquestación conversacional general. No contiene nombres de productos del propietario de ZYNKO.
     * El modelo recibe exclusivamente memoria, historial y conocimiento ya filtrado por tenant_id.
     */
    public function conversationTurn(int $tenantId,int $conversationId,string $channelType,string $message,string $contactName,string $companyName,array $memory,array $history,array $knowledge): ?array
    {
        [$allowed,$reason,$cfg,$tenantCfg]=$this->canUse($tenantId,$channelType);if(!$allowed)return null;
        try{
            $historyText=[];foreach(array_slice($history,-14) as $h)$historyText[]=strtoupper((string)($h['role']??'customer')).': '.mb_substr((string)($h['text']??''),0,1000);
            $knowledgeText=[];foreach(array_slice($knowledge,0,6) as $k)$knowledgeText[]='['.($k['title']??'Fuente')."]\n".mb_substr((string)($k['content']??''),0,1800);
            $memorySafe=[
                'summary'=>(string)($memory['summary']??''),
                'intent'=>(string)($memory['current_intent']??''),
                'entities'=>(array)($memory['entities']??[]),
                'requirements'=>(array)($memory['requirements']??[]),
                'open_questions'=>(array)($memory['open_questions']??[]),
                'commercial_state'=>(array)($memory['commercial_state']??[]),
            ];
            $tenantPrompt='';try{$tp=$this->pdo->prepare('SELECT system_prompt FROM bot_profiles WHERE tenant_id=? LIMIT 1');$tp->execute([$tenantId]);$tenantPrompt=trim((string)($tp->fetchColumn()?:''));}catch(Throwable $ignore){}
            $instructions="Eres NIVO, asistente conversacional de la empresa indicada. Trabajas en una plataforma multiempresa. REGLAS ABSOLUTAS: 1) usa únicamente el contexto y conocimiento que recibes en esta solicitud; nunca mezcles ni supongas datos de otra empresa; 2) no inventes precios, promociones, funciones, políticas, ubicaciones, demos ni disponibilidad; 3) entiende el mensaje en contexto y no repitas preguntas cuya respuesta ya esté en memoria; 4) si falta información crítica, haz UNA sola pregunta de alto valor antes de recomendar; 5) si recomiendas un producto o plan, elige el MÁS ECONÓMICO que cubra correctamente TODAS las necesidades conocidas, nunca el más caro por defecto; si no tienes suficiente información de necesidades o planes, pregunta o reconoce la limitación; 6) una pregunta lateral no borra el contexto comercial anterior: respóndela y conserva el estado de la oportunidad; 7) mencionar palabras como persona, humano o agente NO significa solicitar transferencia. handoff=true solamente cuando el cliente pide explícitamente hablar/conectarse con una persona, o cuando una regla/situación sensible exige intervención; 8) si no sabes, dilo y pide aclaración; jamás cierres, desconectes ni rompas la conversación; 9) lenguaje sencillo, humano, breve y útil para personas no técnicas; 10) el seguimiento comercial es interno: puedes sugerirlo, pero nunca prometas que se enviará automáticamente; 11) si el cliente dice que lo evaluará, lo revisará o volverá después, NO lo marques como perdido: conserva estado evaluating y sugiere seguimiento interno razonable; 12) extrae y conserva datos comerciales útiles (ciudad, negocio, usuarios, sucursales, necesidades, producto, plan evaluado, demo, estado) solo cuando aparezcan realmente; 13) razona con el historial completo resumido, no por palabras aisladas. Devuelve SOLO JSON válido, sin Markdown.".($tenantPrompt!==''?"\n\nINSTRUCCIONES ADICIONALES CONFIGURADAS POR ESTE TENANT:\n".$tenantPrompt:'');
            $schema="Formato JSON obligatorio: {\"reply\":\"texto para el cliente\",\"intent\":\"intencion breve\",\"language\":\"es|en|otro\",\"confidence\":\"high|medium|low\",\"needs_clarification\":false,\"missing_information\":[\"dato\"],\"open_questions\":[\"pregunta pendiente\"],\"entities\":{},\"requirements\":{},\"commercial\":false,\"commercial_state\":{\"product_interest\":\"\",\"evaluated_plan\":\"\",\"demo_url\":\"\",\"demo_sent\":false,\"status\":\"new|qualifying|evaluating|won|lost|support|\"},\"follow_up_recommended\":false,\"follow_up_days\":3,\"follow_up_reason\":\"\",\"handoff\":false,\"handoff_reason\":\"\",\"memory_summary\":\"resumen acumulativo corto, preservando datos útiles anteriores\"}.";
            $input="Empresa/tenant: {$companyName}\nCanal: {$channelType}\nCliente: ".($contactName!==''?$contactName:'Visitante')."\n\nMEMORIA ESTRUCTURADA:\n".json_encode($memorySafe,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n\nHISTORIAL RECIENTE:\n".implode("\n",$historyText)."\n\nCONOCIMIENTO RECUPERADO DEL TENANT:\n".($knowledgeText?implode("\n\n",$knowledgeText):'No hay fragmentos relevantes recuperados.')."\n\nMENSAJE ACTUAL:\n{$message}\n\n{$schema}";
            if(!empty($tenantCfg['redact_sensitive'])){$redact=static fn(string $v):string=>preg_replace(['/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/iu','/\b(?:\d[ -]*?){13,19}\b/u'],['[correo protegido]','[dato protegido]'],$v)??$v;$input=$redact($input);}
            $payload=['model'=>(string)($cfg['model']??'gpt-6-luna'),'instructions'=>$instructions,'input'=>$input,'max_output_tokens'=>max(450,(int)($cfg['max_output_tokens']??700))];$json=$this->curlJson('https://api.openai.com/v1/responses',(string)$cfg['_api_key'],'POST',$payload);
            $raw='';if(!empty($json['output_text'])&&is_string($json['output_text']))$raw=trim($json['output_text']);if($raw===''){foreach(($json['output']??[]) as $out)foreach(($out['content']??[]) as $c){if(($c['type']??'')==='output_text'&&isset($c['text']))$raw.=($raw!==''?"\n":'').trim((string)$c['text']);}}
            $candidate=trim($raw);$candidate=preg_replace('/^```(?:json)?\s*|\s*```$/u','',$candidate)??$candidate;$turn=json_decode($candidate,true);if(!is_array($turn)||trim((string)($turn['reply']??''))==='')return null;
            $usage=$json['usage']??[];$in=(int)($usage['input_tokens']??0);$out=(int)($usage['output_tokens']??0);$cached=(int)($usage['input_tokens_details']['cached_tokens']??0);$billable=max(0,$in-$cached);$cost=($billable/1000000)*(float)$cfg['input_cost_per_million']+($cached/1000000)*(float)$cfg['cached_input_cost_per_million']+($out/1000000)*(float)$cfg['output_cost_per_million'];$this->pdo->prepare("INSERT INTO ai_usage_logs(tenant_id,conversation_id,provider,channel_type,model,request_id,input_tokens,cached_input_tokens,output_tokens,estimated_cost_usd,status) VALUES(?,?,'openai',?,?,?,?,?,?,?,'ok')")->execute([$tenantId,$conversationId,$channelType,(string)$cfg['model'],(string)($json['id']??''),$in,$cached,$out,$cost]);$turn['_source']='openai:conversation-orchestrator:'.($cfg['model']??'gpt-6-luna');return $turn;
        }catch(Throwable $e){try{$this->pdo->prepare("INSERT INTO ai_usage_logs(tenant_id,conversation_id,provider,channel_type,model,status,error_message) VALUES(?,?,'openai',?,?,'error',?)")->execute([$tenantId,$conversationId,$channelType,(string)($cfg['model']??'gpt-6-luna'),mb_substr($e->getMessage(),0,1000)]);}catch(Throwable $ignore){}return null;}
    }

    public function fallback(int $tenantId,int $conversationId,string $channelType,string $message,string $contactName,string $companyName): ?array
    {
        [$allowed,$reason,$cfg,$tenantCfg]=$this->canUse($tenantId,$channelType);if(!$allowed){return null;}
        try{
            $context=[];$ctxLimit=8;try{$bp=$this->pdo->prepare("SELECT channel_policy_json FROM bot_profiles WHERE tenant_id=? LIMIT 1");$bp->execute([$tenantId]);$bpj=json_decode((string)($bp->fetchColumn()?:"{}"),true)?:[];$ctxLimit=max(1,min(20,(int)($bpj['context_messages']??8)));}catch(Throwable $ignore){}$q=$this->pdo->prepare("SELECT direction,sender_type,body FROM messages WHERE tenant_id=? AND conversation_id=? AND body IS NOT NULL ORDER BY id DESC LIMIT ".$ctxLimit);$q->execute([$tenantId,$conversationId]);$rows=array_reverse($q->fetchAll());foreach($rows as $r){$body=trim((string)$r['body']);if($body==='')continue;$context[]=(($r['direction']??'in')==='in'?'Cliente':'NIVO/Agente').': '.mb_substr($body,0,700);}
            $knowledge=[];$q=$this->pdo->prepare("SELECT name,content FROM knowledge_sources WHERE tenant_id=? AND status='ready' AND approval_status='approved' AND content IS NOT NULL ORDER BY updated_at DESC LIMIT 12");$q->execute([$tenantId]);$budgetChars=7000;foreach($q->fetchAll() as $r){$chunk=trim((string)$r['content']);if($chunk==='')continue;$chunk=mb_substr($chunk,0,1400);if($budgetChars-mb_strlen($chunk)<0)break;$knowledge[]='['.$r['name'].'] '.$chunk;$budgetChars-=mb_strlen($chunk);}
            $instructions="Eres NIVO, la inteligencia artificial de {$companyName}. Atiendes el canal {$channelType}. Nunca te presentes como ChatGPT ni como OpenAI; eres NIVO para el cliente. Usa primero y principalmente la información autorizada proporcionada. Si falta un dato crítico, dilo con claridad y pide lo necesario; no inventes políticas, precios, estados de cuenta ni datos de la empresa. Responde en el idioma del cliente, de forma profesional, útil y breve. Si te preguntan por tu identidad, responde que eres NIVO, la IA de {$companyName}.";
            $safeMessage=$message;$safeContext=$context;if(!empty($tenantCfg['redact_sensitive'])){$redact=static fn(string $v):string=>preg_replace(['/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/iu','/\b(?:\d[ -]*?){13,19}\b/u'],['[correo protegido]','[dato protegido]'],$v)??$v;$safeMessage=$redact($safeMessage);$safeContext=array_map($redact,$safeContext);}$input="Cliente: ".($contactName!==''?$contactName:'Visitante')."\nCanal: {$channelType}\n\nContexto reciente:\n".implode("\n",$safeContext)."\n\nConocimiento autorizado:\n".($knowledge?implode("\n\n",$knowledge):'No hay fuentes adicionales disponibles.')."\n\nMensaje actual:\n{$safeMessage}";
            $payload=['model'=>(string)($cfg['model']??'gpt-6-luna'),'instructions'=>$instructions,'input'=>$input,'max_output_tokens'=>(int)($cfg['max_output_tokens']??700)];$json=$this->curlJson('https://api.openai.com/v1/responses',(string)$cfg['_api_key'],'POST',$payload);
            $reply='';if(!empty($json['output_text'])&&is_string($json['output_text']))$reply=trim($json['output_text']);if($reply===''){foreach(($json['output']??[]) as $out)foreach(($out['content']??[]) as $c){if(($c['type']??'')==='output_text'&&isset($c['text']))$reply.=($reply!==''?"\n":'').trim((string)$c['text']);}}
            if($reply==='')throw new RuntimeException('OpenAI no devolvió texto utilizable.');$usage=$json['usage']??[];$in=(int)($usage['input_tokens']??0);$out=(int)($usage['output_tokens']??0);$cached=(int)($usage['input_tokens_details']['cached_tokens']??0);$billable=max(0,$in-$cached);$cost=($billable/1000000)*(float)$cfg['input_cost_per_million']+($cached/1000000)*(float)$cfg['cached_input_cost_per_million']+($out/1000000)*(float)$cfg['output_cost_per_million'];
            $this->pdo->prepare("INSERT INTO ai_usage_logs(tenant_id,conversation_id,provider,channel_type,model,request_id,input_tokens,cached_input_tokens,output_tokens,estimated_cost_usd,status) VALUES(?,?,'openai',?,?,?,?,?,?,?,'ok')")->execute([$tenantId,$conversationId,$channelType,(string)$cfg['model'],(string)($json['id']??''),$in,$cached,$out,$cost]);
            return ['reply'=>$reply,'source'=>'openai:'.($cfg['model']??'gpt-6-luna'),'confidence'=>'ai','usage'=>['input_tokens'=>$in,'cached_input_tokens'=>$cached,'output_tokens'=>$out,'estimated_cost_usd'=>$cost]];
        }catch(Throwable $e){try{$this->pdo->prepare("INSERT INTO ai_usage_logs(tenant_id,conversation_id,provider,channel_type,model,status,error_message) VALUES(?,?,'openai',?,?,'error',?)")->execute([$tenantId,$conversationId,$channelType,(string)($cfg['model']??'gpt-6-luna'),mb_substr($e->getMessage(),0,1000)]);}catch(Throwable $ignore){}return null;}
    }
}
