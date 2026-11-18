<?php
declare(strict_types=1);
require_once __DIR__.'/../Support/ChannelSecurity.php';
require_once __DIR__.'/ChannelTemplateComponents.php';

/** Only explicitly supported provider payloads may enter the persistent queue. */
final class ChannelStructuredMessage
{
    public static function build(array $ch,array $input): array
    {
        $kind=(string)($input['kind']??'');$type=(string)($ch['type']??'');$official=$type==='whatsapp'&&in_array(ChannelSecurity::mode($ch),['meta_cloud','meta_coexistence'],true);
        if($kind==='location'&&($official||$type==='telegram')){
            $lat=$input['latitude']??null;$lng=$input['longitude']??null;
            if(!is_numeric($lat)||!is_numeric($lng)||!is_finite((float)$lat)||!is_finite((float)$lng)||abs((float)$lat)>90||abs((float)$lng)>180)throw new InvalidArgumentException('Coordenadas inválidas.');
            return ['kind'=>'location','latitude'=>(float)$lat,'longitude'=>(float)$lng];
        }
        if($kind==='buttons'&&$official){
            $body=trim((string)($input['body']??''));$buttons=$input['buttons']??null;
            if($body===''||ChannelTemplateComponents::length($body)>1024||!is_array($buttons)||count($buttons)<1||count($buttons)>3)throw new InvalidArgumentException('Ingrese un mensaje y uno a tres botones.');
            $titles=[];foreach($buttons as $b){if(!is_string($b))throw new InvalidArgumentException('Botón inválido.');$b=trim($b);if($b===''||ChannelTemplateComponents::length($b)>20||in_array($b,$titles,true))throw new InvalidArgumentException('Botones únicos de hasta 20 caracteres.');$titles[]=$b;}return ['kind'=>'buttons','body'=>$body,'buttons'=>$titles];
        }
        if($kind==='template'&&$official){$id=(string)($input['template_id']??'');$v=$input['values']??[];if(!preg_match('/^[0-9]{1,30}$/D',$id)||!is_array($v)||count($v)>100)throw new InvalidArgumentException('Plantilla inválida.');foreach($v as $x)if(!is_string($x)||trim($x)===''||strlen($x)>4096)throw new InvalidArgumentException('Valor de plantilla inválido.');return ['kind'=>'template','template_id'=>$id,'values'=>array_values($v)];}
        throw new InvalidArgumentException('Este tipo de mensaje no es compatible con el canal.');
    }
    public static function body(array $input): string
    {return match($input['kind']){'location'=>'📍 Ubicación: '.$input['latitude'].', '.$input['longitude'],'buttons'=>$input['body']."\n".implode(' · ',$input['buttons']),'template'=>'Plantilla WA #'.$input['template_id'],default=>''};}
}
