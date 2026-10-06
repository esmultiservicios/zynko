<?php
declare(strict_types=1);

final class AutomationEngine
{
    public static function evaluate(PDO $pdo,int $tenantId,int $conversationId,string $channelType,string $message): ?array
    {
        try{
            $q=$pdo->prepare("SELECT * FROM automation_flows WHERE tenant_id=? AND status='active' AND (channel_type='all' OR channel_type=?) ORDER BY id ASC");
            $q->execute([$tenantId,$channelType]);
            $normalized=mb_strtolower(trim($message));
            foreach($q->fetchAll() as $flow){
                $definition=json_decode((string)$flow['definition_json'],true)?:[];
                $trigger=(string)($flow['trigger_type']??'message_received');
                $condition=(string)($definition['condition']['value']??'');
                if($trigger==='keyword' && $condition==='')continue;
                if($condition!=='' && !self::matches($normalized,$condition))continue;
                $actionType=(string)($definition['action']['type']??'nivo_reply');
                $actionValue=trim((string)($definition['action']['value']??''));
                $result=['matched'=>true,'flow_id'=>(int)$flow['id'],'flow_name'=>(string)$flow['name'],'reply'=>null,'handoff'=>false,'stop'=>false,'source'=>'automation'];
                if($actionType==='send_message'){$result['reply']=$actionValue;$result['stop']=$actionValue!=='';return $result;}
                if($actionType==='handoff'){$pdo->prepare("UPDATE conversations SET status='pending' WHERE id=? AND tenant_id=?")->execute([$conversationId,$tenantId]);$result['handoff']=true;$result['stop']=true;$result['reply']=$actionValue!==''?$actionValue:null;return $result;}
                if($actionType==='assign_agent'){$uid=(int)$actionValue;if($uid>0){$u=$pdo->prepare('SELECT 1 FROM tenant_users WHERE tenant_id=? AND user_id=?');$u->execute([$tenantId,$uid]);if($u->fetchColumn())$pdo->prepare("UPDATE conversations SET assigned_user_id=?,status='open' WHERE id=? AND tenant_id=?")->execute([$uid,$conversationId,$tenantId]);}return $result;}
                if($actionType==='add_tag' && $actionValue!==''){$tag=mb_substr($actionValue,0,80);$pdo->prepare("INSERT INTO tags(tenant_id,name,color) VALUES(?,?,'#0F766E') ON DUPLICATE KEY UPDATE name=VALUES(name)")->execute([$tenantId,$tag]);$t=$pdo->prepare('SELECT id FROM tags WHERE tenant_id=? AND name=?');$t->execute([$tenantId,$tag]);$tagId=(int)$t->fetchColumn();if($tagId)$pdo->prepare('INSERT IGNORE INTO conversation_tags(conversation_id,tag_id) VALUES(?,?)')->execute([$conversationId,$tagId]);return $result;}
                if($actionType==='nivo_reply')return $result;
            }
        }catch(Throwable $e){}
        return null;
    }

    private static function matches(string $message,string $condition): bool
    {
        $parts=preg_split('/[,\n]+/u',mb_strtolower($condition))?:[];
        foreach($parts as $part){$part=trim($part);if($part!==''&&mb_strpos($message,$part)!==false)return true;}
        return false;
    }
}
