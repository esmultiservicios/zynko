<?php
require_once __DIR__.'/../app/Support/Plan.php';
$n=0;function checkPlan($ok,$label){global $n;if(!$ok)throw new RuntimeException($label);echo 'OK '.$label.PHP_EOL;$n++;}
$pro=['plan_code'=>'pro','active'=>true,'max_channels'=>3,'allowed_channels'=>['webchat','whatsapp','messenger','instagram']];
$free=['plan_code'=>'free','active'=>true,'is_free'=>true,'max_channels'=>1];
$starter=['plan_code'=>'starter','active'=>true,'max_channels'=>2,'allowed_channels'=>['webchat','whatsapp']];
$business=['plan_code'=>'business','active'=>true,'max_channels'=>5,'distinct_external_types'=>1,'allowed_channels'=>['webchat','whatsapp','messenger','instagram','telegram','email','threads','x']];
$row=fn($id,$type,$status='connected')=>compact('id','type','status');
checkPlan(zynkoPlanExternalConnectionLimit($free)===0,'Gratis 0');
checkPlan(zynkoPlanExternalConnectionLimit($starter)===1,'Starter 1');
checkPlan(zynkoPlanExternalConnectionLimit($pro)===2,'Pro 2');
checkPlan(zynkoPlanExternalConnectionLimit($business)===4,'Business 4');
checkPlan(zynkoPlanConnectionDenial($free,'whatsapp',[])!==null,'Gratis bloquea WhatsApp');
checkPlan(zynkoPlanConnectionDenial($starter,'messenger',[])!==null,'Starter bloquea Messenger');
checkPlan(zynkoPlanConnectionDenial($pro,'instagram',[$row(1,'messenger')])===null,'Pro Messenger + Instagram');
checkPlan(zynkoPlanConnectionDenial($pro,'messenger',[$row(1,'whatsapp')])===null,'Pro WhatsApp + Messenger');
checkPlan(zynkoPlanConnectionDenial($pro,'instagram',[$row(1,'whatsapp')])===null,'Pro WhatsApp + Instagram');
checkPlan(zynkoPlanConnectionDenial($pro,'whatsapp',[$row(1,'whatsapp')])!==null,'Pro una cuenta por tipo');
checkPlan(zynkoPlanConnectionDenial($pro,'instagram',[$row(1,'whatsapp','pending'),$row(2,'messenger','warning')])!==null,'Pendientes y advertencias ocupan cupo');
checkPlan(zynkoPlanConnectionDenial($pro,'instagram',[$row(1,'whatsapp','disconnected'),$row(2,'messenger'),$row(3,'webchat')])===null,'Desconectado y Web Chat no ocupan cupo');
checkPlan(zynkoPlanConnectionDenial($business,'threads',[],0,false)!==null,'Threads bloqueado');
checkPlan(zynkoPlanConnectionDenial($business,'whatsapp',[$row(1,'whatsapp'),$row(2,'messenger'),$row(3,'instagram'),$row(4,'telegram')])!==null,'Business máximo 4');
checkPlan(zynkoPlanConnectionDenial($business,'whatsapp',[$row(1,'whatsapp')])!==null,'Business una cuenta por tipo');
checkPlan(zynkoPlanConnectionDenial(array_replace($business,['distinct_external_types'=>0]),'whatsapp',[$row(1,'whatsapp')])===null,'Owner puede permitir varias cuentas por tipo');
$catalog=zynkoPlanChannelCatalog([['code'=>'whatsapp','visible'=>1,'linkable'=>1,'connector_ready'=>1],['code'=>'instagram','visible'=>1,'linkable'=>0,'connector_ready'=>1]]);
checkPlan(zynkoPlanChannelAvailability($catalog,'whatsapp')==='Disponible','Estado disponible');
checkPlan(zynkoPlanChannelAvailability($catalog,'instagram')==='Próximamente','Estado próximo');
checkPlan(zynkoPlanChannelAvailability($catalog,'threads','en')==='Coming soon','Estado inglés');
$p=['code'=>'pro','max_channels'=>3,'allowed_channels_json'=>json_encode($pro['allowed_channels'])];
checkPlan(str_contains(zynkoPlanChannelCapacityText($p,$catalog),'2 de 3'),'Capacidad publicada');
checkPlan(zynkoPlanExcessChannels($p,[$row(1,'whatsapp'),$row(2,'messenger'),$row(3,'instagram')])===[3],'Reconciliación de exceso');
echo "$n pruebas aprobadas\n";

$display=array_replace($business,["allowed_channels_json"=>json_encode($business["allowed_channels"])]);$stats=zynkoPlanChannelStats($display,$catalog);checkPlan($stats["limit"]===4&&$stats["count"]===7&&$stats["ready_total"]===2&&$stats["ready"]===1,"Business 4 de 7 externos, 2 de 8 disponibles");
