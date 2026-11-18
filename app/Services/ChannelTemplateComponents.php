<?php
declare(strict_types=1);

/** Builds supported official WhatsApp template components; never modifies an approved body at send time. */
final class ChannelTemplateComponents
{
    public static function length(string $text): int {return function_exists('mb_strlen')?mb_strlen($text,'UTF-8'):preg_match_all('/./us',$text);}
    public static function variables(string $text): array
    {
        preg_match_all('/\{\{([1-9][0-9]*)\}\}/',$text,$m);$vars=array_values(array_unique(array_map('intval',$m[1])));sort($vars);
        $remaining=preg_replace('/\{\{[1-9][0-9]*\}\}/','',$text);
        if(str_contains((string)$remaining,'{{')||str_contains((string)$remaining,'}}')||($vars&&$vars!==range(1,count($vars))))throw new InvalidArgumentException('Las variables deben ser numéricas y consecutivas.');return $vars;
    }
    public static function build(string $body,array $examples=[],string $header='',string $footer='',array $buttons=[]): array
    {
        $body=trim($body);$header=trim($header);$footer=trim($footer);
        if($body===''||self::length($body)>1024)throw new InvalidArgumentException('Contenido de plantilla inválido (máximo 1024 caracteres).');
        $components=[];if($header!==''){if(self::length($header)>60||self::variables($header))throw new InvalidArgumentException('Encabezado fijo de hasta 60 caracteres, sin variables.');$components[]=['type'=>'HEADER','format'=>'TEXT','text'=>$header];}
        $vars=self::variables($body);if(count($vars)!==count($examples)||in_array('',array_map('trim',$examples),true))throw new InvalidArgumentException('Es necesario indicar un ejemplo por cada variable.');
        $c=['type'=>'BODY','text'=>$body];if($vars)$c['example']=['body_text'=>[array_values($examples)]];$components[]=$c;
        if($footer!==''){if(self::length($footer)>60||self::variables($footer))throw new InvalidArgumentException('Pie fijo de hasta 60 caracteres, sin variables.');$components[]=['type'=>'FOOTER','text'=>$footer];}
        if(count($buttons)>3)throw new InvalidArgumentException('Máximo tres botones en este editor.');$list=[];$names=[];
        foreach($buttons as $b){if(!is_array($b))throw new InvalidArgumentException('Botón inválido.');$type=strtoupper((string)($b['type']??'QUICK_REPLY'));$text=trim((string)($b['text']??''));if($text===''||self::length($text)>25||isset($names[$text])||self::variables($text))throw new InvalidArgumentException('Nombre de botón inválido, repetido o superior a 25 caracteres.');$names[$text]=true;$x=['type'=>$type,'text'=>$text];
            if($type==='URL'){$url=trim((string)($b['url']??''));if(!filter_var($url,FILTER_VALIDATE_URL)||!str_starts_with($url,'https://')||self::variables($url))throw new InvalidArgumentException('El botón debe usar una URL HTTPS fija.');$x['url']=$url;}
            elseif($type==='PHONE_NUMBER'){$phone=trim((string)($b['phone_number']??''));if(!preg_match('/^\+[1-9][0-9]{6,14}$/D',$phone))throw new InvalidArgumentException('Número de botón inválido.');$x['phone_number']=$phone;}
            elseif($type!=='QUICK_REPLY')throw new InvalidArgumentException('Tipo de botón no disponible en este editor.');$list[]=$x;
        }
        if($list)$components[]=['type'=>'BUTTONS','buttons'=>$list];return $components;
    }
    public static function forSend(array $template,array $values): array
    {
        $components=[];$body=null;$otherVars=false;
        foreach($template['components']??[] as $c){$type=strtoupper((string)($c['type']??''));if($type==='BODY')$body=(string)($c['text']??'');elseif($type==='HEADER'&&((string)($c['format']??'TEXT')!=='TEXT'||self::variables((string)($c['text']??''))))$otherVars=true;elseif($type==='BUTTONS'){foreach($c['buttons']??[] as $b){if(str_contains((string)($b['url']??''),'{{'))$otherVars=true;}}elseif(!in_array($type,['FOOTER','HEADER'],true))$otherVars=true;}
        if($body===null||$otherVars)throw new InvalidArgumentException('Esta plantilla requiere parámetros especiales no incluidos en este formulario.');$vars=self::variables($body);
        if(count($values)!==count($vars)||in_array('',array_map('trim',$values),true))throw new InvalidArgumentException('Debe completar exactamente las variables del cuerpo aprobado.');
        if($vars)$components[]=['type'=>'body','parameters'=>array_map(fn($v)=>['type'=>'text','text'=>(string)$v],array_values($values))];
        return $components;
    }
}
