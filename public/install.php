<?php
/**
 * Installer mail preview uses the same transactional template engine as the dashboard.
 * This keeps SMTP/Graph tests visually identical everywhere in ZYNKO.
 */
function testEmailHtml(string $company='ZYNKO'): string {
  global $root;
  require_once $root.'/app/Services/EmailTemplates.php';
  $company=trim($company)!==''?trim($company):'ZYNKO';
  return EmailTemplates::test([
    'company_name'=>$company,
    'app_title'=>'ZYNKO',
    'app_url'=>function_exists('appUrl')?appUrl():'',
  ]);
}

session_start();
$root=dirname(__DIR__); $envFile=$root.'/.env'; $lock=$root.'/storage/installed.lock';
if (is_file($lock) && !(isset($_SESSION['install_done']) && (int)($_GET['step']??0)===5)) { header('Location: ./'); exit; }
if (!is_dir($root.'/storage')) @mkdir($root.'/storage',0775,true);
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function uuidv4(){ $d=random_bytes(16); $d[6]=chr((ord($d[6])&0x0f)|0x40); $d[8]=chr((ord($d[8])&0x3f)|0x80); return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($d),4)); }
function writeEnv($path,$data){$out=''; foreach($data as $k=>$v){$v=str_replace(["\r","\n"],'',(string)$v); $out.=$k.'="'.addcslashes($v,"\\\"")."\"\n";} return file_put_contents($path,$out,LOCK_EX)!==false;}
function appUrl(){
  $forwardedProto=trim(explode(',',(string)($_SERVER['HTTP_X_FORWARDED_PROTO']??''))[0]);
  $https=(!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off')||strtolower($forwardedProto)==='https'||(string)($_SERVER['SERVER_PORT']??'')==='443';
  $scheme=$https?'https':'http';
  $forwardedHost=trim(explode(',',(string)($_SERVER['HTTP_X_FORWARDED_HOST']??''))[0]);
  $host=$forwardedHost!==''?$forwardedHost:(string)($_SERVER['HTTP_HOST']??'localhost');
  $script=str_replace('\\','/',(string)($_SERVER['SCRIPT_NAME']??'/install.php'));
  $dir=rtrim(dirname($script),'/');
  // ZYNKO puede publicarse desde la raíz mediante el index frontal. No expongas /public en APP_URL.
  if ($dir==='/public' || str_ends_with($dir,'/public')) $dir=substr($dir,0,-7);
  return $scheme.'://'.$host.($dir!==''&&$dir!=='.'&&$dir!=='/'?$dir:'');
}
function installChecks(string $root): array {
  return [
    ['PHP 8.1 o superior',version_compare(PHP_VERSION,'8.1.0','>=')],
    ['PDO MySQL',extension_loaded('pdo_mysql')],
    ['OpenSSL',extension_loaded('openssl')],
    ['cURL (requerido para Microsoft Graph)',extension_loaded('curl')],
    ['Directorio storage escribible',is_dir($root.'/storage')&&is_writable($root.'/storage')],
    ['Carpeta del proyecto escribible para crear .env',is_writable($root)],
  ];
}
function pdoDb(array $d,bool $withDb=true){$dsn="mysql:host={$d['host']};port={$d['port']}".($withDb?";dbname={$d['database']}":'').";charset=utf8mb4";return new PDO($dsn,$d['username'],$d['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>true]);}
function encryptSecret(string $plain,string $hexKey): string { if($plain==='')return ''; $key=hex2bin($hexKey); $iv=random_bytes(12); $tag=''; $cipher=openssl_encrypt($plain,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag); if($cipher===false)throw new RuntimeException('No se pudo cifrar la credencial.'); return 'enc:v1:'.base64_encode($iv.$tag.$cipher); }

function smtpRead($fp): string { $out=''; while(($line=fgets($fp,515))!==false){$out.=$line;if(isset($line[3])&&$line[3]===' ')break;} return $out; }
function smtpExpect($fp,array $codes,string $label): string { $r=smtpRead($fp); $code=(int)substr($r,0,3); if(!in_array($code,$codes,true)) throw new RuntimeException($label.' (SMTP '.$code.').'); return $r; }
function smtpCmd($fp,string $cmd,array $codes,string $label): string { fwrite($fp,$cmd."\r\n"); return smtpExpect($fp,$codes,$label); }
function testSmtp(array $x): void {
  $host=trim($x['server']??'');$port=(int)($x['port']??587);$secure=strtolower(trim($x['smtp_secure']??'tls'));$user=trim($x['sender']??'');$pass=(string)($x['smtp_password']??'');$to=trim($x['recipient']??'')?:trim($_SESSION['install_owner_email']??'');
  if($host===''||$port<1||$port>65535||!in_array($secure,['tls','ssl'],true)||!filter_var($user,FILTER_VALIDATE_EMAIL)||$pass===''||!filter_var($to,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Completa correctamente los datos SMTP antes de probar.');
  $target=($secure==='ssl'?'ssl://':'').$host.':'.$port;$errno=0;$errstr='';$fp=@stream_socket_client($target,$errno,$errstr,12,STREAM_CLIENT_CONNECT);if(!$fp)throw new RuntimeException('No se pudo conectar al servidor SMTP: '.$errstr);stream_set_timeout($fp,12);
  try{smtpExpect($fp,[220],'El servidor SMTP no respondió correctamente');smtpCmd($fp,'EHLO zynko.local',[250],'EHLO rechazado');if($secure==='tls'){smtpCmd($fp,'STARTTLS',[220],'STARTTLS no disponible');if(!stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new RuntimeException('No se pudo iniciar TLS con el servidor SMTP.');smtpCmd($fp,'EHLO zynko.local',[250],'EHLO después de TLS rechazado');}smtpCmd($fp,'AUTH LOGIN',[334],'El servidor no aceptó autenticación');smtpCmd($fp,base64_encode($user),[334],'Usuario SMTP rechazado');smtpCmd($fp,base64_encode($pass),[235],'Credenciales SMTP rechazadas');smtpCmd($fp,'MAIL FROM:<'.$user.'>',[250],'Correo emisor rechazado');smtpCmd($fp,'RCPT TO:<'.$to.'>',[250,251],'Destino de prueba rechazado');smtpCmd($fp,'DATA',[354],'El servidor no aceptó el mensaje');$subject='ZYNKO - Prueba de correo';$body=testEmailHtml($_POST['company']??'ZYNKO');$msg='From: ZYNKO <'.$user.">\r\n".'To: <'.$to.">\r\n".'Subject: '.$subject."\r\n".'MIME-Version: 1.0'."\r\n".'Content-Type: text/html; charset=UTF-8'."\r\n\r\n".$body."\r\n.";smtpCmd($fp,$msg,[250],'No se pudo enviar el correo de prueba');@smtpCmd($fp,'QUIT',[221,250],'QUIT');}finally{fclose($fp);}
}
function testGraph(array $x): void {
  if(!function_exists('curl_init'))throw new RuntimeException('PHP cURL es requerido para probar Microsoft Graph.');$tenant=trim($x['graph_tenant']??'');$client=trim($x['client_id']??'');$secret=(string)($x['client_secret']??'');$user=trim($x['graph_user']??'');$to=trim($x['recipient']??'')?:trim($_SESSION['install_owner_email']??'');if($tenant===''||$client===''||$secret===''||!filter_var($user,FILTER_VALIDATE_EMAIL)||!filter_var($to,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Completa correctamente Tenant ID, Client ID, Client Secret y buzón de Microsoft Graph antes de probar.');
  $ch=curl_init('https://login.microsoftonline.com/'.rawurlencode($tenant).'/oauth2/v2.0/token');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded'],CURLOPT_POSTFIELDS=>http_build_query(['client_id'=>$client,'client_secret'=>$secret,'scope'=>'https://graph.microsoft.com/.default','grant_type'=>'client_credentials'])]);$raw=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$cerr=curl_error($ch);curl_close($ch);if($raw===false||$http<200||$http>=300){$j=json_decode((string)$raw,true);$msg=$j['error_description']??$cerr?:'No se pudo obtener el token de Microsoft Graph.';throw new RuntimeException('Graph: '.$msg);} $token=json_decode($raw,true)['access_token']??'';if($token==='')throw new RuntimeException('Microsoft Graph no devolvió un access token.');
  $payload=['message'=>['subject'=>'ZYNKO - Prueba de correo','body'=>['contentType'=>'HTML','content'=>testEmailHtml($_POST['company']??'ZYNKO')],'toRecipients'=>[['emailAddress'=>['address'=>$to]]]],'saveToSentItems'=>true];$url='https://graph.microsoft.com/v1.0/users/'.rawurlencode($user).'/sendMail';$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_SLASHES)]);$raw=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$cerr=curl_error($ch);curl_close($ch);if($raw===false||$http<200||$http>=300){$j=json_decode((string)$raw,true);$msg=$j['error']['message']??$cerr?:'No se pudo enviar el correo mediante Microsoft Graph.';throw new RuntimeException('Graph: '.$msg);}
}

function finalizeInstall($lock){ if(file_put_contents($lock,date(DATE_ATOM),LOCK_EX)===false) throw new RuntimeException('No se pudo crear storage/installed.lock. Revisa permisos.'); $_SESSION['install_done']=true; }
$step=max(1,min(5,(int)($_GET['step']??1))); $error=''; $notice='';
// V2.26.1: una entrada nueva al instalador siempre comienza limpia.
// Evita reutilizar credenciales, prefijos o nombres de BD de intentos anteriores en la misma sesión del navegador.
if($_SERVER['REQUEST_METHOD']==='GET' && $step===1 && !is_file($lock)){
  foreach(array_keys($_SESSION) as $k){ if(str_starts_with((string)$k,'install_') || in_array($k,['mail_configured'],true)) unset($_SESSION[$k]); }
}
$flash=$_SESSION['install_flash']??null; unset($_SESSION['install_flash']);
$defaults=['host'=>'localhost','port'=>'3306','db_prefix'=>'','database'=>'zynko','username'=>'root','password'=>''];
$checks=installChecks($root); $requiredChecksOk=!in_array(false,array_column($checks,1),true);
if($_SERVER['REQUEST_METHOD']==='POST'){
  $action=$_POST['action']??'';
  if($action==='database'){
    foreach($defaults as $k=>$v) $_SESSION['install_db'][$k]=trim((string)($_POST[$k]??$v));
    try{$d=$_SESSION['install_db'];$base=preg_replace('/[^a-zA-Z0-9_]/','',$d['database']);$prefix=preg_replace('/[^a-zA-Z0-9_]/','',$d['db_prefix']??'');if(!$base||$base!==$d['database'])throw new Exception('Nombre de base de datos inválido. Usa solo letras, números y guion bajo.');if($prefix!==($d['db_prefix']??''))throw new Exception('Prefijo inválido. Usa solo letras, números y guion bajo.');if($prefix!==''&&!str_ends_with($prefix,'_'))$prefix.='_';$safe=($prefix!==''&&!str_starts_with($base,$prefix))?$prefix.$base:$base;
      // Primero probar la base final. En cPanel normalmente la BD ya debe existir y el usuario debe estar asignado.
      $probe=$d;$probe['database']=$safe;
      try{
        pdoDb($probe,true);
      }catch(Throwable $directError){
        // Si no pudimos abrirla, intentamos crearla solo para hostings que sí conceden CREATE DATABASE.
        try{
          $serverPdo=pdoDb($d,false);
          $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `$safe` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
          pdoDb($probe,true);
        }catch(Throwable $createOrConnectError){
          $msg=$directError->getMessage();
          $detail=$createOrConnectError->getMessage();
          $combined=$msg.' '.$detail;
          if(preg_match('/Access denied|SQLSTATE\\[HY000\\] \\[1045\\]/i',$combined)){
            throw new Exception("La base `$safe` existe o fue indicada, pero MySQL rechazó el usuario o la contraseña. Verifica las credenciales y que el usuario `{$d['username']}` esté asignado a esa base en cPanel con los permisos necesarios.");
          }
          if(preg_match('/1044|access denied for user.*database/i',$combined)){
            throw new Exception("La base `$safe` existe, pero el usuario `{$d['username']}` no tiene permisos para usarla. Asígnalo a la base desde cPanel → MySQL Databases y concede los permisos necesarios.");
          }
          if(preg_match('/1049|Unknown database/i',$combined)){
            throw new Exception("La base `$safe` no existe o MySQL no puede verla. Créala primero desde cPanel y asigna el usuario `{$d['username']}`.");
          }
          if(preg_match('/2002|Connection refused|No such file|php_network_getaddresses|server has gone away/i',$combined)){
            throw new Exception("No fue posible conectar con el servidor MySQL `{$d['host']}:{$d['port']}`. Revisa el Host MySQL indicado por tu proveedor; en algunos hosting no es `localhost`.");
          }
          throw new Exception("No fue posible abrir la base `$safe` con el usuario `{$d['username']}`. MySQL respondió: ".$msg);
        }
      }
      $_SESSION['install_db']['database']=$base;$_SESSION['install_db']['database_full']=$safe;$_SESSION['install_db']['db_prefix']=$prefix;$_SESSION['install_flash']=['type'=>'success','title'=>'Base de datos conectada','message'=>'Conexión MySQL validada correctamente con la base '.$safe.'.'];header('Location: ?step=3');exit;}catch(Throwable $e){$error='No se pudo conectar: '.$e->getMessage();$step=2;}
  }
  if($action==='install'){
    try{
      $d=$_SESSION['install_db']??null;if(!$d)throw new Exception('Primero configura la base de datos.');$d['database']=$d['database_full']??$d['database'];$pdo=pdoDb($d,true);
      $sql=file_get_contents($root.'/database/schema.sql');if($sql===false)throw new Exception('No se pudo leer database/schema.sql.');$sql=preg_replace('/CREATE DATABASE IF NOT EXISTS[^;]+;/i','',$sql);$sql=preg_replace('/USE\s+[^;]+;/i','',$sql);$pdo->exec($sql);
      $email=trim($_POST['email']??'');$name=trim($_POST['name']??'');$company=trim($_POST['company']??'');$pass=$_POST['password']??'';$locale=($_POST['locale']??'es')==='en'?'en':'es';
      if($company===''||$name==='')throw new Exception('Empresa y administrador son obligatorios.');if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($pass)<8)throw new Exception('Usa un correo válido y una contraseña de al menos 8 caracteres.');
      $pdo->beginTransaction();$st=$pdo->prepare('INSERT INTO tenants(uuid,name,slug,status,locale,bot_name) VALUES(?,?,?,?,?,?)');$slug=strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/','-',$company),'-')).'-'.substr(bin2hex(random_bytes(3)),0,6);$st->execute([uuidv4(),$company,$slug,'active',$locale,'NIVO']);$tid=(int)$pdo->lastInsertId();
      $st=$pdo->prepare('INSERT INTO users(uuid,name,email,password_hash,locale,status) VALUES(?,?,?,?,?,?)');$st->execute([uuidv4(),$name,$email,password_hash($pass,PASSWORD_DEFAULT),$locale,'active']);$uid=(int)$pdo->lastInsertId();$pdo->prepare("INSERT INTO tenant_users(tenant_id,user_id,role_code,is_owner) VALUES(?,?,'owner',1)")->execute([$tid,$uid]);$pdo->prepare("INSERT INTO bot_profiles(tenant_id,name,enabled,mode) VALUES(?,'NIVO',0,'hybrid')")->execute([$tid]);$pdo->prepare("INSERT INTO branding_settings(tenant_id,app_title,primary_color,secondary_color) VALUES(?,'ZYNKO','#13a88a','#0b1625')")->execute([$tid]);$pdo->commit();
      $key=bin2hex(random_bytes(32));$url=trim($_POST['app_url']??'')?:appUrl();if(!writeEnv($envFile,['APP_ENV'=>'production','APP_URL'=>$url,'APP_KEY'=>$key,'DB_HOST'=>$d['host'],'DB_PORT'=>$d['port'],'DB_DATABASE'=>$d['database'],'DB_USERNAME'=>$d['username'],'DB_PASSWORD'=>$d['password'],'META_APP_ID'=>'','META_APP_SECRET'=>'','META_VERIFY_TOKEN'=>'','META_GRAPH_VERSION'=>'','WS_HOST'=>'localhost','WS_PORT'=>'8080','WS_PUBLIC_HOST'=>parse_url($url,PHP_URL_HOST)?:'localhost']))throw new Exception('No se pudo escribir .env. Revisa permisos.');
      $_SESSION['install_tenant_id']=$tid;$_SESSION['install_owner_email']=$email;$_SESSION['install_app_key']=$key;$_SESSION['install_flash']=['type'=>'success','title'=>'Cuenta principal creada','message'=>'Tablas, empresa y administrador creados correctamente. Ahora puedes configurar el correo o dejarlo para después.'];header('Location: ?step=4');exit;
    }catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();$error='Instalación detenida: '.$e->getMessage();$step=3;}
  }
  if($action==='mail_test'){
    header('Content-Type: application/json; charset=utf-8');
    try{$method=strtoupper(trim($_POST['method']??'SMTP'));if($method==='GRAPH')testGraph($_POST);elseif($method==='SMTP')testSmtp($_POST);else throw new RuntimeException('Método de correo inválido.');echo json_encode(['ok'=>true,'message'=>'Prueba completada. Se envió un correo de prueba correctamente.'],JSON_UNESCAPED_UNICODE);}
    catch(Throwable $e){http_response_code(422);echo json_encode(['ok'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}exit;
  }
  if($action==='mail_save' || $action==='mail_skip'){
    try{
      $d=$_SESSION['install_db']??null;$tid=(int)($_SESSION['install_tenant_id']??0);if(!$d||!$tid)throw new Exception('La instalación principal no está completa.');$d['database']=$d['database_full']??$d['database'];$pdo=pdoDb($d,true);
      if($action==='mail_save'){
        $method=strtoupper(trim($_POST['method']??'SMTP'));if(!in_array($method,['SMTP','GRAPH'],true))throw new Exception('Método de correo inválido.');$sender=trim($method==='GRAPH'?($_POST['graph_user']??''):($_POST['sender']??''));if(!filter_var($sender,FILTER_VALIDATE_EMAIL))throw new Exception($method==='GRAPH'?'Escribe un buzón / correo emisor válido.':'Escribe un correo emisor válido.');$key=$_SESSION['install_app_key']??'';if(!preg_match('/^[a-f0-9]{64}$/',$key))throw new Exception('No se encontró la clave de cifrado de la instalación.');
        $type=(int)$pdo->query("SELECT correo_tipo_id FROM correo_tipo WHERE codigo='email_tests' LIMIT 1")->fetchColumn();if(!$type)throw new Exception('No existe el tipo de correo de prueba.');
        if($method==='SMTP'){$server=trim($_POST['server']??'');$port=(int)($_POST['port']??587);$secure=strtolower($_POST['smtp_secure']??'tls');$secret=$_POST['smtp_password']??'';if($server===''||$port<1||$port>65535||!in_array($secure,['tls','ssl'],true)||$secret==='')throw new Exception('Completa servidor, puerto, seguridad y contraseña SMTP.');$st=$pdo->prepare("INSERT INTO correo(tenant_id,correo_tipo_id,nombre,metodo_envio,server,correo,destinatario,password,port,smtp_secure,estado,is_default) VALUES(?,?,'Principal','SMTP',?,?,?,?,?,?,1,1)");$st->execute([$tid,$type,$server,$sender,(trim($_POST['recipient']??'')!==''?trim($_POST['recipient']):($_SESSION['install_owner_email']??'')),encryptSecret($secret,$key),$port,$secure]);}
        else{$gt=trim($_POST['graph_tenant']??'');$cid=trim($_POST['client_id']??'');$sec=$_POST['client_secret']??'';$guser=trim($_POST['graph_user']??$sender);if($gt===''||$cid===''||$sec===''||!filter_var($guser,FILTER_VALIDATE_EMAIL))throw new Exception('Completa Tenant ID, Client ID, Client Secret y buzón de Microsoft Graph.');$st=$pdo->prepare("INSERT INTO correo(tenant_id,correo_tipo_id,nombre,metodo_envio,correo,destinatario,tenant_graph_id,client_id,client_secret,graph_user,save_to_sent_items,estado,is_default) VALUES(?,?,'Principal','GRAPH',?,?,?,?,?,?,1,1,1)");$st->execute([$tid,$type,$sender,(trim($_POST['recipient']??'')!==''?trim($_POST['recipient']):($_SESSION['install_owner_email']??'')),$gt,$cid,encryptSecret($sec,$key),$guser]);}
        $pdo->prepare("INSERT INTO notification_preferences(tenant_id,correo_tipo_id,email_enabled,in_app_enabled) SELECT ?,correo_tipo_id,1,1 FROM correo_tipo WHERE activo=1 ON DUPLICATE KEY UPDATE email_enabled=VALUES(email_enabled),in_app_enabled=VALUES(in_app_enabled)")->execute([$tid]);$_SESSION['mail_configured']=true;try{require_once $root.'/app/Services/NotificationService.php';(new NotificationService($pdo,$root))->sendAccountCreated($tid,(string)($_SESSION['install_owner_email']??''));}catch(Throwable $mailWelcomeError){}
      } else { $_SESSION['mail_configured']=false; }
      finalizeInstall($lock);header('Location: ?step=5');exit;
    }catch(Throwable $e){$error='Correo no guardado: '.$e->getMessage();$step=4;}
  }
}
$db=array_merge($defaults,$_SESSION['install_db']??[]);$dbPreview=(string)($db['database_full']??(($db['db_prefix']??'').($db['database']??'zynko')));$mailMethod=strtoupper(trim($_POST['method']??'LATER'));if(!in_array($mailMethod,['LATER','SMTP','GRAPH'],true))$mailMethod='LATER';
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Instalar ZYNKO</title><link rel="stylesheet" href="assets/vendor/select2/select2.min.css"><link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css"><link rel="stylesheet" href="assets/css/zynko-ui.css"><style>:root{--p:#13a88a;--n:#0b1625;--bg:#f5f7fb;--line:#e4eaf0;--muted:#6f7f91;--warn:#a76600}*{box-sizing:border-box}html,body{width:100%;max-width:100%;margin:0;overflow-x:clip}body{font:14px/1.5 Inter,system-ui,sans-serif;background:var(--bg);color:#152235}.wrap{width:calc(100% - 28px);max-width:960px;margin:4vh auto;min-width:0}.brand{display:flex;align-items:center;gap:12px;margin-bottom:22px}.mark{width:42px;height:42px;border-radius:13px;background:var(--p);color:#fff;display:grid;place-items:center;font-weight:900}.card{background:#fff;border:1px solid var(--line);border-radius:20px;padding:28px;box-shadow:0 20px 55px rgba(20,40,60,.08)}.steps{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:24px}.steps div{padding:11px;border:1px solid var(--line);border-radius:11px;color:var(--muted)}.steps .on{border-color:var(--p);color:#08705c;background:#f0fbf8}.grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:16px;min-width:0}.field{display:grid;grid-template-rows:auto minmax(44px,auto) auto;align-content:start;gap:7px;min-width:0;max-width:100%}.field>label{min-height:21px;display:flex;align-items:flex-end}.field.full{grid-template-rows:auto minmax(44px,auto) auto}.field label{font-weight:700}.field input,.field select{width:100%;min-height:44px;border:1px solid var(--line);border-radius:11px;padding:10px 12px;font:inherit}.select2-container{width:100%!important;max-width:100%!important;min-width:0!important}.select2-container .select2-selection--single{height:44px!important;border:1px solid var(--line)!important;border-radius:11px!important;background:#fff!important}.select2-container .select2-selection--single .select2-selection__rendered{line-height:42px!important;padding-left:12px!important;color:#152235!important}.select2-container .select2-selection--single .select2-selection__arrow{height:42px!important;right:8px!important}.select2-dropdown{max-width:100vw!important;border:1px solid var(--line)!important;border-radius:11px!important;overflow:hidden;box-shadow:0 12px 28px rgba(20,40,60,.12)}.select2-results__option{padding:10px 12px!important}.select2-results__option--highlighted.select2-results__option--selectable{background:var(--p)!important}.select2-search__field{border:1px solid var(--line)!important;border-radius:8px!important;padding:8px!important}.full{grid-column:1/-1}.actions{display:flex;justify-content:flex-end;align-items:center;gap:10px;margin-top:22px;flex-wrap:wrap}.btn{border:0;border-radius:11px;padding:11px 16px;font-weight:700;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:44px}.btn:disabled{opacity:.65;cursor:not-allowed}.btn i{width:16px;text-align:center}.primary{background:var(--p);color:#fff}.secondary{background:#eef3f7;color:#203044}.error{padding:12px;border:1px solid #f0c5c5;background:#fff6f6;border-radius:10px;margin-bottom:16px}.note{padding:14px;background:#f8fafc;border:1px solid var(--line);border-radius:12px;color:var(--muted)}.warning{background:#fff9ee;border-color:#f0d8ad;color:#79500a}.mailbox{margin-top:18px;padding:18px;border:1px solid var(--line);border-radius:14px}h1{margin:0 0 7px}p{color:var(--muted)}small{color:var(--muted)}@media(max-width:760px){.grid,.steps{grid-template-columns:1fr}.card{padding:20px}.steps div{display:none}.steps .on{display:block}.full{grid-column:auto}.actions .btn{width:100%;text-align:center}}.mail-premium{padding:0!important;border:0!important}.mail-choice-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:16px 0}.mail-choice{position:relative;display:grid;grid-template-columns:42px 1fr 24px;gap:11px;align-items:start;min-height:112px;padding:16px;border:1px solid var(--line);border-radius:15px;background:#fff;cursor:pointer;transition:border-color .18s,box-shadow .18s,transform .18s}.mail-choice:hover{border-color:#b8dcd5;transform:translateY(-1px)}.mail-choice.is-selected{border-color:var(--p);background:#f3fbf9;box-shadow:0 0 0 3px rgba(19,168,138,.08)}.mail-choice>input{position:absolute;opacity:0;pointer-events:none}.mail-choice-icon{width:42px;height:42px;border-radius:12px;background:#f1f5f8;color:#53677c;display:grid;place-items:center;font-size:16px}.mail-choice.is-selected .mail-choice-icon{background:#dff5ef;color:#078b78}.mail-choice-copy{display:flex;flex-direction:column;gap:5px;min-width:0}.mail-choice-copy strong{font-size:14px;color:#142238}.mail-choice-copy small{line-height:1.35}.mail-choice-check{width:22px;height:22px;border:1px solid #d7e0e8;border-radius:50%;display:grid;place-items:center;color:transparent;font-size:10px}.mail-choice.is-selected .mail-choice-check{background:var(--p);border-color:var(--p);color:#fff}.mail-config-panel{margin-top:12px;padding:16px;border:1px solid var(--line);border-radius:14px;background:#fbfcfd}.mail-later-state{display:flex;align-items:flex-start;gap:12px}.mail-later-state>span{width:36px;height:36px;display:grid;place-items:center;border-radius:10px;background:#eaf5f2;color:#078b78;flex:0 0 auto}.mail-later-state p{margin:3px 0 0}.mail-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:14px}.mail-footer-right{display:flex;align-items:center;justify-content:flex-end;gap:10px;flex-wrap:wrap}@media(max-width:760px){.mail-choice-grid{grid-template-columns:1fr}.mail-choice{min-height:auto}.mail-footer,.mail-footer-right{display:grid;grid-template-columns:1fr;width:100%}.mail-footer .btn{width:100%}}</style>
<style id="zynko-installer-compact-v2253">
/* V2.26.1 — installer compact, aligned and responsive */
.installer-shell,.install-shell,.wizard-shell{min-height:auto!important}
.installer-card,.install-card,.wizard-card,.card{
    max-width:980px!important;margin:18px auto!important;
}
.installer-card,.install-card,.wizard-card{padding:22px 24px!important}
.install-head,.installer-head,.wizard-head{margin-bottom:14px!important}
.install-steps,.installer-steps,.wizard-steps{
    gap:10px!important;margin-bottom:20px!important;
}
.install-steps>* ,.installer-steps>* ,.wizard-steps>*{
    min-height:42px!important;padding:9px 12px!important;
}
.install-content h1,.install-content h2,.installer-content h1,.installer-content h2,
.wizard-content h1,.wizard-content h2{margin:0 0 10px!important}
.install-content p,.installer-content p,.wizard-content p{margin-top:0!important;margin-bottom:12px!important}
.install-content .notice,.installer-content .notice,.wizard-content .notice,
.install-content .alert,.installer-content .alert,.wizard-content .alert{
    margin:12px 0 16px!important;padding:12px 14px!important;
}
.install-content form,.installer-content form,.wizard-content form{margin:0!important}
.install-content .grid,.installer-content .grid,.wizard-content .grid,
.install-content .form-grid,.installer-content .form-grid,.wizard-content .form-grid{
    gap:12px 14px!important;
}
.install-content label,.installer-content label,.wizard-content label{
    margin-bottom:5px!important;
}
.install-content input,.install-content select,.installer-content input,.installer-content select,
.wizard-content input,.wizard-content select{
    min-height:42px!important;height:42px!important;padding:9px 12px!important;
}
.install-content textarea,.installer-content textarea,.wizard-content textarea{
    min-height:88px!important;padding:10px 12px!important;
}
.install-content small,.installer-content small,.wizard-content small{
    display:block!important;margin-top:5px!important;line-height:1.35!important;
}
.install-actions,.installer-actions,.wizard-actions{
    margin-top:16px!important;padding-top:0!important;gap:10px!important;
}
.install-actions button,.installer-actions button,.wizard-actions button,
.install-actions .btn,.installer-actions .btn,.wizard-actions .btn{
    min-height:42px!important;
}
@media (max-width:760px){
  .installer-card,.install-card,.wizard-card,.card{margin:10px!important;max-width:none!important}
  .installer-card,.install-card,.wizard-card{padding:16px!important}
  .install-steps,.installer-steps,.wizard-steps{
      display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:7px!important
  }
  .install-content .grid,.installer-content .grid,.wizard-content .grid,
  .install-content .form-grid,.installer-content .form-grid,.wizard-content .form-grid{
      grid-template-columns:1fr!important
  }
}
@media (max-width:430px){
  .install-steps,.installer-steps,.wizard-steps{grid-template-columns:1fr!important}
}
</style>

<link rel="icon" href="assets/img/favicon.svg" type="image/x-icon">

<style id="zynko-installer-fit-v2254">
html,body{min-height:100%;height:auto!important}
body{margin:0!important;padding:0!important;overflow-x:hidden!important}
.wrap,.installer-wrap,.install-wrap,.page-wrap{padding-top:10px!important;padding-bottom:10px!important}
.installer-card,.install-card,.wizard-card,.card{margin:8px auto!important;max-width:980px!important}
.installer-card,.install-card,.wizard-card{padding:16px 20px!important}
.install-brand,.installer-brand,.brand{margin:6px auto 10px!important}
.install-steps,.installer-steps,.wizard-steps{margin:0 0 12px!important;gap:8px!important}
.install-steps>* ,.installer-steps>* ,.wizard-steps>*{min-height:36px!important;padding:7px 10px!important}
.install-content h1,.install-content h2,.installer-content h1,.installer-content h2,.wizard-content h1,.wizard-content h2{margin:0 0 6px!important;line-height:1.15!important}
.install-content p,.installer-content p,.wizard-content p{margin:0 0 8px!important;line-height:1.35!important}
.install-content .notice,.installer-content .notice,.wizard-content .notice,.install-content .alert,.installer-content .alert,.wizard-content .alert{margin:8px 0 10px!important;padding:9px 11px!important;line-height:1.3!important}
.install-content .grid,.installer-content .grid,.wizard-content .grid,.install-content .form-grid,.installer-content .form-grid,.wizard-content .form-grid{gap:8px 12px!important}
.install-content label,.installer-content label,.wizard-content label{margin:0 0 3px!important;line-height:1.2!important}
.install-content input,.install-content select,.installer-content input,.installer-content select,.wizard-content input,.wizard-content select{height:36px!important;min-height:36px!important;padding:6px 10px!important}
.install-content small,.installer-content small,.wizard-content small{margin-top:3px!important;line-height:1.2!important}
.install-actions,.installer-actions,.wizard-actions{margin-top:10px!important;gap:8px!important}
.install-actions button,.installer-actions button,.wizard-actions button,.install-actions .btn,.installer-actions .btn,.wizard-actions .btn{min-height:38px!important;padding-top:7px!important;padding-bottom:7px!important}
@media (min-width:900px) and (min-height:650px){
 body{overflow-y:auto!important}
}
@media(max-width:760px){
 .installer-card,.install-card,.wizard-card,.card{margin:6px 8px!important}
 .installer-card,.install-card,.wizard-card{padding:13px!important}
 .install-steps,.installer-steps,.wizard-steps{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important}
 .install-content .grid,.installer-content .grid,.wizard-content .grid,.install-content .form-grid,.installer-content .form-grid,.wizard-content .form-grid{grid-template-columns:1fr!important}
}
</style>


<style id="zynko-installer-viewport-v2256">
/* Desktop: fit the complete wizard step inside the visible viewport. */
@media (min-width: 900px) and (min-height: 650px){
  html,body{height:100%!important;min-height:100%!important;overflow:hidden!important}
  body{display:flex!important;align-items:flex-start!important;justify-content:center!important}
  .wrap,.installer-wrap,.install-wrap,.page-wrap{
    width:100%!important;min-height:0!important;height:100vh!important;
    padding:8px 16px!important;box-sizing:border-box!important;overflow:hidden!important
  }
  .install-brand,.installer-brand,.brand{
    margin:0 auto 6px!important;min-height:42px!important
  }
  .installer-card,.install-card,.wizard-card,.card{
    max-width:980px!important;margin:0 auto!important;
  }
  .installer-card,.install-card,.wizard-card{
    padding:12px 18px!important;max-height:calc(100vh - 66px)!important;
    overflow:hidden!important;box-sizing:border-box!important
  }
  .install-steps,.installer-steps,.wizard-steps{margin:0 0 8px!important;gap:8px!important}
  .install-steps>* ,.installer-steps>* ,.wizard-steps>*{min-height:34px!important;height:34px!important;padding:5px 9px!important}
  .install-content h1,.install-content h2,.installer-content h1,.installer-content h2,.wizard-content h1,.wizard-content h2{
    margin:0 0 5px!important;font-size:25px!important;line-height:1.1!important
  }
  .install-content p,.installer-content p,.wizard-content p{margin:0 0 6px!important;line-height:1.25!important}
  .install-content .notice,.installer-content .notice,.wizard-content .notice,
  .install-content .alert,.installer-content .alert,.wizard-content .alert{
    margin:6px 0 8px!important;padding:7px 10px!important;line-height:1.22!important
  }
  .install-content .grid,.installer-content .grid,.wizard-content .grid,
  .install-content .form-grid,.installer-content .form-grid,.wizard-content .form-grid{
    gap:6px 12px!important
  }
  .install-content label,.installer-content label,.wizard-content label{margin:0 0 2px!important;line-height:1.15!important}
  .install-content input,.install-content select,.installer-content input,.installer-content select,
  .wizard-content input,.wizard-content select{height:34px!important;min-height:34px!important;padding:5px 9px!important}
  .install-content small,.installer-content small,.wizard-content small{margin-top:2px!important;line-height:1.15!important}
  .install-actions,.installer-actions,.wizard-actions{
    margin-top:8px!important;padding-top:0!important;gap:8px!important;display:flex!important;justify-content:space-between!important;align-items:center!important
  }
  .install-actions button,.installer-actions button,.wizard-actions button,
  .install-actions .btn,.installer-actions .btn,.wizard-actions .btn{
    min-height:36px!important;height:36px!important;padding:6px 12px!important
  }
}
@media (max-width:899px),(max-height:649px){
 html,body{overflow-y:auto!important}
}
</style>

<style id="zynko-installer-db-v2257">
.install-hosting-note{margin:5px 0 7px!important;padding:7px 10px!important}.db-final-preview{display:flex!important;align-items:center!important;justify-content:space-between!important;gap:12px!important;background:#f3faf8!important;border:1px solid #ccece5!important;border-radius:10px!important;padding:6px 10px!important;min-height:34px!important}.db-final-preview span{display:inline-flex!important;align-items:center!important;gap:7px!important;color:#52677d!important}.db-final-preview strong{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace!important;color:#07364a!important;overflow-wrap:anywhere!important;text-align:right!important}.password-wrap{position:relative!important;width:100%!important}.password-wrap input{width:100%!important;padding-right:42px!important;box-sizing:border-box!important}.password-toggle{position:absolute!important;right:4px!important;top:50%!important;transform:translateY(-50%)!important;width:32px!important;height:28px!important;min-height:28px!important;padding:0!important;border:0!important;background:transparent!important;color:#52677d!important;display:grid!important;place-items:center!important;cursor:pointer!important;border-radius:7px!important}.password-toggle:hover{background:#eaf5f2!important;color:#078b78!important}@media (min-width:900px) and (min-height:650px){.db-grid{gap:4px 12px!important}.db-final-preview{grid-column:1/-1!important}.actions{margin-top:5px!important}}
</style>

<style id="zynko-installer-buttons-v2266">
/* Installer premium actions: no white action buttons. */
.btn.soft,.btn.secondary{background:#0B2A3C!important;color:#fff!important;border:1px solid #0B2A3C!important;box-shadow:0 7px 16px rgba(11,42,60,.12)!important}
.btn.soft:hover,.btn.secondary:hover{background:#123A50!important;border-color:#123A50!important;color:#fff!important}
.btn.primary{background:#13A88A!important;color:#fff!important;border:1px solid #13A88A!important;box-shadow:0 7px 16px rgba(19,168,138,.16)!important}
.btn.primary:hover{background:#0E9278!important;border-color:#0E9278!important}
.btn:focus-visible{outline:3px solid rgba(19,168,138,.22)!important;outline-offset:2px!important}
.btn:disabled{background:#607487!important;border-color:#607487!important;color:#fff!important;opacity:.72!important}
.mail-footer .btn,.actions .btn{white-space:nowrap!important}
</style>
</head><body><div class="wrap"><div class="brand"><div class="mark">Z</div><div><b>ZYNKO</b><div style="color:#6f7f91">Asistente de instalación</div></div></div><section class="card"><?php if($error):?><div id="installFlash" hidden data-type="error" data-title="No se pudo continuar" data-message="<?=h($error)?>"></div><?php elseif(is_array($flash)):?><div id="installFlash" hidden data-type="<?=h($flash['type']??'info')?>" data-title="<?=h($flash['title']??'ZYNKO')?>" data-message="<?=h($flash['message']??'')?>"></div><?php elseif($notice):?><div id="installFlash" hidden data-type="info" data-title="Información" data-message="<?=h($notice)?>"></div><?php endif?><div class="steps"><?php foreach(['Bienvenida','Base de datos','Administrador','Correo','Finalizar'] as $i=>$s):?><div class="<?=$step===$i+1?'on':''?>"><?=($i+1).'. '.h($s)?></div><?php endforeach?></div><?php if($error):?><noscript><div class="error"><?=h($error)?></div></noscript><?php endif?>
<?php if($step===1):?><h1>Instalemos ZYNKO</h1><p>El asistente preparará la base de datos, creará todas las tablas y configurará la primera empresa y su administrador. Funciona tanto en entorno local como en hosting compatible y no requiere importar SQL manualmente.</p><div class="note"><b><i class="fa-solid fa-list-check"></i> Verificación del servidor</b><div style="display:grid;gap:8px;margin-top:12px"><?php foreach($checks as [$label,$ok]):?><div style="display:flex;align-items:center;gap:9px"><i class="fa-solid <?=$ok?'fa-circle-check':'fa-circle-xmark'?>" style="color:<?=$ok?'#087d69':'#c0392b'?>"></i><span><?=h($label)?></span></div><?php endforeach?></div></div><div class="note" style="margin-top:12px"><b><i class="fa-solid fa-link"></i> URL detectada</b><br><?=h(appUrl())?></div><div class="actions"><?php if($requiredChecksOk):?><a class="btn primary" href="?step=2"><i class="fa-solid fa-arrow-right"></i> Comenzar instalación</a><?php else:?><button class="btn secondary" type="button" disabled><i class="fa-solid fa-triangle-exclamation"></i> Corrige los requisitos para continuar</button><?php endif?></div>
<?php elseif($step===2):?><h1>Conexión a MySQL</h1><p>ZYNKO probará primero las credenciales. Si el servidor permite crear bases, puede crearla automáticamente; si el hosting exige una base precreada, validará el acceso a esa base existente.</p><div class="note warning install-hosting-note"><b><i class="fa-solid fa-circle-info"></i> Importante en algunos hosting</b><br>Algunos proveedores requieren crear primero la base de datos y asignarle un usuario MySQL con permisos desde su panel (por ejemplo, cPanel). <b>Si aplica en tu hosting, hazlo antes de continuar.</b></div><form method="post"><input type="hidden" name="action" value="database"><div class="grid db-grid"><div class="field"><label>Servidor / Host</label><input name="host" value="<?=h($db['host'])?>" required></div><div class="field"><label>Puerto</label><input name="port" value="<?=h($db['port'])?>" required inputmode="numeric"></div><div class="field"><label>Prefijo del hosting (opcional)</label><input name="db_prefix" id="dbPrefix" value="<?=h($db['db_prefix']??'')?>" placeholder="Ej. cuenta_"><small>Ejemplo: esmultiservicios_</small></div><div class="field"><label>Nombre de la base de datos</label><input name="database" id="dbName" value="<?=h($db['database'])?>" required><small>Nombre propio, por ejemplo: zynko</small></div><div class="field full db-final-preview"><span><i class="fa-solid fa-database"></i> Base de datos completa</span><strong id="dbPreview"><?=h($dbPreview)?></strong></div><div class="field"><label>Usuario MySQL / del hosting</label><input name="username" value="<?=h($db['username'])?>" required autocomplete="username"></div><div class="field"><label>Contraseña MySQL / del hosting</label><div class="password-wrap"><input id="dbPassword" type="password" name="password" value="<?=h($db['password'])?>" autocomplete="current-password"><button class="password-toggle" type="button" id="toggleDbPassword" aria-label="Mostrar contraseña" title="Mostrar contraseña"><i class="fa-solid fa-eye"></i></button></div></div></div><div class="actions"><a id="installer-back-v2257" class="btn soft" href="install.php?step=1"><i class="fa-solid fa-arrow-left"></i> Volver</a><button class="btn primary"><i class="fa-solid fa-database"></i> Probar y continuar</button></div></form>
<?php elseif($step===3):?><h1>Cuenta principal</h1><p>Esta será la primera empresa y el propietario de ZYNKO. NIVO quedará creado y listo para configurarse después.</p><form method="post"><input type="hidden" name="action" value="install"><div class="grid"><div class="field"><label>Empresa</label><input name="company" required placeholder="Nombre de la empresa"></div><div class="field"><label>Nombre del administrador</label><input name="name" required></div><div class="field"><label>Correo</label><input type="email" name="email" required></div><div class="field"><label>Contraseña</label><input type="password" name="password" minlength="8" required></div><div class="field"><label>Idioma inicial</label><select class="select2" name="locale"><option value="es">Español</option><option value="en">English</option></select></div><div class="field"><label>URL de ZYNKO</label><input name="app_url" value="<?=h(appUrl())?>"></div></div><div class="actions"><a class="btn soft" href="?step=2"><i class="fa-solid fa-arrow-left"></i> Volver</a><button class="btn primary"><i class="fa-solid fa-wand-magic-sparkles"></i> Crear tablas y continuar</button></div></form>
<?php elseif($step===4):?><h1>Correo y notificaciones</h1><p>Elige cómo quieres configurar el envío de correos de ZYNKO. Puedes dejarlo pendiente y configurarlo después sin bloquear la instalación.</p><form method="post" class="mailbox mail-premium" id="mailForm"><input type="hidden" name="action" value="mail_save"><input type="hidden" name="method" id="method" value="<?=h($mailMethod)?>"><div class="mail-choice-grid" role="radiogroup" aria-label="Método de correo"><label class="mail-choice <?=($mailMethod==='LATER'?'is-selected':'')?>" data-method="LATER"><input type="radio" name="mail_method_choice" value="LATER" <?=($mailMethod==='LATER'?'checked':'')?>><span class="mail-choice-icon"><i class="fa-solid fa-clock"></i></span><span class="mail-choice-copy"><strong>Configurar después</strong><small>Opción recomendada si todavía no tienes las credenciales. ZYNKO quedará instalado y podrás configurar el correo más tarde.</small></span><span class="mail-choice-check"><i class="fa-solid fa-check"></i></span></label><label class="mail-choice <?=($mailMethod==='SMTP'?'is-selected':'')?>" data-method="SMTP"><input type="radio" name="mail_method_choice" value="SMTP" <?=($mailMethod==='SMTP'?'checked':'')?>><span class="mail-choice-icon"><i class="fa-solid fa-envelope"></i></span><span class="mail-choice-copy"><strong>SMTP</strong><small>Hosting, Gmail, Microsoft 365 SMTP u otro proveedor compatible.</small></span><span class="mail-choice-check"><i class="fa-solid fa-check"></i></span></label><label class="mail-choice <?=($mailMethod==='GRAPH'?'is-selected':'')?>" data-method="GRAPH"><input type="radio" name="mail_method_choice" value="GRAPH" <?=($mailMethod==='GRAPH'?'checked':'')?>><span class="mail-choice-icon"><i class="fa-brands fa-microsoft"></i></span><span class="mail-choice-copy"><strong>Microsoft Graph</strong><small>OAuth2 mediante Tenant ID, Client ID y Client Secret para Microsoft 365.</small></span><span class="mail-choice-check"><i class="fa-solid fa-check"></i></span></label></div><div id="later" class="mail-config-panel"><div class="mail-later-state"><span><i class="fa-solid fa-circle-info"></i></span><div><strong>Lo configurarás más tarde</strong><p>ZYNKO funcionará normalmente. El envío de correos quedará pendiente hasta que lo configures en <b>Correo y notificaciones</b>.</p></div></div></div><div id="smtp" class="mail-config-panel grid" style="display:none"><div class="field"><label>Correo emisor</label><input type="email" name="sender" value="<?=h($_SESSION['install_owner_email']??'')?>"></div><div class="field"><label>Correo para notificaciones internas (opcional)</label><input type="email" name="recipient" placeholder="Opcional"><small>Vacío = correo del administrador principal.</small></div><div class="field"><label>Servidor SMTP</label><input name="server" placeholder="smtp.office365.com"></div><div class="field"><label>Puerto</label><input name="port" value="587" inputmode="numeric"></div><div class="field"><label>Seguridad</label><select class="select2" name="smtp_secure"><option value="tls">TLS</option><option value="ssl">SSL</option></select></div><div class="field"><label>Contraseña / App password</label><div class="password-wrap"><input type="password" name="smtp_password" autocomplete="new-password"><button class="password-toggle" type="button" aria-label="Mostrar contraseña"><i class="fa-solid fa-eye"></i></button></div></div></div><div id="graph" class="mail-config-panel grid" style="display:none"><div class="field"><label>Buzón / Graph User</label><input type="email" name="graph_user" placeholder="administracion@empresa.com"></div><div class="field"><label>Correo para notificaciones internas (opcional)</label><input type="email" name="graph_recipient" placeholder="Opcional"><small>Vacío = correo del administrador principal.</small></div><div class="field"><label>Tenant ID</label><input name="graph_tenant" autocomplete="off"></div><div class="field"><label>Client ID</label><input name="client_id" autocomplete="off"></div><div class="field full"><label>Client Secret</label><div class="password-wrap"><input type="password" name="client_secret" autocomplete="new-password"><button class="password-toggle" type="button" aria-label="Mostrar Client Secret"><i class="fa-solid fa-eye"></i></button></div><small>Usa el Secret Value, no el identificador del secreto.</small></div></div><div class="mail-footer"><a class="btn soft" href="?step=3"><i class="fa-solid fa-arrow-left"></i> Volver</a><div class="mail-footer-right"><button class="btn secondary" type="button" id="testMail"><i class="fa-solid fa-paper-plane"></i> Probar configuración</button><button class="btn primary" type="submit" id="mailContinue"><i class="fa-solid fa-arrow-right"></i> Continuar sin correo</button></div></div></form><script>const m=document.getElementById('method'),s=document.getElementById('smtp'),g=document.getElementById('graph'),l=document.getElementById('later'),t=document.getElementById('testMail'),c=document.getElementById('mailContinue');function sync(){const v=m.value;document.querySelectorAll('.mail-choice').forEach(x=>x.classList.toggle('is-selected',x.dataset.method===v));l.style.display=v==='LATER'?'block':'none';s.style.display=v==='SMTP'?'grid':'none';g.style.display=v==='GRAPH'?'grid':'none';s.querySelectorAll('input,select').forEach(e=>e.disabled=v!=='SMTP');g.querySelectorAll('input,select').forEach(e=>e.disabled=v!=='GRAPH');t.style.display=v==='LATER'?'none':'inline-flex';c.innerHTML=v==='LATER'?'<i class="fa-solid fa-arrow-right"></i> Continuar sin correo':'<i class="fa-solid fa-floppy-disk"></i> Guardar correo y finalizar';}document.querySelectorAll('input[name="mail_method_choice"]').forEach(r=>r.addEventListener('change',()=>{m.value=r.value;sync()}));sync();</script>
<?php else:?><h1><i class="fa-solid fa-circle-check" style="color:var(--p)"></i> ZYNKO está instalado</h1><p>La base de datos, tablas, empresa principal, administrador, branding inicial y perfil de NIVO fueron creados automáticamente.</p><div class="note"><?php if(!empty($_SESSION['mail_configured'])):?><b>Correo configurado.</b> Puedes administrarlo después desde Configuración → Correo y notificaciones.<?php else:?><b>Correo omitido.</b> ZYNKO funcionará normalmente, pero no enviará emails hasta que configures el servicio desde Configuración → Correo y notificaciones.<?php endif?><br><br>Por seguridad, el instalador quedó bloqueado mediante <b>storage/installed.lock</b>.</div><div class="actions"><a class="btn primary" href="./"><i class="fa-solid fa-arrow-right-to-bracket"></i> Ir a ZYNKO</a></div><?php endif?></section></div><script>
const testMail=document.getElementById('testMail'),mailForm=document.getElementById('mailForm');
if(testMail&&mailForm){testMail.addEventListener('click',async()=>{if(!mailForm.reportValidity())return;const original=testMail.innerHTML;testMail.disabled=true;testMail.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Probando…';try{const fd=new FormData(mailForm);fd.set('action','mail_test');const res=await fetch(location.href,{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}});let data={};try{data=await res.json()}catch(_){throw new Error('El servidor devolvió una respuesta no válida.')}if(!res.ok||!data.ok)throw new Error(data.message||'La prueba de correo falló.');showNotify('success',data.message,'Correo funcionando');}catch(err){showNotify('error',err.message||'No se pudo completar la prueba.','Prueba de correo');}finally{testMail.disabled=false;testMail.innerHTML=original;}})}
</script><script src="assets/vendor/jquery/jquery-3.7.1.min.js"></script><script src="assets/vendor/select2/select2.min.js"></script><script src="assets/js/zynko-ui.js"></script><script>if(window.jQuery&&jQuery.fn.select2){jQuery(".select2").select2({width:"100%",minimumResultsForSearch:6});jQuery("#method").on("change select2:select",function(){if(typeof sync==="function")sync();});if(typeof sync==="function")sync();}</script><script id="zynko-installer-db-js-v2257">
document.addEventListener('DOMContentLoaded',function(){
 var prefix=document.getElementById('dbPrefix'),name=document.getElementById('dbName'),preview=document.getElementById('dbPreview');
 function normalizePrefix(v){v=(v||'').replace(/[^a-zA-Z0-9_]/g,'');return v&&!v.endsWith('_')?v+'_':v}
 function paint(){if(preview)preview.textContent=normalizePrefix(prefix?prefix.value:'')+((name?name.value:'').replace(/[^a-zA-Z0-9_]/g,''))}
 if(prefix)prefix.addEventListener('input',paint);if(name)name.addEventListener('input',paint);paint();
 var pass=document.getElementById('dbPassword'),toggle=document.getElementById('toggleDbPassword');
 if(toggle&&pass)toggle.addEventListener('click',function(){var show=pass.type==='password';pass.type=show?'text':'password';toggle.innerHTML=show?'<i class="fa-solid fa-eye-slash"></i>':'<i class="fa-solid fa-eye"></i>';toggle.setAttribute('aria-label',show?'Ocultar contraseña':'Mostrar contraseña');toggle.title=show?'Ocultar contraseña':'Mostrar contraseña'});
 var flash=document.getElementById('installFlash');if(flash&&typeof showNotify==='function')showNotify(flash.dataset.type||'info',flash.dataset.message||'',flash.dataset.title||'ZYNKO');
});
</script>
</body></html>
