<?php
declare(strict_types=1);

final class ZynkoEnvManager
{
    private string $path;
    private string $backupDir;

    public function __construct(string $root)
    {
        $this->path=rtrim($root,'/').'/.env';
        $this->backupDir=rtrim($root,'/').'/storage/env-backups';
    }

    public function exists(): bool { return is_file($this->path); }

    public function values(): array
    {
        $v=@parse_ini_file($this->path,false,INI_SCANNER_RAW);
        return is_array($v)?$v:[];
    }

    public static function editableKeys(): array
    {
        return [
            'APP_URL'=>['label'=>'URL principal','type'=>'url','secret'=>false],
            'APP_ENV'=>['label'=>'Entorno','type'=>'select','secret'=>false,'options'=>['production','staging','local']],
            'APP_DEBUG'=>['label'=>'Depuración','type'=>'bool','secret'=>false],
            'WS_HOST'=>['label'=>'Host interno WebSocket','type'=>'text','secret'=>false],
            'WS_PUBLIC_HOST'=>['label'=>'Host público WebSocket','type'=>'text','secret'=>false],
            'WS_PUBLIC_PORT'=>['label'=>'Puerto público WebSocket','type'=>'number','secret'=>false],
            'WS_PUBLIC_URL'=>['label'=>'URL pública WebSocket','type'=>'text','secret'=>false],
            'WS_PORT'=>['label'=>'Puerto interno WebSocket','type'=>'number','secret'=>false],
            'WS_PUBLIC_SCHEME'=>['label'=>'Esquema WebSocket','type'=>'select','secret'=>false,'options'=>['wss','ws']],
            'DB_HOST'=>['label'=>'Servidor de base de datos','type'=>'text','secret'=>false],
            'DB_PORT'=>['label'=>'Puerto de base de datos','type'=>'number','secret'=>false],
            'DB_DATABASE'=>['label'=>'Base de datos','type'=>'text','secret'=>false],
            'DB_USERNAME'=>['label'=>'Usuario de base de datos','type'=>'text','secret'=>false],
            'DB_PASSWORD'=>['label'=>'Contraseña de base de datos','type'=>'password','secret'=>true],
            'EMAIL_VALIDATION_API_URL'=>['label'=>'URL API validación de correo','type'=>'url','secret'=>false],
            'EMAIL_VALIDATION_API_KEY'=>['label'=>'API Key validación de correo','type'=>'password','secret'=>true],
            'EMAIL_VALIDATION_API_TIMEOUT'=>['label'=>'Timeout validación de correo','type'=>'number','secret'=>false],
        ];
    }

    public static function platformChannelKeys(): array
    {
        return [
          'META_APP_ID'=>['label'=>'Meta App ID','type'=>'text','secret'=>false],
          'META_CONFIG_ID'=>['label'=>'Embedded Signup Configuration ID','type'=>'text','secret'=>false],
          'META_APP_SECRET'=>['label'=>'Meta App Secret','type'=>'password','secret'=>true],
          'META_WEBHOOK_VERIFY_TOKEN'=>['label'=>'Verify token general','type'=>'password','secret'=>true],
          'META_GRAPH_VERSION'=>['label'=>'Versión Graph API','type'=>'text','secret'=>false],
          'META_EMBEDDED_SIGNUP_ENABLED'=>['label'=>'Embedded Signup para clientes','type'=>'bool','secret'=>false],
          'META_COEXISTENCE_ENABLED'=>['label'=>'Coexistence para clientes','type'=>'bool','secret'=>false],
          'X_DM_ENABLED'=>['label'=>'X / Twitter · mensajes directos','type'=>'bool','secret'=>false],
          'X_CLIENT_ID'=>['label'=>'X OAuth Client ID','type'=>'text','secret'=>false],
          'X_CLIENT_SECRET'=>['label'=>'X OAuth Client Secret','type'=>'password','secret'=>true],
          'ZYNKO_QR_BRIDGE_URL'=>['label'=>'URL interna del bridge QR','type'=>'text','secret'=>false],
          'ZYNKO_QR_BRIDGE_TOKEN'=>['label'=>'Token interno del bridge QR','type'=>'password','secret'=>true],
        ];
    }

    public function save(array $input,bool $platformChannels=false): array
    {
        return $this->locked(fn()=>$this->saveUnlocked($input,$platformChannels));
    }

