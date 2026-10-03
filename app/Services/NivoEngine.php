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

            if(!empty($policy['pause_when_assigned'])){
                $q=$pdo->prepare('SELECT assigned_user_id FROM conversations WHERE id=? AND tenant_id=? LIMIT 1');$q->execute([$conversationId,$tenantId]);
                if((int)($q->fetchColumn()?:0)>0){$result['reason']='human_assigned';return $result;}
            }

            $max=max(1,min(100,(int)($policy['max_auto_replies']??25)));
            $q=$pdo->prepare("SELECT COUNT(*) FROM messages WHERE tenant_id=? AND conversation_id=? AND direction='out' AND sender_type='bot'");$q->execute([$tenantId,$conversationId]);
            if((int)$q->fetchColumn()>=$max){$result['reason']='max_auto_replies';$result['handoff']=true;return $result;}

            $cool=max(0,min(30,(int)($policy['cooldown_seconds']??1)));
            if($cool>0){$q=$pdo->prepare("SELECT sent_at FROM messages WHERE tenant_id=? AND conversation_id=? AND direction='out' AND sender_type='bot' ORDER BY id DESC LIMIT 1");$q->execute([$tenantId,$conversationId]);$last=$q->fetchColumn();if($last&&time()-strtotime((string)$last)<$cool){$result['reason']='cooldown';return $result;}}

            $norm=self::norm($message);$displayName=trim($contactName);if($displayName===''||in_array(self::norm($displayName),['visitante','visitante web'],true))$displayName='';$personalized=!array_key_exists('personalized_greeting',$policy)||!empty($policy['personalized_greeting']);
            $english=!empty($policy['language_auto'])&&(bool)preg_match('/\b(hello|hi|what|how|where|when|help|please|thanks|thank you)\b/i',$message);
            $isGreeting=(bool)preg_match('/^(hola|buenas|buenos dias|buen dia|buenas tardes|buenas noches|hey|hello|hi)([!. ,].*)?$/u',$norm);
            $isCapabilities=(bool)preg_match('/\b(que sabes hacer|que puedes hacer|en que puedes ayudar|como me puedes ayudar|tus funciones|tus capacidades|para que sirves|en que te especializas|cual es tu especialidad|cuales son tus especialidades|que haces|que puedes responder|que temas manejas|que temas conoces|como funcionas|que puedes explicarme)\b/u',$norm);
            if($isCapabilities){
                $reply=$english
                  ? 'I specialize in helping with '.$companyName.' and ZYNKO using the information that has been approved for me. I can explain services, NIVO Web Chat, NIVO AI, plans, channels and integrations, answer common questions, guide you step by step and route your request. If something is outside my approved knowledge, I will say so; I only transfer you to a person when you ask for one or when the configured rules require it.'
                  : 'Me especializo en orientarte sobre '.$companyName.' y ZYNKO usando la información que tengo aprobada. Puedo explicarte servicios, NIVO Web Chat, NIVO IA, planes, canales e integraciones, responder preguntas frecuentes, guiarte paso a paso y ayudarte a encaminar tu solicitud. Si algo está fuera de mi conocimiento aprobado, te lo diré; solo te transfiero con una persona cuando lo pides o cuando las reglas configuradas realmente lo requieren.';
                return self::finish($pdo,$tenantId,$conversationId,$policy,$result,$reply,'capabilities','high',false,$displayName,$english);
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
