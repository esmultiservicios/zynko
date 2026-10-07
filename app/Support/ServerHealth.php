<?php
declare(strict_types=1);

final class ZynkoServerHealth
{
    private string $root;
    private array $env;
    private PDO $pdo;
    private int $tenantId;

    public function __construct(string $root, PDO $pdo, array $env, int $tenantId)
    {
        $this->root = rtrim($root, '/');
        $this->pdo = $pdo;
        $this->env = $env;
        $this->tenantId = $tenantId;
    }

    public function report(): array
    {
        $items = [];
        $add = static function(array &$items, string $key, string $label, string $status, string $value, string $detail, string $group='Servidor'): void {
            $items[] = compact('key','label','status','value','detail','group');
        };

        $appUrl = rtrim((string)($this->env['APP_URL'] ?? ''), '/');
        $appHost = (string)(parse_url($appUrl, PHP_URL_HOST) ?: '');
        $isHttps = str_starts_with(strtolower($appUrl), 'https://');
        $add($items,'app_url','APP_URL',$appUrl !== '' && $appHost !== '' ? ($isHttps?'ok':'warning') : 'error',$appUrl ?: 'No configurada',$isHttps?'URL pública HTTPS válida.':'En producción se recomienda HTTPS.','Aplicación');

        $envPath = $this->root.'/.env';
        $envOk = is_file($envPath) && is_readable($envPath);
        $envWritable = $envOk && is_writable($envPath);
        $add($items,'env','.env',$envOk?($envWritable?'ok':'warning'):'error',$envOk?($envWritable?'Legible y escribible':'Solo lectura'):'No disponible',$envOk?'ZYNKO puede leer la configuración'.($envWritable?' y crear respaldos antes de editar.':'.'): 'No se encontró el archivo de entorno.','Aplicación');

        $serverNow = new DateTimeImmutable('now');
        $tz = date_default_timezone_get();
        $dbNow = '';
        try {
            $dbNow = (string)($this->pdo->query('SELECT NOW()')->fetchColumn() ?: '');
        } catch (Throwable $e) {}
        $timeDetail = $dbNow !== ''
            ? 'PHP: '.$serverNow->format('Y-m-d H:i:s').' · DB: '.$dbNow.'. Si estas horas no coinciden con tu operación, revisa la zona horaria de PHP/MySQL.'
            : 'Hora PHP: '.$serverNow->format('Y-m-d H:i:s').'. No se pudo leer NOW() de la base de datos.';
        $add($items,'server_time','Hora / zona horaria','ok',$tz,$timeDetail,'Servidor');

        try {
            $this->pdo->query('SELECT 1')->fetchColumn();
            $dbOk = true;
        } catch (Throwable $e) {
            $dbOk = false;
        }
        $dbHost = (string)($this->env['DB_HOST'] ?? '127.0.0.1');
        $dbPort = (int)($this->env['DB_PORT'] ?? 3306);
        $add($items,'db','Base de datos',$dbOk?'ok':'error',$dbHost.':'.$dbPort,$dbOk?'Conexión PDO operativa.':'La aplicación no pudo ejecutar SELECT 1.','Base de datos');

        $wsHost = trim((string)($this->env['WS_HOST'] ?? '127.0.0.1')) ?: '127.0.0.1';
        $wsPort = (int)($this->env['WS_PORT'] ?? 8080);
        $wsHostStatus = $wsHost === 'localhost' ? 'warning' : 'ok';
        $wsHostDetail = $wsHost === 'localhost'
            ? 'Evita localhost en producción: puede resolver a IPv6 (::1) mientras Apache usa 127.0.0.1.'
            :  'Dirección interna usada por el servicio WebSocket. El servicio es el proceso que mantiene abierta la comunicación en tiempo real.';
        $add($items,'ws_host','WS_HOST',$wsHostStatus,$wsHost,$wsHostDetail,'Tiempo real');

        $tcp = $this->tcp($wsHost,$wsPort,0.7);
        $add($items,'ws_internal','WebSocket interno',$tcp?'ok':'error',$wsHost.':'.$wsPort,$tcp?'El puerto interno acepta conexiones TCP.': 'El servicio WebSocket no está aceptando conexiones en esta dirección y puerto.','Tiempo real');

        $pidFile = $this->root.'/storage/websocket.pid';
        $pid = is_file($pidFile) ? trim((string)@file_get_contents($pidFile)) : '';
        $pidNumeric = $pid !== '' && ctype_digit($pid);
        $pidConfirmed = false;
        if ($pidNumeric && function_exists('posix_kill')) {
            $pidConfirmed = @posix_kill((int)$pid,0);
        }
        // En hosting compartido posix_kill puede no estar disponible aun cuando el daemon está vivo.
        // Si el puerto interno responde, consideramos el runtime activo y dejamos el PID como dato informativo.
        $pidOk = $pidConfirmed || $tcp;
        $pidDetail = $pidConfirmed
            ?  'Proceso confirmado. PID es el identificador numérico que el servidor asigna al servicio WebSocket.'
            : ($tcp ?  'El puerto interno responde. El hosting no permitió comprobar el identificador del proceso desde PHP, pero el servicio está accesible.' :  'No se pudo confirmar el proceso y el puerto interno tampoco responde.');
        $add($items,'ws_pid','Proceso WebSocket',$pidOk?'ok':'warning',$pid ?: 'Sin PID',$pidDetail,'Tiempo real');

        $disabledFns=array_filter(array_map('trim',explode(',',strtolower((string)ini_get('disable_functions')))));
        $controlFns=['exec','proc_open','shell_exec','system','passthru','popen'];
        $availableControl=array_values(array_filter($controlFns,static fn(string $fn): bool => function_exists($fn) && !in_array($fn,$disabledFns,true)));
        $controlOk=!empty($availableControl);
        $add($items,'ws_runtime_control','Control desde panel',$controlOk?'ok':'warning',$controlOk?implode(', ',$availableControl):'Bloqueado por hosting',$controlOk? 'El panel dispone de al menos un método permitido por el hosting para iniciar, detener o reiniciar el servicio WebSocket.': 'El hosting bloquea la administración de procesos desde PHP. El Update/Deploy de Git/cPanel puede seguir reiniciando WebSocket automáticamente, pero el panel no puede saltarse esa política.','Tiempo real');

        $marker = $this->root.'/storage/websocket.restart.marker';
        $markerTime = is_file($marker) ? (int)@filemtime($marker) : 0;
        $watched = [$this->root.'/websocket/server.php',$this->root.'/app/Support/Realtime.php',$this->root.'/.htaccess',$this->root.'/.env'];
        $latestChange = 0;
        foreach ($watched as $watchedFile) { if (is_file($watchedFile)) $latestChange=max($latestChange,(int)@filemtime($watchedFile)); }
        $restartRecommended = !$tcp || $markerTime===0 || $latestChange>$markerTime;
        $restartDetail = $restartRecommended
            ? ($tcp
                ? 'El tiempo real está activo, pero ZYNKO detectó cambios posteriores al último arranque o no tiene constancia de un reinicio posterior. Usa el botón “Reiniciar WebSocket” de esta misma sección una sola vez; no hay otro servicio que reiniciar. La interrupción normal es de 1–3 segundos.'
                : 'El servicio WebSocket no está respondiendo. Usa “Iniciar” si está detenido. Si logra quedar activo, vuelve a comprobar; si además existen cambios pendientes, ZYNKO indicará si conviene reiniciarlo una vez.')
            : 'No necesitas reiniciar: el servicio está operativo y el último arranque es posterior a los cambios operativos detectados.';
        $add($items,'ws_restart_recommended','Reinicio recomendado',$restartRecommended?'warning':'ok',$restartRecommended?'Sí':'No',$restartDetail,'Tiempo real');

        $publicUrl = trim((string)($this->env['WS_PUBLIC_URL'] ?? ''));
        if ($publicUrl === '') {
            $scheme = strtolower(trim((string)($this->env['WS_PUBLIC_SCHEME'] ?? ($isHttps?'wss':'ws'))));
            if (!in_array($scheme,['ws','wss'],true)) $scheme = $isHttps?'wss':'ws';
            $host = preg_replace('/:\\d+$/','',(string)($this->env['WS_PUBLIC_HOST'] ?? $appHost));
            $port = (int)($this->env['WS_PUBLIC_PORT'] ?? $wsPort);
            $publicUrl = $scheme.'://'.$host.($scheme==='ws'?':'.$port:'').'/ws';
        }
        $publicCheck = $this->publicWebSocket($publicUrl);
        $publicStatus = $publicCheck['ok'] ? 'ok' : 'error';
        $add($items,'ws_public','WebSocket público',$publicStatus,$publicUrl,$publicCheck['detail'],'Tiempo real');

        $wsRunning = $tcp && $publicCheck['ok'];
        $add(
            $items,
            'ws_state',
            'Estado WebSocket',
            $wsRunning ? 'ok' : 'error',
            $wsRunning ? 'Ejecutándose' : 'Detenido / no disponible',
            $wsRunning
                ? 'La comunicación en tiempo real está disponible tanto internamente como desde la URL pública.'
                : 'El tiempo real no está completamente operativo. Revisa WebSocket interno, proceso y WebSocket público en esta misma sección.',
            'Tiempo real'
        );

        $proxyHost='';$proxyPort=0;$htaccess=$this->root.'/.htaccess';
        if(is_file($htaccess)){
            $raw=(string)@file_get_contents($htaccess);
            if(preg_match('#RewriteRule\s+\^ws/\?\$\s+ws://([^/:\s]+):(\d+)/#i',$raw,$m)){$proxyHost=(string)$m[1];$proxyPort=(int)$m[2];}
        }
        $proxyCompatible = $proxyHost!=='' && $proxyPort>0 && $proxyHost===$wsHost && $proxyPort===$wsPort;
        $proxyValue=$proxyHost!==''?('Apache → '.$proxyHost.':'.$proxyPort):'Regla /ws no detectada';
        $add($items,'ws_proxy_alignment', 'Ruta Apache / servicio',$proxyCompatible?'ok':'error',$proxyValue,$proxyCompatible? 'La ruta pública /ws de Apache y el servicio WebSocket usan exactamente la misma dirección y puerto.': 'La ruta /ws de Apache y WS_HOST/WS_PORT no coinciden. El navegador puede fallar aunque el servicio esté activo.','Tiempo real');

        $storage = $this->root.'/storage';
        $storageOk = is_dir($storage) && is_writable($storage);
        $add($items,'storage','Storage',$storageOk?'ok':'error',$storageOk?'Escribible':'No escribible',$storageOk?'Logs, PID, sesiones y archivos operativos pueden guardarse.':'Corrige permisos de storage antes de operar en producción.','Servidor');

        $extensions = ['pdo_mysql','curl','openssl','mbstring','json'];
        $missing = array_values(array_filter($extensions, static fn(string $ext): bool => !extension_loaded($ext)));
        $add($items,'php','PHP / extensiones',$missing?'error':'ok',PHP_VERSION,$missing?'Faltan: '.implode(', ',$missing):'Extensiones críticas disponibles: '.implode(', ',$extensions).'.','Servidor');

        try {
            $mail=$this->pdo->prepare("SELECT metodo_envio,server,port,graph_user,estado FROM correo WHERE tenant_id=? AND is_default=1 ORDER BY correo_id DESC LIMIT 1");
            $mail->execute([$this->tenantId]);$mailRow=$mail->fetch(PDO::FETCH_ASSOC)?:[];
            if($mailRow){
                $method=strtoupper((string)($mailRow['metodo_envio']??''));
                if($method==='SMTP'){
                    $smtpHost=trim((string)($mailRow['server']??''));$smtpPort=(int)($mailRow['port']??0);$smtpOk=$smtpHost!==''&&$smtpPort>0&&$this->tcp($smtpHost,$smtpPort,1.2);
                    $add($items,'mail','Correo SMTP',$smtpOk?'ok':'warning',$smtpHost.':'.$smtpPort,$smtpOk?'El servidor SMTP acepta conexión TCP.':'No se pudo confirmar conexión TCP al SMTP configurado.','Correo');
                } elseif($method==='GRAPH') {
                    $graphUser=trim((string)($mailRow['graph_user']??''));
                    $add($items,'mail','Microsoft Graph',$graphUser!==''?'ok':'warning',$graphUser?:'Sin buzón',$graphUser!==''?'Perfil Graph configurado. Usa la prueba de correo para validar credenciales OAuth.':'Falta el buzón Graph.','Correo');
                }
            }
        } catch (Throwable $e) {}

        $channelRows=[];$channelDetails=[];
        try {
            $q=$this->pdo->prepare("SELECT id,name,type,status,display_address,external_account_id,external_phone_id,token_ciphertext,settings_json,last_event_at FROM channels WHERE tenant_id=? ORDER BY type,name,id");
            $q->execute([$this->tenantId]);
            $channelDetails=$q->fetchAll(PDO::FETCH_ASSOC)?:[];
            $summary=[];foreach($channelDetails as $r){$k=(string)$r['type'].'|'.(string)$r['status'];$summary[$k]=($summary[$k]??0)+1;}
            foreach($summary as $k=>$total){[$type,$status]=explode('|',$k,2);$channelRows[]=['type'=>$type,'status'=>$status,'total'=>$total];}
        } catch (Throwable $e) {}
        $channelValue = $channelRows ? implode(' · ',array_map(static fn(array $r): string => ucfirst((string)$r['type']).' '.(string)$r['status'].' x'.(int)$r['total'],$channelRows)) : 'Sin canales';
        $add($items,'channels','Canales',$channelRows?'ok':'warning',$channelValue,$channelRows?'Estado registrado por los conectores de ZYNKO.':'Todavía no hay canales registrados.','Omnicanal');
        foreach($channelDetails as $channel){
            $settings=json_decode((string)($channel['settings_json']??''),true)?:[];$type=(string)($channel['type']??'');$status=(string)($channel['status']??'pending');
            $provider=(string)($settings['provider_mode']??($type==='whatsapp'?'meta_cloud':$type));$checks=[];
            if($type==='webchat'){$checks[]='canal interno';}
            elseif($type==='whatsapp'&&$provider==='qr'){$checks[]='bridge '.(!empty($settings['bridge_url'])?'configurado':'pendiente');$checks[]='sesión '.(!empty($settings['session_id'])?'configurada':'pendiente');}
            elseif(in_array($type,['whatsapp','messenger','instagram'],true)){$checks[]='token '.(!empty($channel['token_ciphertext'])?'configurado':'pendiente');$checks[]='cuenta '.(!empty($channel['external_account_id'])||!empty($channel['external_phone_id'])?'configurada':'pendiente');}
            elseif($type==='telegram'){$checks[]='token '.(!empty($channel['token_ciphertext'])?'configurado':'pendiente');}
            $ok=$status==='connected';$detail='Proveedor: '.$provider.($checks?' · '.implode(' · ',$checks):'').(!empty($channel['last_event_at'])?' · último evento '.$channel['last_event_at']:'');
            $add($items,'channel_'.$channel['id'],'Canal · '.((string)($channel['name']?:ucfirst($type))),$ok?'ok':($status==='warning'?'warning':'error'),ucfirst($type).' · '.$status,$detail,'Canales externos');
        }

        $counts=['ok'=>0,'warning'=>0,'error'=>0];
        foreach($items as $item){$counts[$item['status']] = ($counts[$item['status']]??0)+1;}
        $overall = $counts['error']>0?'error':($counts['warning']>0?'warning':'ok');
        return ['overall'=>$overall,'counts'=>$counts,'items'=>$items,'checked_at'=>date('Y-m-d H:i:s')];
    }