    public function saveKey(string $key,string $value,bool $new=false,bool $allowEmpty=false): array
    {
        if(!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,127}$/D',$key))throw new RuntimeException('Nombre de variable inválido.');
        if(strtoupper($key)==='APP_KEY')throw new RuntimeException('APP_KEY está protegida: su rotación requiere recifrar los datos fuera de este editor.');
        if($value===''&&!$allowEmpty)throw new RuntimeException('Escribe el valor o confirma expresamente que deseas guardar un valor vacío.');
        return $this->locked(function()use($key,$value,$new){
            $current=$this->values();
            if($new&&array_key_exists($key,$current))throw new RuntimeException('La variable ya existe. Selecciónala para modificarla.');
            if(!$new&&!array_key_exists($key,$current))throw new RuntimeException('La variable ya no existe. Actualiza la página.');
            return $this->saveUnlocked([$key=>$value],isset(self::platformChannelKeys()[$key]),true);
        });
    }

    private function locked(callable $operation): array
    {
        if(!$this->exists()||is_link($this->path))throw new RuntimeException('No se puede administrar este archivo .env.');
        if(!is_dir($this->backupDir)&&!mkdir($this->backupDir,0700,true)&&!is_dir($this->backupDir))throw new RuntimeException('No se pudo preparar la carpeta de respaldos.');
        $lock=fopen($this->backupDir.'/.write.lock','c+b');
        if(!$lock)throw new RuntimeException('No se pudo bloquear la configuración.');
        @chmod($this->backupDir.'/.write.lock',0600);
        try{if(!flock($lock,LOCK_EX))throw new RuntimeException('No se pudo bloquear la configuración.');return $operation();}
        finally{flock($lock,LOCK_UN);fclose($lock);}
    }

