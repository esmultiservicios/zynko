<?php
declare(strict_types=1);

/** Aggregated metrics only; never expose webhook bodies, message text or credentials. */
final class ChannelMetrics
{
    public static function tenant(PDO $pdo,int $tid,int $days=7): array
    {
        if($tid<1)throw new InvalidArgumentException('Empresa inválida.');$days=max(1,min(90,$days));
        $q=$pdo->prepare("SELECT ch.id,ch.name,ch.type,ch.status,ch.last_event_at,ch.token_expires_at,COALESCE(m.inbound,0) inbound,COALESCE(m.outbound,0) outbound,COALESCE(m.delivered,0) delivered,COALESCE(m.read_count,0) read_count,COALESCE(m.failed,0) failed,COALESCE(o.queued,0) queued,COALESCE(o.uncertain,0) uncertain FROM channels ch LEFT JOIN (SELECT c.channel_id,COUNT(CASE WHEN m.direction='in' THEN 1 END) inbound,COUNT(CASE WHEN m.direction='out' THEN 1 END) outbound,COUNT(CASE WHEN m.direction='out' AND m.status IN ('delivered','read') THEN 1 END) delivered,COUNT(CASE WHEN m.direction='out' AND m.status='read' THEN 1 END) read_count,COUNT(CASE WHEN m.direction='out' AND m.status IN ('failed','partial') THEN 1 END) failed FROM messages m JOIN conversations c ON c.id=m.conversation_id AND c.tenant_id=m.tenant_id WHERE m.tenant_id=? AND m.sent_at>=DATE_SUB(NOW(),INTERVAL $days DAY) GROUP BY c.channel_id) m ON m.channel_id=ch.id LEFT JOIN (SELECT channel_id,COUNT(CASE WHEN state IN('queued','retrying','sending') THEN 1 END) queued,COUNT(CASE WHEN state IN('uncertain','partial') THEN 1 END) uncertain FROM channel_outbox WHERE tenant_id=? GROUP BY channel_id) o ON o.channel_id=ch.id WHERE ch.tenant_id=? ORDER BY ch.name,ch.id");$q->execute([$tid,$tid,$tid]);$channels=$q->fetchAll();
        $q=$pdo->prepare("SELECT DATE(sent_at) day,COUNT(CASE WHEN direction='in' THEN 1 END) inbound,COUNT(CASE WHEN direction='out' THEN 1 END) outbound FROM messages WHERE tenant_id=? AND sent_at>=DATE_SUB(NOW(),INTERVAL $days DAY) GROUP BY DATE(sent_at) ORDER BY day");$q->execute([$tid]);$daily=$q->fetchAll();return ['days'=>$days,'channels'=>$channels,'daily'=>$daily];
    }
    public static function platform(PDO $pdo,int $days=7): array
    {
        $days=max(1,min(90,$days));return $pdo->query("SELECT t.id,t.name,t.status,COALESCE(c.total,0) channels,COALESCE(c.connected,0) connected,COALESCE(m.inbound,0) inbound,COALESCE(m.outbound,0) outbound,COALESCE(m.failed,0) failed,COALESCE(o.queued,0) queued FROM tenants t LEFT JOIN (SELECT tenant_id,COUNT(*) total,COUNT(CASE WHEN status='connected' THEN 1 END) connected FROM channels GROUP BY tenant_id) c ON c.tenant_id=t.id LEFT JOIN (SELECT tenant_id,COUNT(CASE WHEN direction='in' THEN 1 END) inbound,COUNT(CASE WHEN direction='out' THEN 1 END) outbound,COUNT(CASE WHEN direction='out' AND status IN ('failed','partial') THEN 1 END) failed FROM messages WHERE sent_at>=DATE_SUB(NOW(),INTERVAL $days DAY) GROUP BY tenant_id) m ON m.tenant_id=t.id LEFT JOIN (SELECT tenant_id,COUNT(*) queued FROM channel_outbox WHERE state IN('queued','retrying','sending') GROUP BY tenant_id) o ON o.tenant_id=t.id ORDER BY t.name,t.id")->fetchAll();
    }
    public static function markExpired(PDO $pdo): int
    {
        // X uses refresh tokens; its polling/dispatch flow handles expiration independently.
        $q=$pdo->query("SELECT id,tenant_id,type FROM channels WHERE status='connected' AND type<>'x' AND token_expires_at IS NOT NULL AND token_expires_at<=NOW()");$count=0;
        foreach($q->fetchAll() as $ch){$u=$pdo->prepare("UPDATE channels SET status='warning' WHERE id=? AND tenant_id=? AND status='connected' AND token_expires_at IS NOT NULL AND token_expires_at<=NOW()");$u->execute([$ch['id'],$ch['tenant_id']]);if($u->rowCount()){$count++;try{$pdo->prepare('INSERT INTO system_event_logs(tenant_id,level,module,message,context_json) VALUES(?,?,?,?,?)')->execute([$ch['tenant_id'],'warning','channel_provider','La autorización del canal venció. Es necesaria una nueva vinculación.',json_encode(['channel_id'=>(int)$ch['id'],'provider'=>$ch['type']])]);}catch(Throwable $ignore){}}}return $count;
    }
}
