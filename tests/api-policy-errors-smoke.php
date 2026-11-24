<?php
declare(strict_types=1);
/** Test offline: no credentials, network, database or outgoing messages. */
require_once __DIR__.'/../app/Support/AccessPolicy.php';
function checkPolicy(bool $pass, string $label): void { if (!$pass) throw new RuntimeException('FAIL: '.$label); echo 'OK '.$label.PHP_EOL; }
checkPolicy(ZynkoAccessPolicy::ipAllowed('13.140.230.254',['13.140.230.254']), 'IP de WEB01 autorizada por IP exacta');
checkPolicy(!ZynkoAccessPolicy::ipAllowed('198.51.100.23',['13.140.230.254']), 'Laragon con otra IP se rechaza');
checkPolicy(ZynkoAccessPolicy::ipAllowed('198.51.100.23',['198.51.100.0/24']), 'CIDR IPv4');
checkPolicy(ZynkoAccessPolicy::ipAllowed('2001:db8::12',['2001:db8::/64']), 'CIDR IPv6');
checkPolicy(!ZynkoAccessPolicy::ipAllowed('2001:db9::12',['2001:db8::/64']), 'IPv6 ajena');
$_SERVER['REMOTE_ADDR']='198.51.100.23';
$_SERVER['HTTP_X_FORWARDED_FOR']='13.140.230.254';
checkPolicy(ZynkoAccessPolicy::requestIp()==='198.51.100.23', 'No confiar en X-Forwarded-For manipulable');
checkPolicy(!ZynkoAccessPolicy::originAllowed('https://otro.test',['https://newizzy.test']), 'Política de Origin independiente de IP');
$api=file_get_contents(__DIR__.'/../public/api.php');
checkPolicy(str_contains($api, 'h.api_key_id=?') && str_contains($api, "Mensaje no encontrado para esta clave API."), 'Estado WhatsApp aislado por API Key');
checkPolicy(str_contains($api, '$e->details,$e->errorCode'), 'Error tipado con detalles seguros');
checkPolicy(str_contains($api, 'IP_NOT_ALLOWED') || str_contains(file_get_contents(__DIR__.'/../app/Support/AccessPolicy.php'), 'IP_NOT_ALLOWED'), 'Código de error IP estable');
echo "Pruebas offline completadas. Producción/DB/Meta requieren pruebas separadas.".PHP_EOL;
