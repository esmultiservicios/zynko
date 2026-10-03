<?php
declare(strict_types=1);

final class ZynkoAccessPolicy
{
    public static function normalizeOrigin(string $value): string
    {
        $value=trim($value);
        if($value==='')return '';
        if(!preg_match('#^https?://#i',$value))$value='https://'.$value;
        $p=parse_url($value);
        if(!is_array($p)||empty($p['scheme'])||empty($p['host']))return '';
        $scheme=strtolower((string)$p['scheme']);
        if(!in_array($scheme,['http','https'],true))return '';
        $host=strtolower((string)$p['host']);
        $port=!empty($p['port'])?':'.(int)$p['port']:'';
        return $scheme.'://'.$host.$port;
    }

    public static function normalizeOriginList(string|array $raw): array
    {
        $items=is_array($raw)?$raw:preg_split('/[\r\n,;]+/',(string)$raw);
        $out=[];
        foreach($items?:[] as $item){$origin=self::normalizeOrigin((string)$item);if($origin!==''&&!in_array($origin,$out,true))$out[]=$origin;}
        return array_slice($out,0,50);
    }

    public static function normalizeIpList(string|array $raw): array
    {
        $items=is_array($raw)?$raw:preg_split('/[\r\n,;]+/',(string)$raw);
        $out=[];
        foreach($items?:[] as $item){$ip=trim((string)$item);if($ip!==''&&(filter_var($ip,FILTER_VALIDATE_IP)||preg_match('/^[0-9a-f:.]+\/\d{1,3}$/i',$ip))&&!in_array($ip,$out,true))$out[]=$ip;}
        return array_slice($out,0,50);
    }

    public static function requestOrigin(): string
    {
        return self::normalizeOrigin((string)($_SERVER['HTTP_ORIGIN']??''));
    }

    public static function requestIp(): string
    {
        return trim((string)($_SERVER['REMOTE_ADDR']??''));
    }

    public static function originAllowed(string $origin,array $allowed): bool
    {
        if(!$allowed)return true;
        $origin=self::normalizeOrigin($origin);
        if($origin==='')return false;
        foreach($allowed as $candidate){if(hash_equals(self::normalizeOrigin((string)$candidate),$origin))return true;}
        return false;
    }

    public static function ipAllowed(string $ip,array $allowed): bool
    {
        if(!$allowed)return true;
        if(!filter_var($ip,FILTER_VALIDATE_IP))return false;
        foreach($allowed as $candidate){$candidate=trim((string)$candidate);if($candidate===$ip)return true;if(str_contains($candidate,'/')&&self::ipInCidr($ip,$candidate))return true;}
        return false;
    }

    private static function ipInCidr(string $ip,string $cidr): bool
    {
        [$subnet,$bits]=array_pad(explode('/',$cidr,2),2,null);$bits=(int)$bits;
        $ipBin=@inet_pton($ip);$subnetBin=@inet_pton((string)$subnet);
        if($ipBin===false||$subnetBin===false||strlen($ipBin)!==strlen($subnetBin))return false;
        $max=strlen($ipBin)*8;if($bits<0||$bits>$max)return false;
        $bytes=intdiv($bits,8);$rem=$bits%8;
        if($bytes>0&&substr($ipBin,0,$bytes)!==substr($subnetBin,0,$bytes))return false;
        if($rem===0)return true;$mask=(0xFF<<(8-$rem))&0xFF;
        return (ord($ipBin[$bytes])&$mask)===(ord($subnetBin[$bytes])&$mask);
    }

    public static function ensureTables(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS api_client_policies(
            api_key_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            allowed_origins_json JSON NULL,
            allowed_ips_json JSON NULL,
            rate_limit_per_minute INT UNSIGNED NOT NULL DEFAULT 120,
            require_https TINYINT(1) NOT NULL DEFAULT 1,
            active TINYINT(1) NOT NULL DEFAULT 1,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_api_policy_tenant(tenant_id,active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS api_rate_limits(
            api_key_id BIGINT UNSIGNED NOT NULL,
            bucket_minute CHAR(12) NOT NULL,
            request_count INT UNSIGNED NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY(api_key_id,bucket_minute)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public static function policyForKey(PDO $pdo,int $keyId,int $tenantId): array
    {
        self::ensureTables($pdo);
        $q=$pdo->prepare('SELECT * FROM api_client_policies WHERE api_key_id=? AND tenant_id=? LIMIT 1');$q->execute([$keyId,$tenantId]);$row=$q->fetch()?:[];
        return [
            'active'=>!array_key_exists('active',$row)||(int)$row['active']===1,
            'require_https'=>!array_key_exists('require_https',$row)||(int)$row['require_https']===1,
            'rate_limit_per_minute'=>max(10,min(5000,(int)($row['rate_limit_per_minute']??120))),
            'allowed_origins'=>json_decode((string)($row['allowed_origins_json']??'[]'),true)?:[],
            'allowed_ips'=>json_decode((string)($row['allowed_ips_json']??'[]'),true)?:[],
        ];
    }

    public static function enforce(PDO $pdo,array $key): array
    {
        $policy=self::policyForKey($pdo,(int)$key['id'],(int)$key['tenant_id']);
        if(!$policy['active'])throw new RuntimeException('La política de acceso de esta clave API está desactivada.');
        $origin=self::requestOrigin();
        if($origin!==''&&!self::originAllowed($origin,$policy['allowed_origins']))throw new RuntimeException('El origen web no está autorizado para esta clave API.');
        if($origin!==''&&$policy['require_https']&&!str_starts_with($origin,'https://')&&!str_starts_with($origin,'http://localhost')&&!str_contains($origin,'.test'))throw new RuntimeException('Esta clave API exige HTTPS para solicitudes desde navegador.');
        $ip=self::requestIp();if(!self::ipAllowed($ip,$policy['allowed_ips']))throw new RuntimeException('La dirección IP no está autorizada para esta clave API.');
        $bucket=gmdate('YmdHi');
        $pdo->prepare('INSERT INTO api_rate_limits(api_key_id,bucket_minute,request_count) VALUES(?,?,1) ON DUPLICATE KEY UPDATE request_count=request_count+1')->execute([(int)$key['id'],$bucket]);
        $q=$pdo->prepare('SELECT request_count FROM api_rate_limits WHERE api_key_id=? AND bucket_minute=?');$q->execute([(int)$key['id'],$bucket]);
        if((int)$q->fetchColumn()>$policy['rate_limit_per_minute'])throw new OverflowException('Se alcanzó el límite de solicitudes por minuto para esta clave API.');
        return $policy;
    }
}
