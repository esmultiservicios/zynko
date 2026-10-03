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

    public static function evaluate(PDO $pdo,int $tenantId,int $conversationId,string $message,string $channelType,string $contactName,string $companyName): array
    {
        $result=['enabled'=>false,'reply'=>null,'handoff'=>false,'source'=>null,'confidence'=>'none','channel'=>$channelType,'reason'=>'inactive'];
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

            // V2.31.81 · Las consultas comerciales principales nunca deben quedar en silencio.
            // Se resuelven antes de los límites de respuestas automáticas para mantener una conversación natural.
            $companyNorm=self::norm($companyName);
            $displayNameEarly=trim($contactName);
            if($displayNameEarly===''||in_array(self::norm($displayNameEarly),['visitante','visitante web'],true))$displayNameEarly='';
            $englishEarly=!empty($policy['language_auto'])&&(bool)preg_match('/\b(hello|hi|what|how|where|when|help|please|thanks|thank you)\b/i',$message);
            if(str_contains($companyNorm,'es multiservicios')&&str_contains($normMessage,'izzy')){
                $reply='IZZY puede ayudarte a manejar facturación, inventario, productos, ventas, POS, restaurantes y tareas administrativas desde una misma solución. Para saber si encaja en tu negocio, dime qué tipo de empresa tienes, cuántas personas o puntos de venta manejas y qué proceso quieres mejorar; con eso puedo orientarte de forma concreta sobre los módulos que te convienen.';
                return self::finish($pdo,$tenantId,$conversationId,$policy,$result,$reply,'product:izzy:guided','high',false,$displayNameEarly,$englishEarly);
            }
            if(str_contains($companyNorm,'es multiservicios')&&str_contains($normMessage,'cami')){
                $reply='CAMI está pensado para clínicas y centros médicos. Ayuda a organizar pacientes, procesos clínicos, farmacia y facturación. Si me dices qué tipo de clínica manejas y qué proceso deseas ordenar, puedo indicarte cómo CAMI puede adaptarse a tu operación.';
                return self::finish($pdo,$tenantId,$conversationId,$policy,$result,$reply,'product:cami:guided','high',false,$displayNameEarly,$englishEarly);
            }
            if(str_contains($companyNorm,'es multiservicios')&&(str_contains($normMessage,'zynko')||str_contains($normMessage,'nivo web chat'))){
                $reply='ZYNKO centraliza las conversaciones de atención al cliente en una sola bandeja para que tu equipo pueda responder, asignar, dar seguimiento y mantener el historial ordenado. Incluye NIVO Web Chat, NIVO IA e integraciones; y puede incorporar canales externos cuando estén habilitados. Si me cuentas cómo atiendes hoy a tus clientes, puedo orientarte sobre cómo usarlo.';
                return self::finish($pdo,$tenantId,$conversationId,$policy,$result,$reply,'product:zynko:guided','high',false,$displayNameEarly,$englishEarly);
            }

            if(!empty($policy['pause_when_assigned'])){
                $q=$pdo->prepare('SELECT assigned_user_id FROM conversations WHERE id=? AND tenant_id=? LIMIT 1');$q->execute([$conversationId,$tenantId]);
                if((int)($q->fetchColumn()?:0)>0){$result['reason']='human_assigned';return $result;}
            }

            $max=max(1,min(100,(int)($policy['max_auto_replies']??25)));
            $q=$pdo->prepare("SELECT COUNT(*) FROM messages WHERE tenant_id=? AND conversation_id=? AND direction='out' AND sender_type='bot'");$q->execute([$tenantId,$conversationId]);
            if((int)$q->fetchColumn()>=$max){
                $result['reason']='max_auto_replies';
                $result['handoff']=true;
                $result['reply']='He llegado al límite de respuestas automáticas configurado para esta conversación. Puedes finalizar este chat e iniciar uno nuevo, o solicitar que una persona continúe contigo.';
                return $result;
            }

            $cool=max(0,min(30,(int)($policy['cooldown_seconds']??1)));
            // El visitante nunca debe quedar sin respuesta por escribir apenas termina NIVO.
            // En Web Chat procesamos cada mensaje normalmente; el rate limit ya protege contra abuso.
            if($cool>0&&$channelType!=='webchat'){$q=$pdo->prepare("SELECT sent_at FROM messages WHERE tenant_id=? AND conversation_id=? AND direction='out' AND sender_type='bot' ORDER BY id DESC LIMIT 1");$q->execute([$tenantId,$conversationId]);$last=$q->fetchColumn();if($last&&time()-strtotime((string)$last)<$cool){$result['reason']='cooldown';return $result;}}

            $norm=self::norm($message);$displayName=trim($contactName);if($displayName===''||in_array(self::norm($displayName),['visitante','visitante web'],true))$displayName='';$personalized=!array_key_exists('personalized_greeting',$policy)||!empty($policy['personalized_greeting']);
            $english=!empty($policy['language_auto'])&&(bool)preg_match('/\b(hello|hi|what|how|where|when|help|please|thanks|thank you)\b/i',$message);
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

            // V2.31.79 · Lenguaje comercial claro para la empresa principal.
            if(self::norm($companyName)==='es multiservicios'&&(
                str_contains($norm,'soluciones')||str_contains($norm,'servicios')||str_contains($norm,'que ofrecen')||str_contains($norm,'que tiene es multiservicios')
            )&&!str_contains($norm,'izzy')&&!str_contains($norm,'cami')&&!str_contains($norm,'zynko')){
                $reply='ES MULTISERVICIOS ofrece tres soluciones principales: IZZY para facturación, inventario, POS, restaurantes y gestión empresarial; CAMI para clínicas, pacientes, farmacia y facturación; y ZYNKO para reunir en una sola bandeja las conversaciones que llegan desde distintos canales, además de NIVO Web Chat, NIVO IA e integraciones. Si me dices qué tipo de negocio tienes, puedo ayudarte a identificar cuál encaja mejor.';
                return self::finish($pdo,$tenantId,$conversationId,$policy,$result,$reply,'company:solutions','high',false,$displayName,$english);
            }

            // V2.31.78 · Intenciones de productos principales. Estas respuestas base evitan
            // silencios cuando el visitante pregunta por funciones, utilidad o ajuste al negocio.
            if(self::norm($companyName)==='es multiservicios'&&str_contains($norm,'izzy')){
                $reply='IZZY es la solución empresarial de ES MULTISERVICIOS para facturación, inventario, POS, restaurantes y gestión administrativa. Puede ayudarte a controlar ventas, productos y existencias, operar puntos de venta, manejar procesos de restaurante y centralizar tareas administrativas. Si me dices qué tipo de negocio tienes y qué proceso quieres mejorar, puedo orientarte sobre cómo encaja IZZY en tu operación.';
                return self::finish($pdo,$tenantId,$conversationId,$policy,$result,$reply,'product:izzy','high',false,$displayName,$english);
            }
            if(self::norm($companyName)==='es multiservicios'&&str_contains($norm,'cami')){
                $reply='CAMI es la solución de ES MULTISERVICIOS orientada a clínicas y centros médicos. Ayuda a organizar pacientes, procesos clínicos, farmacia y facturación. Si me cuentas qué tipo de clínica o servicio manejas, puedo orientarte sobre las áreas de CAMI que mejor se ajustan a tu operación.';
                return self::finish($pdo,$tenantId,$conversationId,$policy,$result,$reply,'product:cami','high',false,$displayName,$english);
            }
            if(self::norm($companyName)==='es multiservicios'&&(str_contains($norm,'zynko')||str_contains($norm,'nivo web chat'))){
                $reply='ZYNKO reúne en una sola bandeja las conversaciones que llegan desde distintos canales, por ejemplo NIVO Web Chat y, cuando estén habilitados, WhatsApp o Messenger. También incorpora NIVO IA, equipos, asignaciones e integraciones para que la empresa atienda y organice sus conversaciones desde un solo lugar.';
                return self::finish($pdo,$tenantId,$conversationId,$policy,$result,$reply,'product:zynko','high',false,$displayName,$english);
            }

            if($isGreeting){
                if($english)$reply='Hello'.($personalized&&$displayName!==''?', '.$displayName:'').'! 👋 I’m NIVO, the virtual assistant for '.$companyName.'. How can I help you today?';
                else $reply='¡Hola'.($personalized&&$displayName!==''?', '.$displayName:'').'! 👋 Soy NIVO, el asistente virtual de '.$companyName.'. ¿En qué puedo ayudarte hoy?';
                return self::finish($pdo,$tenantId,$conversationId,$policy,$result,$reply,'greeting','high',false,$displayName,$english);
            }

            $handoffWords=array_values(array_filter(array_map([self::class,'norm'],explode(',',(string)($settings['handoff_keywords']??'agente, asesor, persona, humano, representante')))));
            foreach($handoffWords as $kw){if($kw!==''&&mb_strpos($norm,$kw)!==false){
                $hours=json_decode($bot['business_hours_json']??'{}',true)?:[];$inHours=self::inBusinessHours($hours);
                $reply=$english?'Of course. I’ll hand this conversation over to a person from '.$companyName.'.':($inHours?'Claro. Te transfiero con una persona de '.$companyName.' para que continúe contigo.':($hours['outside_message']??'En este momento estamos fuera del horario de atención. Dejé tu conversación pendiente para que una persona continúe contigo.'));
                return self::finish($pdo,$tenantId,$conversationId,$policy,$result,$reply,'handoff','high',true,$displayName,$english);
            }}

            $rq=$pdo->prepare('SELECT name,keywords,response FROM nivo_rules WHERE tenant_id=? AND active=1 ORDER BY priority,id');$rq->execute([$tenantId]);
            foreach($rq->fetchAll() as $r){foreach(array_filter(array_map([self::class,'norm'],explode(',',(string)$r['keywords']))) as $kw){if($kw!==''&&mb_strpos($norm,$kw)!==false)return self::finish($pdo,$tenantId,$conversationId,$policy,$result,(string)$r['response'],'rule:'.($r['name']??''),'high',false,$displayName,$english);}}

            if(!empty($bot['knowledge_enabled'])){
                $words=self::expandedWords($norm);$q=$pdo->prepare("SELECT name,source_type,source_ref,content FROM knowledge_sources WHERE tenant_id=? AND status='ready' AND approval_status='approved' AND content IS NOT NULL ORDER BY updated_at DESC LIMIT 350");$q->execute([$tenantId]);$best=null;$score=0;
                foreach($q->fetchAll() as $r){$n=self::knowledgeScore($message,(string)($r['name']??''),(string)($r['content']??''),$words);if($n>$score){$score=$n;$best=$r;}}
                $min=$settings['min_confidence']??'medium';$required=$min==='high'?7:($min==='low'?2:4);
                if($best&&$score>=$required){$limit=max(180,min(1500,(int)($settings['max_response_length']??700)));$reply=self::relevantExcerpt((string)$best['content'],$words,$limit);$tone=$settings['tone']??'professional';if($tone==='friendly')$reply='Con gusto. '.$reply;elseif($tone==='concise'&&mb_strlen($reply)>420)$reply=mb_substr($reply,0,420).'…';$source='knowledge:'.($best['name']??'');if(($best['source_type']??'')==='url'&&str_contains((string)($best['source_ref']??''),'|')){$parts=explode('|',(string)$best['source_ref'],2);$source.=' · '.($parts[1]??'');}return self::finish($pdo,$tenantId,$conversationId,$policy,$result,$reply,$source,$score>=9?'high':'medium',false,$displayName,$english);}
            }

            // Segunda fase opcional: NIVO local siempre intenta primero. OpenAI solo entra como fallback cuando está conectado, habilitado y permitido por el plan/tenant/canal.
            if(($bot['mode']??'hybrid')==='hybrid')try{
                $external=(new OpenAIProviderService($pdo,dirname(__DIR__,2)))->fallback($tenantId,$conversationId,$channelType,$message,$contactName,$companyName);
                if($external&&trim((string)($external['reply']??''))!=='')return self::finish($pdo,$tenantId,$conversationId,$policy,$result,(string)$external['reply'],(string)($external['source']??'openai'),(string)($external['confidence']??'ai'),false,$displayName,$english);
            }catch(Throwable $ignore){}

            $fallback=trim((string)($bot['fallback_message']??''));if($fallback==='')$fallback=$english?'I don’t have enough approved information to answer that safely. I can transfer you to a person.':'No tengo información suficiente para responder eso con seguridad. Si quieres, te transfiero con una persona para que continúe contigo.';
            if(!empty($policy['identity_enabled'])&&!str_contains(self::norm($fallback),'nivo'))$fallback=($english?'I’m NIVO, the virtual assistant for '.$companyName.'. ':'Soy NIVO, el asistente virtual de '.$companyName.'. ').$fallback;
            $unknownBefore=max(1,min(5,(int)($policy['unknown_before_handoff']??1)));$q=$pdo->prepare("SELECT body FROM messages WHERE tenant_id=? AND conversation_id=? AND direction='out' AND sender_type='bot' ORDER BY id DESC LIMIT 10");$q->execute([$tenantId,$conversationId]);$unknown=0;foreach($q->fetchAll() as $m){if(str_contains(self::norm((string)$m['body']),'no tengo informacion')||str_contains(self::norm((string)$m['body']),'enough approved information'))$unknown++;}
            $handoff=(!array_key_exists('auto_handoff',$settings)||!empty($settings['auto_handoff']))&&($unknown+1>=$unknownBefore);
            return self::finish($pdo,$tenantId,$conversationId,$policy,$result,$fallback,'fallback','low',$handoff,$displayName,$english);
        }catch(Throwable $e){$result['reason']='engine_error';return $result;}
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
