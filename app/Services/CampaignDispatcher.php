<?php
declare(strict_types=1);
require_once __DIR__.'/ChannelProviderGateway.php';
final class CampaignDispatcher
{
    public static function dispatch(PDO $pdo,string $root,int $tenantId,int $campaignId,int $limit=100): array
    {
        $q=$pdo->prepare("SELECT * FROM outbound_campaigns WHERE id=? AND tenant_id=? LIMIT 1");$q->execute([$campaignId,$tenantId]);$campaign=$q->fetch();if(!$campaign)throw new RuntimeException('Campaña no encontrada.');
        $q=$pdo->prepare("SELECT * FROM channels WHERE tenant_id=? AND type='whatsapp' AND status='connected' ORDER BY id DESC LIMIT 1");$q->execute([$tenantId]);$channel=$q->fetch();if(!$channel)throw new RuntimeException('No hay un canal WhatsApp conectado para despachar la campaña.');
        $q=$pdo->prepare("SELECT * FROM campaign_recipients WHERE tenant_id=? AND campaign_id=? AND status IN('pending','queued') ORDER BY id LIMIT ".max(1,min(500,$limit)));$q->execute([$tenantId,$campaignId]);$rows=$q->fetchAll();if(!$rows)throw new RuntimeException('No hay destinatarios pendientes. Primero prepara la audiencia.');
        $gateway=new ChannelProviderGateway($pdo,$root);$sent=0;$failed=0;$pdo->prepare("UPDATE outbound_campaigns SET status='running' WHERE id=? AND tenant_id=?")->execute([$campaignId,$tenantId]);
        foreach($rows as $r){$result=$gateway->dispatchWhatsAppCampaign($channel,(string)$r['destination'],(string)$campaign['message_body'],$campaign['template_name']?:null);if($result[0]){$sent++;$pdo->prepare("UPDATE campaign_recipients SET status='sent',error_message=NULL WHERE id=? AND tenant_id=?")->execute([$r['id'],$tenantId]);}else{$failed++;$pdo->prepare("UPDATE campaign_recipients SET status='failed',error_message=? WHERE id=? AND tenant_id=?")->execute([mb_substr((string)$result[1],0,500),$r['id'],$tenantId]);}}
        $stats=$pdo->prepare("SELECT COUNT(*) total,SUM(status='sent') sent,SUM(status='delivered') delivered,SUM(status='failed') failed,SUM(status IN('pending','queued')) pending FROM campaign_recipients WHERE tenant_id=? AND campaign_id=?");$stats->execute([$tenantId,$campaignId]);$st=$stats->fetch()?:[];$status=((int)($st['pending']??0)===0)?'completed':'running';$pdo->prepare("UPDATE outbound_campaigns SET status=?,total_recipients=?,sent_count=?,delivered_count=?,failed_count=? WHERE id=? AND tenant_id=?")->execute([$status,(int)($st['total']??0),(int)($st['sent']??0),(int)($st['delivered']??0),(int)($st['failed']??0),$campaignId,$tenantId]);return ['sent'=>$sent,'failed'=>$failed,'remaining'=>(int)($st['pending']??0),'status'=>$status];
    }
}
