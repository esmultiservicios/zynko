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

            if(!empty($policy['pause_when_assigned'])){
                $q=$pdo->prepare('SELECT assigned_user_id FROM conversations WHERE id=? AND tenant_id=? LIMIT 1');$q->execute([$conversationId,$tenantId]);
                if((int)($q->fetchColumn()?:0)>0){$result['reason']='human_assigned';return $result;}
            }

            $max=max(1,min(100,(int)($policy['max_auto_replies']??25)));
            $q=$pdo->prepare("SELECT COUNT(*) FROM messages WHERE tenant_id=? AND conversation_id=? AND direction='out' AND sender_type='bot'");$q->execute([$tenantId,$conversationId]);
            if((int)$q->fetchColumn()>=$max){$result['reason']='max_auto_replies';$result['handoff']=true;return $result;}

            $cool=max(0,min(30,(int)($policy['cooldown_seconds']??1)));
            if($cool>0){$q=$pdo->prepare("SELECT sent_at FROM messages WHERE tenant_id=? AND conversation_id=? AND direction='out' AND sender_type='bot' ORDER BY id DESC LIMIT 1");$q->execute([$tenantId,$conversationId]);$last=$q->fetchColumn();if($last&&time()-strtotime((string)$last)<$cool){$result['reason']='cooldown';return $result;}}

            $norm=self::norm($message);$firstName=trim(preg_split('/\s+/u',$contactName)[0]??'');if($firstName===''||self::norm($firstName)==='visitante')$firstName='';
            $english=!empty($policy['language_auto'])&&(bool)preg_match('/\b(hello|hi|what|how|where|when|help|please|thanks|thank you)\b/i',$message);
            $isGreeting=(bool)preg_match('/^(hola|buenas|buenos dias|buen dia|buenas tardes|buenas noches|hey|hello|hi)([!. ,].*)?$/u',$norm);
            if($isGreeting){
                if($english)$reply='Hello'.(!empty($policy['personalized_greeting'])&&$firstName!==''?', '.$firstName:'').'! 👋 I’m NIVO, the virtual assistant for '.$companyName.'. How can I help you today?';
                else $reply='¡Hola'.(!empty($policy['personalized_greeting'])&&$firstName!==''?', '.$firstName:'').'! 👋 Soy NIVO, el asistente virtual de '.$companyName.'. ¿En qué puedo ayudarte hoy?';
                return self::finish($pdo,$tenantId,$conversationId,$policy,$result,$reply,'greeting','high',false);
            }

            $handoffWords=array_values(array_filter(array_map([self::class,'norm'],explode(',',(string)($settings['handoff_keywords']??'agente, asesor, persona, humano, representante')))));
            foreach($handoffWords as $kw){if($kw!==''&&mb_strpos($norm,$kw)!==false){
                $hours=json_decode($bot['business_hours_json']??'{}',true)?:[];$inHours=self::inBusinessHours($hours);
                $reply=$english?'Of course. I’ll hand this conversation over to a person from '.$companyName.'.':($inHours?'Claro. Te transfiero con una persona de '.$companyName.' para que continúe contigo.':($hours['outside_message']??'En este momento estamos fuera del horario de atención. Dejé tu conversación pendiente para que una persona continúe contigo.'));
                return self::finish($pdo,$tenantId,$conversationId,$policy,$result,$reply,'handoff','high',true);
            }}

            $rq=$pdo->prepare('SELECT name,keywords,response FROM nivo_rules WHERE tenant_id=? AND active=1 ORDER BY priority,id');$rq->execute([$tenantId]);
            foreach($rq->fetchAll() as $r){foreach(array_filter(array_map([self::class,'norm'],explode(',',(string)$r['keywords']))) as $kw){if($kw!==''&&mb_strpos($norm,$kw)!==false)return self::finish($pdo,$tenantId,$conversationId,$policy,$result,(string)$r['response'],'rule:'.($r['name']??''),'high',false);}}

            if(!empty($bot['knowledge_enabled'])){
                $words=self::words($norm);$q=$pdo->prepare("SELECT name,content FROM knowledge_sources WHERE tenant_id=? AND status='ready' AND approval_status='approved' AND content IS NOT NULL ORDER BY updated_at DESC LIMIT 200");$q->execute([$tenantId]);$best=null;$score=0;
                foreach($q->fetchAll() as $r){$hay=self::norm(($r['name']??'').' '.($r['content']??''));$n=0;foreach($words as $word)if(mb_strpos($hay,$word)!==false)$n++;if($n>$score){$score=$n;$best=$r;}}
                $min=$settings['min_confidence']??'medium';$required=$min==='high'?4:($min==='low'?1:2);
                if($best&&$score>=$required){$limit=max(180,min(1500,(int)($settings['max_response_length']??700)));$reply=trim((string)$best['content']);if(mb_strlen($reply)>$limit)$reply=mb_substr($reply,0,$limit).'…';$tone=$settings['tone']??'professional';if($tone==='friendly')$reply='Con gusto. '.$reply;elseif($tone==='concise'&&mb_strlen($reply)>420)$reply=mb_substr($reply,0,420).'…';return self::finish($pdo,$tenantId,$conversationId,$policy,$result,$reply,'knowledge:'.($best['name']??''),$score>=4?'high':'medium',false);}
            }

            // Segunda fase opcional: NIVO local siempre intenta primero. OpenAI solo entra como fallback cuando está conectado, habilitado y permitido por el plan/tenant/canal.
            if(($bot['mode']??'hybrid')==='hybrid')try{
                $external=(new OpenAIProviderService($pdo,dirname(__DIR__,2)))->fallback($tenantId,$conversationId,$channelType,$message,$contactName,$companyName);
                if($external&&trim((string)($external['reply']??''))!=='')return self::finish($pdo,$tenantId,$conversationId,$policy,$result,(string)$external['reply'],(string)($external['source']??'openai'),(string)($external['confidence']??'ai'),false);
            }catch(Throwable $ignore){}

            $fallback=trim((string)($bot['fallback_message']??''));if($fallback==='')$fallback=$english?'I don’t have enough approved information to answer that safely. I can transfer you to a person.':'No tengo información suficiente para responder eso con seguridad. Si quieres, te transfiero con una persona para que continúe contigo.';
            if(!empty($policy['identity_enabled'])&&!str_contains(self::norm($fallback),'nivo'))$fallback=($english?'I’m NIVO, the virtual assistant for '.$companyName.'. ':'Soy NIVO, el asistente virtual de '.$companyName.'. ').$fallback;
            $unknownBefore=max(1,min(5,(int)($policy['unknown_before_handoff']??1)));$q=$pdo->prepare("SELECT body FROM messages WHERE tenant_id=? AND conversation_id=? AND direction='out' AND sender_type='bot' ORDER BY id DESC LIMIT 10");$q->execute([$tenantId,$conversationId]);$unknown=0;foreach($q->fetchAll() as $m){if(str_contains(self::norm((string)$m['body']),'no tengo informacion')||str_contains(self::norm((string)$m['body']),'enough approved information'))$unknown++;}
            $handoff=(!array_key_exists('auto_handoff',$settings)||!empty($settings['auto_handoff']))&&($unknown+1>=$unknownBefore);
            return self::finish($pdo,$tenantId,$conversationId,$policy,$result,$fallback,'fallback','low',$handoff);
        }catch(Throwable $e){$result['reason']='engine_error';return $result;}
    }

    private static function finish(PDO $pdo,int $tenantId,int $conversationId,array $policy,array $result,string $reply,string $source,string $confidence,bool $handoff): array
    {
        if(!empty($policy['duplicate_guard'])){$q=$pdo->prepare("SELECT body FROM messages WHERE tenant_id=? AND conversation_id=? AND direction='out' AND sender_type='bot' ORDER BY id DESC LIMIT 1");$q->execute([$tenantId,$conversationId]);$last=trim((string)($q->fetchColumn()?:''));if($last!==''&&self::norm($last)===self::norm($reply)){$result['reason']='duplicate_guard';return $result;}}
        $result['reply']=$reply;$result['source']=$source;$result['confidence']=$confidence;$result['handoff']=$handoff;$result['reason']='reply';return $result;
    }

    private static function inBusinessHours(array $hours): bool
    {
        if(empty($hours['enabled']))return true;$now=new DateTimeImmutable('now');$day=(int)$now->format('N');$hm=$now->format('H:i');$days=array_map('intval',$hours['days']??[]);$ok=in_array($day,$days,true)&&$hm>=($hours['start']??'08:00')&&$hm<=($hours['end']??'17:00');$today=$now->format('Y-m-d');foreach(($hours['special_dates']??[]) as $special){if(($special['date']??'')!==$today)continue;if(($special['type']??'closed')==='closed')return false;$ss=$special['start']??'';$se=$special['end']??'';return $ss!==''&&$se!==''&&$hm>=$ss&&$hm<=$se;}return $ok;
    }
}