    private function saveUnlocked(array $input,bool $platformChannels=false,bool $singleKey=false): array
    {
        if(!$this->exists())throw new RuntimeException('El archivo .env no existe en la raíz de ZYNKO.');
        if(!is_readable($this->path)||!is_writable($this->path))throw new RuntimeException('El archivo .env no tiene permisos de lectura/escritura para PHP.');
        $raw=file_get_contents($this->path);if($raw===false)throw new RuntimeException('No se pudo leer el archivo .env.');
        $current=@parse_ini_string($raw,false,INI_SCANNER_RAW);if(!is_array($current))throw new RuntimeException('El .env actual contiene sintaxis inválida. No se realizaron cambios.');$expected=$current;$changed=[];
        $keys=$platformChannels?self::platformChannelKeys():self::editableKeys();
        if($singleKey){foreach($input as $k=>$v)if(!isset($keys[$k]))$keys[$k]=['secret'=>true];}
        foreach($keys as $key=>$meta){
            if(!array_key_exists($key,$input))continue;
            $value=$singleKey?(string)$input[$key]:trim((string)$input[$key]);
            if(strtoupper($key)==='APP_KEY'||preg_match('/[\r\n\x00]/',$value)||strlen($value)>32768)throw new RuntimeException('Variable protegida o valor no válido.');
            if(!$singleKey&&!empty($meta['secret'])&&$value==='')continue;
            if($platformChannels){
                if($value===''&&!$singleKey)continue;
                if(in_array($key,['META_APP_ID','META_CONFIG_ID'],true)&&!preg_match('/^[0-9]{5,30}$/D',$value))throw new RuntimeException('Identificador de Meta inválido.');
                if($key==='META_GRAPH_VERSION'&&!preg_match('/^v[0-9]{1,3}\.[0-9]{1,2}$/D',$value))throw new RuntimeException('Versión Graph inválida.');
                if(in_array($key,['META_EMBEDDED_SIGNUP_ENABLED','META_COEXISTENCE_ENABLED','X_DM_ENABLED'],true))$value=in_array(strtolower($value),['1','true','yes'],true)?'1':'0';
                if($key==='ZYNKO_QR_BRIDGE_URL'){$value=rtrim($value,'/');$url=parse_url($value);if(!is_array($url)||!isset($url['host'])||!in_array($url['scheme']??'',['http','https'],true)||isset($url['user'])||isset($url['pass'])||isset($url['query'])||isset($url['fragment']))throw new RuntimeException('URL del bridge inválida.');}
                if(preg_match('/[\r\n\x00]/',$value)||strlen($value)>4096)throw new RuntimeException('Valor de configuración inválido.');
            }
            if($key==='APP_URL'){$value=rtrim($value,'/');if($value!==''&&(!filter_var($value,FILTER_VALIDATE_URL)||!preg_match('#^https?://#i',$value)))throw new RuntimeException('APP_URL debe ser una URL http/https válida.');}
            if($key==='EMAIL_VALIDATION_API_URL'&&$value!==''&&(!filter_var(str_replace('{email}','test@example.com',$value),FILTER_VALIDATE_URL)||!preg_match('#^https?://#i',$value)))throw new RuntimeException('EMAIL_VALIDATION_API_URL debe ser una URL http/https válida.');
            if($key==='APP_ENV'&&!in_array($value,['production','staging','local'],true))$value='production';
            if($key==='APP_DEBUG')$value=in_array(strtolower($value),['1','true','yes','on'],true)?'true':'false';
            if($key==='WS_PUBLIC_SCHEME'&&!in_array($value,['wss','ws'],true))$value='wss';
            if(in_array($key,['DB_PORT','WS_PORT','WS_PUBLIC_PORT'],true)){$n=(int)$value;if($n<1||$n>65535)throw new RuntimeException($key.' debe usar un puerto entre 1 y 65535.');$value=(string)$n;}
            if($key==='EMAIL_VALIDATION_API_TIMEOUT'){$n=(int)$value;if($n<2||$n>12)$n=5;$value=(string)$n;}
            if($key==='WS_HOST'){if($value==='localhost')$value='127.0.0.1';if($value===''||!preg_match('/^(?:127\.0\.0\.1|::1|[a-z0-9.-]+)$/i',$value))throw new RuntimeException('WS_HOST no es válido. Usa 127.0.0.1 para producción.');}
            if($key==='WS_PUBLIC_HOST'){$value=preg_replace('#^https?://#i','',$value);$value=preg_replace('#/.*$#','',$value);if($value!==''&&!preg_match('/^[a-z0-9.-]+(?::\d+)?$/i',$value))throw new RuntimeException('WS_PUBLIC_HOST no es válido.');}
            if($key==='WS_PUBLIC_URL'&&$value!==''){if(!preg_match('#^wss?://#i',$value))throw new RuntimeException('WS_PUBLIC_URL debe comenzar con ws:// o wss://.');$parts=parse_url($value);if(!is_array($parts)||empty($parts['host']))throw new RuntimeException('WS_PUBLIC_URL no es válida.');$value=rtrim($value,'/');}
            if(in_array($key,['DB_HOST','DB_DATABASE','DB_USERNAME'],true)&&$value==='')throw new RuntimeException($key.' no puede quedar vacío.');
            if(array_key_exists($key,$current)&&(string)$current[$key]===$value)continue;
            $raw=$this->replaceValue($raw,$key,$value);
            $expected[$key]=$value;$changed[]=$key;
        }
        if(!$changed)return [];
        // Verifica que el lector real de ZYNKO recupera exactamente todos los valores.
        $parsed=@parse_ini_string($raw,false,INI_SCANNER_RAW);
        if(!is_array($parsed))throw new RuntimeException('El valor no es compatible con el formato .env. No se realizaron cambios.');
        ksort($parsed);ksort($expected);
        if($parsed!==$expected)throw new RuntimeException('El valor alteraría la interpretación de otras variables. No se realizaron cambios.');
        if(!is_dir($this->backupDir))throw new RuntimeException('Carpeta de respaldos no disponible.');
        $sourceStat=stat(dirname($this->path));$backupStat=stat($this->backupDir);
        if($sourceStat===false||$backupStat===false||$sourceStat['dev']!==$backupStat['dev'])throw new RuntimeException('El respaldo debe estar en el mismo sistema de archivos.');
        $backup=tempnam($this->backupDir,'env-'.date('Ymd-His').'-');
        if($backup===false)throw new RuntimeException('No se pudo crear el respaldo.');
        @chmod($backup,0600);
        if(!copy($this->path,$backup)){@unlink($backup);throw new RuntimeException('No se pudo respaldar el .env.');}
        // El temporal nunca queda expuesto en la raíz pública.
        $tmp=tempnam($this->backupDir,'.env-tmp-');
        if($tmp===false)throw new RuntimeException('No se pudo preparar la actualización del .env.');
        @chmod($tmp,0600);
        if(file_put_contents($tmp,$raw,LOCK_EX)!==strlen($raw)){@unlink($tmp);throw new RuntimeException('No se pudo preparar la actualización del .env.');}
        if(!rename($tmp,$this->path)){@unlink($tmp);throw new RuntimeException('No se pudo reemplazar el archivo .env de forma segura.');}
        @chmod($this->path,0600);
        return $changed;
    }

    private function replaceValue(string $raw,string $key,string $value): string
    {
        $encoded=$this->encode($value);
        $pattern='/^[ \t]*'.preg_quote($key,'/').'[ \t]*=[^\r\n]*/m';
        if(preg_match($pattern,$raw))return preg_replace_callback($pattern,fn()=>$key.'='.$encoded,$raw)??$raw;
        return $raw.($raw!==''&&!str_ends_with($raw,"\n")?PHP_EOL:'').$key.'='.$encoded.PHP_EOL;
    }

    private function encode(string $value): string
    {
        if($value==='')return '""';
        if(preg_match('/^[A-Za-z0-9_\.\-:\/]+$/',$value))return $value;
        return '"'.$value.'"';
    }
}
