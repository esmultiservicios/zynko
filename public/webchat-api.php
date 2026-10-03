<?php
declare(strict_types=1);$root=dirname(__DIR__);require_once $root.'/app/Support/Realtime.php';require_once $root.'/app/Support/Plan.php';require_once $root.'/app/Support/Cors.php';require_once $root.'/app/Services/NivoEngine.php';
function envc($p){$v=@parse_ini_file($p,false,INI_SCANNER_RAW);return is_array($v)?$v:[];}function db(){static $p;if($p)return $p;global $root;$e=envc($root.'/.env');return $p=new PDO('mysql:host='.($e['DB_HOST']??'127.0.0.1').';port='.($e['DB_PORT']??3306).';dbname='.($e['DB_DATABASE']??'zynko').';charset=utf8mb4',$e['DB_USERNAME']??'root',$e['DB_PASSWORD']??'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);}function out($ok,$msg,$data=[],$code=200){http_response_code($code);header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow, nosnippet', true);echo json_encode(['ok'=>$ok,'message'=>$msg,'data'=>$data],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}function uuid4(){ $d=random_bytes(16);$d[6]=chr((ord($d[6])&15)|64);$d[8]=chr((ord($d[8])&63)|128);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($d),4));}function b64u($s){return rtrim(strtr(base64_encode($s),'+/','-_'),'=');}function nivoNorm($s){$s=mb_strtolower(trim((string)$s),'UTF-8');$s=strtr($s,['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);return preg_replace('/\s+/u',' ',$s);}function nivoWords($s){$stop=['que','como','para','por','con','una','uno','unos','unas','del','las','los','este','esta','esto','esa','ese','soy','eres','es','son','hay','muy','mas','pero','porque','donde','cuando','puedo','puede','quiero','quiere','necesito','me','mi','tu','su','de','la','el','y','o','a','en','un'];$words=array_values(array_unique(array_filter(preg_split('/[^\p{L}\p{N}]+/u',nivoNorm($s)),fn($x)=>mb_strlen($x)>=3&&!in_array($x,$stop,true))));return $words;}function ensureNivoRuntime(PDO $pdo,int $tid):void{try{$pdo->exec("CREATE TABLE IF NOT EXISTS nivo_rules (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,name VARCHAR(160) NOT NULL,keywords VARCHAR(500) NOT NULL,response TEXT NOT NULL,priority INT NOT NULL DEFAULT 100,active TINYINT(1) NOT NULL DEFAULT 1,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX idx_nivo_rules_tenant(tenant_id,active,priority)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");$convCols=['archived_at'=>"DATETIME NULL",'deleted_at'=>"DATETIME NULL",'deleted_by'=>"BIGINT UNSIGNED NULL"];foreach($convCols as $cc=>$def){try{$c=$pdo->query("SHOW COLUMNS FROM conversations LIKE ".$pdo->quote($cc))->fetch();if(!$c)$pdo->exec("ALTER TABLE conversations ADD `{$cc}` {$def}");}catch(Throwable $ignore){}}$botCols=['fallback_message'=>"TEXT NULL",'handoff_rules_json'=>"JSON NULL",'business_hours_json'=>"JSON NULL",'channel_policy_json'=>"JSON NULL",'knowledge_enabled'=>"TINYINT(1) NOT NULL DEFAULT 0"];foreach($botCols as $bc=>$def){try{$c=$pdo->query("SHOW COLUMNS FROM bot_profiles LIKE ".$pdo->quote($bc))->fetch();if(!$c)$pdo->exec("ALTER TABLE bot_profiles ADD `{$bc}` {$def}");}catch(Throwable $ignore){}}try{$wc=$pdo->query("SHOW COLUMNS FROM webchat_widgets LIKE 'experience_json'")->fetch();if(!$wc)$pdo->exec("ALTER TABLE webchat_widgets ADD experience_json JSON NULL AFTER allow_multiple_domains");}catch(Throwable $ignore){}
$col=$pdo->query("SHOW COLUMNS FROM knowledge_sources LIKE 'approval_status'")->fetch();if(!$col)$pdo->exec("ALTER TABLE knowledge_sources ADD approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved' AFTER status");$bp=$pdo->prepare('SELECT enabled FROM bot_profiles WHERE tenant_id=? LIMIT 1');$bp->execute([$tid]);$enabled=(int)($bp->fetchColumn()?:0)===1;if($enabled){$rq=$pdo->prepare('SELECT COUNT(*) FROM nivo_rules WHERE tenant_id=? AND active=1');$rq->execute([$tid]);$rules=(int)$rq->fetchColumn();$kq=$pdo->prepare("SELECT COUNT(*) FROM knowledge_sources WHERE tenant_id=? AND status='ready' AND approval_status='approved' AND content IS NOT NULL");$kq->execute([$tid]);$knowledge=(int)$kq->fetchColumn();if($rules===0&&$knowledge===0){$starter=[['Saludo','hola,buenas,buenos dias,buenas tardes,buenas noches','¡Hola! Soy NIVO. ¿En qué puedo ayudarte hoy?',10],['Qué es ZYNKO','que es zynko,qué es zynko,para que sirve zynko,para qué sirve zynko,plataforma zynko','ZYNKO es una plataforma SaaS omnicanal para centralizar conversaciones, atención, NIVO Web Chat, automatización, usuarios e integraciones desde un solo lugar.',20],['NIVO Web Chat','nivo web chat,web chat,chat de nivo','NIVO Web Chat es el canal web propio de ZYNKO. Permite atender visitantes desde sitios autorizados y llevar las conversaciones a la Bandeja omnicanal.',30],['NIVO IA','nivo ia,asistente nivo,inteligencia artificial','NIVO IA trabaja junto con NIVO Web Chat usando reglas y conocimiento aprobado. Si no tiene información suficiente o el visitante pide una persona, puede transferir la conversación a atención humana.',40],['Canales y Meta','whatsapp,messenger,instagram,canales,meta','ZYNKO puede administrar distintos canales. WhatsApp Business, Messenger e Instagram requieren la autorización oficial correspondiente de Meta antes de considerarse conectados.',50],['Integraciones','api,webhook,integraciones,integracion','ZYNKO permite conectar otros sistemas mediante API y webhooks seguros, según la configuración y permisos de la empresa.',60]];$ins=$pdo->prepare('INSERT INTO nivo_rules(tenant_id,name,keywords,response,priority,active) VALUES(?,?,?,?,?,1)');foreach($starter as $r)$ins->execute([$tid,$r[0],$r[1],$r[2],$r[3]]);}}}catch(Throwable $e){}}
function nivoRequestDomain(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    $port = (int) (parse_url($url, PHP_URL_PORT) ?: 0);

    if ($host === '') {
        return '';
    }

    return $host . ($port > 0 ? ':' . $port : '');
}

function nivoDomainKey(string $domain): string
{
    $domain = strtolower(trim($domain));
    if ($domain === '') {
        return '';
    }

    if (str_contains($domain, '://')) {
        $domain = (string) (parse_url($domain, PHP_URL_HOST) ?: '');
    }

    $domain = preg_replace('/\/.*$/', '', $domain);
    $domain = preg_replace('/:\\d+$/', '', $domain);

    return (string) preg_replace('/^www\./', '', $domain);
}

function nivoCorsKey(array $input = []): string
{
    $raw = (string) ($_GET['key'] ?? $input['key'] ?? '');
    return preg_replace('/[^a-f0-9]/', '', strtolower($raw)) ?: '';
}

function nivoOrigin(): string
{
    $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($origin === '') {
        return '';
    }

    $parts = parse_url($origin);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return '';
    }

    $scheme = strtolower((string) $parts['scheme']);
    if (!in_array($scheme, ['http', 'https'], true)) {
        return '';
    }

    $normalized = $scheme . '://' . strtolower((string) $parts['host']);
    if (!empty($parts['port'])) {
        $normalized .= ':' . (int) $parts['port'];
    }

    return $normalized;
}

