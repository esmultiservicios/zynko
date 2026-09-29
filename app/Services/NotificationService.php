<?php
declare(strict_types=1);
require_once __DIR__.'/EmailTemplates.php';
final class NotificationService{
 public function __construct(private PDO $pdo,private string $root){}
 private function dec(?string $v):string{if(!$v)return '';if(!str_starts_with($v,'enc:v1:'))return $v;$e=@parse_ini_file($this->root.'/.env',false,INI_SCANNER_RAW)?:[];$hex=$e['APP_KEY']??'';if(!preg_match('/^[a-f0-9]{64}$/i',$hex))return '';$raw=base64_decode(substr($v,7),true);if($raw===false||strlen($raw)<29)return '';$iv=substr($raw,0,12);$tag=substr($raw,12,16);$c=substr($raw,28);return (string)(openssl_decrypt($c,'aes-256-gcm',hex2bin($hex),OPENSSL_RAW_DATA,$iv,$tag)?:'');}
 private function cfg(int $tid):?array{$q=$this->pdo->prepare('SELECT * FROM correo WHERE tenant_id=? AND is_default=1 AND estado=1 ORDER BY correo_id DESC LIMIT 1');$q->execute([$tid]);return $q->fetch()?:null;}
 private function setting(int $tid):array{try{$q=$this->pdo->prepare('SELECT * FROM notification_runtime_settings WHERE tenant_id=?');$q->execute([$tid]);return $q->fetch()?:['login_enabled'=>1,'message_enabled'=>1,'handoff_enabled'=>1,'critical_enabled'=>1,'cooldown_minutes'=>10];}catch(Throwable $e){return ['login_enabled'=>1,'message_enabled'=>1,'handoff_enabled'=>1,'critical_enabled'=>1,'cooldown_minutes'=>10];}}
 private function publicBase():string{
  $env=@parse_ini_file($this->root.'/.env',false,INI_SCANNER_RAW)?:[];
  $base=trim((string)($env['APP_URL']??''));
  if($base===''){
   $https=(!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off')||(string)($_SERVER['SERVER_PORT']??'')==='443';
   $base=($https?'https':'http').'://'.(string)($_SERVER['HTTP_HOST']??'localhost');
  }
  $base=rtrim($base,'/');
  if(str_ends_with(strtolower($base),'/public'))$base=substr($base,0,-7);
  return rtrim($base,'/');
 }
 public function send(int $tid,string $event,string $to,string $subject,string $message,array $meta=[]):array{$s=$this->setting($tid);$map=['login'=>'login_enabled','message'=>'message_enabled','handoff'=>'handoff_enabled','critical'=>'critical_enabled'];$col=$map[$event]??'critical_enabled';if(empty($s[$col]))return ['success'=>false,'skipped'=>true,'message'=>'Notificación desactivada'];if(!filter_var($to,FILTER_VALIDATE_EMAIL))return ['success'=>false,'skipped'=>true,'message'=>'Destino inválido'];$cool=max(0,(int)($s['cooldown_minutes']??10));$key=(string)($meta['dedupe_key']??$event);if($event==='message'&&$cool>0){$q=$this->pdo->prepare("SELECT id FROM notification_log WHERE tenant_id=? AND channel='email' AND status='sent' AND JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.dedupe_key'))=? AND created_at>=DATE_SUB(NOW(),INTERVAL ? MINUTE) LIMIT 1");$q->execute([$tid,$key,$cool]);if($q->fetch())return ['success'=>false,'skipped'=>true,'message'=>'Agrupado para evitar spam'];}$cfg=$this->cfg($tid);if(!$cfg)return ['success'=>false,'skipped'=>true,'message'=>'Correo no configurado'];$q=$this->pdo->prepare('SELECT t.name,b.app_title,b.logo_path FROM tenants t LEFT JOIN branding_settings b ON b.tenant_id=t.id WHERE t.id=?');$q->execute([$tid]);$b=$q->fetch()?:[];$base=$this->publicBase();$html=EmailTemplates::generic(strtoupper($event==='message'?'NUEVO MENSAJE':($event==='login'?'SEGURIDAD':'NIVO')),$subject,$message,['company_name'=>$b['name']??'Tu empresa','app_title'=>$b['app_title']??'ZYNKO','logo_url'=>!empty($b['logo_path'])?$base.'/'.ltrim($b['logo_path'],'/'):'','app_url'=>$base.'/?page='.($event==='message'||$event==='handoff'?'inbox':'dashboard')],strtoupper($event));$res=$this->deliver($cfg,$to,$subject,$html);$type=$this->pdo->query("SELECT correo_tipo_id FROM correo_tipo WHERE codigo='".($event==='login'?'security':($event==='message'?'conversation_alerts':($event==='handoff'?'nivo_ai':'system_alerts')))."' LIMIT 1")->fetchColumn()?:null;$this->pdo->prepare('INSERT INTO notification_log(tenant_id,correo_tipo_id,channel,recipient,subject,status,provider,error_message,metadata_json,sent_at) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([$tid,$type,'email',$to,$subject,$res['success']?'sent':'failed',$cfg['metodo_envio']??'SYSTEM',$res['success']?null:$res['message'],json_encode(array_merge($meta,['event'=>$event,'dedupe_key'=>$key]),JSON_UNESCAPED_UNICODE),$res['success']?date('Y-m-d H:i:s'):null]);return $res;}
 public function sendAccountCreated(int $tid,string $to):array{if(!filter_var($to,FILTER_VALIDATE_EMAIL))return ['success'=>false,'message'=>'Destino inválido'];$cfg=$this->cfg($tid);if(!$cfg)return ['success'=>false,'skipped'=>true,'message'=>'Correo no configurado'];$q=$this->pdo->prepare('SELECT t.name,b.app_title,b.logo_path FROM tenants t LEFT JOIN branding_settings b ON b.tenant_id=t.id WHERE t.id=?');$q->execute([$tid]);$b=$q->fetch()?:[];$base=$this->publicBase();$settings=['company_name'=>$b['name']??'Tu empresa','app_title'=>$b['app_title']??'ZYNKO','logo_url'=>!empty($b['logo_path'])?$base.'/'.ltrim($b['logo_path'],'/'):'','app_url'=>$base.'/'];$html=EmailTemplates::accountCreated($settings);$res=$this->deliver($cfg,$to,'Tu cuenta de ZYNKO fue creada',$html);try{$this->pdo->prepare('INSERT INTO notification_log(tenant_id,channel,recipient,subject,status,provider,error_message,metadata_json,sent_at) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$tid,'email',$to,'Tu cuenta de ZYNKO fue creada',$res['success']?'sent':'failed',$cfg['metodo_envio']??'SYSTEM',$res['success']?null:$res['message'],json_encode(['event'=>'account_created'],JSON_UNESCAPED_UNICODE),$res['success']?date('Y-m-d H:i:s'):null]);}catch(Throwable $e){}return $res;}
 public function sendRegistrationCode(int $platformTid,string $to,string $code,string $company):array{if(!filter_var($to,FILTER_VALIDATE_EMAIL))return ['success'=>false,'message'=>'Destino inválido'];$cfg=$this->cfg($platformTid);if(!$cfg)return ['success'=>false,'message'=>'El correo principal de ZYNKO no está configurado.'];$q=$this->pdo->prepare('SELECT t.name,b.app_title,b.logo_path FROM tenants t LEFT JOIN branding_settings b ON b.tenant_id=t.id WHERE t.id=?');$q->execute([$platformTid]);$b=$q->fetch()?:[];$base=$this->publicBase();$settings=['company_name'=>$b['name']??'ZYNKO','app_title'=>$b['app_title']??'ZYNKO','logo_url'=>!empty($b['logo_path'])?$base.'/'.ltrim($b['logo_path'],'/'):'','app_url'=>$base.'/'];$subject='ZYNKO · Código de verificación';$html=EmailTemplates::verificationCode($code,$company,$settings);$res=$this->deliver($cfg,$to,$subject,$html);$this->logDirect($platformTid,$to,$subject,$cfg,$res,['event'=>'registration_verification','company'=>$company]);return $res;}
 public function sendFreeAccountWelcome(int $platformTid,string $to,array $account):array{if(!filter_var($to,FILTER_VALIDATE_EMAIL))return ['success'=>false,'message'=>'Destino inválido'];$cfg=$this->cfg($platformTid);if(!$cfg)return ['success'=>false,'message'=>'El correo principal de ZYNKO no está configurado.'];$q=$this->pdo->prepare('SELECT t.name,b.app_title,b.logo_path FROM tenants t LEFT JOIN branding_settings b ON b.tenant_id=t.id WHERE t.id=?');$q->execute([$platformTid]);$b=$q->fetch()?:[];$base=$this->publicBase();$settings=['company_name'=>$b['name']??'ZYNKO','app_title'=>$b['app_title']??'ZYNKO','logo_url'=>!empty($b['logo_path'])?$base.'/'.ltrim($b['logo_path'],'/'):'','app_url'=>$base.'/'];$subject='Bienvenido a ZYNKO · Plan Gratis activado';$html=EmailTemplates::freeAccountWelcome($account,$settings);$res=$this->deliver($cfg,$to,$subject,$html);$this->logDirect($platformTid,$to,$subject,$cfg,$res,['event'=>'free_account_welcome','tenant_id'=>$account['tenant_id']??null]);return $res;}
 public function sendNewCustomerAdmin(int $platformTid,string $to,array $customer):array{if(!filter_var($to,FILTER_VALIDATE_EMAIL))return ['success'=>false,'message'=>'Destino inválido'];$cfg=$this->cfg($platformTid);if(!$cfg)return ['success'=>false,'message'=>'El correo principal de ZYNKO no está configurado.'];$q=$this->pdo->prepare('SELECT t.name,b.app_title,b.logo_path FROM tenants t LEFT JOIN branding_settings b ON b.tenant_id=t.id WHERE t.id=?');$q->execute([$platformTid]);$b=$q->fetch()?:[];$base=$this->publicBase();$settings=['company_name'=>$b['name']??'ZYNKO','app_title'=>$b['app_title']??'ZYNKO','logo_url'=>!empty($b['logo_path'])?$base.'/'.ltrim($b['logo_path'],'/'):'','app_url'=>$base.'/?page=billing'];$subject='ZYNKO · Nuevo cliente registrado · '.($customer['company_name']??'Nueva empresa');$html=EmailTemplates::newCustomerAdmin($customer,$settings);$res=$this->deliver($cfg,$to,$subject,$html);$this->logDirect($platformTid,$to,$subject,$cfg,$res,['event'=>'new_customer','tenant_id'=>$customer['tenant_id']??null,'customer_email'=>$customer['email']??'']);return $res;}
 private function platformSettings(int $platformTid,string $path='/?page=billing'):array{
  $q=$this->pdo->prepare('SELECT t.name,b.app_title,b.logo_path FROM tenants t LEFT JOIN branding_settings b ON b.tenant_id=t.id WHERE t.id=?');
  $q->execute([$platformTid]);$b=$q->fetch()?:[];$base=$this->publicBase();
  return ['company_name'=>$b['name']??'ZYNKO','app_title'=>$b['app_title']??'ZYNKO','logo_url'=>!empty($b['logo_path'])?$base.'/'.ltrim($b['logo_path'],'/'):'','app_url'=>$base.$path];
 }
 private function directPlatformMail(int $platformTid,string $to,string $subject,string $html,array $meta=[]):array{
  if(!filter_var($to,FILTER_VALIDATE_EMAIL))return ['success'=>false,'message'=>'Destino inválido'];
  $cfg=$this->cfg($platformTid);if(!$cfg)return ['success'=>false,'message'=>'El correo principal de ZYNKO no está configurado.'];
  $res=$this->deliver($cfg,$to,$subject,$html);$this->logDirect($platformTid,$to,$subject,$cfg,$res,$meta);return $res;
 }
 public function sendPlanCatalogAdmin(int $platformTid,string $to,string $action,array $plan):array{
  $verb=['created'=>'creado','updated'=>'actualizado','deleted'=>'eliminado'][$action]??'actualizado';
  $subject='ZYNKO · Plan '.$verb.' · '.($plan['name']??'Plan');
  $html=EmailTemplates::planCatalogEvent($action,$plan,$this->platformSettings($platformTid));
  return $this->directPlatformMail($platformTid,$to,$subject,$html,['event'=>'plan_'.$action,'plan_id'=>$plan['id']??null,'plan_name'=>$plan['name']??'']);
 }
 public function sendPlanRequestCustomer(int $platformTid,string $to,array $data):array{
  $subject='ZYNKO · Solicitud de plan recibida · '.($data['plan_name']??'Plan');
  $html=EmailTemplates::planRequestCustomer($data,$this->platformSettings($platformTid,'/?page=billing'));
  return $this->directPlatformMail($platformTid,$to,$subject,$html,['event'=>'plan_request_customer','tenant_id'=>$data['tenant_id']??null,'plan_id'=>$data['plan_id']??null,'request_id'=>$data['request_id']??null]);
 }
 public function sendPlanRequestAdmin(int $platformTid,string $to,array $data):array{
  $subject='ZYNKO · Nueva solicitud de plan · '.($data['company_name']??'Empresa');
  $html=EmailTemplates::planRequestAdmin($data,$this->platformSettings($platformTid,'/?page=billing'));
  return $this->directPlatformMail($platformTid,$to,$subject,$html,['event'=>'plan_request_admin','tenant_id'=>$data['tenant_id']??null,'plan_id'=>$data['plan_id']??null,'request_id'=>$data['request_id']??null]);
 }
 public function sendSubscriptionCustomer(int $platformTid,string $to,array $data):array{
  $subject='ZYNKO · Tu suscripción fue actualizada · '.($data['plan_name']??'Plan');
  $html=EmailTemplates::subscriptionChangedCustomer($data,$this->platformSettings($platformTid,'/?page=billing'));
  return $this->directPlatformMail($platformTid,$to,$subject,$html,['event'=>'subscription_customer','tenant_id'=>$data['tenant_id']??null,'plan_id'=>$data['plan_id']??null,'status'=>$data['status']??'']);
 }
 public function sendSubscriptionAdmin(int $platformTid,string $to,array $data):array{
  $subject='ZYNKO · Suscripción actualizada · '.($data['company_name']??'Empresa');
  $html=EmailTemplates::subscriptionChangedAdmin($data,$this->platformSettings($platformTid,'/?page=billing'));
  return $this->directPlatformMail($platformTid,$to,$subject,$html,['event'=>'subscription_admin','tenant_id'=>$data['tenant_id']??null,'plan_id'=>$data['plan_id']??null,'status'=>$data['status']??'']);
 }
 public function sendPlanRequestResolved(int $platformTid,string $to,array $data):array{
  $approved=($data['resolution']??'')==='approved';
  $subject='ZYNKO · Solicitud de plan '.($approved?'aprobada':'actualizada').' · '.($data['plan_name']??'Plan');
  $html=EmailTemplates::planRequestResolved($data,$this->platformSettings($platformTid,'/?page=billing'));
  return $this->directPlatformMail($platformTid,$to,$subject,$html,['event'=>'plan_request_'.$data['resolution'],'tenant_id'=>$data['tenant_id']??null,'plan_id'=>$data['plan_id']??null,'request_id'=>$data['request_id']??null]);
 }
 public function sendUserLifecycle(int $platformTid,string $to,string $title,string $message,array $meta=[]):array{
  $html=EmailTemplates::userLifecycle($title,$message,$this->platformSettings($platformTid,'/?page=users'));
  return $this->directPlatformMail($platformTid,$to,'ZYNKO · '.$title,$html,array_merge(['event'=>'user_lifecycle'],$meta));
 }
 public function sendChannelLifecycle(int $platformTid,string $to,string $title,string $message,array $meta=[]):array{
  $html=EmailTemplates::channelLifecycle($title,$message,$this->platformSettings($platformTid,'/?page=channels'));
  return $this->directPlatformMail($platformTid,$to,'ZYNKO · '.$title,$html,array_merge(['event'=>'channel_lifecycle'],$meta));
 }
 public function sendPublicContactAdmin(int $platformTid,string $to,array $data):array{
  $subject='ZYNKO · Nueva consulta web · '.($data['subject_label']??'Contacto');
  $html=EmailTemplates::publicContactAdmin($data,$this->platformSettings($platformTid,'/#contacto'));
  return $this->directPlatformMail($platformTid,$to,$subject,$html,['event'=>'public_contact_admin','inquiry_id'=>$data['inquiry_id']??null,'contact_email'=>$data['email']??'','source'=>$data['source']??'']);
 }
 public function sendPublicContactConfirmation(int $platformTid,string $to,array $data):array{
  $subject='ZYNKO · Recibimos tu consulta';
  $html=EmailTemplates::publicContactConfirmation($data,$this->platformSettings($platformTid,'/'));
  return $this->directPlatformMail($platformTid,$to,$subject,$html,['event'=>'public_contact_confirmation','inquiry_id'=>$data['inquiry_id']??null,'source'=>$data['source']??'']);
 }
 private function logDirect(int $tid,string $to,string $subject,array $cfg,array $res,array $meta=[]):void{
  try{
   $event=(string)($meta['event']??'system');
   $typeCode=match(true){
    str_starts_with($event,'registration_'),$event==='user_lifecycle'=>'security',
    $event==='email_test'=>'email_tests',
    str_starts_with($event,'plan_'),str_starts_with($event,'subscription_'),$event==='free_account_welcome',$event==='new_customer'=>'company_lifecycle',
    $event==='channel_lifecycle'=>'channel_events',
    default=>'system_alerts'
   };
   $q=$this->pdo->prepare('SELECT correo_tipo_id FROM correo_tipo WHERE codigo=? LIMIT 1');$q->execute([$typeCode]);$type=$q->fetchColumn()?:null;
   $this->pdo->prepare('INSERT INTO notification_log(tenant_id,correo_tipo_id,channel,recipient,subject,status,provider,error_message,metadata_json,sent_at) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([$tid,$type,'email',$to,$subject,!empty($res['success'])?'sent':'failed',$cfg['metodo_envio']??'SYSTEM',!empty($res['success'])?null:($res['message']??'Error'),json_encode($meta,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),!empty($res['success'])?date('Y-m-d H:i:s'):null]);
  }catch(Throwable $e){}
 }
 public function sendTest(int $tid,string $to):array{if(!filter_var($to,FILTER_VALIDATE_EMAIL))return ['success'=>false,'message'=>'El usuario actual no tiene un correo válido.'];$cfg=$this->cfg($tid);if(!$cfg)return ['success'=>false,'message'=>'Primero guarda y activa un proveedor de correo.'];$q=$this->pdo->prepare('SELECT t.name,b.app_title,b.logo_path FROM tenants t LEFT JOIN branding_settings b ON b.tenant_id=t.id WHERE t.id=?');$q->execute([$tid]);$b=$q->fetch()?:[];$base=$this->publicBase();$settings=['company_name'=>$b['name']??'Tu empresa','app_title'=>$b['app_title']??'ZYNKO','logo_url'=>!empty($b['logo_path'])?$base.'/'.ltrim($b['logo_path'],'/'):'','app_url'=>$base.'/'];$html=EmailTemplates::test($settings);$res=$this->deliver($cfg,$to,'ZYNKO · Prueba de correo',$html);try{$this->pdo->prepare('INSERT INTO notification_log(tenant_id,channel,recipient,subject,status,provider,error_message,metadata_json,sent_at) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$tid,'email',$to,'ZYNKO · Prueba de correo',$res['success']?'sent':'failed',$cfg['metodo_envio']??'SYSTEM',$res['success']?null:$res['message'],json_encode(['event'=>'test'],JSON_UNESCAPED_UNICODE),$res['success']?date('Y-m-d H:i:s'):null]);}catch(Throwable $e){}return $res;}
 private function deliver(array $c,string $to,string $subject,string $html):array{return strtoupper((string)$c['metodo_envio'])==='GRAPH'?$this->graph($c,$to,$subject,$html):$this->smtp($c,$to,$subject,$html);}
 private function graph(array $c,string $to,string $subject,string $html):array{$tenant=trim((string)($c['tenant_graph_id']??''));$client=trim((string)($c['client_id']??''));$secret=$this->dec($c['client_secret']??'');$from=trim((string)($c['graph_user']??''));if(!$tenant||!$client||!$secret||!$from||!function_exists('curl_init'))return ['success'=>false,'message'=>'Microsoft Graph incompleto'];$ch=curl_init('https://login.microsoftonline.com/'.rawurlencode($tenant).'/oauth2/v2.0/token');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_POSTFIELDS=>http_build_query(['client_id'=>$client,'client_secret'=>$secret,'scope'=>'https://graph.microsoft.com/.default','grant_type'=>'client_credentials'])]);$raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$j=json_decode((string)$raw,true);if($code<200||$code>=300||empty($j['access_token']))return ['success'=>false,'message'=>'No se pudo autenticar Microsoft Graph'];$payload=['message'=>['subject'=>$subject,'body'=>['contentType'=>'HTML','content'=>$html],'toRecipients'=>[['emailAddress'=>['address'=>$to]]]],'saveToSentItems'=>(bool)($c['save_to_sent_items']??1)];$ch=curl_init('https://graph.microsoft.com/v1.0/users/'.rawurlencode($from).'/sendMail');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$j['access_token'],'Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload)]);curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);return $code===202?['success'=>true,'message'=>'Enviado']:['success'=>false,'message'=>'Error Graph HTTP '.$code];}
 private function smtp(array $c,string $to,string $subject,string $html):array{$host=trim((string)($c['server']??''));$port=(int)($c['port']??587);$secure=strtolower((string)($c['smtp_secure']??'tls'));$user=trim((string)($c['correo']??''));$pass=$this->dec($c['password']??'');if(!$host||!$user||!$pass)return ['success'=>false,'message'=>'SMTP incompleto'];$fp=@stream_socket_client(($secure==='ssl'?'ssl://':'tcp://').$host.':'.$port,$no,$err,12);if(!$fp)return ['success'=>false,'message'=>'No se pudo conectar SMTP'];stream_set_timeout($fp,15);$read=function()use($fp){$r='';while(($l=fgets($fp,515))!==false){$r.=$l;if(strlen($l)<4||$l[3]!=='-')break;}return (int)substr($r,0,3);};$cmd=function($s,$ok)use($fp,$read){fwrite($fp,$s."\r\n");$c=$read();if(!in_array($c,(array)$ok,true))throw new RuntimeException('SMTP '.$c);};try{$read();$cmd('EHLO '.($_SERVER['HTTP_HOST']??'localhost'),250);if($secure==='tls'){$cmd('STARTTLS',220);stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT);$cmd('EHLO '.($_SERVER['HTTP_HOST']??'localhost'),250);}$cmd('AUTH LOGIN',334);$cmd(base64_encode($user),334);$cmd(base64_encode($pass),235);$cmd('MAIL FROM:<'.$user.'>',250);$cmd('RCPT TO:<'.$to.'>',[250,251]);$cmd('DATA',354);$headers=['From: ZYNKO <'.$user.'>','To: <'.$to.'>','Subject: =?UTF-8?B?'.base64_encode($subject).'?=','MIME-Version: 1.0','Content-Type: text/html; charset=UTF-8','Content-Transfer-Encoding: base64'];fwrite($fp,implode("\r\n",$headers)."\r\n\r\n".chunk_split(base64_encode($html))."\r\n.\r\n");$read();@fwrite($fp,"QUIT\r\n");fclose($fp);return ['success'=>true,'message'=>'Enviado'];}catch(Throwable $e){@fclose($fp);return ['success'=>false,'message'=>$e->getMessage()];}}
}
