<?php
declare(strict_types=1);$root=dirname(__DIR__);require_once $root.'/app/Support/Realtime.php';require_once $root.'/app/Support/Plan.php';require_once $root.'/app/Support/Cors.php';require_once $root.'/app/Services/NivoEngine.php';
require_once $root.'/app/Services/AutomationEngine.php';
function envc($p){$v=@parse_ini_file($p,false,INI_SCANNER_RAW);return is_array($v)?$v:[];}
function ensureMessagesUtf8mb4(PDO $pdo): void {
    static $done=false;
    if($done)return;
    $done=true;
    try{
        $q=$pdo->query("SELECT CHARACTER_SET_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='messages' AND COLUMN_NAME='body' LIMIT 1");
        $charset=strtolower((string)($q?$q->fetchColumn():''));
        if($charset!=='' && $charset!=='utf8mb4'){
            $pdo->exec("ALTER TABLE messages MODIFY body TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL");
        }
    }catch(Throwable $ignored){
        // El script acumulativo de BD realiza la misma normalización de forma permanente.
    }
}
function db(){
    static $p;
    if($p)return $p;
    global $root;
    $e=envc($root.'/.env');
    $p=new PDO('mysql:host='.($e['DB_HOST']??'127.0.0.1').';port='.($e['DB_PORT']??3306).';dbname='.($e['DB_DATABASE']??'zynko').';charset=utf8mb4',$e['DB_USERNAME']??'root',$e['DB_PASSWORD']??'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $p->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    ensureMessagesUtf8mb4($p);
    return $p;
}function out($ok,$msg,$data=[],$code=200){http_response_code($code);header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow, nosnippet', true);echo json_encode(['ok'=>$ok,'message'=>$msg,'data'=>$data],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}function uuid4(){ $d=random_bytes(16);$d[6]=chr((ord($d[6])&15)|64);$d[8]=chr((ord($d[8])&63)|128);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($d),4));}function b64u($s){return rtrim(strtr(base64_encode($s),'+/','-_'),'=');}function nivoNorm($s){$s=mb_strtolower(trim((string)$s),'UTF-8');$s=strtr($s,['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);return preg_replace('/\s+/u',' ',$s);}function nivoWords($s){$stop=['que','como','para','por','con','una','uno','unos','unas','del','las','los','este','esta','esto','esa','ese','soy','eres','es','son','hay','muy','mas','pero','porque','donde','cuando','puedo','puede','quiero','quiere','necesito','me','mi','tu','su','de','la','el','y','o','a','en','un'];$words=array_values(array_unique(array_filter(preg_split('/[^\p{L}\p{N}]+/u',nivoNorm($s)),fn($x)=>mb_strlen($x)>=3&&!in_array($x,$stop,true))));return $words;}function ensureNivoRuntime(PDO $pdo,int $tid):void{try{$pdo->exec("CREATE TABLE IF NOT EXISTS nivo_rules (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,name VARCHAR(160) NOT NULL,keywords VARCHAR(500) NOT NULL,response TEXT NOT NULL,priority INT NOT NULL DEFAULT 100,active TINYINT(1) NOT NULL DEFAULT 1,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX idx_nivo_rules_tenant(tenant_id,active,priority)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");$pdo->exec("CREATE TABLE IF NOT EXISTS agent_presence(tenant_id BIGINT UNSIGNED NOT NULL,user_id BIGINT UNSIGNED NOT NULL,status ENUM('online','busy','offline') NOT NULL DEFAULT 'online',updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(tenant_id,user_id),INDEX idx_agent_presence_status(tenant_id,status,updated_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");$convCols=['archived_at'=>"DATETIME NULL",'deleted_at'=>"DATETIME NULL",'deleted_by'=>"BIGINT UNSIGNED NULL"];foreach($convCols as $cc=>$def){try{$c=$pdo->query("SHOW COLUMNS FROM conversations LIKE ".$pdo->quote($cc))->fetch();if(!$c)$pdo->exec("ALTER TABLE conversations ADD `{$cc}` {$def}");}catch(Throwable $ignore){}}$botCols=['fallback_message'=>"TEXT NULL",'handoff_rules_json'=>"JSON NULL",'business_hours_json'=>"JSON NULL",'channel_policy_json'=>"JSON NULL",'knowledge_enabled'=>"TINYINT(1) NOT NULL DEFAULT 0"];foreach($botCols as $bc=>$def){try{$c=$pdo->query("SHOW COLUMNS FROM bot_profiles LIKE ".$pdo->quote($bc))->fetch();if(!$c)$pdo->exec("ALTER TABLE bot_profiles ADD `{$bc}` {$def}");}catch(Throwable $ignore){}}try{$wc=$pdo->query("SHOW COLUMNS FROM webchat_widgets LIKE 'experience_json'")->fetch();if(!$wc)$pdo->exec("ALTER TABLE webchat_widgets ADD experience_json JSON NULL AFTER allow_multiple_domains");}catch(Throwable $ignore){}
$col=$pdo->query("SHOW COLUMNS FROM knowledge_sources LIKE 'approval_status'")->fetch();if(!$col)$pdo->exec("ALTER TABLE knowledge_sources ADD approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved' AFTER status");$bp=$pdo->prepare('SELECT enabled FROM bot_profiles WHERE tenant_id=? LIMIT 1');$bp->execute([$tid]);$enabled=(int)($bp->fetchColumn()?:0)===1;if($enabled){$rq=$pdo->prepare('SELECT COUNT(*) FROM nivo_rules WHERE tenant_id=? AND active=1');$rq->execute([$tid]);$rules=(int)$rq->fetchColumn();$kq=$pdo->prepare("SELECT COUNT(*) FROM knowledge_sources WHERE tenant_id=? AND status='ready' AND approval_status='approved' AND content IS NOT NULL");$kq->execute([$tid]);$knowledge=(int)$kq->fetchColumn();if($rules===0&&$knowledge===0){$starter=[['Saludo','hola,buenas,buenos dias,buenas tardes,buenas noches','¡Hola! Soy NIVO. ¿En qué puedo ayudarte hoy?',10],['Qué es ZYNKO','que es zynko,qué es zynko,para que sirve zynko,para qué sirve zynko,plataforma zynko','ZYNKO es una plataforma SaaS omnicanal para centralizar conversaciones, atención, NIVO Web Chat, automatización, usuarios e integraciones desde un solo lugar.',20],['NIVO Web Chat','nivo web chat,web chat,chat de nivo','NIVO Web Chat es el canal web propio de ZYNKO. Permite atender visitantes desde sitios autorizados y llevar las conversaciones a la Bandeja omnicanal.',30],['NIVO IA','nivo ia,asistente nivo,inteligencia artificial','NIVO IA trabaja junto con NIVO Web Chat usando reglas y conocimiento aprobado. Si no tiene información suficiente o el visitante pide una persona, puede transferir la conversación a atención humana.',40],['Canales y Meta','whatsapp,messenger,instagram,canales,meta','ZYNKO puede administrar distintos canales. WhatsApp Business, Messenger e Instagram requieren la autorización oficial correspondiente de Meta antes de considerarse conectados.',50],['Integraciones','api,webhook,integraciones,integracion','ZYNKO permite conectar otros sistemas mediante API y webhooks seguros, según la configuración y permisos de la empresa.',60]];$tenantName='';try{$tn=$pdo->prepare('SELECT name FROM tenants WHERE id=? LIMIT 1');$tn->execute([$tid]);$tenantName=nivoNorm((string)($tn->fetchColumn()?:''));$tenantCompact=str_replace(' ','',$tenantName);if(in_array($tenantCompact,['esmultiservicios','esmultsiervicios'],true))$tenantName='es multiservicios';}catch(Throwable $ignore){}if($tenantName==='es multiservicios'){array_push($starter,['ES MULTISERVICIOS','que es es multiservicios,qué es es multiservicios,quien es es multiservicios,quién es es multiservicios,que hace es multiservicios,qué hace es multiservicios','ES MULTISERVICIOS desarrolla software, sitios web, integraciones y soluciones digitales para empresas. Es la empresa creadora de IZZY, CAMI y ZYNKO.',15],['IZZY','que es izzy,qué es izzy,para que sirve izzy,para qué sirve izzy,funciones de izzy','IZZY es la solución empresarial de ES MULTISERVICIOS para facturación, inventario, POS, restaurantes y gestión administrativa.',25],['CAMI','que es cami,qué es cami,para que sirve cami,para qué sirve cami','CAMI es una solución de ES MULTISERVICIOS orientada a clínicas y centros médicos. Su conocimiento detallado puede ampliarse desde las fuentes y base aprobadas del tenant.',35],['Familia ES MULTISERVICIOS','productos de es multiservicios,soluciones de es multiservicios,que sistemas tiene es multiservicios,qué sistemas tiene es multiservicios','ES MULTISERVICIOS reúne una familia de soluciones que incluye IZZY, CAMI y ZYNKO. NIVO puede usar en cualquier sitio autorizado del mismo tenant el conocimiento aprobado de toda esa familia.',18]);}$ins=$pdo->prepare('INSERT INTO nivo_rules(tenant_id,name,keywords,response,priority,active) VALUES(?,?,?,?,?,1)');foreach($starter as $r)$ins->execute([$tid,$r[0],$r[1],$r[2],$r[3]]);}}}catch(Throwable $e){}}
function nivoAssignAvailableAgent(PDO $pdo, int $tenantId, int $conversationId): ?array
{
    if ($tenantId < 1 || $conversationId < 1) {
        return null;
    }

    try {
        $query = $pdo->prepare(
            "SELECT u.id,u.name,COUNT(c.id) active_chats
             FROM users u
             JOIN tenant_users tu ON tu.user_id=u.id AND tu.tenant_id=?
             LEFT JOIN agent_presence ap ON ap.user_id=u.id AND ap.tenant_id=tu.tenant_id
             LEFT JOIN conversations c ON c.tenant_id=tu.tenant_id AND c.assigned_user_id=u.id AND c.status IN ('open','pending') AND c.deleted_at IS NULL
             WHERE u.status='active'
               AND tu.role_code IN ('agent','supervisor','admin','owner')
               AND COALESCE(ap.status,'online')='online'
             GROUP BY u.id,u.name
             ORDER BY active_chats ASC, COALESCE(ap.updated_at,'1970-01-01') DESC, u.id ASC
             LIMIT 1"
        );
        $query->execute([$tenantId]);
        $agent = $query->fetch();
        if (!$agent) {
            return null;
        }

        $userId = (int) $agent['id'];
        $name = trim((string) $agent['name']);
        $pdo->prepare("UPDATE conversations SET assigned_user_id=?,status='open',archived_at=NULL WHERE id=? AND tenant_id=?")
            ->execute([$userId, $conversationId, $tenantId]);

        return ['id' => $userId, 'name' => $name !== '' ? $name : 'Agente'];
    } catch (Throwable $error) {
        error_log('NIVO auto assignment failed: ' . $error->getMessage());
        return null;
    }
}

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