function nivoFindAuthorizedInstallation(PDO $pdo, string $key, string $originHost): array
{
    if ($key === '' || $originHost === '') {
        return [null, null];
    }

    $installation = null;
    $widget = null;
    $hasInstallationKey = false;

    try {
        $hasInstallationKey = (bool) $pdo->query("SHOW COLUMNS FROM webchat_installations LIKE 'installation_key'")->fetch();
    } catch (Throwable $ignore) {
        $hasInstallationKey = false;
    }

    if ($hasInstallationKey) {
        $query = $pdo->prepare(
            'SELECT i.* FROM webchat_installations i '
            . 'JOIN webchat_widgets w ON w.id=i.widget_id '
            . 'WHERE i.installation_key=? AND i.enabled=1 AND w.enabled=1 '
            . 'LIMIT 1'
        );
        $query->execute([$key]);
        $candidate = $query->fetch() ?: null;

        if ($candidate && nivoDomainKey((string) $candidate['domain']) === nivoDomainKey($originHost)) {
            $installation = $candidate;

            $widgetQuery = $pdo->prepare(
                'SELECT w.*,t.name company,t.logo_path FROM webchat_widgets w '
                . 'JOIN tenants t ON t.id=w.tenant_id '
                . 'WHERE w.id=? AND w.enabled=1 LIMIT 1'
            );
            $widgetQuery->execute([(int) $installation['widget_id']]);
            $widget = $widgetQuery->fetch() ?: null;
        }
    }

    // Compatibilidad con scripts anteriores que todavía usan public_key.
    if (!$widget) {
        $widgetQuery = $pdo->prepare(
            'SELECT w.*,t.name company,t.logo_path FROM webchat_widgets w '
            . 'JOIN tenants t ON t.id=w.tenant_id '
            . 'WHERE w.public_key=? AND w.enabled=1 LIMIT 1'
        );
        $widgetQuery->execute([$key]);
        $legacyWidget = $widgetQuery->fetch() ?: null;

        if ($legacyWidget) {
            $installations = $pdo->prepare(
                'SELECT * FROM webchat_installations WHERE widget_id=? AND enabled=1 ORDER BY id'
            );
            $installations->execute([(int) $legacyWidget['id']]);

            foreach ($installations->fetchAll() as $candidate) {
                if (nivoDomainKey((string) $candidate['domain']) === nivoDomainKey($originHost)) {
                    $installation = $candidate;
                    $widget = $legacyWidget;
                    break;
                }
            }
        }
    }

    return [$installation, $widget];
}

