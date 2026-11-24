<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Support/AccessPolicy.php';
$ips=ZynkoAccessPolicy::validatedIpList("13.140.230.254\n2605:a144:2370:6446::\n2001:db8::/64\n13.140.230.254");
if(count($ips)!==3)throw new RuntimeException('Listas IP duplicadas o IPv6 mal interpretadas.');
if(!ZynkoAccessPolicy::ipAllowed('2605:a144:2370:6446::',$ips))throw new RuntimeException('IPv6 exacta no autorizada.');
if(ZynkoAccessPolicy::ipAllowed('2605:a144:2370:6447::',$ips))throw new RuntimeException('IPv6 ajena autorizada.');
if(ZynkoAccessPolicy::validatedOriginList("https://sistema.izzycloud.app\nhttps://demo.izzycloud.app/")!==['https://sistema.izzycloud.app','https://demo.izzycloud.app'])throw new RuntimeException('Origen incorrecto.');
foreach(['192.0.2.0/33','2001:db8::/129','IP_DESCONOCIDA','1.2.3.4/abc'] as $bad){try{ZynkoAccessPolicy::validatedIpList($bad);throw new RuntimeException('Aceptó entrada incorrecta: '.$bad);}catch(InvalidArgumentException $e){}}
echo "OK: entradas IP, IPv6, CIDR y orígenes\n";
