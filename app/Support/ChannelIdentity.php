<?php
declare(strict_types=1);
require_once __DIR__.'/ChannelSecurity.php';

/** Provider-confirmed WhatsApp identities. Never trusts a display number supplied by a browser. */
final class ChannelIdentity
{
    public static function phone(string $phone): string
    {
        $number=preg_replace('/[\s()+.-]/','',trim($phone));
        return is_string($number)&&preg_match('/^[1-9][0-9]{6,14}$/D',$number)?$number:'';
    }
    public static function claim(PDO $pdo,array $channel,string $raw): bool
    {
        $number=self::phone($raw);if($number==='')return false;
        $id=(int)$channel['id'];$tid=(int)$channel['tenant_id'];
        $lock=$pdo->query("SELECT GET_LOCK('zynko_verified_wa_numbers',5)");
        if((int)$lock->fetchColumn()!==1)throw new RuntimeException('No fue posible comprobar la titularidad del número. Intenta nuevamente.');
        try{
            $pdo->beginTransaction();
            $q=$pdo->prepare('SELECT * FROM channels WHERE id=? AND tenant_id=? FOR UPDATE');$q->execute([$id,$tid]);$current=$q->fetch();
            if(!$current||$current['type']!=='whatsapp'||ChannelSecurity::mode($current)!==ChannelSecurity::mode($channel))throw new RuntimeException('La conexión cambió durante la verificación.');
            $q=$pdo->prepare('SELECT channel_id FROM channel_verified_phones WHERE phone=? AND channel_id<>?');$q->execute([$number,$id]);$conflict=(bool)$q->fetchColumn();$q=$pdo->prepare('SELECT phone FROM channel_verified_phones WHERE channel_id=?');$q->execute([$id]);$oldPhone=(string)$q->fetchColumn();if($oldPhone!==''&&$oldPhone!==$number)$conflict=true;
            // Legacy connected numbers: preserve their ownership until the administrator explicitly resolves conflicts.
            if(!$conflict){$q=$pdo->prepare("SELECT id,display_address,settings_json FROM channels WHERE type='whatsapp' AND id<>? AND status<>'disconnected'");$q->execute([$id]);foreach($q->fetchAll() as $r){$s=ChannelSecurity::settings($r);$known=(string)($s['verified_phone']??'');if($known!==''&&self::phone($known)===$number){$conflict=true;break;}}}
            $settings=ChannelSecurity::settings($current);
            if(isset($settings['verified_phone'])&&$settings['verified_phone']!==$number&&$settings['verified_phone']!=='')$conflict=true;
            if($conflict){$settings['phone_conflict']=true;$pdo->prepare("UPDATE channels SET status='warning',settings_json=? WHERE id=? AND tenant_id=?")->execute([json_encode($settings,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$id,$tid]);$pdo->commit();return false;}
            $pdo->prepare('INSERT INTO channel_verified_phones(phone,channel_id,tenant_id) VALUES(?,?,?) ON DUPLICATE KEY UPDATE verified_at=NOW()')->execute([$number,$id,$tid]);
            $settings['verified_phone']=$number;unset($settings['phone_conflict']);
            $pdo->prepare('UPDATE channels SET display_address=?,settings_json=? WHERE id=? AND tenant_id=?')->execute(['+'.$number,json_encode($settings,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$id,$tid]);$pdo->commit();return true;
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}finally{$pdo->query("SELECT RELEASE_LOCK('zynko_verified_wa_numbers')");}
    }
}
