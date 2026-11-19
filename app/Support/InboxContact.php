<?php
declare(strict_types=1);
require_once __DIR__.'/ChannelIdentity.php';
final class InboxContact {
    public static function countries(): array {static $data;return $data??=json_decode(file_get_contents(__DIR__.'/phone-countries.json'),true,32,JSON_THROW_ON_ERROR);}
    public static function phone(string $raw,string $country=''): string {
        $raw=trim($raw);if(!preg_match('/^\+?[0-9\s().-]+$/D',$raw))throw new RuntimeException('Número de teléfono inválido.');
        $digits=preg_replace('/\D/','',$raw);$full=str_starts_with($raw,'+');
        if($country!==''){$prefix=self::countries()[$country]['dial']??null;if(!$prefix)throw new RuntimeException('Selecciona un país válido.');
            if(!$full)$digits=$prefix.$digits;
            if(!str_starts_with($digits,$prefix))throw new RuntimeException('El número no corresponde al prefijo seleccionado.');
            if($country==='HN'&&strlen($digits)!==11)throw new RuntimeException('El número de Honduras debe tener 8 dígitos.');
        }
        $number=ChannelIdentity::phone($digits);if($number==='')throw new RuntimeException('Introduce un número internacional válido.');return $number;
    }
    public static function split(string $phone): array {
        $digits=preg_replace('/\D/','',$phone);if($digits==='')return ['HN',''];if(strlen($digits)===8&&!str_starts_with($phone,'+'))return ['HN',$digits];
        $rows=self::countries();$codes=array_keys($rows);usort($codes,fn($a,$b)=>strlen($rows[$b]['dial'])<=>strlen($rows[$a]['dial']));
        foreach($codes as $code){$p=$rows[$code]['dial'];if(str_starts_with($digits,$p))return [$p==='1'?'US':$code,substr($digits,strlen($p))];}return ['HN','+'.$digits];
    }
    public static function options(string $selected='HN',string $lang='es'): string {
        $rows=self::countries();uasort($rows,fn($a,$b)=>strnatcasecmp($a[$lang==='en'?'en':'es'],$b[$lang==='en'?'en':'es']));$out='';
        foreach($rows as $code=>$r){$flag=html_entity_decode('&#'.(127397+ord($code[0])).';&#'.(127397+ord($code[1])).';',ENT_QUOTES,'UTF-8');$label=$flag.' '.$r[$lang==='en'?'en':'es'].' (+'.$r['dial'].')';$out.='<option value="'.$code.'" data-dial="'.$r['dial'].'"'.($selected===$code?' selected':'').'>'.htmlspecialchars($label,ENT_QUOTES,'UTF-8').'</option>'; }return $out;
    }
    public static function isSaved(array $contact): bool { $meta=json_decode((string)($contact['custom_fields']??''),true);return is_array($meta)&&!empty($meta['inbox_saved']); }
    public static function mediaUrl(array $asset,int $messageId,int $index): string {
        $u=(string)($asset['url']??'');if(!empty($asset['provider_id'])||preg_match('#^uploads/(chat|quick-replies|quick_replies)/[0-9]+/[A-Za-z0-9_./-]+$#D',$u)&&!str_contains($u,'..'))return 'channel-media.php?message_id='.$messageId.'&index='.$index;
        if(!empty($asset['remote'])&&class_exists('ChannelProviderGateway')&&ChannelProviderGateway::validPublicMediaUrl($u))return $u;return '';
    }
}