function nivoEnsureSecurityRuntime(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS webchat_security_events (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tenant_id BIGINT UNSIGNED NOT NULL,
        widget_id BIGINT UNSIGNED NOT NULL,
        installation_id BIGINT UNSIGNED NULL,
        visitor_id BIGINT UNSIGNED NULL,
        conversation_id BIGINT UNSIGNED NULL,
        origin_domain VARCHAR(255) NULL,
        ip_hash CHAR(64) NULL,
        user_agent VARCHAR(500) NULL,
        body_hash CHAR(64) NULL,
        score SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        verdict ENUM('clean','suspicious','blocked') NOT NULL DEFAULT 'clean',
        reasons_json JSON NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_wc_security_tenant_created(tenant_id,created_at),
        INDEX idx_wc_security_ip_created(tenant_id,ip_hash,created_at),
        INDEX idx_wc_security_body_created(tenant_id,body_hash,created_at),
        INDEX idx_wc_security_verdict(tenant_id,verdict,created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS webchat_conversation_security (
        tenant_id BIGINT UNSIGNED NOT NULL,
        conversation_id BIGINT UNSIGNED NOT NULL,
        origin_domain VARCHAR(255) NULL,
        ip_hash CHAR(64) NULL,
        user_agent VARCHAR(500) NULL,
        risk_score SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        verdict ENUM('clean','suspicious','blocked') NOT NULL DEFAULT 'clean',
        blocked_events INT UNSIGNED NOT NULL DEFAULT 0,
        last_reason VARCHAR(500) NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY(tenant_id,conversation_id),
        INDEX idx_wc_conversation_security_verdict(tenant_id,verdict,risk_score)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function nivoRequestIpHash(array $env): string
{
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($ip === '') {
        return '';
    }

    $secret = (string) ($env['APP_KEY'] ?? '');
    if ($secret === '') {
        $secret = 'zynko-webchat-security';
    }

    return hash_hmac('sha256', $ip, $secret);
}

function nivoSecurityAssessment(
    PDO $pdo,
    int $tenantId,
    int $widgetId,
    int $installationId,
    ?array $visitor,
    string $originHost,
    string $body,
    array $input,
    array $env,
    array $experience
): array {
    nivoEnsureSecurityRuntime($pdo);

    $score = 0;
    $reasons = [];
    $hardBlock = false;
    $normalized = nivoNorm($body);
    $ua = mb_substr(trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 500);
    $ipHash = nivoRequestIpHash($env);
    $bodyHash = hash('sha256', $normalized);

    // Honeypot invisible: a normal visitor never fills this field.
    $honeypot = trim((string) ($input['website'] ?? ''));
    if ($honeypot !== '') {
        $score += 100;
        $reasons[] = 'honeypot';
        $hardBlock = true;
    }

    if ($ua === '') {
        $score += 20;
        $reasons[] = 'sin_user_agent';
    } elseif (preg_match('/(?:curl|wget|python-requests|scrapy|httpclient|headless|phantomjs|selenium|bot\b|crawler|spider)/i', $ua)) {
        $score += 35;
        $reasons[] = 'user_agent_automatizado';
    }

    if (preg_match('/(.)\1{24,}/u', $body)) {
        $score += 20;
        $reasons[] = 'repeticion_excesiva';
    }

    preg_match_all('~https?://|www\.~iu', $body, $urlMatches);
    $urlCount = count($urlMatches[0] ?? []);
    if ($urlCount >= 4) {
        $score += 35;
        $reasons[] = 'muchos_enlaces';
    } elseif ($urlCount >= 2) {
        $score += 15;
        $reasons[] = 'varios_enlaces';
    }

    if (preg_match('~^\s*(?:https?://|www\.)\S+\s*$~iu', $body)) {
        $score += 30;
        $reasons[] = 'solo_enlace';
    }

    $clientElapsed = (int) ($input['client_elapsed_ms'] ?? 0);
    if ($clientElapsed > 0 && $clientElapsed < 700) {
        $score += 25;
        $reasons[] = 'envio_demasiado_rapido';
    }

    if ($visitor && !empty($visitor['created_at'])) {
        $age = time() - strtotime((string) $visitor['created_at']);
        if ($age >= 0 && $age < 1) {
            $score += 20;
            $reasons[] = 'sesion_instantanea';
        }
    }

    if ($ipHash !== '') {
        $q = $pdo->prepare("SELECT COUNT(*) FROM webchat_security_events WHERE tenant_id=? AND ip_hash=? AND created_at>=DATE_SUB(NOW(),INTERVAL 1 MINUTE)");
        $q->execute([$tenantId, $ipHash]);
        $perMinute = (int) $q->fetchColumn();
        $hardLimit = max(10, min(120, (int) ($experience['antispam_ip_per_minute'] ?? 30)));
        if ($perMinute >= $hardLimit) {
            $score += 80;
            $reasons[] = 'limite_ip';
            $hardBlock = true;
        } elseif ($perMinute >= max(6, (int) floor($hardLimit / 2))) {
            $score += 25;
            $reasons[] = 'trafico_ip_alto';
        }

        $q = $pdo->prepare("SELECT COUNT(*) FROM webchat_security_events WHERE tenant_id=? AND ip_hash=? AND body_hash=? AND created_at>=DATE_SUB(NOW(),INTERVAL 10 MINUTE)");
        $q->execute([$tenantId, $ipHash, $bodyHash]);
        $duplicates = (int) $q->fetchColumn();
        // Repetir una pregunta es comportamiento humano normal (pruebas, reintentos, mala conexión).
        // Nunca debe provocar por sí solo que el mensaje desaparezca de la Bandeja.
        if ($duplicates >= 8) {
            $score += 25;
            $reasons[] = 'mensaje_repetido_frecuente';
        } elseif ($duplicates >= 3) {
            $score += 12;
            $reasons[] = 'mensaje_repetido';
        }
    }

    // Mensajes humanos cortos habituales nunca se bloquean solo por ser breves.
    $safeShort = ['hola','hello','hi','buenas','buenos dias','buenas tardes','buenas noches','quien eres','quién eres','ayuda','info','informacion','información'];
    if (in_array($normalized, array_map('nivoNorm', $safeShort), true)) {
        $score = min($score, 35);
        $reasons = array_values(array_filter($reasons, static fn($r) => !in_array($r, ['sesion_instantanea','envio_demasiado_rapido'], true)));
    }

    $blockThreshold = max(60, min(100, (int) ($experience['antispam_block_score'] ?? 70)));
    $reviewThreshold = max(20, min($blockThreshold - 5, (int) ($experience['antispam_review_score'] ?? 40)));

    // Bloqueo silencioso eliminado: un score heurístico alto no basta para tirar mensajes.
    // Solo señales inequívocas (honeypot o límite duro de IP) bloquean. El resto se
    // persiste como suspicious para que la conversación siempre llegue a la Bandeja.
    if (!$hardBlock && in_array('user_agent_automatizado', $reasons, true) && $urlCount >= 4 && $score >= $blockThreshold) {
        $hardBlock = true;
        $reasons[] = 'automatizacion_con_spam';
    }

    $verdict = $hardBlock ? 'blocked' : ($score >= $reviewThreshold ? 'suspicious' : 'clean');

    return [
        'score' => min(100, $score),
        'verdict' => $verdict,
        'reasons' => $reasons,
        'origin_domain' => $originHost,
        'ip_hash' => $ipHash,
        'user_agent' => $ua,
        'body_hash' => $bodyHash,
        'installation_id' => $installationId,
        'widget_id' => $widgetId
    ];
}

function nivoLogSecurityEvent(PDO $pdo, int $tenantId, ?array $visitor, int $conversationId, array $assessment): void
{
    $pdo->prepare(
        "INSERT INTO webchat_security_events(tenant_id,widget_id,installation_id,visitor_id,conversation_id,origin_domain,ip_hash,user_agent,body_hash,score,verdict,reasons_json)
         VALUES(?,?,?,?,?,?,?,?,?,?,?,?)"
    )->execute([
        $tenantId,
        (int) ($assessment['widget_id'] ?? 0),
        (int) ($assessment['installation_id'] ?? 0) ?: null,
        (int) ($visitor['id'] ?? 0) ?: null,
        $conversationId ?: null,
        $assessment['origin_domain'] ?? null,
        $assessment['ip_hash'] ?? null,
        $assessment['user_agent'] ?? null,
        $assessment['body_hash'] ?? null,
        (int) ($assessment['score'] ?? 0),
        (string) ($assessment['verdict'] ?? 'clean'),
        json_encode($assessment['reasons'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    ]);

    if ($conversationId > 0) {
        $reasonText = implode(', ', (array) ($assessment['reasons'] ?? []));
        $pdo->prepare(
            "INSERT INTO webchat_conversation_security(tenant_id,conversation_id,origin_domain,ip_hash,user_agent,risk_score,verdict,blocked_events,last_reason)
             VALUES(?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
                origin_domain=VALUES(origin_domain),
                ip_hash=VALUES(ip_hash),
                user_agent=VALUES(user_agent),
                risk_score=GREATEST(risk_score,VALUES(risk_score)),
                verdict=CASE
                    WHEN verdict='blocked' OR VALUES(verdict)='blocked' THEN 'blocked'
                    WHEN verdict='suspicious' OR VALUES(verdict)='suspicious' THEN 'suspicious'
                    ELSE 'clean'
                END,
                blocked_events=blocked_events + VALUES(blocked_events),
                last_reason=VALUES(last_reason)"
        )->execute([
            $tenantId,
            $conversationId,
            $assessment['origin_domain'] ?? null,
            $assessment['ip_hash'] ?? null,
            $assessment['user_agent'] ?? null,
            (int) ($assessment['score'] ?? 0),
            (string) ($assessment['verdict'] ?? 'clean'),
            ($assessment['verdict'] ?? '') === 'blocked' ? 1 : 0,
            mb_substr($reasonText, 0, 500)
        ]);
    }
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


$tid = (int) $w['tenant_id'];
$wid = (int) $w['id'];
ensureNivoRuntime($pdo, $tid);
$pdo->exec("CREATE TABLE IF NOT EXISTS conversation_surveys(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,conversation_id BIGINT UNSIGNED NOT NULL,visitor_id BIGINT UNSIGNED NULL,rating TINYINT UNSIGNED NULL,comment VARCHAR(1000) NULL,requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,responded_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_conversation_survey(tenant_id,conversation_id),INDEX idx_survey_tenant(tenant_id,responded_at,requested_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
zynkoEnsurePlanSchema($pdo);
$planCtx = zynkoPlanContext($pdo, $tid, false);

if (!zynkoPlanAllowsChannel($planCtx, 'webchat')) {
    out(false, 'NIVO Web Chat no está habilitado en el plan actual.', [], 403);
}

$pdo->prepare(
    'UPDATE webchat_installations SET first_seen_at=COALESCE(first_seen_at,NOW()),last_seen_at=NOW() WHERE id=?'
)->execute([$installation['id']]);

$experience = json_decode((string) ($w['experience_json'] ?? '{}'), true) ?: [];
$tenantNameQuery = $pdo->prepare('SELECT name FROM tenants WHERE id=? LIMIT 1');
$tenantNameQuery->execute([$tid]);
$tenantCompany = trim((string) ($tenantNameQuery->fetchColumn() ?: ($w['company'] ?? '')));
if ($tenantCompany === '') {
    $tenantCompany = 'Tu empresa';
}
$assistantCompany = $tenantCompany;
$installationLabel = trim((string) ($installation['label'] ?? ''));
$installationDomain = strtolower(trim((string) ($installation['domain'] ?? '')));
$contextHaystack = mb_strtolower($installationLabel . ' ' . $installationDomain, 'UTF-8');
$siteContext = '';

foreach ([
    'IZZY' => 'izzy',
    'CAMI' => 'cami',
    'ZYNKO' => 'zynko',
    'ES MULTISERVICIOS' => 'esmultiservicios'
] as $label => $needle) {
    if (str_contains($contextHaystack, $needle)) {
        $siteContext = $label;
        break;
    }
}

if ($siteContext === '' && $installationLabel !== '' && !str_contains(mb_strtolower($installationLabel, 'UTF-8'), 'sitio principal')) {
    $siteContext = mb_substr($installationLabel, 0, 80);
}

$smartGreeting = !array_key_exists('smart_greeting', $experience) || !empty($experience['smart_greeting']);
$contextualBranding = !array_key_exists('contextual_branding', $experience) || !empty($experience['contextual_branding']);
$clientHourRaw=(string)($input['client_hour']??$_POST['client_hour']??'');
$hour=(preg_match('/^(?:[01]?\d|2[0-3])$/',$clientHourRaw)?(int)$clientHourRaw:(int)date('G'));
$timeGreeting = ($hour >= 5 && $hour < 12) ? 'Buenos días' : (($hour >= 12 && $hour < 19) ? 'Buenas tardes' : 'Buenas noches');
$baseWelcome = trim((string) ($w['welcome_message'] ?? '¿En qué puedo ayudarte hoy?'));

if (!$smartGreeting) {
    $timeGreeting = 'Hola';
}

if (!$contextualBranding) {
    $initialGreeting = $timeGreeting . ' 👋 ' . $baseWelcome;
    $headerTitle = trim((string) ($w['welcome_title'] ?? '')) ?: 'NIVO';
    $headerSubtitle = trim((string) ($w['assistant_subtitle'] ?? '')) ?: ('Asistente virtual de ' . $assistantCompany);
} elseif ($isPlatformTenant) {
    if ($siteContext !== '' && $siteContext !== $platformBrand && $siteContext !== 'ES MULTISERVICIOS') {
        $initialGreeting = $timeGreeting . ' 👋 Soy NIVO, el asistente virtual de ' . $platformBrand . ' para ' . $siteContext . '. '
            . 'Puedo ayudarte con ' . $siteContext . ' y, si quieres, también contarte sobre las demás soluciones de ' . $platformBrand . '. '
            . $baseWelcome;
        $headerSubtitle = 'Asistente virtual para ' . $siteContext;
    } else {
        $initialGreeting = $timeGreeting . ' 👋 Soy NIVO, el asistente virtual de ' . $platformBrand . '. ' . $baseWelcome;
        $headerSubtitle = 'Asistente virtual de ' . $platformBrand;
    }
    $headerTitle = 'NIVO · ' . $platformBrand;
} else {
    $initialGreeting = $timeGreeting . ' 👋 Soy NIVO, el asistente virtual de ' . $tenantCompany . '. ' . $baseWelcome;
    $headerTitle = 'NIVO · ' . $tenantCompany;
    $headerSubtitle = 'Asistente virtual de ' . $tenantCompany;
}

$brandFooter = 'NIVO Web Chat · Tecnología ZYNKO by ES MULTISERVICIOS';

// V2.31.94 · El saludo inicial es un único evento de apertura de la conversación.
// Se inserta solamente cuando nace una conversación y nunca se reconstruye durante
// lecturas, polling o refrescos. El orden visual también lo fuerza como primer mensaje.
$ensureInitialGreeting = static function (int $conversationId) use ($pdo, $tid, $initialGreeting): int {
    if ($conversationId <= 0 || trim($initialGreeting) === '') {
        return 0;
    }

    $already = $pdo->prepare(
        "SELECT id FROM messages WHERE tenant_id=? AND conversation_id=? AND direction='out' AND sender_type='bot' "
        . "AND (type='greeting' OR body LIKE '%Soy NIVO, el asistente virtual%') ORDER BY id ASC LIMIT 1"
    );
    $already->execute([$tid, $conversationId]);
    $existingId = (int) ($already->fetchColumn() ?: 0);
    if ($existingId > 0) {
        return $existingId;
    }

    $pdo->prepare(
        "INSERT INTO messages(tenant_id,conversation_id,uuid,direction,sender_type,type,body,status,sent_at) VALUES(?,?,?,'out','bot','greeting',?,'sent',NOW())"
    )->execute([$tid, $conversationId, uuid4(), $initialGreeting]);

    return (int) $pdo->lastInsertId();
};

function nivoConversationMessages(PDO $pdo, int $tenantId, int $conversationId): array
{
    if ($conversationId <= 0) {
        return [];
    }

    $query = $pdo->prepare(
        "SELECT m.id,m.uuid,m.conversation_id,m.direction,m.sender_type,m.sender_user_id,u.name sender_name,"
        . "m.type,m.body,m.media_json,m.status,m.sent_at,m.created_at "
        . "FROM messages m LEFT JOIN users u ON u.id=m.sender_user_id "
        . "WHERE m.tenant_id=? AND m.conversation_id=? "
        . "ORDER BY CASE WHEN m.type='greeting' THEN 0 ELSE 1 END,m.sent_at,m.id"
    );
    $query->execute([$tenantId, $conversationId]);
    return $query->fetchAll() ?: [];
}

function nivoRecoverVisitorConversation(PDO $pdo, int $tenantId, int $widgetId, array &$visitor): int
{
    $conversationId = (int) ($visitor['conversation_id'] ?? 0);

    if ($conversationId > 0) {
        $query = $pdo->prepare(
            "SELECT c.id FROM conversations c "
            . "JOIN channels ch ON ch.id=c.channel_id "
            . "WHERE c.id=? AND c.tenant_id=? AND ch.tenant_id=? AND c.deleted_at IS NULL LIMIT 1"
        );
        $query->execute([$conversationId, $tenantId, $tenantId]);
        if ($query->fetchColumn()) {
            return $conversationId;
        }
    }

    $contactId = (int) ($visitor['contact_id'] ?? 0);
    if ($contactId <= 0) {
        return 0;
    }

    $query = $pdo->prepare(
        "SELECT c.id FROM conversations c "
        . "JOIN webchat_widgets w ON w.channel_id=c.channel_id AND w.id=? AND w.tenant_id=c.tenant_id "
        . "WHERE c.tenant_id=? AND c.contact_id=? AND c.deleted_at IS NULL "
        . "AND c.status IN ('open','pending') "
        . "ORDER BY COALESCE(c.last_message_at,c.created_at) DESC,c.id DESC LIMIT 1"
    );
    $query->execute([$widgetId, $tenantId, $contactId]);
    $recoveredId = (int) ($query->fetchColumn() ?: 0);

    if ($recoveredId > 0) {
        $pdo->prepare(
            'UPDATE webchat_visitors SET conversation_id=?,last_seen_at=NOW() WHERE id=? AND tenant_id=?'
        )->execute([$recoveredId, (int) $visitor['id'], $tenantId]);
        $visitor['conversation_id'] = $recoveredId;
    }

    return $recoveredId;
}

$visitor = (string) ($input['visitor_token'] ?? $_GET['visitor_token'] ?? '');
$v = null;

if ($visitor !== '') {
    $query = $pdo->prepare('SELECT * FROM webchat_visitors WHERE visitor_token=? AND tenant_id=? AND widget_id=?');
    $query->execute([$visitor, $tid, $wid]);
    $v = $query->fetch();
    if ($v) {
        nivoRecoverVisitorConversation($pdo, $tid, $wid, $v);
    }
}

if ($action === 'bootstrap') {
    if (!$v) {
        $visitor = bin2hex(random_bytes(32));
        $pdo->prepare(
            'INSERT INTO webchat_visitors(tenant_id,widget_id,visitor_token,origin_domain) VALUES(?,?,?,?)'
        )->execute([$tid, $wid, $visitor, $originHost]);
        $v = [
            'id' => (int) $pdo->lastInsertId(),
            'visitor_token' => $visitor,
            'conversation_id' => null,
            'name' => null,
            'email' => null
        ];
    } else {
        $pdo->prepare('UPDATE webchat_visitors SET last_seen_at=NOW(),origin_domain=? WHERE id=?')
            ->execute([$originHost, $v['id']]);
    }

    $messages = [];
    $cid = (int) ($v['conversation_id'] ?? 0);

    if ($cid) {
        $conversationQuery = $pdo->prepare('SELECT status,archived_at,deleted_at,last_message_at FROM conversations WHERE id=? AND tenant_id=? LIMIT 1');
        $conversationQuery->execute([$cid, $tid]);
        $conversation = $conversationQuery->fetch();

        $closeMinutes = max(2, min(1440, (int) ($experience['inactivity_close_minutes'] ?? 30)));
        $lastMessageAt = !empty($conversation['last_message_at']) ? strtotime((string) $conversation['last_message_at']) : 0;
        $serverExpired = $conversation
            && in_array((string) ($conversation['status'] ?? ''), ['open','pending'], true)
            && $lastMessageAt > 0
            && $lastMessageAt <= time() - ($closeMinutes * 60);

        if ($serverExpired) {
            $pdo->prepare("UPDATE conversations SET status='closed',unread_count=0 WHERE id=? AND tenant_id=?")->execute([$cid, $tid]);
            $pdo->prepare('UPDATE webchat_visitors SET conversation_id=NULL,last_seen_at=NOW() WHERE id=? AND tenant_id=?')->execute([$v['id'], $tid]);
            zynkoRealtimePublishSafe($pdo,$tid,'conversation.closed',['conversation_id'=>$cid,'channel'=>'webchat','reason'=>'server_inactivity'],'conversation',(string)$cid);
            $cid = 0;
            $conversation = null;
        }

        if (!$conversation || !empty($conversation['deleted_at'])) {
            $pdo->prepare('UPDATE webchat_visitors SET conversation_id=NULL WHERE id=?')->execute([$v['id']]);
            $cid = 0;
        } else {
            if (!empty($conversation['archived_at']) && !in_array((string) ($conversation['status'] ?? ''), ['resolved', 'closed'], true)) {
                $pdo->prepare("UPDATE conversations SET archived_at=NULL,status='open' WHERE id=? AND tenant_id=?")
                    ->execute([$cid, $tid]);
            }

            // No insertamos saludos retroactivamente en conversaciones existentes.
            // El saludo se persiste únicamente al crear una conversación nueva para evitar duplicados o re-presentaciones tardías.
            $messages = nivoConversationMessages($pdo, $tid, $cid);
        }
    }

    $conversationStatus = 'new';
    $conversationAssignedUserId = 0;
    $conversationAgentName = '';
    $survey = null;
    if ($cid) {
        try {
            $sq = $pdo->prepare('SELECT c.status,c.assigned_user_id,u.name agent_name FROM conversations c LEFT JOIN users u ON u.id=c.assigned_user_id WHERE c.id=? AND c.tenant_id=? LIMIT 1');
            $sq->execute([$cid, $tid]);
            $conversationRow = $sq->fetch() ?: [];
            $conversationStatus = (string) ($conversationRow['status'] ?? 'open');
            $conversationAssignedUserId = (int) ($conversationRow['assigned_user_id'] ?? 0);
            $conversationAgentName = trim((string) ($conversationRow['agent_name'] ?? ''));
            $surveyQuery = $pdo->prepare('SELECT conversation_id,rating,comment,requested_at,responded_at FROM conversation_surveys WHERE tenant_id=? AND conversation_id=? LIMIT 1');
            $surveyQuery->execute([$tid, $cid]);
            $surveyRow = $surveyQuery->fetch();
            if ($surveyRow) {
                $survey = [
                    'conversation_id' => (int) $surveyRow['conversation_id'],
                    'requested' => true,
                    'answered' => !empty($surveyRow['responded_at']),
                    'rating' => $surveyRow['rating'] !== null ? (int) $surveyRow['rating'] : null
                ];
            }
        } catch (Throwable $ignoreSurvey) {
        }
    }

    $env = envc($root . '/.env');
    $host = $env['WS_PUBLIC_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $host = preg_replace('/:\d+$/', '', $host);
    $scheme = strtolower(trim((string) ($env['WS_PUBLIC_SCHEME'] ?? '')));
    if (!in_array($scheme, ['ws', 'wss'], true)) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'wss' : 'ws';
    }
    $payload = [
        'tenant_id' => $tid,
        'visitor_id' => (int) $v['id'],
        'conversation_id' => $cid,
        'exp' => time() + 43200,
        'aud' => 'webchat'
    ];
    $encoded = b64u(json_encode($payload));
    $signature = preg_match('/^[a-f0-9]{64}$/i', $env['APP_KEY'] ?? '')
        ? hash_hmac('sha256', $encoded, hex2bin($env['APP_KEY']), true)
        : '';
    $publicWs = trim((string) ($env['WS_PUBLIC_URL'] ?? ''));
    $publicPort = (int) ($env['WS_PUBLIC_PORT'] ?? ($env['WS_PORT'] ?? 8080));
    $ws = $publicWs !== '' ? rtrim($publicWs, '/') : (($scheme === 'wss') ? ('wss://' . $host . '/ws') : ('ws://' . $host . ':' . $publicPort));
    $aiEnabled = false;

    try {
        $aiQuery = $pdo->prepare('SELECT enabled FROM bot_profiles WHERE tenant_id=? LIMIT 1');
        $aiQuery->execute([$tid]);
        $aiEnabled = (int) ($aiQuery->fetchColumn() ?: 0) === 1;
    } catch (Throwable $ignore) {
    }

    $profileName = trim((string) ($v['name'] ?? ''));
    if (mb_strtolower($profileName, 'UTF-8') === 'visitante web') {
        $profileName = '';
    }

    out(true, 'NIVO Web Chat listo.', [
        'visitor_token' => $visitor,
        'conversation_id' => $cid,
        'conversation_status' => $conversationStatus,
        'conversation_closed' => in_array($conversationStatus, ['resolved','closed'], true),
        'conversation_pending' => $conversationStatus === 'pending',
        'human_assigned' => $conversationAssignedUserId > 0,
        'handoff_agent' => $conversationAssignedUserId > 0 ? ['id'=>$conversationAssignedUserId,'name'=>$conversationAgentName ?: 'Agente'] : null,
        'survey' => $survey,
        'visitor_profile' => [
            'name' => $profileName,
            'email' => trim((string) ($v['email'] ?? ''))
        ],
        'widget' => [
            'company' => $tenantCompany,
            'brand_owner' => $assistantCompany,
            'site_context' => $siteContext,
            'header_title' => $headerTitle,
            'header_subtitle' => $headerSubtitle,
            'brand_footer' => $brandFooter,
            'initial_greeting' => $initialGreeting,
            'name' => $w['name'],
            'position' => $w['position'],
            'display_mode' => $w['display_mode'] ?? 'launcher',
            'offset_x' => (int) $w['offset_x'],
            'offset_y' => (int) $w['offset_y'],
            'accent_color' => $w['accent_color'],
            'welcome_title' => $w['welcome_title'],
            'assistant_subtitle' => $headerSubtitle,
            'welcome_message' => $baseWelcome,
            'ask_name' => (bool) $w['ask_name'],
            'ask_email' => (bool) $w['ask_email'],
            'profile_required' => (bool) ($w['profile_required'] ?? 0),
            'launcher_label' => $w['launcher_label'] ?? '',
            'sound_enabled' => (bool) ($w['sound_enabled'] ?? 1),
            'privacy_enabled' => (bool) ($w['privacy_enabled'] ?? 0),
            'privacy_text' => $w['privacy_text'] ?? '',
            'privacy_url' => $w['privacy_url'] ?? '',
            'nivo_ai_enabled' => $aiEnabled,
            'experience' => $experience
        ],
        'plan' => [
            'name' => $planCtx['plan_name'] ?? '',
            'daily_chat_limit' => zynkoPlanLimit($planCtx, 'max_daily_chats'),
            'daily_chat_usage' => zynkoPlanDailyChatUsage($pdo, $tid),
            'monthly_chat_limit' => zynkoPlanLimit($planCtx, 'max_monthly_chats'),
            'monthly_chat_usage' => zynkoPlanMonthlyChatUsage($pdo, $tid)
        ],
        'messages' => $messages,
        'ws_url' => $ws,
        'ws_token' => $encoded . '.' . b64u($signature)
    ]);
}

if (!$v) {
    out(false, 'Sesión del visitante inválida.', [], 401);
}

if ($action === 'profile') {
    $name = mb_substr(trim((string) ($input['name'] ?? '')), 0, 160);
    $email = mb_substr(trim((string) ($input['email'] ?? '')), 0, 190);

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        out(false, 'Ingresa un correo válido.', [], 422);
    }

    $pdo->prepare('UPDATE webchat_visitors SET name=NULLIF(?,\'\'),email=NULLIF(?,\'\'),last_seen_at=NOW() WHERE id=? AND tenant_id=?')
        ->execute([$name, $email, $v['id'], $tid]);

    if (!empty($v['contact_id'])) {
        $contactName = $name !== '' ? $name : 'Visitante web';
        $pdo->prepare('UPDATE contacts SET name=?,email=NULLIF(?,\'\') WHERE id=? AND tenant_id=?')
            ->execute([$contactName, $email, (int) $v['contact_id'], $tid]);
    }

    out(true, 'Perfil actualizado.', [
        'name' => $name,
        'email' => $email
    ]);
}

if ($action === 'close') {
    $cid = (int) ($v['conversation_id'] ?? 0);
    if (!$cid) {
        out(false, 'No hay una conversación activa para finalizar.', [], 422);
    }

    $conversationQuery = $pdo->prepare('SELECT c.id,c.status,c.deleted_at FROM conversations c WHERE c.id=? AND c.tenant_id=? LIMIT 1');
    $conversationQuery->execute([$cid, $tid]);
    $conversation = $conversationQuery->fetch();
    if (!$conversation || !empty($conversation['deleted_at'])) {
        out(false, 'La conversación ya no está disponible.', [], 404);
    }

    $closingMessage = 'Gracias por conversar con nosotros. La atención quedó finalizada. Si quieres, califica tu experiencia y luego puedes iniciar un nuevo chat.';
    $pdo->beginTransaction();
    try {
        $closingMessageId = 0;
        if (!in_array((string) $conversation['status'], ['resolved','closed'], true)) {
            $pdo->prepare("INSERT INTO messages(tenant_id,conversation_id,uuid,direction,sender_type,type,body,status,sent_at) VALUES(?,?,?,'out','bot','text',?,'sent',NOW())")
                ->execute([$tid, $cid, uuid4(), $closingMessage]);
            $closingMessageId = (int) $pdo->lastInsertId();
        }
        $pdo->prepare("UPDATE conversations SET status='resolved',unread_count=0,last_message_at=NOW() WHERE id=? AND tenant_id=?")
            ->execute([$cid, $tid]);
        $pdo->prepare("INSERT INTO conversation_surveys(tenant_id,conversation_id,visitor_id,requested_at) VALUES(?,?,?,NOW()) ON DUPLICATE KEY UPDATE visitor_id=VALUES(visitor_id),rating=NULL,comment=NULL,requested_at=NOW(),responded_at=NULL")
            ->execute([$tid, $cid, (int) $v['id']]);
        if ($closingMessageId > 0) {
            zynkoRealtimePublishMessage($pdo, $tid, $closingMessageId, [
                'visitor_id' => (int) $v['id'],
                'channel' => 'webchat',
                'sender' => 'bot'
            ]);
        }
        zynkoRealtimePublish(
            $pdo,
            $tid,
            'conversation.resolved',
            ['conversation_id'=>$cid,'visitor_id'=>(int)$v['id'],'channel'=>'webchat','reason'=>'visitor_finished'],
            'conversation',
            (string) $cid
        );
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }

    out(true, 'Chat finalizado.', [
        'conversation_id' => $cid,
        'conversation_status' => 'resolved',
        'message' => $closingMessage,
        'survey' => ['conversation_id'=>$cid,'requested'=>true,'answered'=>false,'rating'=>null]
    ]);
}

if ($action === 'survey') {
    $cid = max(1, (int) ($input['conversation_id'] ?? 0));
    $rating = max(1, min(5, (int) ($input['rating'] ?? 0)));
    $comment = mb_substr(trim((string) ($input['comment'] ?? '')), 0, 1000);
    $q = $pdo->prepare('SELECT c.id FROM conversations c WHERE c.id=? AND c.tenant_id=? AND c.contact_id=? LIMIT 1');
    $q->execute([$cid, $tid, (int) ($v['contact_id'] ?? 0)]);
    if (!$q->fetchColumn()) {
        out(false, 'No fue posible validar esta encuesta.', [], 403);
    }
    $pdo->prepare("INSERT INTO conversation_surveys(tenant_id,conversation_id,visitor_id,rating,comment,requested_at,responded_at) VALUES(?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE visitor_id=VALUES(visitor_id),rating=VALUES(rating),comment=VALUES(comment),responded_at=NOW()")
        ->execute([$tid, $cid, (int) $v['id'], $rating, $comment ?: null]);
    out(true, 'Gracias por tu opinión.', ['conversation_id'=>$cid,'rating'=>$rating]);
}

if ($action === 'new_chat') {
    $cid = (int) ($v['conversation_id'] ?? 0);
    if ($cid) {
        $q = $pdo->prepare('SELECT status FROM conversations WHERE id=? AND tenant_id=? LIMIT 1');
        $q->execute([$cid,$tid]);
        $status = (string) ($q->fetchColumn() ?: '');
        if (!in_array($status,['resolved','closed'],true)) {
            out(false,'Finaliza el chat actual antes de iniciar uno nuevo.',[],409);
        }
    }
    $pdo->prepare('UPDATE webchat_visitors SET conversation_id=NULL,last_seen_at=NOW() WHERE id=? AND tenant_id=?')->execute([$v['id'],$tid]);
    out(true,'Nueva conversación lista.',['conversation_id'=>0]);
}

if ($action === 'inactivity_nudge') {
    $cid = (int) ($v['conversation_id'] ?? 0);
    $message = trim((string) ($experience['inactivity_message'] ?? ''));

    if ($message === '') {
        $message = '¿Sigues por aquí? Si necesitas algo más, estoy pendiente para ayudarte.';
    }

    if (!$cid) {
        out(true, 'Sin conversación activa.', ['conversation_id'=>0,'message'=>$message,'persisted'=>false]);
    }

    $q = $pdo->prepare('SELECT status FROM conversations WHERE id=? AND tenant_id=? LIMIT 1');
    $q->execute([$cid,$tid]);
    $status = (string) ($q->fetchColumn() ?: '');
    if (in_array($status,['resolved','closed'],true)) {
        out(true, 'La conversación ya está cerrada.', ['conversation_id'=>$cid,'message'=>$message,'persisted'=>false]);
    }

    $dup = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE tenant_id=? AND conversation_id=? AND sender_type='bot' AND body=? AND sent_at>=DATE_SUB(NOW(),INTERVAL 2 MINUTE)");
    $dup->execute([$tid,$cid,$message]);
    $persisted = false;
    if ((int)$dup->fetchColumn() === 0) {
        $pdo->beginTransaction();
        try {
            $pdo->prepare("INSERT INTO messages(tenant_id,conversation_id,uuid,direction,sender_type,type,body,status,sent_at) VALUES(?,?,?,'out','bot','inactivity_nudge',?,'sent',NOW())")
                ->execute([$tid,$cid,uuid4(),$message]);
            $nudgeMessageId = (int) $pdo->lastInsertId();
            $pdo->prepare('UPDATE conversations SET last_message_at=NOW() WHERE id=? AND tenant_id=?')->execute([$cid,$tid]);
            zynkoRealtimePublishMessage($pdo,$tid,$nudgeMessageId,[
                'visitor_id'=>(int)$v['id'],
                'channel'=>'webchat',
                'sender'=>'bot',
                'reason'=>'visitor_inactivity_nudge'
            ]);
            $pdo->commit();
            $persisted = true;
        } catch (Throwable $nudgeError) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $nudgeError;
        }
    }

    out(true, 'Seguimiento de inactividad procesado.', ['conversation_id'=>$cid,'message'=>$message,'persisted'=>$persisted]);
}

if ($action === 'expire') {
    $cid = (int) ($v['conversation_id'] ?? 0);
    $closeMessage = trim((string) ($experience['inactivity_close_message'] ?? ''));

    if ($closeMessage === '') {
        $closeMessage = 'Cerré esta sesión por inactividad. Cuando quieras, puedes calificar la atención e iniciar un nuevo chat.';
    }

    if (!$cid) {
        out(true, 'No había una conversación activa para cerrar.', [
            'conversation_id' => 0,
            'message' => $closeMessage,
            'survey' => null
        ]);
    }

    $q = $pdo->prepare('SELECT status FROM conversations WHERE id=? AND tenant_id=? LIMIT 1');
    $q->execute([$cid,$tid]);
    $status = (string) ($q->fetchColumn() ?: '');

    if (!in_array($status,['resolved','closed'],true)) {
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "INSERT INTO messages(tenant_id,conversation_id,uuid,direction,sender_type,type,body,status,sent_at) VALUES(?,?,?,'out','bot','inactivity_close',?,'sent',NOW())"
            )->execute([$tid, $cid, uuid4(), $closeMessage]);
            $closeMessageId = (int) $pdo->lastInsertId();
            $pdo->prepare(
                "UPDATE conversations SET status='closed',unread_count=0,last_message_at=NOW() WHERE id=? AND tenant_id=?"
            )->execute([$cid, $tid]);
            $pdo->prepare("INSERT INTO conversation_surveys(tenant_id,conversation_id,visitor_id,requested_at) VALUES(?,?,?,NOW()) ON DUPLICATE KEY UPDATE visitor_id=VALUES(visitor_id),rating=NULL,comment=NULL,requested_at=NOW(),responded_at=NULL")
                ->execute([$tid, $cid, (int) $v['id']]);
            $persistProfile = !array_key_exists('persist_profile', $experience) || !empty($experience['persist_profile']);
            if ($persistProfile) {
                $pdo->prepare('UPDATE webchat_visitors SET last_seen_at=NOW() WHERE id=? AND tenant_id=?')
                    ->execute([$v['id'], $tid]);
            } else {
                $pdo->prepare('UPDATE webchat_visitors SET name=NULL,email=NULL,last_seen_at=NOW() WHERE id=? AND tenant_id=?')
                    ->execute([$v['id'], $tid]);
            }
            zynkoRealtimePublishMessage($pdo,$tid,$closeMessageId,[
                'visitor_id'=>(int)$v['id'],
                'channel'=>'webchat',
                'sender'=>'bot',
                'reason'=>'visitor_inactivity'
            ]);
            zynkoRealtimePublish(
                $pdo,
                $tid,
                'conversation.closed',
                ['conversation_id'=>$cid,'visitor_id'=>(int)$v['id'],'channel'=>'webchat','reason'=>'visitor_inactivity'],
                'conversation',
                (string) $cid
            );
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }

    }

    out(true, 'Sesión finalizada por inactividad.', [
        'conversation_id' => $cid,
        'conversation_status' => 'closed',
        'message' => $closeMessage,
        'survey' => ['conversation_id'=>$cid,'requested'=>true,'answered'=>false,'rating'=>null]
    ]);
}

if ($action === 'send') {
    $body = trim((string) ($input['body'] ?? ''));
    if ($body === '') {
        out(false, 'Escribe un mensaje.', [], 422);
    }

    $maxMessage = max(120, min(3000, (int) ($experience['max_message_length'] ?? 1000)));
    if (mb_strlen($body) > $maxMessage) {
        out(false, 'El mensaje supera el máximo de ' . $maxMessage . ' caracteres.', [], 422);
    }

    $rate = max(2, min(30, (int) ($experience['rate_limit_per_minute'] ?? 12)));
    if ($v) {
        $rateQuery = $pdo->prepare(
            "SELECT COUNT(*) FROM messages m JOIN conversations c ON c.id=m.conversation_id WHERE c.tenant_id=? AND c.id=? AND m.direction='in' AND m.sent_at>=DATE_SUB(NOW(),INTERVAL 1 MINUTE)"
        );
        $rateQuery->execute([$tid, (int) ($v['conversation_id'] ?? 0)]);
        if ((int) $rateQuery->fetchColumn() >= $rate) {
            out(false, 'Has enviado varios mensajes muy rápido. Espera unos segundos e inténtalo de nuevo.', [], 429);
        }
    }

    $rawName = mb_substr(trim((string) ($input['name'] ?? ($v['name'] ?? ''))), 0, 160);
    if (mb_strtolower($rawName, 'UTF-8') === 'visitante web') {
        $rawName = '';
    }
    $email = mb_substr(trim((string) ($input['email'] ?? ($v['email'] ?? ''))), 0, 190);

    if (!empty($w['profile_required'])) {
        if (!empty($w['ask_name']) && $rawName === '') {
            out(false, 'Ingresa tu nombre para continuar.', [], 422);
        }
        if (!empty($w['ask_email']) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            out(false, 'Ingresa un correo válido para continuar.', [], 422);
        }
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        out(false, 'Ingresa un correo válido.', [], 422);
    }

    if (!empty($w['privacy_enabled']) && empty($input['privacy_accepted'])) {
        out(false, 'Debes aceptar el aviso de privacidad para continuar.', [], 422);
    }

    $securityEnv = envc($root . '/.env');
    $securityAssessment = nivoSecurityAssessment(
        $pdo,
        $tid,
        $wid,
        (int) ($installation['id'] ?? 0),
        $v ?: null,
        $originHost,
        $body,
        $input,
        $securityEnv,
        $experience
    );

    if (($securityAssessment['verdict'] ?? 'clean') === 'blocked') {
        nivoLogSecurityEvent($pdo, $tid, $v ?: null, 0, $securityAssessment);
        // Nunca responder OK si el servidor no persistió el mensaje. El widget debe saber
        // que el envío falló y conservar el texto para reintentar, en vez de simular entrega.
        out(false, 'No fue posible aceptar este mensaje por protección anti-spam. Espera unos segundos e inténtalo de nuevo.', [
            'conversation_id' => (int) ($v['conversation_id'] ?? 0),
            'security_blocked' => true
        ], 429);
    }

    $contactDisplayName = $rawName !== '' ? $rawName : 'Visitante web';
    $cid = (int) ($v['conversation_id'] ?? 0);

    if ($cid) {
        $conversationQuery = $pdo->prepare('SELECT status,archived_at,deleted_at FROM conversations WHERE id=? AND tenant_id=? LIMIT 1');
        $conversationQuery->execute([$cid, $tid]);
        $conversation = $conversationQuery->fetch();

        if (!$conversation || !empty($conversation['deleted_at']) || in_array((string) ($conversation['status'] ?? ''), ['resolved', 'closed'], true)) {
            $pdo->prepare('UPDATE webchat_visitors SET conversation_id=NULL WHERE id=?')->execute([$v['id']]);
            $cid = 0;
        } elseif (!empty($conversation['archived_at'])) {
            $pdo->prepare("UPDATE conversations SET archived_at=NULL,status='open' WHERE id=? AND tenant_id=?")
                ->execute([$cid, $tid]);
        }
    }

    $firstMessagePersisted = false;
    $inboundMessageId = 0;
    $visitorId = (int) ($v['id'] ?? 0);

    if (!$cid) {
        $dailyLimit = zynkoPlanLimit($planCtx, 'max_daily_chats');
        $monthlyLimit = zynkoPlanLimit($planCtx, 'max_monthly_chats');

        if ($dailyLimit !== null) {
            $dailyUsed = zynkoPlanDailyChatUsage($pdo, $tid);
            if ($dailyUsed >= $dailyLimit) {
                out(false, 'Este sitio alcanzó el límite de ' . $dailyLimit . ' chats nuevos de hoy. Las conversaciones ya iniciadas pueden continuar normalmente.', [
                    'limit' => $dailyLimit,
                    'used' => $dailyUsed,
                    'period' => 'day'
                ], 429);
            }
        }

        if ($monthlyLimit !== null) {
            $monthlyUsed = zynkoPlanMonthlyChatUsage($pdo, $tid);
            if ($monthlyUsed >= $monthlyLimit) {
                out(false, 'Esta empresa alcanzó el límite de ' . number_format($monthlyLimit) . ' chats nuevos de este mes. Las conversaciones ya iniciadas pueden continuar normalmente.', [
                    'limit' => $monthlyLimit,
                    'used' => $monthlyUsed,
                    'period' => 'month'
                ], 429);
            }
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO contacts(tenant_id,uuid,name,email) VALUES(?,?,?,?)')
                ->execute([$tid, uuid4(), $contactDisplayName, $email ?: null]);
            $contact = (int) $pdo->lastInsertId();

            $pdo->prepare(
                "INSERT INTO conversations(tenant_id,uuid,channel_id,contact_id,status,last_message_at) VALUES(?,?,?,?, 'open',NOW())"
            )->execute([$tid, uuid4(), (int) $w['channel_id'], $contact]);
            $cid = (int) $pdo->lastInsertId();

            $pdo->prepare(
                'UPDATE webchat_visitors SET contact_id=?,conversation_id=?,name=NULLIF(?,\'\'),email=NULLIF(?,\'\'),last_seen_at=NOW() WHERE id=?'
            )->execute([$contact, $cid, $rawName, $email, $visitorId]);

            $greetingMessageId = $ensureInitialGreeting($cid);

            $pdo->prepare(
                "INSERT INTO messages(tenant_id,conversation_id,uuid,direction,sender_type,type,body,status,sent_at) VALUES(?,?,?,'in','contact','text',?,'received',NOW())"
            )->execute([$tid, $cid, uuid4(), $body]);
            $inboundMessageId = (int) $pdo->lastInsertId();

            $pdo->prepare(
                "UPDATE conversations SET unread_count=unread_count+1,last_message_at=NOW(),status='open',archived_at=NULL WHERE id=? AND tenant_id=?"
            )->execute([$cid, $tid]);

            zynkoRealtimePublish(
                $pdo,
                $tid,
                'conversation.created',
                [
                    'conversation_id' => $cid,
                    'visitor_id' => $visitorId,
                    'channel' => 'webchat',
                    'contact_name' => $contactDisplayName
                ],
                'conversation',
                (string) $cid
            );

            if ($greetingMessageId > 0) {
                zynkoRealtimePublishMessage($pdo, $tid, $greetingMessageId, [
                    'visitor_id' => $visitorId,
                    'channel' => 'webchat',
                    'sender' => 'bot'
                ]);
            }

            zynkoRealtimePublishMessage($pdo, $tid, $inboundMessageId, [
                'visitor_id' => $visitorId,
                'channel' => 'webchat',
                'sender' => 'contact'
            ]);
            zynkoRealtimePublish($pdo,$tid,'conversation.updated',[
                'conversation_id'=>$cid,'visitor_id'=>$visitorId,'channel'=>'webchat','reason'=>'inbound_message','message_id'=>$inboundMessageId
            ],'conversation',(string)$cid);

            $pdo->commit();
            $firstMessagePersisted = true;
        } catch (Throwable $creationError) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $creationError;
        }
    } else {
        $pdo->prepare('UPDATE webchat_visitors SET name=NULLIF(?,\'\'),email=NULLIF(?,\'\'),last_seen_at=NOW() WHERE id=? AND tenant_id=?')
            ->execute([$rawName, $email, $visitorId, $tid]);

        if (!empty($v['contact_id'])) {
            $pdo->prepare('UPDATE contacts SET name=?,email=NULLIF(?,\'\') WHERE id=? AND tenant_id=?')
                ->execute([$contactDisplayName, $email, (int) $v['contact_id'], $tid]);
        }
    }

    if (!$firstMessagePersisted) {
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "INSERT INTO messages(tenant_id,conversation_id,uuid,direction,sender_type,type,body,status,sent_at) VALUES(?,?,?,'in','contact','text',?,'received',NOW())"
            )->execute([$tid, $cid, uuid4(), $body]);
            $inboundMessageId = (int) $pdo->lastInsertId();

            $pdo->prepare(
                "UPDATE conversations SET unread_count=unread_count+1,last_message_at=NOW(),status=IF(status='pending','pending','open'),archived_at=NULL WHERE id=? AND tenant_id=?"
            )->execute([$cid, $tid]);

            zynkoRealtimePublishMessage($pdo, $tid, $inboundMessageId, [
                'visitor_id' => $visitorId,
                'channel' => 'webchat',
                'sender' => 'contact'
            ]);
            zynkoRealtimePublish($pdo,$tid,'conversation.updated',[
                'conversation_id'=>$cid,'visitor_id'=>$visitorId,'channel'=>'webchat','reason'=>'inbound_message','message_id'=>$inboundMessageId
            ],'conversation',(string)$cid);

            $pdo->commit();
        } catch (Throwable $messageError) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $messageError;
        }
    }

    nivoLogSecurityEvent($pdo, $tid, $v ?: null, $cid, $securityAssessment);

    try {
        require_once $root . '/app/Services/NotificationService.php';
        $recipientQuery = $pdo->prepare(
            "SELECT COALESCE(NULLIF(c.destinatario,''),(SELECT u.email FROM users u JOIN tenant_users tu ON tu.user_id=u.id WHERE tu.tenant_id=? AND tu.role_code IN ('owner','admin') AND u.status='active' ORDER BY FIELD(tu.role_code,'owner','admin'),u.id LIMIT 1)) recipient FROM correo c WHERE c.tenant_id=? AND c.is_default=1 AND c.estado=1 ORDER BY c.correo_id DESC LIMIT 1"
        );
        $recipientQuery->execute([$tid, $tid]);
        $notifyTo = (string) ($recipientQuery->fetchColumn() ?: '');
        if ($notifyTo !== '') {
            (new NotificationService($pdo, $root))->send(
                $tid,
                'message',
                $notifyTo,
                'Nuevo mensaje · NIVO Web Chat',
                $contactDisplayName . ' escribió: ' . mb_substr($body, 0, 260),
                ['dedupe_key' => 'conversation:' . $cid, 'conversation_id' => $cid, 'channel' => 'webchat']
            );
        }
    } catch (Throwable $ignore) {
    }

    $statusQuery = $pdo->prepare('SELECT status,assigned_user_id FROM conversations WHERE id=? AND tenant_id=? LIMIT 1');
    $statusQuery->execute([$cid, $tid]);
    $conversationState = $statusQuery->fetch() ?: ['status' => 'open', 'assigned_user_id' => null];
    $currentStatus = (string) ($conversationState['status'] ?? 'open');
    $assignedUserId = (int) ($conversationState['assigned_user_id'] ?? 0);
    $handoffAgent = null;

    // Una conversación entregada a un humano nunca debe volver a ser contestada por NIVO.
    // Si estaba en cola, cada nuevo mensaje vuelve a intentar una asignación a un agente disponible.
    if ($assignedUserId > 0) {
        $engine = [
            'reply' => null,
            'handoff' => true,
            'source' => 'handoff:assigned',
            'sources' => [],
            'reason' => 'human_assigned'
        ];
        $agentQuery = $pdo->prepare('SELECT name FROM users WHERE id=? LIMIT 1');
        $agentQuery->execute([$assignedUserId]);
        $agentName = trim((string) ($agentQuery->fetchColumn() ?: 'Agente'));
        $handoffAgent = ['id' => $assignedUserId, 'name' => $agentName];
    } elseif ($currentStatus === 'pending') {
        $handoffAgent = nivoAssignAvailableAgent($pdo, $tid, $cid);
        if ($handoffAgent) {
            $engine = [
                'reply' => null,
                'handoff' => true,
                'source' => 'handoff:assigned',
                'sources' => [],
                'reason' => 'human_assigned'
            ];
            zynkoRealtimePublishSafe(
                $pdo,
                $tid,
                'conversation.assigned',
                ['conversation_id' => $cid, 'visitor_id' => $visitorId, 'agent' => $handoffAgent['name'], 'channel' => 'webchat'],
                'conversation',
                (string) $cid
            );
        } else {
            // Mientras la conversación espera un humano, NIVO puede seguir ayudando con conocimiento aprobado.
            // Esto también recupera conversaciones antiguas que quedaron en pending por el comportamiento previo.
            try {
                $automation = AutomationEngine::evaluate($pdo, $tid, $cid, 'webchat', $body);
                $engine = ($automation && (!empty($automation['stop']) || !empty($automation['reply']))) ? $automation : NivoEngine::evaluate(
                    $pdo,
                    $tid,
                    $cid,
                    $body,
                    'webchat',
                    $rawName,
                    $assistantCompany
                );
            } catch (Throwable $engineError) {
                error_log('NIVO pending engine failed in webchat: ' . $engineError->getMessage());
                $engine = [
                    'enabled' => true,
                    'reply' => 'Tu conversación sigue en cola para atención humana. Mientras esperas, puedo seguir intentando ayudarte con el conocimiento aprobado.',
                    'handoff' => true,
                    'source' => 'handoff:pending-recovery',
                    'sources' => [],
                    'confidence' => 'low',
                    'reason' => 'awaiting_human'
                ];
            }
        }
    } else {
        try {
            $automation = AutomationEngine::evaluate($pdo, $tid, $cid, 'webchat', $body);
            $engine = ($automation && (!empty($automation['stop']) || !empty($automation['reply']))) ? $automation : NivoEngine::evaluate(
                $pdo,
                $tid,
                $cid,
                $body,
                'webchat',
                $rawName,
                $assistantCompany
            );
        } catch (Throwable $engineError) {
            error_log('NIVO engine failed in webchat: ' . $engineError->getMessage());
            $engine = [
                'enabled' => true,
                'reply' => 'Recibí tu mensaje, pero tuve un problema temporal consultando el conocimiento aprobado. La conversación sigue activa; intenta la pregunta una vez más.',
                'handoff' => false,
                'source' => 'engine:recovery',
                'sources' => [],
                'confidence' => 'low',
                'reason' => 'engine_exception'
            ];
        }
    }

    $alreadyHuman = $assignedUserId > 0 || $handoffAgent !== null;

    // Garantía definitiva de respuesta: mientras ningún humano sea dueño de la conversación,
    // cada mensaje entrante debe terminar con respuesta o handoff explícito. Esta protección
    // cubre perfiles deshabilitados por datos heredados, reglas de canal, cooldowns y fallos
    // secundarios sin permitir que el visitante vea su mensaje enviado y quede en silencio.
    if (!$alreadyHuman && trim((string)($engine['reply'] ?? '')) === '' && empty($engine['handoff'])) {
        $engine['enabled'] = true;
        $engine['reply'] = 'Recibí tu mensaje. En este momento no pude construir una respuesta segura con el conocimiento aprobado de ' . $assistantCompany . '. Puedes reformular la pregunta y seguiré intentando ayudarte sin cerrar la conversación.';
        $engine['source'] = 'engine:non-silent-fallback';
        $engine['sources'] = $engine['sources'] ?? [];
        $engine['confidence'] = 'low';
        $engine['reason'] = 'non_silent_fallback';
    }

    $reply = $engine['reply'] ?? null;
    $handoff = !empty($engine['handoff']);
    $replySource = $engine['source'] ?? null;
    $replySources = $engine['sources'] ?? [];

    if ($handoff && !$alreadyHuman) {
        $handoffAgent = nivoAssignAvailableAgent($pdo, $tid, $cid);
        if (!$handoffAgent) {
            $pdo->prepare("UPDATE conversations SET status='pending',assigned_user_id=NULL WHERE id=? AND tenant_id=?")
                ->execute([$cid, $tid]);
        }

        if (trim((string) $reply) === '') {
            $reply = $handoffAgent
                ? 'Te transfiero con ' . $handoffAgent['name'] . '. Puedes seguir escribiendo aquí; la conversación continúa en tiempo real.'
                : 'Necesito que una persona continúe contigo para ayudarte correctamente. Tu conversación quedó en cola y el próximo agente disponible podrá verla completa.';
            $replySource = $handoffAgent ? 'handoff:assigned' : 'handoff:notice';
        } elseif ($handoffAgent) {
            $reply .= ' Ya te conecté con ' . $handoffAgent['name'] . '; puedes seguir escribiendo aquí.';
        }

        zynkoRealtimePublishSafe(
            $pdo,
            $tid,
            'conversation.assigned',
            ['conversation_id' => $cid, 'visitor_id' => $visitorId, 'agent' => $handoffAgent['name'] ?? null, 'channel' => 'webchat'],
            'conversation',
            (string) $cid
        );
    }

    if ($reply) {
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "INSERT INTO messages(tenant_id,conversation_id,uuid,direction,sender_type,type,body,status,sent_at) VALUES(?,?,?,'out','bot','text',?,'sent',NOW())"
            )->execute([$tid, $cid, uuid4(), $reply]);
            $replyMessageId = (int) $pdo->lastInsertId();

            $pdo->prepare('UPDATE conversations SET last_message_at=NOW() WHERE id=? AND tenant_id=?')
                ->execute([$cid, $tid]);

            zynkoRealtimePublishMessage($pdo, $tid, $replyMessageId, [
                'visitor_id' => $visitorId,
                'channel' => 'webchat',
                'sender' => 'bot'
            ]);
            zynkoRealtimePublish($pdo,$tid,'conversation.updated',[
                'conversation_id'=>$cid,'visitor_id'=>$visitorId,'channel'=>'webchat','reason'=>'bot_reply','message_id'=>$replyMessageId
            ],'conversation',(string)$cid);

            $pdo->commit();
        } catch (Throwable $replyPersistenceError) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $replyPersistenceError;
        }

        if ($handoff) {
            try {
                if (!isset($notifyTo) || $notifyTo === '') {
                    $recipientQuery = $pdo->prepare(
                        "SELECT COALESCE(NULLIF(c.destinatario,''),(SELECT u.email FROM users u JOIN tenant_users tu ON tu.user_id=u.id WHERE tu.tenant_id=? AND tu.role_code IN ('owner','admin') AND u.status='active' ORDER BY u.id LIMIT 1)) FROM correo c WHERE c.tenant_id=? AND c.is_default=1 AND c.estado=1 LIMIT 1"
                    );
                    $recipientQuery->execute([$tid, $tid]);
                    $notifyTo = (string) ($recipientQuery->fetchColumn() ?: '');
                }

                if ($notifyTo !== '') {
                    require_once $root . '/app/Services/NotificationService.php';
                    (new NotificationService($pdo, $root))->send(
                        $tid,
                        'handoff',
                        $notifyTo,
                        'NIVO solicita atención humana',
                        $handoffAgent
                            ? 'NIVO transfirió la conversación de ' . $contactDisplayName . ' a ' . $handoffAgent['name'] . '.'
                            : 'NIVO dejó la conversación de ' . $contactDisplayName . ' en cola para atención humana.',
                        ['dedupe_key' => 'handoff:' . $cid, 'conversation_id' => $cid, 'channel' => 'webchat']
                    );
                }
            } catch (Throwable $ignore) {
            }
        }

    }

    $pendingQuery = $pdo->prepare('SELECT status,assigned_user_id FROM conversations WHERE id=? AND tenant_id=? LIMIT 1');
    $pendingQuery->execute([$cid, $tid]);
    $finalState = $pendingQuery->fetch() ?: [];

    $canonicalMessages = nivoConversationMessages($pdo, $tid, $cid);

    out(true, 'Mensaje recibido.', [
        'conversation_id' => $cid,
        'messages' => $canonicalMessages,
        'bot_reply' => $reply,
        'handoff' => $handoff,
        'conversation_pending' => (($finalState['status'] ?? '') === 'pending'),
        'human_assigned' => (int)($finalState['assigned_user_id'] ?? 0) > 0,
        'handoff_agent' => $handoffAgent,
        'reply_source' => $replySource,
        'reply_sources' => $replySources
    ]);
}

