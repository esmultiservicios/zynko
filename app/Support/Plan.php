<?php
declare(strict_types=1);

function zynkoPlanColumn(PDO $pdo,string $table,string $column,string $ddl): void{
    try{$q=$pdo->query("SHOW COLUMNS FROM `$table` LIKE ".$pdo->quote($column));if(!$q->fetch())$pdo->exec("ALTER TABLE `$table` ADD $ddl");}catch(Throwable $e){}
}

function zynkoEnsurePlanSchema(PDO $pdo): void{
    $pdo->exec("CREATE TABLE IF NOT EXISTS subscription_plans(
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) NULL UNIQUE,
        name VARCHAR(120) NOT NULL,
        monthly_price DECIMAL(12,2) NOT NULL DEFAULT 0,
        currency VARCHAR(8) NOT NULL DEFAULT 'HNL',
        max_users INT NULL,
        max_channels INT NULL,
        max_webchat_sites INT NULL,
        max_daily_chats INT NULL,
        allowed_channels_json JSON NULL,
        module_access_json JSON NULL,
        features_json JSON NULL,
        is_default_free TINYINT(1) NOT NULL DEFAULT 0,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    zynkoPlanColumn($pdo,'subscription_plans','code','code VARCHAR(50) NULL UNIQUE AFTER id');
    zynkoPlanColumn($pdo,'subscription_plans','max_webchat_sites','max_webchat_sites INT NULL AFTER max_channels');
    zynkoPlanColumn($pdo,'subscription_plans','max_daily_chats','max_daily_chats INT NULL AFTER max_webchat_sites');
    zynkoPlanColumn($pdo,'subscription_plans','allowed_channels_json','allowed_channels_json JSON NULL AFTER max_daily_chats');
    zynkoPlanColumn($pdo,'subscription_plans','module_access_json','module_access_json JSON NULL AFTER allowed_channels_json');
    zynkoPlanColumn($pdo,'subscription_plans','is_default_free','is_default_free TINYINT(1) NOT NULL DEFAULT 0 AFTER features_json');
    $pdo->exec("CREATE TABLE IF NOT EXISTS tenant_subscriptions(
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tenant_id BIGINT UNSIGNED NOT NULL,
        plan_id BIGINT UNSIGNED NOT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'active',
        starts_at DATETIME NULL,
        ends_at DATETIME NULL,
        UNIQUE KEY uq_tenant_subscription(tenant_id),
        INDEX(plan_id,status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS plan_upgrade_requests(
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tenant_id BIGINT UNSIGNED NOT NULL,
        plan_id BIGINT UNSIGNED NOT NULL,
        requested_by BIGINT UNSIGNED NOT NULL,
        status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
        note VARCHAR(500) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        resolved_at DATETIME NULL,
        resolved_by BIGINT UNSIGNED NULL,
        INDEX idx_plan_request_tenant_status(tenant_id,status,created_at),
        INDEX idx_plan_request_plan_status(plan_id,status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS tenant_channel_entitlements(
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tenant_id BIGINT UNSIGNED NOT NULL,
        channel_type VARCHAR(50) NOT NULL,
        enabled TINYINT(1) NOT NULL DEFAULT 0,
        monthly_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        UNIQUE KEY uq_entitlement(tenant_id,channel_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $modules=json_encode([
        'dashboard'=>1,'inbox'=>1,'channels'=>1,'webchat'=>1,'billing'=>1,'onboarding'=>1,
        'users'=>0,'chatbot'=>0,'integrations'=>0,'email'=>0,'settings'=>0,'api'=>0
    ],JSON_UNESCAPED_SLASHES);
    $channels=json_encode(['webchat'],JSON_UNESCAPED_SLASHES);
    $features=json_encode([
        'NIVO Web Chat incluido','1 sitio web autorizado','Hasta 5 chats nuevos por día','1 usuario propietario'
    ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $q=$pdo->query("SELECT id FROM subscription_plans WHERE code='free' ORDER BY id LIMIT 1");
    $free=(int)($q->fetchColumn()?:0);
    if(!$free){$q=$pdo->query("SELECT id FROM subscription_plans WHERE is_default_free=1 ORDER BY id LIMIT 1");$free=(int)($q->fetchColumn()?:0);}
    if(!$free){
        $st=$pdo->prepare("INSERT INTO subscription_plans(code,name,monthly_price,currency,max_users,max_channels,max_webchat_sites,max_daily_chats,allowed_channels_json,module_access_json,features_json,is_default_free,active) VALUES('free','Gratis',0,'HNL',1,1,1,5,?,?,?,1,1)");
        $st->execute([$channels,$modules,$features]);
    }else{
        $pdo->prepare("UPDATE subscription_plans SET is_default_free=0,code=NULL WHERE id<>? AND (is_default_free=1 OR code='free')")->execute([$free]);
        $pdo->prepare("UPDATE subscription_plans SET code='free',name=IF(name='', 'Gratis', name),monthly_price=0,max_users=1,max_channels=1,max_webchat_sites=1,max_daily_chats=5,allowed_channels_json=?,module_access_json=?,features_json=COALESCE(features_json,?),is_default_free=1,active=1 WHERE id=?")->execute([$channels,$modules,$features,$free]);
    }
}

function zynkoFreePlanId(PDO $pdo): int{
    zynkoEnsurePlanSchema($pdo);
    $q=$pdo->query("SELECT id FROM subscription_plans WHERE is_default_free=1 AND active=1 ORDER BY id LIMIT 1");
    return (int)($q->fetchColumn()?:0);
}

function zynkoDecodeList(mixed $value): array{
    if(is_array($value))return array_values($value);
    if(!is_string($value)||trim($value)==='')return [];
    $j=json_decode($value,true);
    return is_array($j)?$j:[];
}

function zynkoDecodeMap(mixed $value): array{
    if(is_array($value))return $value;
    if(!is_string($value)||trim($value)==='')return [];
    $j=json_decode($value,true);
    return is_array($j)?$j:[];
}

function zynkoPlanContext(PDO $pdo,int $tenantId,bool $platformOwner=false): array{
    if($platformOwner)return [
        'unrestricted'=>true,'has_plan'=>true,'plan_id'=>0,'plan_code'=>'platform','plan_name'=>'Plataforma','subscription_status'=>'active',
        'max_users'=>null,'max_channels'=>null,'max_webchat_sites'=>null,'max_daily_chats'=>null,'allowed_channels'=>[],'modules'=>[],'is_free'=>false
    ];
    try{
        $q=$pdo->prepare("SELECT sp.*,ts.status subscription_status FROM tenant_subscriptions ts JOIN subscription_plans sp ON sp.id=ts.plan_id WHERE ts.tenant_id=? LIMIT 1");
        $q->execute([$tenantId]);$p=$q->fetch();
        if(!$p){
            // Preserve existing installations that predate commercial plan enforcement.
            return ['unrestricted'=>true,'has_plan'=>false,'plan_id'=>0,'plan_code'=>'legacy','plan_name'=>'Sin plan','subscription_status'=>'active','max_users'=>null,'max_channels'=>null,'max_webchat_sites'=>null,'max_daily_chats'=>null,'allowed_channels'=>[],'modules'=>[],'is_free'=>false];
        }
        $mods=zynkoDecodeMap($p['module_access_json']??null);
        $channels=zynkoDecodeList($p['allowed_channels_json']??null);
        $status=(string)($p['subscription_status']??'active');
        $active=in_array($status,['active','grace','trial'],true)&&((int)($p['active']??1)===1);
        return [
            'unrestricted'=>false,'has_plan'=>true,'plan_id'=>(int)$p['id'],'plan_code'=>(string)($p['code']??''),'plan_name'=>(string)$p['name'],'subscription_status'=>$status,
            'max_users'=>$p['max_users']!==null?(int)$p['max_users']:null,'max_channels'=>$p['max_channels']!==null?(int)$p['max_channels']:null,
            'max_webchat_sites'=>$p['max_webchat_sites']!==null?(int)$p['max_webchat_sites']:null,'max_daily_chats'=>$p['max_daily_chats']!==null?(int)$p['max_daily_chats']:null,
            'allowed_channels'=>$channels,'modules'=>$mods,'is_free'=>((int)($p['is_default_free']??0)===1)||(($p['code']??'')==='free'),'active'=>$active
        ];
    }catch(Throwable $e){
        return ['unrestricted'=>true,'has_plan'=>false,'plan_id'=>0,'plan_code'=>'legacy','plan_name'=>'Sin plan','subscription_status'=>'active','max_users'=>null,'max_channels'=>null,'max_webchat_sites'=>null,'max_daily_chats'=>null,'allowed_channels'=>[],'modules'=>[],'is_free'=>false];
    }
}

function zynkoPlanAllowsModule(array $ctx,string $module): bool{
    if(!empty($ctx['unrestricted']))return true;
    if(empty($ctx['active']))return $module==='billing';
    if(in_array($module,['dashboard','inbox','channels','webchat','billing','onboarding'],true))return true;
    $mods=$ctx['modules']??[];
    return !empty($mods[$module]);
}

function zynkoPlanAllowsPage(array $ctx,string $page): bool{
    return zynkoPlanAllowsModule($ctx,$page);
}

function zynkoPlanAllowsChannel(array $ctx,string $channel): bool{
    if(!empty($ctx['unrestricted']))return true;
    if(empty($ctx['active']))return false;
    $allowed=$ctx['allowed_channels']??[];
    if(!$allowed)return true; // legacy paid plan without explicit channel matrix
    return in_array($channel,$allowed,true);
}

function zynkoPlanLimit(array $ctx,string $key): ?int{
    $v=$ctx[$key]??null;
    if($v===null)return null;
    $i=(int)$v;
    return $i>0?$i:null;
}

function zynkoPlanDailyChatUsage(PDO $pdo,int $tenantId): int{
    try{$q=$pdo->prepare("SELECT COUNT(*) FROM conversations c JOIN channels ch ON ch.id=c.channel_id AND ch.tenant_id=c.tenant_id WHERE c.tenant_id=? AND ch.type='webchat' AND DATE(c.created_at)=CURDATE()");$q->execute([$tenantId]);return (int)$q->fetchColumn();}catch(Throwable $e){return 0;}
}

function zynkoApplyPlanEntitlements(PDO $pdo,int $tenantId,int $planId): void{
    $q=$pdo->prepare('SELECT * FROM subscription_plans WHERE id=?');$q->execute([$planId]);$p=$q->fetch();if(!$p)return;
    $channels=zynkoDecodeList($p['allowed_channels_json']??null);
    $catalog=$pdo->query('SELECT code FROM channel_connector_catalog')->fetchAll(PDO::FETCH_COLUMN)?:[];
    if($catalog){
        $up=$pdo->prepare('INSERT INTO tenant_channel_entitlements(tenant_id,channel_type,enabled,monthly_amount) VALUES(?,?,?,0) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),monthly_amount=0');
        foreach($catalog as $code){$enabled=(!$channels||in_array($code,$channels,true))?1:0;$up->execute([$tenantId,$code,$enabled]);}
    }
    $maxSites=$p['max_webchat_sites']!==null?(int)$p['max_webchat_sites']:0;
    if($maxSites===1){
        $pdo->prepare('UPDATE webchat_widgets SET allow_multiple_domains=0 WHERE tenant_id=?')->execute([$tenantId]);
    }
    if($maxSites>0){
        $q=$pdo->prepare('SELECT id FROM webchat_installations WHERE tenant_id=? AND enabled=1 ORDER BY id');$q->execute([$tenantId]);$ids=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
        foreach(array_slice($ids,$maxSites) as $id)$pdo->prepare('UPDATE webchat_installations SET enabled=0 WHERE id=? AND tenant_id=?')->execute([$id,$tenantId]);
    }
}
