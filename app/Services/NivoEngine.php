<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/Support/Plan.php';
require_once __DIR__.'/OpenAIProviderService.php';

final class NivoEngine
{
    private static function norm(string $s): string
    {
        $s=mb_strtolower(trim($s),'UTF-8');
        $s=strtr($s,['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
        return preg_replace('/\s+/u',' ',$s) ?: '';
    }

    private static function words(string $s): array
    {
        $stop=['que','como','para','por','con','una','uno','unos','unas','del','las','los','este','esta','esto','esa','ese','soy','eres','es','son','hay','muy','mas','pero','porque','donde','cuando','puedo','puede','quiero','quiere','necesito','me','mi','tu','su','de','la','el','y','o','a','en','un'];
        return array_values(array_unique(array_filter(
            preg_split('/[^\p{L}\p{N}]+/u',self::norm($s)) ?: [],
            fn($x)=>mb_strlen($x)>=3&&!in_array($x,$stop,true)
        )));
    }

    private static function expandedWords(string $s): array
    {
        $words=self::words($s);$norm=self::norm($s);$extra=[];
        $maps=[
            ['needles'=>['solucion','soluciones','producto','productos','servicio','servicios','ofrecen','ofrece'],'add'=>['izzy','cami','zynko','multiservicios']],
            ['needles'=>['facturacion','factura','pos','inventario','restaurante'],'add'=>['izzy']],
            ['needles'=>['clinica','medico','paciente','farmacia','operatorio'],'add'=>['cami']],
            ['needles'=>['omnicanal','chat','webchat','nivo','whatsapp','messenger'],'add'=>['zynko']],
        ];
        foreach($maps as $m){foreach($m['needles'] as $n){if(str_contains($norm,$n)){array_push($extra,...$m['add']);break;}}}
        return array_values(array_unique(array_merge($words,$extra)));
    }


    private static function recentConversationTopic(PDO $pdo,int $tenantId,int $conversationId): string
    {
        if($conversationId<=0)return '';
        try{
            $q=$pdo->prepare("SELECT direction,sender_type,body FROM messages WHERE tenant_id=? AND conversation_id=? AND body IS NOT NULL ORDER BY id DESC LIMIT 12");
            $q->execute([$tenantId,$conversationId]);
            foreach($q->fetchAll() as $row){
                $body=self::norm((string)($row['body']??''));
                foreach(['izzy','cami','zynko','nivo web chat','nivo ia'] as $topic){
                    if(str_contains($body,$topic))return $topic;
                }
            }
        }catch(Throwable $ignore){}
        return '';
    }

    private static function isGenericFollowUp(string $norm): bool
    {
        return (bool)preg_match('/\b(explicame|explica|cuentame|dime|detallame|ampliame|quiero saber|mas informacion|más informacion)?\s*(las|sus)?\s*(funciones|funcionalidades|caracteristicas|características|beneficios|como funciona|cómo funciona|como me ayuda|cómo me ayuda|para mi negocio|en mi negocio)\b/u',$norm);
    }

    private static function knowledgeScore(string $query,string $name,string $content,array $words): int
    {
        $q=self::norm($query);$n=self::norm($name);$c=self::norm($content);$score=0;
        if($q!==''&&($n===$q||str_contains($n,$q)||str_contains($q,$n)))$score+=7;
        foreach($words as $word){if(mb_strpos($n,$word)!==false)$score+=4;if(mb_strpos($c,$word)!==false)$score+=1;}
        if(count($words)>=2){$phrase=implode(' ',$words);if($phrase!==''&&mb_strpos($c,$phrase)!==false)$score+=4;}
        return $score;
    }

    private static function relevantExcerpt(string $content,array $words,int $limit): string
    {
        $parts=preg_split('/\n{2,}|(?<=[.!?])\s+(?=[A-ZÁÉÍÓÚÑ])/u',trim($content))?:[];$ranked=[];
        foreach($parts as $idx=>$part){$part=trim($part);if($part==='')continue;$hay=self::norm($part);$score=0;foreach($words as $w)if(mb_strpos($hay,$w)!==false)$score++;$ranked[]=['text'=>$part,'score'=>$score,'idx'=>$idx];}
        usort($ranked,fn($a,$b)=>$b['score']<=>$a['score'] ?: $a['idx']<=>$b['idx']);$picked=[];$len=0;
        foreach($ranked as $r){if($r['score']<=0&&$picked)continue;$t=$r['text'];if($len+mb_strlen($t)>$limit&&$picked)continue;$picked[]=$t;$len+=mb_strlen($t)+2;if($len>=$limit||count($picked)>=3)break;}
        if(!$picked)$picked=[trim($content)];$reply=implode("

",$picked);return mb_strlen($reply)>$limit?mb_substr($reply,0,$limit).'…':$reply;
    }


    private static function ensureLearningQueue(PDO $pdo): void
    {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS nivo_learning_queue (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id BIGINT UNSIGNED NOT NULL,
                conversation_id BIGINT UNSIGNED NULL,
                channel_type VARCHAR(40) NULL,
                question VARCHAR(1200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                normalized_question VARCHAR(1200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                suggested_answer TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
                source_hint VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
                occurrences INT UNSIGNED NOT NULL DEFAULT 1,
                status ENUM('pending','review','approved','rejected') NOT NULL DEFAULT 'pending',
                first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                reviewed_by BIGINT UNSIGNED NULL,
                reviewed_at DATETIME NULL,
                INDEX idx_nivo_learning_tenant_status(tenant_id,status,last_seen_at),
                INDEX idx_nivo_learning_conversation(tenant_id,conversation_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Throwable $ignore) {
        }
    }

    private static function captureLearningQuestion(PDO $pdo,int $tenantId,int $conversationId,string $channelType,string $question,?string $sourceHint=null): void
    {
        self::ensureLearningQueue($pdo);
        $question=trim($question);
        if($question==='')return;
        $normalized=mb_substr(self::norm($question),0,1200);
        try {
            $q=$pdo->prepare("SELECT id FROM nivo_learning_queue WHERE tenant_id=? AND normalized_question=? AND status IN ('pending','review') ORDER BY id DESC LIMIT 1");
            $q->execute([$tenantId,$normalized]);
            $id=(int)($q->fetchColumn()?:0);
            if($id){
                $pdo->prepare("UPDATE nivo_learning_queue SET occurrences=occurrences+1,last_seen_at=NOW(),conversation_id=?,channel_type=?,source_hint=COALESCE(?,source_hint) WHERE id=? AND tenant_id=?")
                    ->execute([$conversationId?:null,$channelType,$sourceHint,$id,$tenantId]);
            }else{
                $pdo->prepare("INSERT INTO nivo_learning_queue(tenant_id,conversation_id,channel_type,question,normalized_question,source_hint,status) VALUES(?,?,?,?,?,?,'pending')")
                    ->execute([$tenantId,$conversationId?:null,$channelType,mb_substr($question,0,1200),$normalized,$sourceHint]);
            }
        } catch (Throwable $ignore) {
        }
    }

    private static function contextualSearchText(PDO $pdo,int $tenantId,int $conversationId,string $message): string
    {
        if($conversationId<=0)return $message;
        try{
            $q=$pdo->prepare("SELECT body FROM messages WHERE tenant_id=? AND conversation_id=? AND direction='in' AND body IS NOT NULL ORDER BY id DESC LIMIT 6");
            $q->execute([$tenantId,$conversationId]);
            $history=array_reverse(array_values(array_filter(array_map('trim',array_column($q->fetchAll(PDO::FETCH_ASSOC),'body')))));
            if($history){
                $history[]=$message;
                return implode("\n",$history);
            }
        }catch(Throwable $ignore){}
        return $message;
    }

    private static function rankedKnowledge(PDO $pdo,int $tenantId,string $query,array $words,int $limit=3): array
    {
        try {
            $q=$pdo->prepare("SELECT id,name,source_type,source_ref,content,updated_at FROM knowledge_sources WHERE tenant_id=? AND status='ready' AND approval_status='approved' AND content IS NOT NULL ORDER BY updated_at DESC LIMIT 500");
            $q->execute([$tenantId]);
            $ranked=[];
            foreach($q->fetchAll() as $r){
                $score=self::knowledgeScore($query,(string)($r['name']??''),(string)($r['content']??''),$words);
                if($score<=0)continue;
                $ts=strtotime((string)($r['updated_at']??'')); if($ts && $ts>=time()-2592000)$score+=1;
                $r['_score']=$score;
                $ranked[]=$r;
            }
            usort($ranked,fn($a,$b)=>(int)$b['_score']<=>(int)$a['_score']);
            $out=[];$seen=[];
            foreach($ranked as $r){
                $fingerprint=sha1(self::norm(mb_substr((string)$r['content'],0,1200)));
                if(isset($seen[$fingerprint]))continue;
                $seen[$fingerprint]=true;
                $out[]=$r;
                if(count($out)>=max(1,min(5,$limit)))break;
            }
            return $out;
        } catch (Throwable $ignore) {
            // Una instalación heredada nunca debe dejar a NIVO mudo por una consulta de conocimiento.
            return [];
        }
    }


    private static function tenantSolutions(PDO $pdo, int $tenantId): array
    {
        try {
            $query = $pdo->prepare(
                "SELECT id,name,code,description FROM nivo_solutions WHERE tenant_id=? AND active=1 ORDER BY name"
            );
            $query->execute([$tenantId]);
            return $query->fetchAll() ?: [];
        } catch (Throwable $ignore) {
            return [];
        }
    }

    private static function entityIntentReply(
        PDO $pdo,
        int $tenantId,
        string $message,
        string $companyName,
        array $settings
    ): ?array {
        $norm = self::norm($message);
        $companyNorm = self::norm($companyName);
        $asksDefinition = (bool) preg_match(
            '/\b(que es|qué es|quien es|quién es|que hace|qué hace|para que sirve|para qué sirve|como funciona|cómo funciona|funciones|funcionalidades|servicios|soluciones|beneficios)\b/u',
            $norm
        );

        $solutions = self::tenantSolutions($pdo, $tenantId);
        $targetName = '';
        $targetDescription = '';

        foreach ($solutions as $solution) {
            $solutionName = trim((string) ($solution['name'] ?? ''));
            $solutionNorm = self::norm($solutionName);
            if ($solutionNorm !== '' && str_contains($norm, $solutionNorm)) {
                $targetName = $solutionName;
                $targetDescription = trim((string) ($solution['description'] ?? ''));
                break;
            }
        }

        $companyMentioned = $companyNorm !== '' && (
            str_contains($norm, $companyNorm)
            || ($companyNorm === 'es multiservicios' && str_contains($norm, 'multiservicios'))
        );

        if ($targetName === '' && !$companyMentioned) {
            foreach (['izzy', 'cami', 'zynko'] as $canonical) {
                if (preg_match('/\b' . preg_quote($canonical, '/') . '\b/u', $norm)) {
                    $targetName = strtoupper($canonical);
                    break;
                }
            }
        }

        if (!$asksDefinition && $targetName === '' && !$companyMentioned) {
            return null;
        }

        $searchQuery = trim($targetName !== '' ? $targetName . ' ' . $message : $companyName . ' ' . $message);
        $words = self::expandedWords($searchQuery);
        $ranked = self::rankedKnowledge($pdo, $tenantId, $searchQuery, $words, 3);

        if ($ranked) {
            $limit = max(260, min(1200, (int) ($settings['max_response_length'] ?? 700)));
            $best = $ranked[0];
            $excerpt = self::relevantExcerpt((string) ($best['content'] ?? ''), $words, $limit);
            if ($excerpt !== '') {
                $label = (string) ($best['name'] ?? 'Conocimiento aprobado');
                return [
                    'reply' => $excerpt,
                    'source' => 'knowledge-entity:' . $label,
                    'confidence' => (int) ($best['_score'] ?? 0) >= 7 ? 'high' : 'medium',
                    'sources' => [$label],
                ];
            }
        }

        if ($targetName !== '' && $targetDescription !== '') {
            return [
                'reply' => $targetDescription,
                'source' => 'solution:' . $targetName,
                'confidence' => 'high',
                'sources' => ['Solución: ' . $targetName],
            ];
        }

        if ($companyMentioned) {
            $solutionNames = array_values(array_filter(array_map(
                static fn(array $row): string => trim((string) ($row['name'] ?? '')),
                $solutions
            )));
            $reply = $companyName . ' es la empresa propietaria de este asistente y de las soluciones configuradas en este tenant.';
            if ($solutionNames) {
                $reply .= ' Entre las soluciones registradas están ' . implode(', ', $solutionNames) . '.';
            }
            $reply .= ' Puedo explicarte sus servicios, soluciones y funciones usando únicamente el conocimiento aprobado de esta empresa.';

            return [
                'reply' => $reply,
                'source' => 'tenant:identity',
                'confidence' => 'high',
                'sources' => [$companyName],
            ];
        }

        if ($companyNorm === 'es multiservicios') {
            $fallbacks = [
                'IZZY' => 'IZZY es una solución empresarial de ES MULTISERVICIOS orientada a facturación, inventario, POS, restaurantes y gestión administrativa.',
                'CAMI' => 'CAMI es una solución de ES MULTISERVICIOS orientada a clínicas y centros médicos, con herramientas para pacientes, procesos clínicos, farmacia y facturación.',
                'ZYNKO' => 'ZYNKO es la plataforma omnicanal de ES MULTISERVICIOS para centralizar conversaciones, NIVO Web Chat, NIVO IA, usuarios, asignaciones e integraciones.',
            ];
            if (isset($fallbacks[$targetName])) {
                return [
                    'reply' => $fallbacks[$targetName],
                    'source' => 'platform-catalog:' . strtolower($targetName),
                    'confidence' => 'high',
                    'sources' => [$targetName],
                ];
            }
        }

        return null;
    }

    private static function guaranteedPlatformReply(string $message,string $companyName,bool $english=false): ?string
    {
        $norm=self::norm($message);
        $companyNorm=self::norm($companyName);
        if($companyNorm!=='es multiservicios')return null;

        $asks=(bool)preg_match('/\b(que es|quien es|que hace|para que sirve|como funciona|funciones|funcionalidades|servicios|soluciones|beneficios|explicame|cuentame)\b/u',$norm);
        if(!$asks)return null;

        if(str_contains($norm,'izzy')){
            return $english
                ? 'IZZY is the business solution from ES MULTISERVICIOS for invoicing, inventory, POS, restaurants and administrative management. I can explain its functions and help you identify which modules fit your business.'
                : 'IZZY es la solución empresarial de ES MULTISERVICIOS para facturación, inventario, POS, restaurantes y gestión administrativa. Puedo explicarte sus funciones y ayudarte a identificar qué módulos encajan mejor en tu negocio.';
        }
        if(str_contains($norm,'cami')){
            return $english
                ? 'CAMI is the ES MULTISERVICIOS solution for clinics and medical centers, focused on patients, clinical processes, pharmacy and billing.'
                : 'CAMI es la solución de ES MULTISERVICIOS para clínicas y centros médicos, enfocada en pacientes, procesos clínicos, farmacia y facturación.';
        }
        if(str_contains($norm,'zynko')||str_contains($norm,'nivo web chat')||str_contains($norm,'nivo ia')){
            return $english
                ? 'ZYNKO is the omnichannel platform from ES MULTISERVICIOS. It centralizes customer conversations and includes NIVO Web Chat, NIVO AI, assignments, teams and integrations.'
                : 'ZYNKO es la plataforma omnicanal de ES MULTISERVICIOS. Centraliza conversaciones de clientes e integra NIVO Web Chat, NIVO IA, asignaciones, equipos e integraciones.';
        }
        if(str_contains($norm,'es multiservicios')||str_contains($norm,'multiservicios')){
            return $english
                ? 'ES MULTISERVICIOS develops software, websites, integrations and digital solutions for businesses. Its solutions include IZZY, CAMI and ZYNKO, and I can explain each one using the approved knowledge of this company.'
                : 'ES MULTISERVICIOS desarrolla software, sitios web, integraciones y soluciones digitales para empresas. Entre sus soluciones están IZZY, CAMI y ZYNKO, y puedo explicarte cada una usando el conocimiento aprobado de esta empresa.';
        }
        return null;
    }

    private static function consecutiveUnknownCount(PDO $pdo, int $tenantId, int $conversationId): int
    {
        if ($conversationId <= 0) {
            return 0;
        }

        try {
            $query = $pdo->prepare(
                "SELECT body FROM messages WHERE tenant_id=? AND conversation_id=? AND direction='out' AND sender_type='bot' ORDER BY id DESC LIMIT 12"
            );
            $query->execute([$tenantId, $conversationId]);
            $count = 0;
            foreach ($query->fetchAll() as $row) {
                $body = self::norm((string) ($row['body'] ?? ''));
                $isUnknown = str_contains($body, 'no tengo informacion')
                    || str_contains($body, 'todavia no tengo suficiente')
                    || str_contains($body, 'enough approved information');

                if (!$isUnknown) {
                    break;
                }
                $count++;
            }
            return $count;
        } catch (Throwable $ignore) {
            return 0;
        }
    }

    public static function evaluate(PDO $pdo,int $tenantId,int $conversationId,string $message,string $channelType,string $contactName,string $companyName): array
    {
        $result=['enabled'=>false,'reply'=>null,'handoff'=>false,'source'=>null,'sources'=>[],'confidence'=>'none','channel'=>$channelType,'reason'=>'inactive'];
        try{
            $q=$pdo->prepare('SELECT enabled,name,mode,fallback_message,handoff_rules_json,business_hours_json,knowledge_enabled,channel_policy_json FROM bot_profiles WHERE tenant_id=? LIMIT 1');
            $q->execute([$tenantId]);$bot=$q->fetch();
            if(!$bot||(int)$bot['enabled']!==1)return $result;
            $result['enabled']=true;
            $settings=json_decode($bot['handoff_rules_json']??'{}',true)?:[];
            $policy=json_decode($bot['channel_policy_json']??'{}',true)?:[];
            $allowed=$policy['channels']??['webchat','whatsapp','messenger','instagram','telegram','email','api'];
            if(!in_array($channelType,$allowed,true)){ $result['reason']='channel_disabled'; return $result; }

            $maxInput=max(200,min(10000,(int)($policy['max_input_chars']??3000)));
            if(mb_strlen($message)>$maxInput){
                $result['reason']='input_too_long';$result['handoff']=true;
                $result['reply']='Tu mensaje es muy extenso para procesarlo de forma segura en una sola consulta. Por favor resúmelo o permite que una persona continúe contigo.';
                return $result;
            }
            $hours=json_decode($bot['business_hours_json']??'{}',true)?:[];
            if(!empty($policy['business_hours_only'])&&!self::inBusinessHours($hours)){
                $result['reason']='outside_business_hours';$result['handoff']=true;
                $result['reply']=trim((string)($hours['outside_message']??''))?:'En este momento estamos fuera del horario de atención. Dejé tu conversación pendiente para que una persona continúe contigo.';
                return $result;
            }
            $blocked=array_values(array_filter(array_map([self::class,'norm'],preg_split('/[,\n]+/u',(string)($policy['blocked_keywords']??'')))));
            $normMessage=self::norm($message);
            foreach($blocked as $word){if($word!==''&&mb_strpos($normMessage,$word)!==false){$result['reason']='blocked_keyword';$result['handoff']=true;$result['reply']='Por seguridad no puedo procesar ese contenido automáticamente. Una persona puede continuar contigo.';return $result;}}

            if(!empty($policy['pause_when_assigned'])){
                $q=$pdo->prepare('SELECT assigned_user_id FROM conversations WHERE id=? AND tenant_id=? LIMIT 1');$q->execute([$conversationId,$tenantId]);
                if((int)($q->fetchColumn()?:0)>0){$result['reason']='human_assigned';return $result;}
            }

            $max=max(0,min(10000,(int)($policy['max_auto_replies']??0)));
            if($max>0){
                $q=$pdo->prepare("SELECT COUNT(*) FROM messages WHERE tenant_id=? AND conversation_id=? AND direction='out' AND sender_type='bot'");$q->execute([$tenantId,$conversationId]);
                if((int)$q->fetchColumn()>=$max){
                    $result['reason']='max_auto_replies';
                    $result['handoff']=true;
                    $result['reply']='Esta conversación alcanzó el límite operativo configurado. Puedes iniciar un nuevo chat o pedir que una persona continúe contigo.';
                    return $result;
                }
            }

            $cool=max(0,min(30,(int)($policy['cooldown_seconds']??1)));
            // El visitante nunca debe quedar sin respuesta por escribir apenas termina NIVO.
            // En Web Chat procesamos cada mensaje normalmente; el rate limit ya protege contra abuso.
            if($cool>0&&$channelType!=='webchat'){$q=$pdo->prepare("SELECT sent_at FROM messages WHERE tenant_id=? AND conversation_id=? AND direction='out' AND sender_type='bot' ORDER BY id DESC LIMIT 1");$q->execute([$tenantId,$conversationId]);$last=$q->fetchColumn();if($last&&time()-strtotime((string)$last)<$cool){$result['reason']='cooldown';return $result;}}

            $norm=self::norm($message);$displayName=trim($contactName);if($displayName===''||in_array(self::norm($displayName),['visitante','visitante web'],true))$displayName='';$personalized=!array_key_exists('personalized_greeting',$policy)||!empty($policy['personalized_greeting']);
            $english=!empty($policy['language_auto'])&&(bool)preg_match('/\b(hello|hi|what|how|where|when|help|please|thanks|thank you)\b/i',$message);
            $contextTopic=self::recentConversationTopic($pdo,$tenantId,$conversationId);
            $genericFollowUp=self::isGenericFollowUp($norm);
            $effectiveNorm=$norm;
            if($genericFollowUp&&$contextTopic!==''&&!str_contains($effectiveNorm,$contextTopic))$effectiveNorm=trim($effectiveNorm.' '.$contextTopic);
            $isGreeting=(bool)preg_match('/^(hola|buenas|buenos dias|buen dia|buenas tardes|buenas noches|hey|hello|hi)([!. ,].*)?$/u',$norm);
            $isCapabilities=(bool)preg_match('/\b(que sabes hacer|que puedes hacer|en que puedes ayudar|como me puedes ayudar|tus funciones|tus capacidades|para que sirves|en que te especializas|cual es tu especialidad|cuales son tus especialidades|que haces|que puedes responder|que temas manejas|que temas conoces|como funcionas|que puedes explicarme)\b/u',$norm);
            $isIdentity=(bool)preg_match('/\b(quien eres|quien sos|que eres|eres un bot|eres una ia|eres ia|como te llamas|cual es tu nombre|quien es nivo|que es nivo)\b/u',$norm);
            if($isIdentity){
                $reply=$english
                  ? 'I’m NIVO, the virtual assistant for '.$companyName.'. I can answer using approved information, guide you through services and solutions, and hand the conversation to a person when needed.'
                  : 'Soy NIVO, el asistente virtual de '.$companyName.'. Puedo responder usando información aprobada, orientarte sobre servicios y soluciones, y transferirte con una persona cuando sea necesario.';
                return self::finish($pdo,$tenantId,$conversationId,$policy,$result,$reply,'identity','high',false,$displayName,$english);
            }

            if($isCapabilities){
                $reply=$english
                  ? 'I specialize in helping with '.$companyName.' and ZYNKO using the information that has been approved for me. I can explain services, NIVO Web Chat, NIVO AI, plans, channels and integrations, answer common questions, guide you step by step and route your request. If something is outside my approved knowledge, I will say so; I only transfer you to a person when you ask for one or when the configured rules require it.'
                  : 'Me especializo en orientarte sobre '.$companyName.' y ZYNKO usando la información que tengo aprobada. Puedo explicarte servicios, NIVO Web Chat, NIVO IA, planes, canales e integraciones, responder preguntas frecuentes, guiarte paso a paso y ayudarte a encaminar tu solicitud. Si algo está fuera de mi conocimiento aprobado, te lo diré; solo te transfiero con una persona cuando lo pides o cuando las reglas configuradas realmente lo requieren.';
                return self::finish($pdo,$tenantId,$conversationId,$policy,$result,$reply,'capabilities','high',false,$displayName,$english);
            }

            if($isGreeting){
                $q=$pdo->prepare("SELECT COUNT(*) FROM messages WHERE tenant_id=? AND conversation_id=? AND direction='in'");
                $q->execute([$tenantId,$conversationId]);
                $inboundCount=(int)$q->fetchColumn();
                if($inboundCount<=1){
                    if($english)$reply='Hello'.($personalized&&$displayName!==''?', '.$displayName:'').'! 👋 I’m NIVO, the virtual assistant for '.$companyName.'. How can I help you today?';
                    else $reply='¡Hola'.($personalized&&$displayName!==''?', '.$displayName:'').'! 👋 Soy NIVO, el asistente virtual de '.$companyName.'. ¿En qué puedo ayudarte hoy?';
                }else{
                    $reply=$english?'Hello again. What would you like to continue with?':'¡Hola de nuevo! ¿Qué parte quieres que sigamos revisando?';
                }
                return self::finish($pdo,$tenantId,$conversationId,$policy,$result,$reply,'greeting','high',false,$displayName,$english);
            }

            $handoffWords=array_values(array_filter(array_map([self::class,'norm'],explode(',',(string)($settings['handoff_keywords']??'agente, asesor, persona, humano, representante')))));
            foreach($handoffWords as $kw){if($kw!==''&&mb_strpos($effectiveNorm,$kw)!==false){
                $hours=json_decode($bot['business_hours_json']??'{}',true)?:[];$inHours=self::inBusinessHours($hours);
                $reply=$english?'Of course. I’ll hand this conversation over to a person from '.$companyName.'.':($inHours?'Claro. Te transfiero con una persona de '.$companyName.' para que continúe contigo.':($hours['outside_message']??'En este momento estamos fuera del horario de atención. Dejé tu conversación pendiente para que una persona continúe contigo.'));
                return self::finish($pdo,$tenantId,$conversationId,$policy,$result,$reply,'handoff','high',true,$displayName,$english);
            }}

            $rq=$pdo->prepare('SELECT name,keywords,response FROM nivo_rules WHERE tenant_id=? AND active=1 ORDER BY priority,id');$rq->execute([$tenantId]);
            foreach($rq->fetchAll() as $r){foreach(array_filter(array_map([self::class,'norm'],explode(',',(string)$r['keywords']))) as $kw){if($kw!==''&&mb_strpos($effectiveNorm,$kw)!==false)return self::finish($pdo,$tenantId,$conversationId,$policy,$result,(string)$r['response'],'rule:'.($r['name']??''),'high',false,$displayName,$english);}}

            $entityReply = self::entityIntentReply($pdo, $tenantId, $effectiveNorm, $companyName, $settings);
            if ($entityReply !== null) {
                $result['sources'] = $entityReply['sources'] ?? [];
                return self::finish(
                    $pdo,
                    $tenantId,
                    $conversationId,
                    $policy,
                    $result,
                    (string) $entityReply['reply'],
                    (string) $entityReply['source'],
                    (string) $entityReply['confidence'],
                    false,
                    $displayName,
                    $english
                );
            }

            $companyNorm=self::norm($companyName);
            $guaranteed=self::guaranteedPlatformReply($effectiveNorm,$companyName,$english);
            if($guaranteed!==null){
                return self::finish($pdo,$tenantId,$conversationId,$policy,$result,$guaranteed,'platform:guaranteed','high',false,$displayName,$english);
            }
            if($companyNorm==='es multiservicios'&&$genericFollowUp&&$contextTopic==='izzy'){
                $reply='Claro. IZZY puede ayudarte con facturación y documentos de venta, control de inventario y existencias, POS para ventas rápidas, operación de restaurantes y mesas/comandas cuando aplica, cuentas por cobrar y pagar, y gestión administrativa desde un solo sistema. Para saber si encaja en tu negocio, dime qué tipo de empresa tienes y cómo llevas hoy ventas, inventario o facturación; con eso te indico qué módulos te servirían más.';
                return self::finish($pdo,$tenantId,$conversationId,$policy,$result,$reply,'context:izzy:functions','high',false,$displayName,$english);
            }
            if($companyNorm==='es multiservicios'&&$genericFollowUp&&$contextTopic==='cami'){
                $reply='Claro. CAMI está orientado a clínicas y centros médicos: organiza pacientes y expedientes, procesos clínicos, farmacia, facturación y seguimiento administrativo. Si me dices qué tipo de clínica manejas y qué proceso deseas mejorar, puedo orientarte sobre las funciones que más te convienen.';
                return self::finish($pdo,$tenantId,$conversationId,$policy,$result,$reply,'context:cami:functions','high',false,$displayName,$english);
            }
            if($companyNorm==='es multiservicios'&&$genericFollowUp&&in_array($contextTopic,['zynko','nivo web chat','nivo ia'],true)){
                $reply='Claro. ZYNKO reúne conversaciones en una bandeja, permite trabajar con NIVO Web Chat y NIVO IA, administrar usuarios y asignaciones, conectar canales e integraciones y mantener trazabilidad de la atención. Si me dices qué canal o proceso quieres mejorar, te explico el flujo exacto.';
                return self::finish($pdo,$tenantId,$conversationId,$policy,$result,$reply,'context:zynko:functions','high',false,$displayName,$english);
            }

            {
                $searchText=self::contextualSearchText($pdo,$tenantId,$conversationId,$message);
                $words=self::expandedWords($searchText);
                $ranked=self::rankedKnowledge($pdo,$tenantId,$searchText,$words,3);
                $min=$settings['min_confidence']??'medium';$required=$min==='high'?7:($min==='low'?2:4);
                $best=$ranked[0]??null;$score=(int)($best['_score']??0);
                if($best&&$score>=$required){
                    $limit=max(180,min(1500,(int)($settings['max_response_length']??700)));
                    $chunks=[];$sources=[];$used=0;
                    foreach($ranked as $r){
                        if((int)($r['_score']??0)<max(2,$required-1))continue;
                        $chunk=self::relevantExcerpt((string)$r['content'],$words,max(180,(int)floor($limit/max(1,min(3,count($ranked))))));
                        if($chunk===''||$used+mb_strlen($chunk)>$limit)continue;
                        $chunks[]=$chunk;$used+=mb_strlen($chunk)+2;
                        $label=(string)($r['name']??'Fuente');
                        if(($r['source_type']??'')==='url'&&str_contains((string)($r['source_ref']??''),'|')){
                            $parts=explode('|',(string)$r['source_ref'],2);$label.=' · '.($parts[1]??'');
                        }
                        $sources[]=$label;
                    }
                    $reply=implode("

",$chunks?:[self::relevantExcerpt((string)$best['content'],$words,$limit)]);
                    $tone=$settings['tone']??'professional';if($tone==='friendly')$reply='Con gusto. '.$reply;elseif($tone==='concise'&&mb_strlen($reply)>420)$reply=mb_substr($reply,0,420).'…';
                    $result['sources']=$sources;
                    $source='knowledge:'.implode(' | ',array_slice($sources,0,3));
                    return self::finish($pdo,$tenantId,$conversationId,$policy,$result,$reply,$source,$score>=9?'high':'medium',false,$displayName,$english);
                }
            }

            // Respuestas base comerciales: solo se usan cuando el conocimiento aprobado del tenant no resolvió.
            // Así las fuentes web y la base de conocimiento siempre tienen prioridad y estos textos evitan silencios mientras una fuente aún no existe.
            if($companyNorm==='es multiservicios'&&(
                str_contains($norm,'soluciones')||str_contains($norm,'servicios')||str_contains($norm,'que ofrecen')||str_contains($norm,'que tiene es multiservicios')
            )&&!str_contains($norm,'izzy')&&!str_contains($norm,'cami')&&!str_contains($norm,'zynko')){
                $reply='ES MULTISERVICIOS ofrece tres soluciones principales: IZZY para facturación, inventario, POS, restaurantes y gestión empresarial; CAMI para clínicas, pacientes, farmacia y facturación; y ZYNKO para reunir en una sola bandeja las conversaciones que llegan desde distintos canales, además de NIVO Web Chat, NIVO IA e integraciones. Si me dices qué tipo de negocio tienes, puedo ayudarte a identificar cuál encaja mejor.';
                return self::finish($pdo,$tenantId,$conversationId,$policy,$result,$reply,'company:solutions:fallback','high',false,$displayName,$english);
            }
            if($companyNorm==='es multiservicios'&&str_contains($norm,'izzy')){
                $reply='IZZY es la solución empresarial de ES MULTISERVICIOS para facturación, inventario, POS, restaurantes y gestión administrativa. Puede ayudarte a controlar ventas, productos y existencias, operar puntos de venta y centralizar tareas administrativas. Si me dices qué tipo de negocio tienes y qué proceso quieres mejorar, puedo orientarte con más precisión.';
                return self::finish($pdo,$tenantId,$conversationId,$policy,$result,$reply,'product:izzy:fallback','high',false,$displayName,$english);
            }
            if($companyNorm==='es multiservicios'&&str_contains($norm,'cami')){
                $reply='CAMI es la solución de ES MULTISERVICIOS orientada a clínicas y centros médicos. Ayuda a organizar pacientes, procesos clínicos, farmacia y facturación. Si me cuentas qué tipo de clínica o servicio manejas, puedo orientarte sobre las áreas que mejor se ajustan a tu operación.';
                return self::finish($pdo,$tenantId,$conversationId,$policy,$result,$reply,'product:cami:fallback','high',false,$displayName,$english);
            }
            if($companyNorm==='es multiservicios'&&(str_contains($norm,'zynko')||str_contains($norm,'nivo web chat'))){
                $reply='ZYNKO centraliza conversaciones de atención en una sola bandeja, incorpora NIVO Web Chat, NIVO IA, equipos, asignaciones e integraciones, y puede conectar canales externos cuando estén habilitados. Si me cuentas cómo atiendes hoy a tus clientes, puedo orientarte sobre el flujo que mejor encaja.';
                return self::finish($pdo,$tenantId,$conversationId,$policy,$result,$reply,'product:zynko:fallback','high',false,$displayName,$english);
            }

            // Segunda fase opcional: NIVO local siempre intenta primero. OpenAI solo entra como fallback cuando está conectado, habilitado y permitido por el plan/tenant/canal.
            if(($bot['mode']??'hybrid')==='hybrid')try{
                $external=(new OpenAIProviderService($pdo,dirname(__DIR__,2)))->fallback($tenantId,$conversationId,$channelType,$message,$contactName,$companyName);
                if($external&&trim((string)($external['reply']??''))!=='')return self::finish($pdo,$tenantId,$conversationId,$policy,$result,(string)$external['reply'],(string)($external['source']??'openai'),(string)($external['confidence']??'ai'),false,$displayName,$english);
            }catch(Throwable $ignore){}

            self::captureLearningQuestion($pdo,$tenantId,$conversationId,$channelType,$message,'low-confidence');
            $fallback=trim((string)($bot['fallback_message']??''));
            if($fallback==='')$fallback=$english
                ? 'I do not have enough approved information yet. Could you give me one more detail about what you need? I will use it to search the approved knowledge again.'
                : 'Todavía no tengo suficiente información aprobada para responder con seguridad. ¿Puedes darme un detalle más de lo que necesitas? Lo usaré para buscar mejor en el conocimiento autorizado.';
            if(!empty($policy['identity_enabled'])&&!str_contains(self::norm($fallback),'nivo')){
                $q=$pdo->prepare("SELECT COUNT(*) FROM messages WHERE tenant_id=? AND conversation_id=? AND direction='out' AND sender_type='bot'");
                $q->execute([$tenantId,$conversationId]);
                if((int)$q->fetchColumn()===0)$fallback=($english?'I’m NIVO, the virtual assistant for '.$companyName.'. ':'Soy NIVO, el asistente virtual de '.$companyName.'. ').$fallback;
            }
            $configuredUnknownBefore = (int) ($policy['unknown_before_handoff'] ?? 3);
            $unknownBefore = max(3, min(10, $configuredUnknownBefore));
            $consecutiveUnknown = self::consecutiveUnknownCount($pdo, $tenantId, $conversationId);
            $handoff = (!array_key_exists('auto_handoff', $settings) || !empty($settings['auto_handoff']))
                && ($consecutiveUnknown + 1 >= $unknownBefore);

            if ($handoff) {
                $fallback .= $english
                    ? ' I have reached the configured number of unresolved attempts, so I will also notify a human agent. You can keep writing here.'
                    : ' Ya agoté los intentos configurados sin una respuesta segura, así que también avisaré a una persona. Puedes seguir escribiendo aquí.';
            }

            return self::finish($pdo,$tenantId,$conversationId,$policy,$result,$fallback,'fallback','low',$handoff,$displayName,$english);
        }catch(Throwable $e){
            $result['enabled']=true;
            $result['reason']='engine_error';
            $english=(bool)preg_match('/\b(hello|hi|what|how|where|when|help|please|thanks)\b/i',$message);
            $recovery=self::guaranteedPlatformReply($message,$companyName,$english);
            $result['reply']=$recovery ?: ($english
                ? 'I received your message, but I had a temporary problem consulting the approved knowledge. Please try the question once more; I will keep the conversation active.'
                : 'Recibí tu mensaje, pero tuve un problema temporal al consultar el conocimiento aprobado. Intenta la pregunta una vez más; mantendré la conversación activa.');
            $result['source']=$recovery?'platform:recovery':'engine:recovery';
            $result['confidence']=$recovery?'high':'low';
            $result['handoff']=false;
            return $result;
        }
    }

    private static function finish(PDO $pdo,int $tenantId,int $conversationId,array $policy,array $result,string $reply,string $source,string $confidence,bool $handoff,string $contactName='',bool $english=false): array
    {
        $personalized=!array_key_exists('personalized_greeting',$policy)||!empty($policy['personalized_greeting']);
        $cleanName=trim($contactName);
        if($cleanName!==''&&in_array(self::norm($cleanName),['visitante','visitante web'],true))$cleanName='';
        if($personalized&&$cleanName!==''&&!in_array($source,['greeting','handoff'],true)){
            $replyNorm=self::norm($reply);
            $first=trim(preg_split('/\s+/u',$cleanName)[0]??'');
            if($first!==''&&!str_contains($replyNorm,self::norm($first)))$reply=$cleanName.', '.$reply;
        }
        if(!empty($policy['duplicate_guard'])){$q=$pdo->prepare("SELECT body FROM messages WHERE tenant_id=? AND conversation_id=? AND direction='out' AND sender_type='bot' ORDER BY id DESC LIMIT 1");$q->execute([$tenantId,$conversationId]);$last=trim((string)($q->fetchColumn()?:''));if($last!==''&&self::norm($last)===self::norm($reply)){$result['reason']='duplicate_guard';return $result;}}
        if(!empty($policy['safe_links_only'])){$reply=preg_replace('/(?:javascript|data):\s*[^\s]+/iu','[enlace bloqueado]',$reply)??$reply;}
        if(!empty($policy['sensitive_data_guard'])){$reply=preg_replace('/\b(?:\d[ -]*?){13,19}\b/u','[dato protegido]',$reply)??$reply;}
        $result['reply']=$reply;$result['source']=$source;$result['confidence']=$confidence;$result['handoff']=$handoff;$result['reason']='reply';return $result;
    }

    private static function inBusinessHours(array $hours): bool
    {
        if(empty($hours['enabled']))return true;$now=new DateTimeImmutable('now');$day=(int)$now->format('N');$hm=$now->format('H:i');$days=array_map('intval',$hours['days']??[]);$ok=in_array($day,$days,true)&&$hm>=($hours['start']??'08:00')&&$hm<=($hours['end']??'17:00');$today=$now->format('Y-m-d');foreach(($hours['special_dates']??[]) as $special){if(($special['date']??'')!==$today)continue;if(($special['type']??'closed')==='closed')return false;$ss=$special['start']??'';$se=$special['end']??'';return $ss!==''&&$se!==''&&$hm>=$ss&&$hm<=$se;}return $ok;
    }
}
