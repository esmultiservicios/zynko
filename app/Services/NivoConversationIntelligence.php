<?php
declare(strict_types=1);
require_once __DIR__.'/OpenAIProviderService.php';

final class NivoConversationIntelligence
{
    private PDO $pdo;
    private string $root;

    public function __construct(PDO $pdo,string $root){$this->pdo=$pdo;$this->root=$root;self::ensureSchema($pdo);}

    public static function ensureSchema(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS nivo_conversation_memory(
            tenant_id BIGINT UNSIGNED NOT NULL,
            conversation_id BIGINT UNSIGNED NOT NULL,
            summary TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
            current_intent VARCHAR(120) NULL,
            language VARCHAR(12) NULL,
            entities_json JSON NULL,
            requirements_json JSON NULL,
            open_questions_json JSON NULL,
            commercial_state_json JSON NULL,
            last_user_message TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY(tenant_id,conversation_id),
            INDEX idx_nivo_memory_updated(tenant_id,updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS nivo_prospects(
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            conversation_id BIGINT UNSIGNED NOT NULL,
            contact_id BIGINT UNSIGNED NULL,
            product_interest VARCHAR(190) NULL,
            business_type VARCHAR(190) NULL,
            city VARCHAR(160) NULL,
            user_count VARCHAR(80) NULL,
            branch_count VARCHAR(80) NULL,
            needs_json JSON NULL,
            evaluated_plan VARCHAR(190) NULL,
            demo_url VARCHAR(500) NULL,
            demo_sent TINYINT(1) NOT NULL DEFAULT 0,
            status VARCHAR(80) NOT NULL DEFAULT 'new',
            metadata_json JSON NULL,
            next_follow_up_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_nivo_prospect_conversation(tenant_id,conversation_id),
            INDEX idx_nivo_prospect_status(tenant_id,status,updated_at),
            INDEX idx_nivo_prospect_followup(tenant_id,next_follow_up_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS nivo_knowledge_chunks(
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            source_id BIGINT UNSIGNED NOT NULL,
            chunk_index INT UNSIGNED NOT NULL,
            title VARCHAR(255) NULL,
            content TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            content_hash CHAR(64) NOT NULL,
            embedding_json LONGTEXT NULL,
            embedding_model VARCHAR(120) NULL,
            source_updated_at DATETIME NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_nivo_chunk(tenant_id,source_id,chunk_index),
            INDEX idx_nivo_chunk_tenant_source(tenant_id,source_id),
            INDEX idx_nivo_chunk_hash(tenant_id,content_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS nivo_followup_suggestions(
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            conversation_id BIGINT UNSIGNED NOT NULL,
            reason VARCHAR(500) NULL,
            suggested_days INT UNSIGNED NULL,
            status ENUM('pending','accepted','dismissed') NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_nivo_followup_suggestion(tenant_id,status,created_at),
            INDEX idx_nivo_followup_conversation(tenant_id,conversation_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    private static function norm(string $s): string
    {
        $s=mb_strtolower(trim($s),'UTF-8');
        $s=strtr($s,['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
        return preg_replace('/\s+/u',' ',$s)?:'';
    }

    private static function words(string $s): array
    {
        $stop=['para','como','esto','esta','este','esas','esos','unos','unas','tengo','quiero','puedo','puede','sobre','desde','donde','cuando','cual','cuales','porque','pero','solo','todo','todos','todas','algo','mas','muy','con','sin','del','las','los','una','uno','que','por','mis','sus'];
        $parts=preg_split('/[^\p{L}\p{N}]+/u',self::norm($s))?:[];
        return array_values(array_unique(array_filter($parts,fn($w)=>mb_strlen($w)>=3&&!in_array($w,$stop,true))));
    }

    private static function splitContent(string $content,int $size=1200,int $overlap=180): array
    {
        $content=trim(preg_replace('/\r\n?/u',"\n",$content)??$content);if($content==='')return [];
        $paras=preg_split('/\n{2,}/u',$content)?:[$content];$chunks=[];$buf='';
        foreach($paras as $p){$p=trim($p);if($p==='')continue;if(mb_strlen($buf)+mb_strlen($p)+2<=$size){$buf.=($buf!==''?"\n\n":'').$p;continue;}if($buf!==''){$chunks[]=$buf;$tail=mb_substr($buf,max(0,mb_strlen($buf)-$overlap));$buf=trim($tail."\n\n".$p);}else{for($i=0,$len=mb_strlen($p);$i<$len;$i+=max(1,$size-$overlap)){$chunks[]=mb_substr($p,$i,$size);}}}
        if(trim($buf)!=='')$chunks[]=$buf;return array_values(array_filter(array_map('trim',$chunks)));
    }

    public function syncChunks(int $tenantId): void
    {
        $q=$this->pdo->prepare("SELECT id,name,content,updated_at FROM knowledge_sources WHERE tenant_id=? AND status='ready' AND approval_status='approved' AND content IS NOT NULL ORDER BY id");$q->execute([$tenantId]);
        foreach($q->fetchAll() as $src){$sid=(int)$src['id'];$content=trim((string)$src['content']);if($content==='')continue;$hash=hash('sha256',$content);$c=$this->pdo->prepare('SELECT content_hash FROM nivo_knowledge_chunks WHERE tenant_id=? AND source_id=? ORDER BY chunk_index LIMIT 1');$c->execute([$tenantId,$sid]);$old=(string)($c->fetchColumn()?:'');if($old===$hash)continue;$this->pdo->prepare('DELETE FROM nivo_knowledge_chunks WHERE tenant_id=? AND source_id=?')->execute([$tenantId,$sid]);$ins=$this->pdo->prepare('INSERT INTO nivo_knowledge_chunks(tenant_id,source_id,chunk_index,title,content,content_hash,source_updated_at) VALUES(?,?,?,?,?,?,?)');$i=0;foreach(self::splitContent($content) as $chunk){$ins->execute([$tenantId,$sid,$i++,mb_substr((string)$src['name'],0,255),$chunk,$hash,$src['updated_at']??null]);}}
        $this->pdo->prepare("DELETE kc FROM nivo_knowledge_chunks kc LEFT JOIN knowledge_sources ks ON ks.id=kc.source_id AND ks.tenant_id=kc.tenant_id WHERE kc.tenant_id=? AND (ks.id IS NULL OR ks.status<>'ready' OR ks.approval_status<>'approved')")->execute([$tenantId]);
    }

    private static function cosine(array $a,array $b): float
    {
        $n=min(count($a),count($b));if($n===0)return 0.0;$dot=$aa=$bb=0.0;for($i=0;$i<$n;$i++){$x=(float)$a[$i];$y=(float)$b[$i];$dot+=$x*$y;$aa+=$x*$x;$bb+=$y*$y;}return ($aa>0&&$bb>0)?$dot/(sqrt($aa)*sqrt($bb)):0.0;
    }

    public function retrieve(int $tenantId,string $query,int $limit=6): array
    {
        $this->syncChunks($tenantId);$words=self::words($query);$q=$this->pdo->prepare('SELECT id,source_id,title,content,embedding_json,embedding_model FROM nivo_knowledge_chunks WHERE tenant_id=? ORDER BY updated_at DESC LIMIT 800');$q->execute([$tenantId]);$rows=$q->fetchAll();$rank=[];
        foreach($rows as $r){$hay=self::norm(((string)$r['title']).' '.((string)$r['content']));$score=0.0;foreach($words as $w){$score+=substr_count($hay,$w)*1.0;if(str_contains(self::norm((string)$r['title']),$w))$score+=2.5;}if($query!==''&&str_contains($hay,self::norm($query)))$score+=5;$r['_lex']=$score;$rank[]=$r;}
        usort($rank,fn($a,$b)=>$b['_lex']<=>$a['_lex']);$candidates=array_slice($rank,0,30);
        try{
            $provider=new OpenAIProviderService($this->pdo,$this->root);$texts=[$query];$missing=[];foreach($candidates as $i=>$r){if(empty($r['embedding_json'])){$texts[]=(string)$r['content'];$missing[]=$i;}}
            $vectors=$provider->embeddingVectors($tenantId,$texts);$qvec=$vectors[0]??null;if(is_array($qvec)){
                $offset=1;foreach($candidates as $i=>&$r){$vec=json_decode((string)($r['embedding_json']??''),true);if(!is_array($vec)&&in_array($i,$missing,true)){$vec=$vectors[$offset++]??null;if(is_array($vec)){$this->pdo->prepare("UPDATE nivo_knowledge_chunks SET embedding_json=?,embedding_model='text-embedding-3-small' WHERE id=? AND tenant_id=?")->execute([json_encode($vec),$r['id'],$tenantId]);}}$sim=is_array($vec)?self::cosine($qvec,$vec):0.0;$r['_semantic']=$sim;$r['_score']=((float)$r['_lex']*0.35)+($sim*10*0.65);}unset($r);usort($candidates,fn($a,$b)=>$b['_score']<=>$a['_score']);}
        }catch(Throwable $ignore){}
        $out=[];foreach($candidates as $r){$score=(float)($r['_score']??$r['_lex']);if($score<=0&&!$out)continue;$out[]=['source_id'=>(int)$r['source_id'],'title'=>(string)$r['title'],'content'=>(string)$r['content'],'score'=>$score];if(count($out)>=max(1,min(10,$limit)))break;}return $out;
    }

    public function memory(int $tenantId,int $conversationId): array
    {
        $q=$this->pdo->prepare('SELECT * FROM nivo_conversation_memory WHERE tenant_id=? AND conversation_id=?');$q->execute([$tenantId,$conversationId]);$r=$q->fetch()?:[];foreach(['entities_json'=>'entities','requirements_json'=>'requirements','open_questions_json'=>'open_questions','commercial_state_json'=>'commercial_state'] as $col=>$key){$r[$key]=json_decode((string)($r[$col]??'[]'),true)?:[];}return $r;
    }

    private static function mergeAssoc(array $old,array $new): array
    {
        foreach($new as $k=>$v){if($v===null||$v===''||$v===[])continue;if(is_array($v)&&isset($old[$k])&&is_array($old[$k]))$old[$k]=self::mergeAssoc($old[$k],$v);else $old[$k]=$v;}return $old;
    }

    public function saveTurn(int $tenantId,int $conversationId,string $message,array $turn): void
    {
        $old=$this->memory($tenantId,$conversationId);$entities=self::mergeAssoc((array)($old['entities']??[]),(array)($turn['entities']??[]));$req=self::mergeAssoc((array)($old['requirements']??[]),(array)($turn['requirements']??[]));$commercial=self::mergeAssoc((array)($old['commercial_state']??[]),(array)($turn['commercial_state']??[]));$questions=array_values(array_filter((array)($turn['open_questions']??[]),fn($v)=>trim((string)$v)!==''));$summary=trim((string)($turn['memory_summary']??($old['summary']??'')));$intent=trim((string)($turn['intent']??($old['current_intent']??'')));$language=trim((string)($turn['language']??($old['language']??'')));
        $q=$this->pdo->prepare("INSERT INTO nivo_conversation_memory(tenant_id,conversation_id,summary,current_intent,language,entities_json,requirements_json,open_questions_json,commercial_state_json,last_user_message) VALUES(?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE summary=VALUES(summary),current_intent=VALUES(current_intent),language=VALUES(language),entities_json=VALUES(entities_json),requirements_json=VALUES(requirements_json),open_questions_json=VALUES(open_questions_json),commercial_state_json=VALUES(commercial_state_json),last_user_message=VALUES(last_user_message)");$q->execute([$tenantId,$conversationId,$summary?:null,$intent?:null,$language?:null,json_encode($entities,JSON_UNESCAPED_UNICODE),json_encode($req,JSON_UNESCAPED_UNICODE),json_encode($questions,JSON_UNESCAPED_UNICODE),json_encode($commercial,JSON_UNESCAPED_UNICODE),mb_substr($message,0,5000)]);
        $this->saveProspect($tenantId,$conversationId,$entities,$req,$commercial,$turn);
        if(!empty($turn['follow_up_recommended'])){$days=max(1,min(60,(int)($turn['follow_up_days']??3)));$reason=mb_substr(trim((string)($turn['follow_up_reason']??'Seguimiento sugerido por NIVO')),0,500);$c=$this->pdo->prepare("SELECT id FROM nivo_followup_suggestions WHERE tenant_id=? AND conversation_id=? AND status='pending' ORDER BY id DESC LIMIT 1");$c->execute([$tenantId,$conversationId]);if(!$c->fetchColumn())$this->pdo->prepare("INSERT INTO nivo_followup_suggestions(tenant_id,conversation_id,reason,suggested_days) VALUES(?,?,?,?)")->execute([$tenantId,$conversationId,$reason,$days]);}
    }

    private function saveProspect(int $tenantId,int $conversationId,array $entities,array $requirements,array $commercial,array $turn): void
    {
        $interest=trim((string)($commercial['product_interest']??$entities['product_interest']??''));$status=trim((string)($commercial['status']??''));$isCommercial=!empty($turn['commercial'])||$interest!==''||$status!==''||!empty($requirements);if(!$isCommercial)return;$q=$this->pdo->prepare('SELECT contact_id FROM conversations WHERE tenant_id=? AND id=?');$q->execute([$tenantId,$conversationId]);$contactId=(int)($q->fetchColumn()?:0);$city=trim((string)($entities['city']??''));$business=trim((string)($entities['business_type']??''));$users=trim((string)($entities['users']??$entities['user_count']??''));$branches=trim((string)($entities['branches']??$entities['branch_count']??''));$plan=trim((string)($commercial['evaluated_plan']??''));$demo=trim((string)($commercial['demo_url']??''));$demoSent=!empty($commercial['demo_sent'])?1:0;if($status==='')$status='qualifying';$follow=null;if(!empty($turn['follow_up_recommended']))$follow=date('Y-m-d H:i:s',time()+86400*max(1,min(60,(int)($turn['follow_up_days']??3))));$meta=['entities'=>$entities,'commercial'=>$commercial];$sql="INSERT INTO nivo_prospects(tenant_id,conversation_id,contact_id,product_interest,business_type,city,user_count,branch_count,needs_json,evaluated_plan,demo_url,demo_sent,status,metadata_json,next_follow_up_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE contact_id=VALUES(contact_id),product_interest=COALESCE(NULLIF(VALUES(product_interest),''),product_interest),business_type=COALESCE(NULLIF(VALUES(business_type),''),business_type),city=COALESCE(NULLIF(VALUES(city),''),city),user_count=COALESCE(NULLIF(VALUES(user_count),''),user_count),branch_count=COALESCE(NULLIF(VALUES(branch_count),''),branch_count),needs_json=VALUES(needs_json),evaluated_plan=COALESCE(NULLIF(VALUES(evaluated_plan),''),evaluated_plan),demo_url=COALESCE(NULLIF(VALUES(demo_url),''),demo_url),demo_sent=GREATEST(demo_sent,VALUES(demo_sent)),status=COALESCE(NULLIF(VALUES(status),''),status),metadata_json=VALUES(metadata_json),next_follow_up_at=COALESCE(VALUES(next_follow_up_at),next_follow_up_at)";$this->pdo->prepare($sql)->execute([$tenantId,$conversationId,$contactId?:null,$interest?:null,$business?:null,$city?:null,$users?:null,$branches?:null,json_encode($requirements,JSON_UNESCAPED_UNICODE),$plan?:null,$demo?:null,$demoSent,$status,json_encode($meta,JSON_UNESCAPED_UNICODE),$follow]);
    }

    public function recentContext(int $tenantId,int $conversationId,int $limit=14): array
    {
        $q=$this->pdo->prepare('SELECT direction,sender_type,body FROM messages WHERE tenant_id=? AND conversation_id=? AND body IS NOT NULL ORDER BY id DESC LIMIT '.max(2,min(30,$limit)));$q->execute([$tenantId,$conversationId]);$rows=array_reverse($q->fetchAll());$out=[];foreach($rows as $r){$body=trim((string)$r['body']);if($body==='')continue;$out[]=['role'=>(($r['direction']??'in')==='in'?'customer':(($r['sender_type']??'bot')==='bot'?'nivo':'agent')),'text'=>mb_substr($body,0,1200)];}return $out;
    }

    public function turn(int $tenantId,int $conversationId,string $channelType,string $message,string $contactName,string $companyName): ?array
    {
        $memory=$this->memory($tenantId,$conversationId);
        $entities=(array)($memory['entities']??[]);$requirements=(array)($memory['requirements']??[]);$commercial=(array)($memory['commercial_state']??[]);
        $focus=[];foreach(['product_interest','business_type','city'] as $k)if(!empty($commercial[$k]??$entities[$k]??null))$focus[]=(string)($commercial[$k]??$entities[$k]);
        foreach($requirements as $k=>$v){if(is_scalar($v)&&$v!==''&&$v!==false)$focus[]=(string)$k.' '.(string)$v;elseif(is_array($v))$focus[]=implode(' ',array_map('strval',array_filter($v,'is_scalar')));}
        $search=trim($message.' '.implode(' ',array_slice($focus,0,12)));
        $knowledge=$this->retrieve($tenantId,$search,8);$provider=new OpenAIProviderService($this->pdo,$this->root);$turn=$provider->conversationTurn($tenantId,$conversationId,$channelType,$message,$contactName,$companyName,$memory,$this->recentContext($tenantId,$conversationId),$knowledge);if(!$turn)return null;$this->saveTurn($tenantId,$conversationId,$message,$turn);return $turn;
    }
}
