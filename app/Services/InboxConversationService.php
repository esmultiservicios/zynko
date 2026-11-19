<?php
declare(strict_types=1);
require_once __DIR__.'/../Support/InboxContact.php';
final class InboxConversationService {
    public static function create(PDO $pdo,int $tid,array $plan,array $data): array {
        $channel=(int)($data['channel_id']??0);$save=($data['save_contact']??'1')==='1';$name=mb_substr(trim((string)($data['contact_name']??'')),0,160);$address=trim((string)($data['address']??''));
        $pdo->beginTransaction();try{
            $pdo->prepare('SELECT id FROM tenants WHERE id=? FOR UPDATE')->execute([$tid]);
            $q=$pdo->prepare('SELECT id,type,status FROM channels WHERE id=? AND tenant_id=? FOR UPDATE');$q->execute([$channel,$tid]);$ch=$q->fetch();
            if(!$ch||$ch['status']!=='connected'||!zynkoPlanAllowsChannel($plan,(string)$ch['type']))throw new RuntimeException('Selecciona un canal conectado e incluido en tu plan.');
            $type=(string)$ch['type'];if(!in_array($type,['whatsapp','messenger','instagram','telegram','x'],true)||!zynkoChannelCatalogAvailable($pdo,$type))throw new RuntimeException('Este canal no admite nuevas conversaciones actualmente.');
            if($type==='whatsapp')$address=InboxContact::phone($address,(string)($data['country']??''));
            elseif(!preg_match('/^-?[0-9]{1,190}$/D',$address))throw new RuntimeException('Introduce el identificador del destinatario en este canal.');
            $q=$pdo->prepare('SELECT ci.contact_id FROM contact_identities ci JOIN contacts ct ON ct.id=ci.contact_id AND ct.tenant_id=ci.tenant_id WHERE ci.tenant_id=? AND ci.channel_id=? AND ci.external_id=?');$q->execute([$tid,$channel,$address]);$contact=(int)$q->fetchColumn();
            // Messenger/Instagram require a provider-scoped identifier from a previous interaction.
            if(!$contact&&in_array($type,['messenger','instagram','telegram','x'],true))throw new RuntimeException('Selecciona un destinatario que ya haya interactuado con este canal.');
            if(!$contact&&$type==='whatsapp'){$q=$pdo->prepare('SELECT id FROM contacts WHERE tenant_id=? AND phone IN (?,?) ORDER BY id LIMIT 1');$q->execute([$tid,$address,'+'.$address]);$contact=(int)$q->fetchColumn();}
            if($contact){$q=$pdo->prepare('SELECT external_id FROM contact_identities WHERE tenant_id=? AND channel_id=? AND contact_id=? AND external_id<>? LIMIT 1');$q->execute([$tid,$channel,$contact,$address]);if($q->fetchColumn())throw new RuntimeException('Esta ficha tiene otro destinatario en el canal. Abre el chat original para evitar enviar al número equivocado.');}
            if(!$contact){$pdo->prepare('INSERT INTO contacts(tenant_id,uuid,name,phone,custom_fields) VALUES(?,?,?,?,?)')->execute([$tid,uuid4(),$save&&$name!==''?$name:'+'. $address,$address,json_encode(['inbox_saved'=>$save])]);$contact=(int)$pdo->lastInsertId();}
            if($save){if($name==='')throw new RuntimeException('Escribe el nombre para guardar el contacto.');$pdo->prepare("UPDATE contacts SET name=?,custom_fields=JSON_SET(COALESCE(custom_fields,JSON_OBJECT()),'$.inbox_saved',true,'$.inbox_country',?) WHERE id=? AND tenant_id=?")->execute([$name,(string)($data['country']??''),$contact,$tid]);}
            $q=$pdo->prepare('INSERT IGNORE INTO contact_identities(tenant_id,contact_id,channel_id,external_id) VALUES(?,?,?,?)');$q->execute([$tid,$contact,$channel,$address]);
            $q=$pdo->prepare('SELECT contact_id FROM contact_identities WHERE tenant_id=? AND channel_id=? AND external_id=?');$q->execute([$tid,$channel,$address]);if((int)$q->fetchColumn()!==$contact)throw new RuntimeException('El destinatario pertenece a otro contacto.');
            $q=$pdo->prepare("SELECT id FROM conversations WHERE tenant_id=? AND channel_id=? AND contact_id=? AND deleted_at IS NULL AND archived_at IS NULL AND status IN ('open','pending') ORDER BY id DESC LIMIT 1");$q->execute([$tid,$channel,$contact]);$cid=(int)$q->fetchColumn();$reused=(bool)$cid;
            if(!$cid)$cid=zynkoPlanCreateChat($pdo,$tid,$channel,$contact,$type,uuid4(),!empty($plan['unrestricted']));
            $pdo->commit();return ['conversation_id'=>$cid,'reused'=>$reused,'contact_name'=>$name?:$address];
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
}
