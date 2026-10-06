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
            : 'Host interno explícito para el daemon WebSocket.';
        $add($items,'ws_host','WS_HOST',$wsHostStatus,$wsHost,$wsHostDetail,'Tiempo real');

        $tcp = $this->tcp($wsHost,$wsPort,0.7);
        $add($items,'ws_internal','WebSocket interno',$tcp?'ok':'error',$wsHost.':'.$wsPort,$tcp?'El puerto interno acepta conexiones TCP.':'El daemon no responde en el host/puerto configurado.','Tiempo real');

        $pidFile = $this->root.'/storage/websocket.pid';
        $pid = is_file($pidFile) ? trim((string)@file_get_contents($pidFile)) : '';
        $pidOk = $pid !== '' && ctype_digit($pid);
        if ($pidOk && function_exists('posix_kill')) {
            $pidOk = @posix_kill((int)$pid,0);
        }
        $add($items,'ws_pid','Proceso WebSocket',$pidOk?'ok':'warning',$pid ?: 'Sin PID',$pidOk?'PID registrado para websocket/server.php.':'No se pudo confirmar el proceso mediante el PID guardado; revisa el daemon si el puerto interno también falla.','Tiempo real');

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

        $proxyHost='';$proxyPort=0;$htaccess=$this->root.'/.htaccess';
        if(is_file($htaccess)){
            $raw=(string)@file_get_contents($htaccess);
            if(preg_match('#RewriteRule\s+\^ws/\?\$\s+ws://([^/:\s]+):(\d+)/#i',$raw,$m)){$proxyHost=(string)$m[1];$proxyPort=(int)$m[2];}
        }
        $proxyCompatible = $proxyHost!=='' && $proxyPort>0 && $proxyHost===$wsHost && $proxyPort===$wsPort;
        $proxyValue=$proxyHost!==''?('Apache → '.$proxyHost.':'.$proxyPort):'Regla /ws no detectada';
        $add($items,'ws_proxy_alignment','Alineación proxy/daemon',$proxyCompatible?'ok':'error',$proxyValue,$proxyCompatible?'La regla /ws de .htaccess y el daemon usan exactamente el mismo host y puerto.':'El proxy /ws y WS_HOST/WS_PORT no coinciden. El navegador puede fallar aunque el daemon esté activo.','Tiempo real');

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

        $channelRows=[];
        try {
            $q=$this->pdo->prepare("SELECT type,status,COUNT(*) total FROM channels WHERE tenant_id=? GROUP BY type,status ORDER BY type,status");
            $q->execute([$this->tenantId]);
            $channelRows=$q->fetchAll(PDO::FETCH_ASSOC)?:[];
        } catch (Throwable $e) {}
        $channelValue = $channelRows ? implode(' · ',array_map(static fn(array $r): string => ucfirst((string)$r['type']).' '.(string)$r['status'].' x'.(int)$r['total'],$channelRows)) : 'Sin canales';
        $add($items,'channels','Canales',$channelRows?'ok':'warning',$channelValue,$channelRows?'Estado registrado por los conectores de ZYNKO.':'Todavía no hay canales registrados.','Omnicanal');

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
