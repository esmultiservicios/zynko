<?php
/** Static smoke validations. Does not require credentials nor modify a database. */
require_once __DIR__.'/../app/Support/Plan.php';
require_once __DIR__.'/../app/Support/AccessPolicy.php';
function checkApi($ok,$label){if(!$ok)throw new RuntimeException($label);echo "OK $label\n";}
$api=['plan_code'=>'api_only','active'=>true,'modules'=>['dashboard'=>1,'channels'=>1,'integrations'=>1,'api'=>1,'billing'=>1],'allowed_channels'=>['whatsapp']];
foreach(['dashboard','channels','integrations','api','billing'] as $m)checkApi(zynkoPlanAllowsModule($api,$m),'API permite '.$m);
foreach(['inbox','chatbot','automations','webchat','email','settings','users','logs','documentation'] as $m)checkApi(!zynkoPlanAllowsModule($api,$m),'API restringe '.$m);
checkApi(zynkoPlanAllowsChannel($api,'whatsapp'),'API admite WhatsApp');
checkApi(!zynkoPlanAllowsChannel($api,'messenger'),'API bloquea Messenger');
$pro=['plan_code'=>'pro','active'=>true,'modules'=>['inbox'=>1,'api'=>1,'integrations'=>1],'allowed_channels'=>['whatsapp','messenger']];
checkApi(zynkoPlanAllowsModule($pro,'inbox'),'Plan existente mantiene bandeja');
checkApi(zynkoPlanAllowsModule($pro,'api'),'Plan existente mantiene API');
checkApi(ZynkoAccessPolicy::originAllowed('https://izzycloud.app',['https://izzycloud.app']),'Origen permitido');
checkApi(!ZynkoAccessPolicy::originAllowed('https://malicioso.example',['https://izzycloud.app']),'Origen no autorizado');
checkApi(ZynkoAccessPolicy::ipAllowed('203.0.113.18',['203.0.113.0/24']),'CIDR autorizado');
checkApi(!ZynkoAccessPolicy::ipAllowed('203.0.114.18',['203.0.113.0/24']),'CIDR rechazado');
$apiCode=file_get_contents(__DIR__.'/../public/api.php');
checkApi(str_contains($apiCode,'api_message_history')&&str_contains($apiCode,'$apiOnly'),'API registra historial y modo');
checkApi(str_contains($apiCode,'$channel!==$keyChannel'),'API limita el canal por clave');
echo "Pruebas estáticas API completadas. Se requiere validación MySQL y envíos reales en DEMO.\n";
