<?php
declare(strict_types=1);
require_once __DIR__.'/Commercial.php';

function zynkoActivationHistory(PDO $pdo,int $id,int $actor,string $status,string $note): void {
 $pdo->prepare('INSERT INTO channel_activation_history(request_id,actor_id,status,note) VALUES(?,?,?,?)')->execute([$id,$actor,$status,$note]);
}
function zynkoActivationNotify(PDO $pdo,string $root,int $tenant,string $title,string $message,bool $toCustomer): void {
 try{require_once $root.'/app/Services/NotificationService.php';$contact=$toCustomer?zynkoTenantOwner($pdo,$tenant):['email'=>zynkoPlatformAdminEmail($pdo)];$to=(string)($contact['email']??'');if($to!==''){
  (new NotificationService($pdo,$root))->sendAdministrativeEvent(mainTenantId(),$to,$title,$message,['page'=>'channels','tenant_id'=>$tenant]);
 }}catch(Throwable $e){zynkoSystemLog('error','activation','No se pudo procesar la notificación de activación.');}
}
function zynkoActivationSave(PDO $pdo,array $data,int $tenant,int $uid,array $context,string $root): int {
 if(!zynkoPlanAllowsChannel($context,'whatsapp'))throw new RuntimeException('WhatsApp no está incluido en el plan actual.');
 if(empty($context['has_plan'])&&!isPlatformOwner())throw new RuntimeException('Se requiere un plan comercial activo.');
 $method=(string)($data['method']??'');if(!in_array($method,['existing','new','help'],true))throw new RuntimeException('Selecciona un método de conexión.');
 $phone=trim((string)($data['phone']??''));$phone=preg_replace('/[\s()+-]/','',$phone);if($phone!==''&&!preg_match('/^[1-9][0-9]{7,14}$/D',$phone))throw new RuntimeException('Ingresa un número internacional válido, con código de país.');
 $progress=[];foreach(zynkoActivationSteps() as $step)if(in_array($step[0],(array)($data['progress']??[]),true))$progress[]=$step[0];
 $assist=$method==='help'||($data['assistance']??'')==='1';$note=mb_substr(trim((string)($data['note']??'')),0,1000);$id=(int)($data['request_id']??0);$cancel=($data['cancel']??'')==='1';$notify=false;
 $pdo->beginTransaction();try{
  // Serialize request creation per company; never trust a submitted tenant ID.
  $lock=$pdo->prepare('SELECT id FROM tenants WHERE id=? FOR UPDATE');$lock->execute([$tenant]);if(!$lock->fetchColumn())throw new RuntimeException('Empresa no disponible.');
  if($id){$q=$pdo->prepare('SELECT * FROM channel_activation_requests WHERE id=? AND tenant_id=? FOR UPDATE');$q->execute([$id,$tenant]);$row=$q->fetch();if(!$row||in_array($row['status'],['active','rejected','cancelled'],true))throw new RuntimeException('Solicitud no editable.');
   $status=$cancel?'cancelled':$row['status'];$notify=$cancel||($assist&&!$row['assistance'])||$note!=='';
   $pdo->prepare('UPDATE channel_activation_requests SET method=?,phone=?,progress_json=?,assistance=?,status=? WHERE id=? AND tenant_id=?')->execute([$method,$phone?:null,json_encode($progress),$assist?1:0,$status,$id,$tenant]);
  }else{if($cancel)throw new RuntimeException('Selecciona una solicitud.');$q=$pdo->prepare("SELECT id FROM channel_activation_requests WHERE tenant_id=? AND status NOT IN ('active','rejected','cancelled') LIMIT 1");$q->execute([$tenant]);if($q->fetchColumn())throw new RuntimeException('Ya existe una solicitud en curso. Continúa desde el asistente.');
   $status='pending';$pdo->prepare('INSERT INTO channel_activation_requests(tenant_id,requested_by,method,phone,progress_json,assistance,status) VALUES(?,?,?,?,?,?,?)')->execute([$tenant,$uid,$method,$phone?:null,json_encode($progress),$assist?1:0,$status]);$id=(int)$pdo->lastInsertId();$notify=true;
  }
  zynkoActivationHistory($pdo,$id,$uid,$status,$note);$pdo->commit();
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
 if($notify)zynkoActivationNotify($pdo,$root,$tenant,'Solicitud de activación WhatsApp #'.$id,'Se registró una actualización de la solicitud #'.$id.'. Consulta Canales para revisar el seguimiento. No adjuntes contraseñas ni datos de tarjeta.',false);return $id;
}
function zynkoActivationAdminUpdate(PDO $pdo,array $data,int $uid,string $root): void {
 $id=(int)($data['request_id']??0);$status=(string)($data['status']??'');if(!isset(zynkoActivationStates()[$status]))throw new RuntimeException('Estado inválido.');
 $actor=(int)($data['assigned_to']??0);$note=mb_substr(trim((string)($data['note']??'')),0,1000);$followup=trim((string)($data['followup_at']??''));$channel=(int)($data['channel_id']??0);
 if($followup!==''){$dt=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$followup);if(!$dt||$dt->format('Y-m-d\TH:i')!==$followup)throw new RuntimeException('Fecha de seguimiento inválida.');$followup=$dt->format('Y-m-d H:i:s');}
 if($actor){$q=$pdo->prepare("SELECT u.id FROM tenant_users tu JOIN users u ON u.id=tu.user_id WHERE tu.tenant_id=? AND u.id=? AND u.status='active' AND tu.role_code IN ('owner','admin')");$q->execute([mainTenantId(),$actor]);if(!$q->fetchColumn())throw new RuntimeException('Responsable no autorizado.');}
 $pdo->beginTransaction();try{$q=$pdo->prepare('SELECT * FROM channel_activation_requests WHERE id=? FOR UPDATE');$q->execute([$id]);$row=$q->fetch();if(!$row)throw new RuntimeException('Solicitud no disponible.');$tenant=(int)$row['tenant_id'];
 if($status==='active'){
  if(!zynkoPlanAllowsChannel(zynkoPlanContext($pdo,$tenant,$tenant===mainTenantId()),'whatsapp'))throw new RuntimeException('El plan actual no permite WhatsApp.');
  $q=$pdo->prepare("SELECT * FROM channels WHERE id=? AND tenant_id=? AND type='whatsapp' AND status='connected'");$q->execute([$channel,$tenant]);$c=$q->fetch();$s=$c?json_decode((string)$c['settings_json'],true):[];
  if(!$c||(!empty($c['token_expires_at'])&&strtotime($c['token_expires_at'])<=time())||!in_array($s['provider_mode']??'',['meta_cloud','meta_coexistence'],true)||empty($s['embedded_signup'])||empty($s['webhook_subscribed'])||empty($c['external_phone_id'])||empty($c['token_ciphertext'])||(($s['provider_mode']??'')==='meta_cloud'&&empty($s['registration_confirmed'])))throw new RuntimeException('Selecciona un canal oficial de la empresa realmente vinculado, registrado y validado.');
   if(!empty($row['phone'])&&preg_replace('/\D/','',(string)$c['display_address'])!==(string)$row['phone'])throw new RuntimeException('El número del canal no coincide con la solicitud.');
 }elseif($channel){$q=$pdo->prepare("SELECT id FROM channels WHERE id=? AND tenant_id=? AND type='whatsapp'");$q->execute([$channel,$tenant]);if(!$q->fetchColumn())throw new RuntimeException('Canal inválido.');}
 $pdo->prepare('UPDATE channel_activation_requests SET status=?,assigned_to=?,followup_at=?,channel_id=? WHERE id=?')->execute([$status,$actor?:null,$followup?:null,$channel?:null,$id]);zynkoActivationHistory($pdo,$id,$uid,$status,$note);$pdo->commit();
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
 zynkoPlatformAudit($pdo,'activation.update',$tenant,null,['request_id'=>$id,'status'=>$status]);
 if($status!==$row['status']||$note!==''||(int)$row['assigned_to']!==$actor||($row['followup_at']??'')!==($followup?:''))zynkoActivationNotify($pdo,$root,$tenant,'Seguimiento de activación WhatsApp #'.$id,'Estado: '.zynkoActivationStates()[$status].'. Consulta Canales para ver los próximos pasos.'.($note!==''?' Observación: '.$note:''),true);
}
function zynkoCommercialServiceSave(PDO $pdo,array $data): void {
 $id=(int)($data['service_id']??0);$mode=(string)($data['pricing_mode']??'');if(!in_array($mode,['free','included','quote','fixed'],true))throw new RuntimeException('Modalidad inválida.');
 $price=null;if($mode==='fixed'){$v=trim((string)($data['price']??''));if(!preg_match('/^\d{1,10}(\.\d{1,2})?$/D',$v))throw new RuntimeException('Precio inválido.');$price=$v;}
 $currency=strtoupper((string)($data['currency']??'USD'));if(!in_array($currency,['USD','HNL','EUR'],true))throw new RuntimeException('Moneda inválida.');
 $values=[];foreach(['name_es'=>160,'name_en'=>160,'description_es'=>1000,'description_en'=>1000] as $key=>$max){$v=trim((string)($data[$key]??''));if($v===''||mb_strlen($v)>$max)throw new RuntimeException('Completa los textos en ambos idiomas.');$values[]=$v;}
 $values=array_merge($values,[$mode,$price,$currency,empty($data['active'])?0:1,max(0,min(9999,(int)($data['sort_order']??0)))]);
 if($id){$pdo->prepare('UPDATE commercial_services SET name_es=?,name_en=?,description_es=?,description_en=?,pricing_mode=?,price=?,currency=?,active=?,sort_order=? WHERE id=?')->execute([...$values,$id]);}
 else{$pdo->prepare('INSERT INTO commercial_services(name_es,name_en,description_es,description_en,pricing_mode,price,currency,active,sort_order) VALUES(?,?,?,?,?,?,?,?,?)')->execute($values);}
}
