<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/Support/Plan.php';
require_once __DIR__.'/OpenAIProviderService.php';
require_once __DIR__.'/NivoConversationIntelligence.php';

final class NivoEngine
{
    private static function norm(string $s): string
    {
        $s=mb_strtolower(trim($s),'UTF-8');
        $s=strtr($s,['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
        return preg_replace('/\s+/u',' ',$s) ?: '';
    }


    /** Normalización tolerante a abreviaturas y errores frecuentes sin alterar el texto original. */
    private static function intentNorm(string $s): string
    {
        $n=self::norm($s);
        $n=preg_replace('/\bpara\s+q\b/u','para que',$n)??$n;
        $n=preg_replace('/\bq\s+es\b/u','que es',$n)??$n;
        $n=preg_replace('/\bq\s+(?:ase|hase|ace)\b/u','que hace',$n)??$n;
        $aliases=[
            '/\b(?:watsap|whatsap|whasap|wasap|guasap|watsapp)\b/u'=>'whatsapp',
            '/\b(?:imventario|inbentario|inventaryo|inventaro)\b/u'=>'inventario',
            '/\b(?:restauramte|restorante|restaurantee)\b/u'=>'restaurante',
            '/\b(?:facturasion|faturacion)\b/u'=>'facturacion',
        ];
        foreach($aliases as $pattern=>$replacement)$n=preg_replace($pattern,$replacement,$n)??$n;
        return trim($n);
    }


    /** Respuestas directas para intenciones comerciales/operativas frecuentes. */
    /** Evita transferencias por mencionar palabras como “persona” dentro de una pregunta informativa. */
    private static function explicitHandoffRequested(string $message,array $keywords): bool
    {
        $n=self::intentNorm($message);
        if(preg_match('/\b(quiero|necesito|deseo|puedes|podrias|quiero que|necesito que)\s+(hablar|comunicarme|contactar|pasar|transferir|conectar)\w*\s+(con\s+)?(una\s+)?(persona|humano|agente|asesor|representante)\b/u',$n))return true;
        if(preg_match('/\b(pasame|transfiereme|conectame|comunícame|comunicarme)\s+(con\s+)?(una\s+)?(persona|humano|agente|asesor|representante)\b/u',$n))return true;
        if(preg_match('/\b(hablar con un humano|hablar con una persona|hablar con un agente|atencion humana|asesor humano)\b/u',$n))return true;
        // Si el mensaje es muy corto y consiste prácticamente en la palabra de handoff, también cuenta.
        foreach($keywords as $kw){if($kw!==''&&preg_match('/^(quiero\s+)?'.preg_quote($kw,'/').'$/u',$n))return true;}
        return false;
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
        // Recuperación léxica neutral: no expande hacia productos de un tenant específico.
        return self::words($s);
    }


    private static function recentConversationTopic(PDO $pdo,int $tenantId,int $conversationId): string
    {
        if($conversationId<=0)return '';
        try{
            $solutions=self::tenantSolutions($pdo,$tenantId);
            $topics=[];foreach($solutions as $solution){$name=trim((string)($solution['name']??''));if($name!=='')$topics[self::norm($name)]=$name;}
            // Los módulos propios de la plataforma pueden dar continuidad sin depender de productos del tenant.
            $topics['nivo web chat']='NIVO Web Chat';$topics['nivo ia']='NIVO IA';
            $q=$pdo->prepare("SELECT body FROM messages WHERE tenant_id=? AND conversation_id=? AND body IS NOT NULL ORDER BY id DESC LIMIT 12");
            $q->execute([$tenantId,$conversationId]);
            foreach($q->fetchAll() as $row){$body=self::norm((string)($row['body']??''));foreach($topics as $needle=>$label){if($needle!==''&&str_contains($body,$needle))return self::norm($label);}}
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
        $norm = self::intentNorm($message);
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

        $companyMentioned = $companyNorm !== '' && str_contains($norm, $companyNorm);


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

            $norm=(!array_key_exists('typo_tolerance',$policy)||!empty($policy['typo_tolerance']))?self::intentNorm($message):self::norm($message);$displayName=trim($contactName);if($displayName===''||in_array(self::norm($displayName),['visitante','visitante web'],true))$displayName='';$personalized=!array_key_exists('personalized_greeting',$policy)||!empty($policy['personalized_greeting']);
            $english=!empty($policy['language_auto'])&&(bool)preg_match('/\b(hello|hi|what|how|where|when|help|please|thanks|thank you)\b/i',$message);
            $contextTopic=(!array_key_exists('topic_continuity',$policy)||!empty($policy['topic_continuity']))?self::recentConversationTopic($pdo,$tenantId,$conversationId):'';
            $genericFollowUp=self::isGenericFollowUp($norm);
            $effectiveNorm=$norm;
            if($genericFollowUp&&$contextTopic!==''&&!str_contains($effectiveNorm,$contextTopic))$effectiveNorm=trim($effectiveNorm.' '.$contextTopic);
            // V2.31.130: las respuestas comerciales ya no dependen de productos hardcodeados.
            // El orquestador multiempresa se ejecuta después de reglas explícitas del tenant.
            $isGreeting=(bool)preg_match('/^(hola|buenas|buenos dias|buen dia|buenas tardes|buenas noches|hey|hello|hi)[!., ]*$/u',$norm);
            $isCapabilities=(bool)preg_match('/\b(que sabes hacer|que puedes hacer|en que puedes ayudar|como me puedes ayudar|tus funciones|tus capacidades|para que sirves|en que te especializas|cual es tu especialidad|cuales son tus especialidades|que haces|que puedes responder|que temas manejas|que temas conoces|como funcionas|que puedes explicarme)\b/u',$norm);
            $isIdentity=(bool)preg_match('/\b(quien eres|quien sos|que eres|eres un bot|eres una ia|eres ia|como te llamas|cual es tu nombre|quien es nivo|que es nivo)\b/u',$norm);
            if($isIdentity){
                $reply=$english
                  ? 'I’m NIVO, the virtual assistant for '.$companyName.'. I can answer using approved information, guide you through services and solutions, and hand the conversation to a person when needed.'
                  : 'Soy NIVO, el asistente virtual de '.$companyName.'. Puedo responder usando información aprobada, orientarte sobre servicios y soluciones, y transferirte con una persona cuando sea necesario.';
                return self::finish($pdo,$tenantId,$conversationId,$policy,$result,$reply,'identity','high',false,$displayName,$english);
            }

            if($isCapabilities){
                $solutions=self::tenantSolutions($pdo,$tenantId);
                $solutionNames=array_values(array_filter(array_map(
                    static fn(array $row): string => trim((string)($row['name']??'')),
                    $solutions
                )));
                $family=$solutionNames?implode(', ',$solutionNames):'los productos y servicios configurados';
                $reply=$english
                  ? 'I can help with '.$companyName.' and the solutions configured for this company ('.$family.'). I can explain products and services, NIVO Web Chat, NIVO AI, plans, channels and integrations, answer common questions, guide you step by step and use approved web sources and knowledge from this tenant. If something is outside my approved knowledge, I will say so; I only transfer you to a person when you ask for one or when the configured rules require it.'
                  : 'Puedo orientarte sobre '.$companyName.' y las soluciones configuradas para esta empresa ('.$family.'). Puedo explicarte productos y servicios, NIVO Web Chat, NIVO IA, planes, canales e integraciones, responder preguntas frecuentes, guiarte paso a paso y utilizar las fuentes web y el conocimiento aprobado de este tenant. Si algo está fuera de mi conocimiento aprobado, te lo diré; solo te transfiero con una persona cuando lo pides o cuando las reglas configuradas realmente lo requieren.';
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
            if(self::explicitHandoffRequested($message,$handoffWords)){
                $hours=json_decode($bot['business_hours_json']??'{}',true)?:[];$inHours=self::inBusinessHours($hours);
                $reply=$english?'Of course. I’ll hand this conversation over to a person from '.$companyName.'.':($inHours?'Claro. Te transfiero con una persona de '.$companyName.' para que continúe contigo.':($hours['outside_message']??'En este momento estamos fuera del horario de atención. Dejé tu conversación pendiente para que una persona continúe contigo.'));
                return self::finish($pdo,$tenantId,$conversationId,$policy,$result,$reply,'handoff','high',true,$displayName,$english);
            }

            $mentionsWebChat=str_contains($effectiveNorm,'nivo web chat')||str_contains($effectiveNorm,'web chat');
            $mentionsNivoAi=str_contains($effectiveNorm,'nivo ia')||str_contains($effectiveNorm,'nivo ai');
            if($mentionsWebChat&&$mentionsNivoAi){
                $reply=$english
                    ? 'NIVO Web Chat is the web channel that receives visitor messages, preserves the full conversation and synchronizes it with the omnichannel inbox. NIVO AI works inside that same conversation using approved rules, synchronized web sources and tenant knowledge to answer in real time, learn through supervised review and hand off to a human when needed.'
                    : 'NIVO Web Chat es el canal web que recibe los mensajes del visitante, conserva la conversación completa y la sincroniza con la Bandeja omnicanal. NIVO IA trabaja dentro de esa misma conversación usando reglas aprobadas, fuentes web sincronizadas y conocimiento del tenant para responder en tiempo real, aprender mediante revisión supervisada y transferir a una persona cuando se necesita.';
                return self::finish($pdo,$tenantId,$conversationId,$policy,$result,$reply,'platform:nivo-combined','high',false,$displayName,$english);
            }

            $rq=$pdo->prepare('SELECT name,keywords,response FROM nivo_rules WHERE tenant_id=? AND active=1 ORDER BY priority,id');$rq->execute([$tenantId]);
            foreach($rq->fetchAll() as $r){foreach(array_filter(array_map([self::class,'norm'],explode(',',(string)$r['keywords']))) as $kw){if($kw!==''&&mb_strpos($effectiveNorm,$kw)!==false)return self::finish($pdo,$tenantId,$conversationId,$policy,$result,(string)$r['response'],'rule:'.($r['name']??''),'high',false,$displayName,$english);}}

            // Orquestador conversacional general: memoria + RAG semántico + razonamiento por tenant.
            if(($bot['mode']??'hybrid')==='hybrid')try{
                $brain=(new NivoConversationIntelligence($pdo,dirname(__DIR__,2)))->turn($tenantId,$conversationId,$channelType,$message,$contactName,$companyName);
                if($brain&&trim((string)($brain['reply']??''))!==''){
                    $handoff=!empty($brain['handoff']);
                    $confidence=(string)($brain['confidence']??'ai');
                    $result['sources']=[];
                    return self::finish($pdo,$tenantId,$conversationId,$policy,$result,(string)$brain['reply'],(string)($brain['_source']??'nivo:conversation-orchestrator'),$confidence,$handoff,$displayName,$english);
                }
            }catch(Throwable $ignore){}

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

            // Si el orquestador externo no está disponible, continuamos con conocimiento local del tenant.

            {
                $searchText=self::contextualSearchText($pdo,$tenantId,$conversationId,$message);
                $words=self::expandedWords($searchText);
                $ranked=self::rankedKnowledge($pdo,$tenantId,$searchText,$words,3);
                $min=$settings['min_confidence']??'medium';$required=$min==='high'?7:($min==='low'?2:4);if(!array_key_exists('strict_source_relevance',$policy)||!empty($policy['strict_source_relevance']))$required=max($required,4);
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

            // Sin respuestas comerciales hardcodeadas: el conocimiento del tenant es la fuente de verdad.
            // El modo híbrido ya fue atendido por el orquestador conversacional al inicio del flujo.

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
            $handoff = !empty($settings['auto_handoff'])
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
            $result['reply']=$english
                ? 'I received your message, but I had a temporary problem consulting the approved knowledge. Please rephrase it or give me one more detail; I will keep the conversation active.'
                : 'Recibí tu mensaje, pero tuve un problema temporal al consultar el conocimiento aprobado. Puedes reformularlo o darme un detalle más; mantendré la conversación activa.';
            $result['source']='engine:recovery';
            $result['confidence']='low';
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
        if(!empty($policy['direct_answer_mode']))$reply=preg_replace('/^(Con gusto|Claro|Por supuesto)\.\s*/u','',$reply)??$reply;
        if(!empty($policy['avoid_repeat_intro'])&&$conversationId>0){try{$q=$pdo->prepare("SELECT body FROM messages WHERE tenant_id=? AND conversation_id=? AND direction='out' AND sender_type='bot' ORDER BY id DESC LIMIT 1");$q->execute([$tenantId,$conversationId]);$last=trim((string)($q->fetchColumn()?:''));$firstCurrent=trim((string)(preg_split('/(?<=[.!?])\s+/u',$reply,2)[0]??''));$firstLast=trim((string)(preg_split('/(?<=[.!?])\s+/u',$last,2)[0]??''));if($firstCurrent!==''&&$firstLast!==''&&self::norm($firstCurrent)===self::norm($firstLast)){$reply=trim(mb_substr($reply,mb_strlen($firstCurrent)));}}catch(Throwable $ignore){}}
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
