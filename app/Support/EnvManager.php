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

    public function save(array $input): array
    {
        if(!$this->exists())throw new RuntimeException('El archivo .env no existe en la raíz de ZYNKO.');
        if(!is_readable($this->path)||!is_writable($this->path))throw new RuntimeException('El archivo .env no tiene permisos de lectura/escritura para PHP.');
        $raw=file_get_contents($this->path);if($raw===false)throw new RuntimeException('No se pudo leer el archivo .env.');
        $current=$this->values();$changed=[];
        foreach(self::editableKeys() as $key=>$meta){
            if(!array_key_exists($key,$input))continue;
            $value=trim((string)$input[$key]);
            if(!empty($meta['secret'])&&$value==='')continue;
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
            if((string)($current[$key]??'')===$value)continue;
            $raw=$this->replaceValue($raw,$key,$value);
            $changed[]=$key;
        }
        if(!$changed)return [];
        if(!is_dir($this->backupDir)&&!mkdir($this->backupDir,0770,true)&&!is_dir($this->backupDir))throw new RuntimeException('No se pudo crear la carpeta segura de respaldos del .env.');
        $backup=$this->backupDir.'/env-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)).'.bak';
        if(!copy($this->path,$backup))throw new RuntimeException('No se pudo crear el respaldo del .env.');
        @chmod($backup,0600);
        $tmp=$this->path.'.tmp.'.bin2hex(random_bytes(4));
        if(file_put_contents($tmp,$raw,LOCK_EX)===false){@unlink($tmp);throw new RuntimeException('No se pudo preparar la actualización del .env.');}
        @chmod($tmp,0600);
        if(!rename($tmp,$this->path)){@unlink($tmp);throw new RuntimeException('No se pudo reemplazar el archivo .env de forma segura.');}
        @chmod($this->path,0600);
        return $changed;
    }

    private function replaceValue(string $raw,string $key,string $value): string
    {
        $encoded=$this->encode($value);
        $pattern='/^'.preg_quote($key,'/').'\s*=.*$/m';
        if(preg_match($pattern,$raw))return preg_replace($pattern,$key.'='.$encoded,$raw,1)??$raw;
        return rtrim($raw).PHP_EOL.$key.'='.$encoded.PHP_EOL;
    }

    private function encode(string $value): string
    {
        if($value==='')return '""';
        if(preg_match('/^[A-Za-z0-9_\.\-:\/]+$/',$value))return $value;
        return '"'.str_replace(['\\','"'],['\\\\','\\"'],$value).'"';
    }
}