function nivoSendCorsHeaders(string $origin): void
{
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Accept');
    header('Access-Control-Max-Age: 600');
    header('Vary: Origin, Access-Control-Request-Method, Access-Control-Request-Headers');
}

$origin = ZynkoCors::requestOrigin();
$originHost = ZynkoCors::originHost($origin);

// En peticiones normales sin Origin (por ejemplo same-origin), usamos Referer
// únicamente para validar el dominio. Nunca se utiliza para conceder CORS.
if ($originHost === '') {
    $originHost = nivoRequestDomain((string) ($_SERVER['HTTP_REFERER'] ?? ''));
}

$isPreflight = ZynkoCors::isPreflight();

try {
    $pdo = db();

    /*
     * CORS seguro y multiempresa:
     * El preflight no contiene el body del POST. Por eso nivo-widget.js incluye
     * la installation key también en el query string de webchat-api.php.
     * Con key + Origin podemos validar el sitio antes de devolver ACAO.
     */
    if ($isPreflight) {
        $preflightKey = nivoCorsKey();

        if ($origin === '' || $originHost === '' || $preflightKey === '') {
            if ($origin !== '') {
                ZynkoCors::send($origin, ['POST', 'OPTIONS'], ['Content-Type', 'Accept']);
            }
            http_response_code(403);
            exit;
        }

        [$preflightInstallation, $preflightWidget] = nivoFindAuthorizedInstallation(
            $pdo,
            $preflightKey,
            $originHost
        );

        if (!$preflightInstallation || !$preflightWidget) {
            ZynkoCors::send($origin, ['POST', 'OPTIONS'], ['Content-Type', 'Accept']);
            http_response_code(403);
            exit;
        }

        ZynkoCors::send($origin, ['POST', 'OPTIONS'], ['Content-Type', 'Accept']);
        http_response_code(204);
        exit;
    }

    try {
        if (!$pdo->query("SHOW COLUMNS FROM webchat_installations LIKE 'installation_key'")->fetch()) {
            $pdo->exec("ALTER TABLE webchat_installations ADD installation_key CHAR(40) NULL AFTER widget_id");
        }

        $missing = $pdo->query(
            "SELECT id FROM webchat_installations WHERE installation_key IS NULL OR installation_key=''"
        )->fetchAll(PDO::FETCH_COLUMN);

        if ($missing) {
            $fill = $pdo->prepare('UPDATE webchat_installations SET installation_key=? WHERE id=?');
            foreach ($missing as $missingId) {
                $fill->execute([bin2hex(random_bytes(20)), (int) $missingId]);
            }
        }
    } catch (Throwable $ignore) {
    }

    $rawInput = file_get_contents('php://input') ?: '';
    $decodedInput = json_decode($rawInput, true);
    $input = is_array($decodedInput) ? $decodedInput : $_POST;
    if (!$input && $rawInput !== '') {
        parse_str($rawInput, $formInput);
        if (is_array($formInput)) {
            $input = $formInput;
        }
    }
    $action = $input['action'] ?? ($_GET['action'] ?? 'bootstrap');
    $key = nivoCorsKey($input);

    // Permite que el navegador lea respuestas controladas; la autorización real
    // sigue dependiendo de installation_key + dominio autorizado.
    if ($origin !== '') {
        ZynkoCors::send($origin, ['POST', 'OPTIONS'], ['Content-Type', 'Accept']);
    }

    if ($originHost === '') {
        out(false, 'No fue posible validar el dominio de origen.', [], 403);
    }

    [$installation, $w] = nivoFindAuthorizedInstallation($pdo, $key, $originHost);

    if (!$w || !$installation) {
        out(false, 'Este código de NIVO Web Chat no está autorizado para este dominio.', [], 403);
    }

    if (nivoDomainKey((string) $installation['domain']) !== nivoDomainKey($originHost)) {
        out(false, 'Este código de NIVO Web Chat pertenece a otro dominio.', [], 403);
    }


$tid=(int)$w['tenant_id'];$wid=(int)$w['id'];ensureNivoRuntime($pdo,$tid);zynkoEnsurePlanSchema($pdo);$planCtx=zynkoPlanContext($pdo,$tid,false);if(!zynkoPlanAllowsChannel($planCtx,'webchat'))out(false,'NIVO Web Chat no está habilitado en el plan actual.',[],403);$pdo->prepare('UPDATE webchat_installations SET first_seen_at=COALESCE(first_seen_at,NOW()),last_seen_at=NOW() WHERE id=?')->execute([$installation['id']]);
$visitor=(string)($input['visitor_token']??$_GET['visitor_token']??'');$v=null;if($visitor!==''){$q=$pdo->prepare('SELECT * FROM webchat_visitors WHERE visitor_token=? AND tenant_id=? AND widget_id=?');$q->execute([$visitor,$tid,$wid]);$v=$q->fetch();}
if($action==='bootstrap'){if(!$v){$visitor=bin2hex(random_bytes(32));$pdo->prepare('INSERT INTO webchat_visitors(tenant_id,widget_id,visitor_token,origin_domain) VALUES(?,?,?,?)')->execute([$tid,$wid,$visitor,$originHost]);$v=['id'=>(int)$pdo->lastInsertId(),'visitor_token'=>$visitor,'conversation_id'=>null];}else{$pdo->prepare('UPDATE webchat_visitors SET last_seen_at=NOW(),origin_domain=? WHERE id=?')->execute([$originHost,$v['id']]);}$messages=[];$cid=(int)($v['conversation_id']??0);if($cid){$cq=$pdo->prepare('SELECT archived_at,deleted_at FROM conversations WHERE id=? AND tenant_id=? LIMIT 1');$cq->execute([$cid,$tid]);$cv=$cq->fetch();if(!$cv||!empty($cv['deleted_at'])){$pdo->prepare('UPDATE webchat_visitors SET conversation_id=NULL WHERE id=?')->execute([$v['id']]);$cid=0;}else{if(!empty($cv['archived_at']))$pdo->prepare("UPDATE conversations SET archived_at=NULL,status='open' WHERE id=? AND tenant_id=?")->execute([$cid,$tid]);$q=$pdo->prepare('SELECT id,direction,sender_type,body,sent_at FROM messages WHERE tenant_id=? AND conversation_id=? ORDER BY id');$q->execute([$tid,$cid]);$messages=$q->fetchAll();}}$e=envc($root.'/.env');$host=$e['WS_PUBLIC_HOST']??($_SERVER['HTTP_HOST']??'localhost');$host=preg_replace('/:\d+$/','',$host);$scheme=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'wss':'ws';$payload=['tenant_id'=>$tid,'visitor_id'=>(int)$v['id'],'conversation_id'=>$cid,'exp'=>time()+43200,'aud'=>'webchat'];$b=b64u(json_encode($payload));$sig=preg_match('/^[a-f0-9]{64}$/i',$e['APP_KEY']??'')?hash_hmac('sha256',$b,hex2bin($e['APP_KEY']),true):'';$ws=$scheme.'://'.$host.':'.((int)($e['WS_PORT']??8080));$aiEnabled=false;try{$aq=$pdo->prepare('SELECT enabled FROM bot_profiles WHERE tenant_id=? LIMIT 1');$aq->execute([$tid]);$aiEnabled=(int)($aq->fetchColumn()?:0)===1;}catch(Throwable $e){}out(true,'NIVO Web Chat listo.',['visitor_token'=>$visitor,'conversation_id'=>$cid,'widget'=>['company'=>$w['company'],'name'=>$w['name'],'position'=>$w['position'],'display_mode'=>$w['display_mode']??'launcher','offset_x'=>(int)$w['offset_x'],'offset_y'=>(int)$w['offset_y'],'accent_color'=>$w['accent_color'],'welcome_title'=>$w['welcome_title'],'assistant_subtitle'=>(trim((string)($w['assistant_subtitle']??''))!==''?$w['assistant_subtitle']:'Asistente virtual de '.$w['company']),'welcome_message'=>$w['welcome_message'],'ask_name'=>(bool)$w['ask_name'],'ask_email'=>(bool)$w['ask_email'],'profile_required'=>(bool)($w['profile_required']??0),'launcher_label'=>$w['launcher_label']??'','sound_enabled'=>(bool)($w['sound_enabled']??1),'privacy_enabled'=>(bool)($w['privacy_enabled']??0),'privacy_text'=>$w['privacy_text']??'','privacy_url'=>$w['privacy_url']??'','nivo_ai_enabled'=>$aiEnabled,'experience'=>(json_decode((string)($w['experience_json']??'{}'),true)?:[])],'plan'=>['name'=>$planCtx['plan_name']??'','daily_chat_limit'=>zynkoPlanLimit($planCtx,'max_daily_chats'),'daily_chat_usage'=>zynkoPlanDailyChatUsage($pdo,$tid),'monthly_chat_limit'=>zynkoPlanLimit($planCtx,'max_monthly_chats'),'monthly_chat_usage'=>zynkoPlanMonthlyChatUsage($pdo,$tid)],'messages'=>$messages,'ws_url'=>$ws,'ws_token'=>$b.'.'.b64u($sig)]);}
if(!$v)out(false,'Sesión del visitante inválida.',[],401);
if($action==='send'){$body=trim((string)($input['body']??''));if($body==='')out(false,'Escribe un mensaje.',[],422);$experience=json_decode((string)($w['experience_json']??'{}'),true)?:[];$maxMessage=max(120,min(3000,(int)($experience['max_message_length']??1000)));if(mb_strlen($body)>$maxMessage)out(false,'El mensaje supera el máximo de '.$maxMessage.' caracteres.',[],422);$rate=max(2,min(30,(int)($experience['rate_limit_per_minute']??12)));if($v){$rq=$pdo->prepare("SELECT COUNT(*) FROM messages m JOIN conversations c ON c.id=m.conversation_id WHERE c.tenant_id=? AND c.id=? AND m.direction='in' AND m.sent_at>=DATE_SUB(NOW(),INTERVAL 1 MINUTE)");$rq->execute([$tid,(int)($v['conversation_id']??0)]);if((int)$rq->fetchColumn()>=$rate)out(false,'Has enviado varios mensajes muy rápido. Espera unos segundos e inténtalo de nuevo.',[],429);}$name=trim((string)($input['name']??($v['name']??'')));$email=trim((string)($input['email']??($v['email']??'')));if($name==='')$name='Visitante web';$cid=(int)($v['conversation_id']??0);if($cid){$cq=$pdo->prepare('SELECT archived_at,deleted_at FROM conversations WHERE id=? AND tenant_id=? LIMIT 1');$cq->execute([$cid,$tid]);$cv=$cq->fetch();if(!$cv||!empty($cv['deleted_at'])){$pdo->prepare('UPDATE webchat_visitors SET conversation_id=NULL WHERE id=?')->execute([$v['id']]);$cid=0;}elseif(!empty($cv['archived_at'])){$pdo->prepare("UPDATE conversations SET archived_at=NULL,status='open' WHERE id=? AND tenant_id=?")->execute([$cid,$tid]);}}if(!$cid){$dailyLimit=zynkoPlanLimit($planCtx,'max_daily_chats');$monthlyLimit=zynkoPlanLimit($planCtx,'max_monthly_chats');if($dailyLimit!==null){$dailyUsed=zynkoPlanDailyChatUsage($pdo,$tid);if($dailyUsed>=$dailyLimit)out(false,'Este sitio alcanzó el límite de '.$dailyLimit.' chats nuevos de hoy. Las conversaciones ya iniciadas pueden continuar normalmente.',['limit'=>$dailyLimit,'used'=>$dailyUsed,'period'=>'day'],429);}if($monthlyLimit!==null){$monthlyUsed=zynkoPlanMonthlyChatUsage($pdo,$tid);if($monthlyUsed>=$monthlyLimit)out(false,'Esta empresa alcanzó el límite de '.number_format($monthlyLimit).' chats nuevos de este mes. Las conversaciones ya iniciadas pueden continuar normalmente.',['limit'=>$monthlyLimit,'used'=>$monthlyUsed,'period'=>'month'],429);}if(!empty($w['profile_required'])){if(!empty($w['ask_name'])&&$name==='')out(false,'Ingresa tu nombre para continuar.',[],422);if(!empty($w['ask_email'])&&!filter_var($email,FILTER_VALIDATE_EMAIL))out(false,'Ingresa un correo válido para continuar.',[],422);}if(!empty($w['privacy_enabled'])&&empty($input['privacy_accepted']))out(false,'Debes aceptar el aviso de privacidad para continuar.',[],422);$pdo->beginTransaction();$pdo->prepare('INSERT INTO contacts(tenant_id,uuid,name,email) VALUES(?,?,?,?)')->execute([$tid,uuid4(),$name,$email?:null]);$contact=(int)$pdo->lastInsertId();$pdo->prepare("INSERT INTO conversations(tenant_id,uuid,channel_id,contact_id,status,last_message_at) VALUES(?,?,?,?, 'open',NOW())")->execute([$tid,uuid4(),(int)$w['channel_id'],$contact]);$cid=(int)$pdo->lastInsertId();$pdo->prepare('UPDATE webchat_visitors SET contact_id=?,conversation_id=?,name=?,email=?,last_seen_at=NOW() WHERE id=?')->execute([$contact,$cid,$name,$email?:null,$v['id']]);$pdo->commit();zynkoRealtimePublish($pdo,$tid,'conversation.created',['conversation_id'=>$cid,'channel'=>'webchat','contact_name'=>$name],'conversation',(string)$cid);} $pdo->prepare("INSERT INTO messages(tenant_id,conversation_id,uuid,direction,sender_type,type,body,status,sent_at) VALUES(?,?,?,'in','contact','text',?,'received',NOW())")->execute([$tid,$cid,uuid4(),$body]);$pdo->prepare("UPDATE conversations SET unread_count=unread_count+1,last_message_at=NOW(),status='open',archived_at=NULL WHERE id=? AND tenant_id=?")->execute([$cid,$tid]);zynkoRealtimePublish($pdo,$tid,'message.created',['conversation_id'=>$cid,'channel'=>'webchat'],'conversation',(string)$cid);try{require_once $root.'/app/Services/NotificationService.php';$rq=$pdo->prepare("SELECT COALESCE(NULLIF(c.destinatario,''),(SELECT u.email FROM users u JOIN tenant_users tu ON tu.user_id=u.id WHERE tu.tenant_id=? AND tu.role_code IN ('owner','admin') AND u.status='active' ORDER BY FIELD(tu.role_code,'owner','admin'),u.id LIMIT 1)) recipient FROM correo c WHERE c.tenant_id=? AND c.is_default=1 AND c.estado=1 ORDER BY c.correo_id DESC LIMIT 1");$rq->execute([$tid,$tid]);$notifyTo=(string)($rq->fetchColumn()?:'');if($notifyTo!==''){(new NotificationService($pdo,$root))->send($tid,'message',$notifyTo,'Nuevo mensaje · NIVO Web Chat',$name.' escribió: '.mb_substr($body,0,260),['dedupe_key'=>'conversation:'.$cid,'conversation_id'=>$cid,'channel'=>'webchat']);}}catch(Throwable $e){}
// NIVO omnicanal: motor central compartido
$engine=NivoEngine::evaluate($pdo,$tid,$cid,$body,'webchat',$name,(string)$w['company']);
$reply=$engine['reply']??null;$handoff=!empty($engine['handoff']);$replySource=$engine['source']??null;
if($reply){
  $pdo->prepare("INSERT INTO messages(tenant_id,conversation_id,uuid,direction,sender_type,type,body,status,sent_at) VALUES(?,?,?,'out','bot','text',?,'sent',NOW())")->execute([$tid,$cid,uuid4(),$reply]);
  $pdo->prepare('UPDATE conversations SET last_message_at=NOW() WHERE id=? AND tenant_id=?')->execute([$cid,$tid]);
  if($handoff)$pdo->prepare("UPDATE conversations SET status='pending' WHERE id=? AND tenant_id=?")->execute([$cid,$tid]);
  if($handoff){try{if(!isset($notifyTo)||$notifyTo===''){$rq=$pdo->prepare("SELECT COALESCE(NULLIF(c.destinatario,''),(SELECT u.email FROM users u JOIN tenant_users tu ON tu.user_id=u.id WHERE tu.tenant_id=? AND tu.role_code IN ('owner','admin') AND u.status='active' ORDER BY u.id LIMIT 1)) FROM correo c WHERE c.tenant_id=? AND c.is_default=1 AND c.estado=1 LIMIT 1");$rq->execute([$tid,$tid]);$notifyTo=(string)($rq->fetchColumn()?:'');}if($notifyTo!==''){require_once $root.'/app/Services/NotificationService.php';(new NotificationService($pdo,$root))->send($tid,'handoff',$notifyTo,'NIVO solicita atención humana','NIVO transfirió la conversación de '.$name.' para atención humana. Abre la Bandeja para continuar la conversación.',['dedupe_key'=>'handoff:'.$cid,'conversation_id'=>$cid,'channel'=>'webchat']);}}catch(Throwable $e){}}
  zynkoRealtimePublish($pdo,$tid,'message.created',['conversation_id'=>$cid,'channel'=>'webchat','sender'=>'bot'],'conversation',(string)$cid);
}
out(true,'Mensaje recibido.',['conversation_id'=>$cid,'bot_reply'=>$reply,'handoff'=>$handoff,'reply_source'=>$replySource]);}
if($action==='messages'){$cid=(int)($v['conversation_id']??0);$messages=[];if($cid){$q=$pdo->prepare('SELECT id,direction,sender_type,body,sent_at FROM messages WHERE tenant_id=? AND conversation_id=? ORDER BY id');$q->execute([$tid,$cid]);$messages=$q->fetchAll();}out(true,'OK',['conversation_id'=>$cid,'messages'=>$messages]);}out(false,'Acción no válida.',[],400);
}catch(Throwable $e){if(($origin??'')!=='')ZynkoCors::send($origin,['POST','OPTIONS'],['Content-Type','Accept']);out(false,'No fue posible procesar el chat: '.$e->getMessage(),[],500);}