if ($action === 'messages') {
    $cid = (int) ($v['conversation_id'] ?? 0);
    $messages = [];
    $conversationStatus = $cid ? 'open' : 'new';
    $survey = null;
    $conversationAssignedUserId = 0;
    $conversationAgentName = '';

    if ($cid) {
        $messages = nivoConversationMessages($pdo, $tid, $cid);
        $statusQuery = $pdo->prepare('SELECT c.status,c.assigned_user_id,u.name agent_name FROM conversations c LEFT JOIN users u ON u.id=c.assigned_user_id WHERE c.id=? AND c.tenant_id=? LIMIT 1');
        $statusQuery->execute([$cid,$tid]);
        $conversationRow = $statusQuery->fetch() ?: [];
        $conversationStatus = (string) ($conversationRow['status'] ?? 'open');
        $conversationAssignedUserId = (int) ($conversationRow['assigned_user_id'] ?? 0);
        $conversationAgentName = trim((string) ($conversationRow['agent_name'] ?? ''));
        $surveyQuery = $pdo->prepare('SELECT conversation_id,rating,responded_at FROM conversation_surveys WHERE tenant_id=? AND conversation_id=? LIMIT 1');
        $surveyQuery->execute([$tid,$cid]);
        if ($surveyRow = $surveyQuery->fetch()) {
            $survey = ['conversation_id'=>(int)$surveyRow['conversation_id'],'requested'=>true,'answered'=>!empty($surveyRow['responded_at']),'rating'=>$surveyRow['rating']!==null?(int)$surveyRow['rating']:null];
        }
    }

    out(true, 'OK', [
        'conversation_id' => $cid,
        'conversation_status' => $conversationStatus,
        'conversation_closed' => in_array($conversationStatus,['resolved','closed'],true),
        'conversation_pending' => $conversationStatus === 'pending',
        'human_assigned' => $conversationAssignedUserId > 0,
        'handoff_agent' => $conversationAssignedUserId > 0 ? ['id'=>$conversationAssignedUserId,'name'=>$conversationAgentName ?: 'Agente'] : null,
        'survey' => $survey,
        'messages' => $messages
    ]);
}

out(false, 'Acción no válida.', [], 400);
}catch(Throwable $e){if(($origin??'')!=='')ZynkoCors::send($origin,['POST','OPTIONS'],['Content-Type','Accept']);out(false,'No fue posible procesar el chat: '.$e->getMessage(),[],500);}