    private function tcp(string $host,int $port,float $timeout): bool
    {
        if ($host==='' || $port<1 || $port>65535) return false;
        $target = str_contains($host,':') ? '['.$host.']' : $host;
        $errno=0;$errstr='';
        $fp=@stream_socket_client('tcp://'.$target.':'.$port,$errno,$errstr,$timeout,STREAM_CLIENT_CONNECT);
        if(!is_resource($fp)) return false;
        fclose($fp);return true;
    }

    private function publicWebSocket(string $url): array
    {
        if(!preg_match('#^wss?://#i',$url)) return ['ok'=>false,'detail'=>'La URL pública no usa ws:// o wss://.'];
        $httpUrl = preg_replace('#^wss://#i','https://',$url);
        $httpUrl = preg_replace('#^ws://#i','http://',$httpUrl);
        if(function_exists('curl_init')){
            $ch=curl_init($httpUrl);
            curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>false,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>2,CURLOPT_TIMEOUT=>4,CURLOPT_HTTPHEADER=>['Connection: Upgrade','Upgrade: websocket','Sec-WebSocket-Version: 13','Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==']]);
            curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=(string)curl_error($ch);curl_close($ch);
            if(in_array($status,[101,400,401,426],true)) return ['ok'=>true,'detail'=>'El endpoint público llega al servicio WebSocket (HTTP '.$status.').'];
            return ['ok'=>false,'detail'=>$error!==''?$error:'El endpoint respondió HTTP '.$status.'.'];
        }
        return ['ok'=>false,'detail'=>'cURL no está disponible para validar el endpoint público.'];
    }
}
