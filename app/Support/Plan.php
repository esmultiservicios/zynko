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
        max_monthly_chats INT NULL,
        allowed_channels_json JSON NULL,
        module_access_json JSON NULL,
        features_json JSON NULL,
        external_ai_included TINYINT(1) NOT NULL DEFAULT 0,
        external_ai_monthly_tokens BIGINT UNSIGNED NULL,
        external_ai_channels_json JSON NULL,
        is_default_free TINYINT(1) NOT NULL DEFAULT 0,
        is_featured TINYINT(1) NOT NULL DEFAULT 0,
        featured_label VARCHAR(60) NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    zynkoPlanColumn($pdo,'subscription_plans','code','code VARCHAR(50) NULL UNIQUE AFTER id');
    zynkoPlanColumn($pdo,'subscription_plans','max_webchat_sites','max_webchat_sites INT NULL AFTER max_channels');
    zynkoPlanColumn($pdo,'subscription_plans','max_daily_chats','max_daily_chats INT NULL AFTER max_webchat_sites');
    zynkoPlanColumn($pdo,'subscription_plans','max_monthly_chats','max_monthly_chats INT NULL AFTER max_daily_chats');
    zynkoPlanColumn($pdo,'subscription_plans','allowed_channels_json','allowed_channels_json JSON NULL AFTER max_monthly_chats');
    zynkoPlanColumn($pdo,'subscription_plans','module_access_json','module_access_json JSON NULL AFTER allowed_channels_json');
    zynkoPlanColumn($pdo,'subscription_plans','external_ai_included','external_ai_included TINYINT(1) NOT NULL DEFAULT 0 AFTER features_json');
    zynkoPlanColumn($pdo,'subscription_plans','external_ai_monthly_tokens','external_ai_monthly_tokens BIGINT UNSIGNED NULL AFTER external_ai_included');
    zynkoPlanColumn($pdo,'subscription_plans','external_ai_channels_json','external_ai_channels_json JSON NULL AFTER external_ai_monthly_tokens');
    zynkoPlanColumn($pdo,'subscription_plans','is_default_free','is_default_free TINYINT(1) NOT NULL DEFAULT 0 AFTER external_ai_channels_json');
    zynkoPlanColumn($pdo,'subscription_plans','is_featured','is_featured TINYINT(1) NOT NULL DEFAULT 0 AFTER is_default_free');
    zynkoPlanColumn($pdo,'subscription_plans','featured_label','featured_label VARCHAR(60) NULL AFTER is_featured');
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
        'users'=>1,'chatbot'=>0,'integrations'=>0,'email'=>0,'settings'=>0,'api'=>0
    ],JSON_UNESCAPED_SLASHES);
    $channels=json_encode(['webchat'],JSON_UNESCAPED_SLASHES);
    $features=json_encode([
        'NIVO Web Chat incluido','1 sitio autorizado para NIVO Web Chat','5 chats nuevos por día','Mensajes ilimitados dentro de cada chat','Usuarios de ZYNKO ilimitados'
    ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $q=$pdo->query("SELECT id FROM subscription_plans WHERE code='free' ORDER BY id LIMIT 1");
    $free=(int)($q->fetchColumn()?:0);
    if(!$free){$q=$pdo->query("SELECT id FROM subscription_plans WHERE is_default_free=1 ORDER BY id LIMIT 1");$free=(int)($q->fetchColumn()?:0);}
    if(!$free){
        $st=$pdo->prepare("INSERT INTO subscription_plans(code,name,monthly_price,currency,max_users,max_channels,max_webchat_sites,max_daily_chats,max_monthly_chats,allowed_channels_json,module_access_json,features_json,is_default_free,is_featured,featured_label,active) VALUES('free','Gratis',0,'USD',NULL,1,1,5,NULL,?,?,?,1,0,NULL,1)");
        $st->execute([$channels,$modules,$features]);
    }else{
        $pdo->prepare("UPDATE subscription_plans SET is_default_free=0,code=NULL WHERE id<>? AND (is_default_free=1 OR code='free')")->execute([$free]);
        $pdo->prepare("UPDATE subscription_plans SET code='free',name='Gratis',monthly_price=0,currency='USD',max_users=NULL,max_channels=1,max_webchat_sites=1,max_daily_chats=5,max_monthly_chats=NULL,allowed_channels_json=?,module_access_json=?,features_json=?,is_default_free=1,is_featured=0,featured_label=NULL,active=1 WHERE id=?")->execute([$channels,$modules,$features,$free]);
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
        'max_users'=>null,'max_channels'=>null,'max_webchat_sites'=>null,'max_daily_chats'=>null,'max_monthly_chats'=>null,'allowed_channels'=>[],'modules'=>[],'is_free'=>false,'external_ai_included'=>true,'external_ai_monthly_tokens'=>null,'external_ai_channels'=>[]
    ];
    try{
        $q=$pdo->prepare("SELECT sp.*,ts.status subscription_status FROM tenant_subscriptions ts JOIN subscription_plans sp ON sp.id=ts.plan_id WHERE ts.tenant_id=? LIMIT 1");
        $q->execute([$tenantId]);$p=$q->fetch();
        if(!$p){
            // Preserve existing installations that predate commercial plan enforcement.
            return ['unrestricted'=>true,'has_plan'=>false,'plan_id'=>0,'plan_code'=>'legacy','plan_name'=>'Sin plan','subscription_status'=>'active','max_users'=>null,'max_channels'=>null,'max_webchat_sites'=>null,'max_daily_chats'=>null,'max_monthly_chats'=>null,'allowed_channels'=>[],'modules'=>[],'is_free'=>false,'external_ai_included'=>true,'external_ai_monthly_tokens'=>null,'external_ai_channels'=>[]];
        }
        $mods=zynkoDecodeMap($p['module_access_json']??null);
        $channels=zynkoDecodeList($p['allowed_channels_json']??null);
        $externalAiChannels=zynkoDecodeList($p['external_ai_channels_json']??null);
        $status=(string)($p['subscription_status']??'active');
        $active=in_array($status,['active','grace','trial'],true)&&((int)($p['active']??1)===1);
        return [
            'unrestricted'=>false,'has_plan'=>true,'plan_id'=>(int)$p['id'],'plan_code'=>(string)($p['code']??''),'plan_name'=>(string)$p['name'],'subscription_status'=>$status,
            'max_users'=>$p['max_users']!==null?(int)$p['max_users']:null,'max_channels'=>$p['max_channels']!==null?(int)$p['max_channels']:null,
            'max_webchat_sites'=>$p['max_webchat_sites']!==null?(int)$p['max_webchat_sites']:null,'max_daily_chats'=>$p['max_daily_chats']!==null?(int)$p['max_daily_chats']:null,'max_monthly_chats'=>$p['max_monthly_chats']!==null?(int)$p['max_monthly_chats']:null,
            'allowed_channels'=>$channels,'modules'=>$mods,'is_free'=>((int)($p['is_default_free']??0)===1)||(($p['code']??'')==='free'),'active'=>$active,
            'external_ai_included'=>(int)($p['external_ai_included']??0)===1,'external_ai_monthly_tokens'=>$p['external_ai_monthly_tokens']!==null?(int)$p['external_ai_monthly_tokens']:null,'external_ai_channels'=>$externalAiChannels
        ];
    }catch(Throwable $e){
        return ['unrestricted'=>true,'has_plan'=>false,'plan_id'=>0,'plan_code'=>'legacy','plan_name'=>'Sin plan','subscription_status'=>'active','max_users'=>null,'max_channels'=>null,'max_webchat_sites'=>null,'max_daily_chats'=>null,'max_monthly_chats'=>null,'allowed_channels'=>[],'modules'=>[],'is_free'=>false,'external_ai_included'=>true,'external_ai_monthly_tokens'=>null,'external_ai_channels'=>[]];
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


function zynkoPlanExternalConnectionLimit(array $ctx): ?int{
    $total=zynkoPlanLimit($ctx,'max_channels');
    if($total===null)return null;
    // NIVO Web Chat ocupa el canal base del tenant; el resto son conexiones externas.
    return max(0,$total-1);
}

function zynkoPlanExternalConnectionUsage(PDO $pdo,int $tenantId): int{
    try{$q=$pdo->prepare("SELECT COUNT(*) FROM channels WHERE tenant_id=? AND type<>'webchat' AND status<>'disconnected'");$q->execute([$tenantId]);return (int)$q->fetchColumn();}catch(Throwable $e){return 0;}
}

function zynkoPlanRequireModule(array $ctx,string $module): void{
    if(!zynkoPlanAllowsModule($ctx,$module)){
        $name=(string)($ctx['plan_name']??'actual');
        if(empty($ctx['active']))throw new RuntimeException('La suscripción de esta empresa no está activa. Revisa Facturación para continuar.');
        throw new RuntimeException('Esta función no está incluida en tu plan '.$name.'.');
    }
}

function zynkoPlanDailyChatUsage(PDO $pdo,int $tenantId): int{
    try{$q=$pdo->prepare("SELECT COUNT(*) FROM conversations c JOIN channels ch ON ch.id=c.channel_id AND ch.tenant_id=c.tenant_id WHERE c.tenant_id=? AND ch.type='webchat' AND DATE(c.created_at)=CURDATE()");$q->execute([$tenantId]);return (int)$q->fetchColumn();}catch(Throwable $e){return 0;}
}

function zynkoPlanMonthlyChatUsage(PDO $pdo,int $tenantId): int{
    try{$q=$pdo->prepare("SELECT COUNT(*) FROM conversations WHERE tenant_id=? AND created_at>=DATE_FORMAT(CURDATE(),'%Y-%m-01') AND created_at<DATE_ADD(LAST_DAY(CURDATE()),INTERVAL 1 DAY)");$q->execute([$tenantId]);return (int)$q->fetchColumn();}catch(Throwable $e){return 0;}
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
        $isPlatformTenant=function_exists('mainTenantId')&&$tenantId===mainTenantId();
        $sql='SELECT id FROM webchat_installations WHERE tenant_id=? AND enabled=1'.($isPlatformTenant?" AND NOT (created_by IS NULL AND label='Sitio principal ZYNKO')":'').' ORDER BY id';
        $q=$pdo->prepare($sql);$q->execute([$tenantId]);$ids=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
        foreach(array_slice($ids,$maxSites) as $id)$pdo->prepare('UPDATE webchat_installations SET enabled=0 WHERE id=? AND tenant_id=?')->execute([$id,$tenantId]);
    }
    // Al bajar de plan, las conexiones externas que ya no estén permitidas quedan desconectadas.
    if($channels){
        $placeholders=implode(',',array_fill(0,count($channels),'?'));
        $args=array_merge([$tenantId],$channels);
        $pdo->prepare("UPDATE channels SET status='disconnected' WHERE tenant_id=? AND type<>'webchat' AND type NOT IN ($placeholders)")->execute($args);
    }
    $maxChannels=$p['max_channels']!==null?(int)$p['max_channels']:0;
    if($maxChannels>0){
        $externalLimit=max(0,$maxChannels-1);
        $q=$pdo->prepare("SELECT id FROM channels WHERE tenant_id=? AND type<>'webchat' AND status<>'disconnected' ORDER BY id");$q->execute([$tenantId]);$ids=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
        foreach(array_slice($ids,$externalLimit) as $id)$pdo->prepare("UPDATE channels SET status='disconnected' WHERE id=? AND tenant_id=?")->execute([$id,$tenantId]);
    }
    $mods=zynkoDecodeMap($p['module_access_json']??null);
    if(empty($mods['api'])){
        $pdo->prepare('UPDATE api_keys SET revoked_at=COALESCE(revoked_at,NOW()) WHERE tenant_id=?')->execute([$tenantId]);
        $pdo->prepare('UPDATE outgoing_webhooks SET active=0 WHERE tenant_id=?')->execute([$tenantId]);
    }
    if(empty($mods['chatbot'])){
        $pdo->prepare('UPDATE bot_profiles SET enabled=0 WHERE tenant_id=?')->execute([$tenantId]);
        try{$pdo->prepare('UPDATE tenant_ai_settings SET enabled=0 WHERE tenant_id=?')->execute([$tenantId]);}catch(Throwable $e){}
    }
}
