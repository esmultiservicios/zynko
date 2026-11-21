<?php
declare(strict_types=1);
/** Department workflows: all identifiers scoped to authenticated tenant. */
function zynkoDepartmentAction(PDO $pdo, int $tenantId, int $actorId, string $role, string $action, array $post): array {
    if (!in_array($role, ['owner','admin'], true)) {
        throw new RuntimeException('Solamente el administrador de la empresa puede modificar departamentos.');
    }
    if ($action === 'department_save') {
        $name = trim((string)($post['name'] ?? ''));
        $id = (int)($post['team_id'] ?? 0);
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) throw new RuntimeException('Escribe un nombre de 2 a 100 caracteres.');
        $active = !empty($post['active']) ? 1 : 0;
        if ($id) {
            $q=$pdo->prepare('UPDATE teams SET name=?,active=? WHERE id=? AND tenant_id=?');
            $q->execute([$name,$active,$id,$tenantId]);
            $v=$pdo->prepare('SELECT id FROM teams WHERE id=? AND tenant_id=?');$v->execute([$id,$tenantId]);
            if(!$v->fetchColumn()) throw new RuntimeException('Departamento no encontrado.');
        } else {
            $q=$pdo->prepare('INSERT INTO teams(tenant_id,uuid,name,active) VALUES(?,?,?,?)');
            $q->execute([$tenantId,zynkoDepartmentUuid(),$name,$active]);
            $id=(int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO department_routes(tenant_id,team_id,routing_mode) VALUES(?,?,'least_load')")->execute([$tenantId,$id]);
        }
        return ['message'=>'Departamento guardado.','team_id'=>$id];
    }
    if ($action === 'department_member_save') {
        $teamId=(int)($post['team_id']??0);$userId=(int)($post['user_id']??0);
        $roleSupervisor=!empty($post['is_supervisor'])?1:0;
        $max=max(1,min(100,(int)($post['max_active']??5)));
        $on=!empty($post['enabled'])?1:0;
        $q=$pdo->prepare('SELECT id FROM teams WHERE id=? AND tenant_id=? AND active=1');$q->execute([$teamId,$tenantId]);
        if(!$q->fetchColumn())throw new RuntimeException('Departamento inválido o inactivo.');
        $q=$pdo->prepare("SELECT u.id FROM users u JOIN tenant_users tu ON tu.user_id=u.id AND tu.tenant_id=? WHERE u.id=? AND u.status='active'");$q->execute([$tenantId,$userId]);
        if(!$q->fetchColumn())throw new RuntimeException('Usuario no pertenece a la empresa o está inactivo.');
        $q=$pdo->prepare('INSERT INTO department_members(tenant_id,team_id,user_id,is_supervisor,max_active,enabled) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE is_supervisor=VALUES(is_supervisor),max_active=VALUES(max_active),enabled=VALUES(enabled)');
        $q->execute([$tenantId,$teamId,$userId,$roleSupervisor,$max,$on]);
        return ['message'=>'Agente o supervisor actualizado.'];
    }
    throw new RuntimeException('Acción de departamentos no reconocida.');
}
function zynkoDepartmentUuid(): string {
    $b=random_bytes(16);$b[6]=chr((ord($b[6])&15)|64);$b[8]=chr((ord($b[8])&63)|128);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($b),4));
}

/** Route a conversation to a department. Never invent a human agent or send a customer-facing greeting. */
function zynkoDepartmentTransfer(PDO $pdo,int $tenantId,int $actorId,string $role,array $post): array {
 $cid=(int)($post['conversation_id']??0);$teamId=(int)($post['team_id']??0);
 $mode=(string)($post['mode']??'queue');
 if(!in_array($mode,['queue','auto'],true))throw new RuntimeException('Modo de transferencia inválido.');
 $pdo->beginTransaction();
 try {
  $q=$pdo->prepare('SELECT id,assigned_user_id,team_id FROM conversations WHERE id=? AND tenant_id=? AND deleted_at IS NULL FOR UPDATE');
  $q->execute([$cid,$tenantId]);$conv=$q->fetch(PDO::FETCH_ASSOC);
  if(!$conv)throw new RuntimeException('Conversación no encontrada.');
  if($role==='supervisor' && (int)$conv['assigned_user_id']!==$actorId) {
    $perm=$pdo->prepare('SELECT 1 FROM department_members WHERE tenant_id=? AND team_id=? AND user_id=? AND enabled=1 AND is_supervisor=1');
    $perm->execute([$tenantId,(int)$conv['team_id'],$actorId]);
    if(!$perm->fetchColumn())throw new RuntimeException('No supervisa el departamento de esta conversación.');
  } elseif(!in_array($role,['owner','admin','supervisor'],true) && (int)$conv['assigned_user_id']!==$actorId)throw new RuntimeException('No puede transferir una conversación que no le está asignada.');
  $q=$pdo->prepare('SELECT id,name FROM teams WHERE id=? AND tenant_id=? AND active=1');$q->execute([$teamId,$tenantId]);
  $team=$q->fetch(PDO::FETCH_ASSOC);if(!$team)throw new RuntimeException('El departamento no está disponible.');
  $agent=null;
  if($mode==='auto') {
    // Available presence is explicit and recent; capacity is evaluated inside the same transaction.
    $q=$pdo->prepare("SELECT u.id,u.name,dm.max_active,COUNT(c.id) AS active_chats
      FROM department_members dm
      JOIN users u ON u.id=dm.user_id AND u.status='active'
      JOIN tenant_users tu ON tu.tenant_id=dm.tenant_id AND tu.user_id=dm.user_id
      JOIN agent_presence ap ON ap.tenant_id=dm.tenant_id AND ap.user_id=dm.user_id AND ap.status='online' AND ap.updated_at>=DATE_SUB(NOW(),INTERVAL 120 SECOND)
      LEFT JOIN conversations c ON c.tenant_id=dm.tenant_id AND c.assigned_user_id=dm.user_id AND c.archived_at IS NULL AND c.deleted_at IS NULL AND c.status IN ('open','pending')
      WHERE dm.tenant_id=? AND dm.team_id=? AND dm.enabled=1 AND dm.is_supervisor=0
      GROUP BY u.id,u.name,dm.max_active HAVING active_chats < dm.max_active
      ORDER BY active_chats/dm.max_active ASC,active_chats ASC,u.id ASC LIMIT 1");
    $q->execute([$tenantId,$teamId]);$agent=$q->fetch(PDO::FETCH_ASSOC)?:null;
  }
  $newUid=$agent?(int)$agent['id']:null;
  $q=$pdo->prepare("UPDATE conversations SET team_id=?,assigned_user_id=?,status=? WHERE id=? AND tenant_id=?");
  $q->execute([$teamId,$newUid,$newUid?'open':'pending',$cid,$tenantId]);
  $pdo->prepare("INSERT INTO conversation_audit_logs(tenant_id,conversation_id,user_id,action,details_json,created_at) VALUES(?,?,?,'department.transfer',?,NOW())")
    ->execute([$tenantId,$cid,$actorId,json_encode(['from_team_id'=>$conv['team_id'],'to_team_id'=>$teamId,'from_user_id'=>$conv['assigned_user_id'],'to_user_id'=>$newUid,'mode'=>$mode],JSON_UNESCAPED_UNICODE)]);
  $pdo->commit();
  return ['message'=>$newUid?'Conversación asignada a '.(string)$agent['name'].'.':'Conversación transferida a la cola de '.(string)$team['name'].'.', 'conversation_id'=>$cid,'team_id'=>$teamId,'agent_id'=>$newUid];
 } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
