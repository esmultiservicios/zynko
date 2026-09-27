<?php
declare(strict_types=1);

$root = dirname(__DIR__);
function wsEnv(string $path): array { $v=@parse_ini_file($path,false,INI_SCANNER_RAW); return is_array($v)?$v:[]; }
function b64urlDecode(string $v): string|false { $v=strtr($v,'-_','+/'); $v.=str_repeat('=',(4-strlen($v)%4)%4); return base64_decode($v,true); }
function verifyToken(string $token,string $hexKey): ?array {
    if(!preg_match('/^[a-f0-9]{64}$/i',$hexKey) || !str_contains($token,'.')) return null;
    [$p,$s]=explode('.',$token,2); $raw=b64urlDecode($p); $sig=b64urlDecode($s); if($raw===false||$sig===false)return null;
    $expected=hash_hmac('sha256',$p,hex2bin($hexKey),true); if(!hash_equals($expected,$sig))return null;
    $data=json_decode($raw,true); if(!is_array($data)||($data['exp']??0)<time()||(int)($data['tenant_id']??0)<1||(int)($data['user_id']??0)<1)return null;
    return $data;
}
function frame(string $payload,int $opcode=1): string {
    $len=strlen($payload); $h=chr(0x80|$opcode);
    if($len<=125)return $h.chr($len).$payload;
    if($len<=65535)return $h.chr(126).pack('n',$len).$payload;
    return $h.chr(127).pack('NN',0,$len).$payload;
}
function decodeFrame(string &$buffer): ?array {
    if(strlen($buffer)<2)return null; $b1=ord($buffer[0]);$b2=ord($buffer[1]);$opcode=$b1&0x0f;$masked=($b2&0x80)!==0;$len=$b2&0x7f;$pos=2;
    if($len===126){if(strlen($buffer)<4)return null;$len=unpack('n',substr($buffer,2,2))[1];$pos=4;}
    elseif($len===127){if(strlen($buffer)<10)return null;$parts=unpack('Nhi/Nlo',substr($buffer,2,8));if($parts['hi']!==0)return ['opcode'=>8,'payload'=>''];$len=$parts['lo'];$pos=10;}
    $mask='';if($masked){if(strlen($buffer)<$pos+4)return null;$mask=substr($buffer,$pos,4);$pos+=4;}
    if(strlen($buffer)<$pos+$len)return null;$payload=substr($buffer,$pos,$len);$buffer=substr($buffer,$pos+$len);
    if($masked){for($i=0;$i<$len;$i++)$payload[$i]=$payload[$i]^$mask[$i%4];}
    return ['opcode'=>$opcode,'payload'=>$payload];
}
function handshake($socket,string $request,string $appKey): ?array {
    if(!preg_match('/GET\s+([^\s]+)\s+HTTP\/1\.[01]/',$request,$m))return null;$target=$m[1];
    if(!preg_match('/Sec-WebSocket-Key:\s*(.+)\r?$/mi',$request,$k))return null;
    $query=parse_url($target,PHP_URL_QUERY)?:'';parse_str($query,$q);$auth=verifyToken((string)($q['token']??''),$appKey);if(!$auth)return null;
    $accept=base64_encode(sha1(trim($k[1]).'258EAFA5-E914-47DA-95CA-C5AB0DC85B11',true));
    fwrite($socket,"HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: $accept\r\n\r\n");return $auth;
}
$env=wsEnv($root.'/.env');$host=$env['WS_HOST']??'127.0.0.1';$port=(int)($env['WS_PORT']??8080);$appKey=$env['APP_KEY']??'';
if(!preg_match('/^[a-f0-9]{64}$/i',$appKey)){fwrite(STDERR,"APP_KEY inválida. Ejecuta primero el instalador.\n");exit(1);}
$dsn='mysql:host='.($env['DB_HOST']??'127.0.0.1').';port='.($env['DB_PORT']??'3306').';dbname='.($env['DB_DATABASE']??'zynko').';charset=utf8mb4';
try{$pdo=new PDO($dsn,$env['DB_USERNAME']??'root',$env['DB_PASSWORD']??'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$pdo->query('SELECT id FROM realtime_events LIMIT 1');}catch(Throwable $e){fwrite(STDERR,"Base de datos/WebSocket no preparada: {$e->getMessage()}\n");exit(1);}
$server=@stream_socket_server("tcp://{$host}:{$port}",$errno,$errstr);if(!$server){fwrite(STDERR,"No se pudo abrir {$host}:{$port}: {$errstr}\n");exit(1);}stream_set_blocking($server,false);
echo "ZYNKO WebSocket activo en ws://{$host}:{$port}\n";
$clients=[];$lastEvent=(int)($pdo->query('SELECT COALESCE(MAX(id),0) FROM realtime_events')->fetchColumn());$lastPoll=0.0;$lastPing=time();
while(true){$read=[$server];foreach($clients as $c)$read[]=$c['socket'];$write=$except=[];@stream_select($read,$write,$except,0,200000);
 foreach($read as $sock){if($sock===$server){$new=@stream_socket_accept($server,0);if($new){stream_set_blocking($new,false);$clients[(int)$new]=['socket'=>$new,'handshake'=>false,'buffer'=>'','auth'=>null,'connected'=>time()];}continue;}
  $id=(int)$sock;$chunk=@fread($sock,8192);if($chunk===''&&feof($sock)){@fclose($sock);unset($clients[$id]);continue;}$clients[$id]['buffer'].=$chunk;
  if(!$clients[$id]['handshake']){if(!str_contains($clients[$id]['buffer'],"\r\n\r\n"))continue;$auth=handshake($sock,$clients[$id]['buffer'],$appKey);if(!$auth){@fwrite($sock,"HTTP/1.1 401 Unauthorized\r\nConnection: close\r\n\r\n");@fclose($sock);unset($clients[$id]);continue;}$clients[$id]['handshake']=true;$clients[$id]['auth']=$auth;$clients[$id]['buffer']='';@fwrite($sock,frame(json_encode(['type'=>'connected','tenant_id'=>(int)$auth['tenant_id'],'user_id'=>(int)$auth['user_id'],'at'=>date(DATE_ATOM)])));continue;}
  while(($f=decodeFrame($clients[$id]['buffer']))!==null){if($f['opcode']===8){@fclose($sock);unset($clients[$id]);break;}if($f['opcode']===9){@fwrite($sock,frame($f['payload'],10));}elseif($f['opcode']===1){$msg=json_decode($f['payload'],true);if(($msg['type']??'')==='ping')@fwrite($sock,frame(json_encode(['type'=>'pong','at'=>date(DATE_ATOM)])));}}
 }
 $now=microtime(true);if($now-$lastPoll>=0.25){$lastPoll=$now;try{$st=$pdo->prepare('SELECT id,tenant_id,event_type,entity_type,entity_id,payload_json,created_at FROM realtime_events WHERE id>? ORDER BY id ASC LIMIT 250');$st->execute([$lastEvent]);foreach($st as $ev){$lastEvent=max($lastEvent,(int)$ev['id']);$payload=['type'=>'event','id'=>(int)$ev['id'],'event'=>$ev['event_type'],'entity_type'=>$ev['entity_type'],'entity_id'=>$ev['entity_id'],'data'=>json_decode($ev['payload_json'],true),'created_at'=>$ev['created_at']];$encoded=frame(json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));foreach($clients as $cid=>$c){if($c['handshake']&&(int)$c['auth']['tenant_id']===(int)$ev['tenant_id']){if(@fwrite($c['socket'],$encoded)===false){@fclose($c['socket']);unset($clients[$cid]);}}}}}catch(Throwable $e){fwrite(STDERR,'Realtime poll: '.$e->getMessage()."\n");}}
 if(time()-$lastPing>=30){$lastPing=time();$ping=frame('zynko',9);foreach($clients as $cid=>$c){if($c['handshake']&&@fwrite($c['socket'],$ping)===false){@fclose($c['socket']);unset($clients[$cid]);}}}
}
