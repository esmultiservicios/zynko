<?php
declare(strict_types=1);

/** Copy an existing authenticated message to the normal send pipeline. Never fetch a user-supplied URL. */
final class InboxMessageForward
{
    public static function prepare(PDO $pdo, string $root, int $tenantId, int $sourceId, int $sourceConversation, int $targetConversation, string $targetType): array
    {
        $q=$pdo->prepare('SELECT ch.*,m.body AS source_body,m.direction AS source_direction,m.sender_type AS source_sender,m.media_json AS source_media FROM messages m JOIN conversations c ON c.id=m.conversation_id AND c.tenant_id=m.tenant_id JOIN channels ch ON ch.id=c.channel_id AND ch.tenant_id=c.tenant_id WHERE m.id=? AND m.tenant_id=? AND c.id=? AND c.deleted_at IS NULL LIMIT 1');
        $q->execute([$sourceId,$tenantId,$sourceConversation]);
        $source=$q->fetch(PDO::FETCH_ASSOC);
        if(!$source)throw new RuntimeException('El mensaje original no está disponible.');
        $body=(string)($source['source_body']??'');
        if($source['source_direction']==='out'&&$source['source_sender']!=='bot'){
            $body=str_replace('\\n',"\n",$body);
            $body=trim(preg_replace('/^\*\*(?:Agente|Agent)\s*[-·:]\s*[^\n]+\*\*\s*\n?/iu','',$body)??$body);
        }
        $assets=json_decode((string)($source['source_media']??''),true);
        if($assets===null&&empty($source['source_media']))$assets=[];
        if(!is_array($assets)||count($assets)>5)throw new RuntimeException('No se pueden reenviar más de cinco adjuntos por operación.');
        if($assets&&$targetType==='x')throw new RuntimeException('Este canal no admite reenvío de adjuntos.');
        if(trim($body)===''&&!$assets)throw new RuntimeException('El mensaje no tiene contenido para reenviar.');
        $exts=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf','text/plain'=>'txt','audio/mpeg'=>'mp3','audio/ogg'=>'ogg','video/mp4'=>'mp4','application/msword'=>'doc','application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx','application/vnd.ms-excel'=>'xls','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'=>'xlsx'];
        $gateway=new ChannelProviderGateway($pdo,$root);
        $media=[];$created=[];$total=0;
        $dir=$root.'/public/uploads/chat/'.$tenantId.'/'.$targetConversation;
        try{
            foreach($assets as $asset){
                if(!is_array($asset))throw new RuntimeException('Adjunto original inválido.');
                $name=mb_substr(basename(str_replace('\\','/',(string)($asset['name']??'archivo'))),0,180)?:'archivo';
                $url=(string)($asset['url']??'');
                // A remote Cloud API asset is forwarded to Meta by reference, not downloaded by ZYNKO.
                if(!empty($asset['remote'])&&empty($asset['provider_id'])){
                    $target=$pdo->prepare('SELECT ch.* FROM conversations c JOIN channels ch ON ch.id=c.channel_id AND ch.tenant_id=c.tenant_id WHERE c.id=? AND c.tenant_id=?');$target->execute([$targetConversation,$tenantId]);$ch=$target->fetch(PDO::FETCH_ASSOC);
                    $mime=(string)($asset['mime']??'');
                    $remoteMimes=['image/jpeg','image/png','video/mp4','audio/mpeg','audio/ogg','application/pdf','application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
                    if(!$ch||$targetType!=='whatsapp'||!in_array(ChannelSecurity::mode($ch),['meta_cloud','meta_coexistence'],true)||!ChannelProviderGateway::validPublicMediaUrl($url)||!in_array($mime,$remoteMimes,true))throw new RuntimeException('Este adjunto por URL solo puede reenviarse a un canal WhatsApp Cloud autorizado.');
                    $media[]=['name'=>$name,'url'=>$url,'mime'=>$mime,'remote'=>true];continue;
                }
                if(!empty($asset['provider_id'])){
                    $download=$gateway->downloadProviderMedia($source,(string)$asset['provider_id']);$bytes=$download['bytes']??'';
                }else{
                    if(!preg_match('#^uploads/(chat|quick-replies|quick_replies)/'.preg_quote((string)$tenantId,'#').'/[A-Za-z0-9_./-]+$#D',$url,$match)||str_contains($url,'..'))throw new RuntimeException('Adjunto original fuera del espacio autorizado.');
                    $base=realpath($root.'/public/uploads/'.$match[1].'/'.$tenantId);$path=realpath($root.'/public/'.$url);
                    if(!$base||!$path||!str_starts_with($path,$base.DIRECTORY_SEPARATOR)||!is_file($path)||filesize($path)>10*1024*1024)throw new RuntimeException('El archivo original no está disponible o supera 10 MB.');
                    $bytes=file_get_contents($path);
                }
                if(!is_string($bytes)||$bytes===''||strlen($bytes)>10*1024*1024)throw new RuntimeException('El archivo original no está disponible o supera 10 MB.');
                $total+=strlen($bytes);if($total>20*1024*1024)throw new RuntimeException('El total de adjuntos supera 20 MB.');
                $mime=AttachmentMime::buffer($bytes);$ext=$exts[$mime]??null;if(!$ext)throw new RuntimeException('No se puede verificar un formato de archivo original.');
                if(!is_dir($dir)&&!mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('No se pudo preparar la carpeta de destino.');
                $filename=bin2hex(random_bytes(16)).'.'.$ext;$path=$dir.'/'.$filename;$created[]=$path;
                if(file_put_contents($path,$bytes,LOCK_EX)!==strlen($bytes))throw new RuntimeException('No se pudo conservar un adjunto reenviado.');
                $media[]=['name'=>$name,'url'=>'uploads/chat/'.$tenantId.'/'.$targetConversation.'/'.$filename,'mime'=>$mime];
                unset($bytes,$download);
            }
            return ['body'=>$body,'media'=>$media,'created'=>$created];
        }catch(Throwable $e){self::cleanup($created);throw $e;}
    }
    public static function cleanup(array $paths): void {foreach($paths as $path)if(is_file($path))@unlink($path);}
}
