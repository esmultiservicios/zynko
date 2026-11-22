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
        distinct_external_types TINYINT(1) NOT NULL DEFAULT 0,
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
        is_available TINYINT(1) NOT NULL DEFAULT 0,
        availability_label VARCHAR(80) NULL,
        availability_message VARCHAR(255) NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    zynkoPlanColumn($pdo,'subscription_plans','code','code VARCHAR(50) NULL UNIQUE AFTER id');
    zynkoPlanColumn($pdo,'subscription_plans','distinct_external_types','distinct_external_types TINYINT(1) NOT NULL DEFAULT 0 AFTER max_channels');
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
    zynkoPlanColumn($pdo,'subscription_plans','is_available','is_available TINYINT(1) NOT NULL DEFAULT 0 AFTER featured_label');
    zynkoPlanColumn($pdo,'subscription_plans','availability_label','availability_label VARCHAR(80) NULL AFTER is_available');
    zynkoPlanColumn($pdo,'subscription_plans','availability_message','availability_message VARCHAR(255) NULL AFTER availability_label');
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
        'users'=>1,'chatbot'=>0,'automations'=>0,'integrations'=>0,'email'=>0,'settings'=>0,'api'=>0
    ],JSON_UNESCAPED_SLASHES);
    $channels=json_encode(['webchat'],JSON_UNESCAPED_SLASHES);
    $features=json_encode([
        'NIVO Web Chat incluido','1 sitio autorizado para NIVO Web Chat','5 chats nuevos por día','Mensajes ilimitados dentro de cada chat','Usuarios de ZYNKO ilimitados'
    ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $q=$pdo->query("SELECT id FROM subscription_plans WHERE code='free' ORDER BY id LIMIT 1");
    $free=(int)($q->fetchColumn()?:0);
    if(!$free){$q=$pdo->query("SELECT id FROM subscription_plans WHERE is_default_free=1 ORDER BY id LIMIT 1");$free=(int)($q->fetchColumn()?:0);}
    if(!$free){
        $st=$pdo->prepare("INSERT INTO subscription_plans(code,name,monthly_price,currency,max_users,max_channels,max_webchat_sites,max_daily_chats,max_monthly_chats,allowed_channels_json,module_access_json,features_json,is_default_free,is_featured,featured_label,is_available,availability_label,availability_message,active) VALUES('free','Gratis',0,'USD',NULL,1,1,5,NULL,?,?,?,1,0,NULL,1,'Disponible',NULL,1)");
        $st->execute([$channels,$modules,$features]);
    }else{
        $pdo->prepare("UPDATE subscription_plans SET is_default_free=0,code=NULL WHERE id<>? AND (is_default_free=1 OR code='free')")->execute([$free]);
        $pdo->prepare("UPDATE subscription_plans SET code='free',name='Gratis',monthly_price=0,currency='USD',max_users=NULL,max_channels=1,max_webchat_sites=1,max_daily_chats=5,max_monthly_chats=NULL,allowed_channels_json=?,module_access_json=?,features_json=?,external_ai_included=0,external_ai_monthly_tokens=NULL,external_ai_channels_json=JSON_ARRAY(),is_default_free=1,is_featured=0,featured_label=NULL,is_available=1,availability_label='Disponible',availability_message=NULL,active=1 WHERE id=?")->execute([$channels,$modules,$features,$free]);
    }
    // Plan API: additive only; preserve the pricing and entitlement matrices of all existing plans.
    $q=$pdo->query("SELECT id FROM subscription_plans WHERE code='api_only' LIMIT 1");
    if(!$q->fetchColumn()){
        $apiModules=['dashboard'=>1,'channels'=>1,'integrations'=>1,'api'=>1,'billing'=>1];
        $apiFeatures=['API de WhatsApp individual y masiva','Vinculación de número propio','Claves API por canal','Historial de envíos independiente','Sin bandeja omnicanal'];
        $pdo->prepare("INSERT INTO subscription_plans(code,name,monthly_price,currency,max_users,max_channels,distinct_external_types,max_webchat_sites,max_daily_chats,max_monthly_chats,allowed_channels_json,module_access_json,features_json,is_default_free,is_featured,is_available,availability_label,active) VALUES('api_only','ZYNKO API',0,'USD',1,1,1,0,NULL,NULL,?,?,?,0,0,0,'Próximamente',1)")
            ->execute([json_encode(['whatsapp']),json_encode($apiModules),json_encode($apiFeatures,JSON_UNESCAPED_UNICODE)]);
    }

}

/** Commercial catalog revision. Prices and availability remain administrable. */
function zynkoUpgradeCommercialCatalog144(PDO $pdo): void {
    $marker='commercial_catalog_2_31_144';
    $pdo->beginTransaction();
    try {
        $claim=$pdo->prepare("INSERT IGNORE INTO system_settings(setting_key,setting_value) VALUES(?, '1')");
        $claim->execute([$marker]);
        if(!$claim->rowCount()){ $pdo->commit(); return; }
        $catalog=[
            'starter'=>[['webchat','whatsapp'],0,null,[],['NIVO Web Chat incluido','2 sitios autorizados para NIVO Web Chat','1 conexión externa: WhatsApp Business','500 chats nuevos por mes','API para conectar sitios y sistemas externos','Bandeja omnicanal y contactos','Usuarios de ZYNKO ilimitados']],
            'pro'=>[['webchat','whatsapp','messenger','instagram'],1,250000,['webchat','whatsapp','messenger','instagram','api'],['NIVO Web Chat incluido','5 sitios autorizados para NIVO Web Chat','Hasta 3 conexiones externas según canales habilitados','3,000 chats nuevos por mes','API completa para integraciones externas','NIVO IA y automatizaciones','Asignación de conversaciones, reportes y auditoría','Usuarios de ZYNKO ilimitados']],
            'business'=>[['webchat','whatsapp','messenger','instagram','telegram','email'],1,1000000,['webchat','whatsapp','messenger','instagram','telegram','email','api'],['NIVO Web Chat incluido','10 sitios autorizados para NIVO Web Chat','Hasta 10 conexiones externas según canales habilitados','10,000 chats nuevos por mes','API completa con mayor capacidad','NIVO IA y automatizaciones avanzadas','Reportes avanzados y auditoría completa','Soporte prioritario','Usuarios de ZYNKO ilimitados']]
        ];
        $get=$pdo->prepare('SELECT id,module_access_json FROM subscription_plans WHERE code=? LIMIT 1');
        $update=$pdo->prepare('UPDATE subscription_plans SET allowed_channels_json=?,module_access_json=?,features_json=?,external_ai_included=?,external_ai_monthly_tokens=?,external_ai_channels_json=? WHERE id=?');
        foreach($catalog as $code=>[$channels,$ai,$tokens,$aiChannels,$features]){
            $get->execute([$code]); $row=$get->fetch(); if(!$row)continue;
            $modules=zynkoDecodeMap($row['module_access_json']??null);
            $modules['chatbot']=$ai;$modules['automations']=$ai;$modules['integrations']=1;$modules['api']=1;
            // Email administration/sending remains available; its omnichannel connection depends on the allowed channels.
            $update->execute([json_encode($channels),json_encode($modules),json_encode($features,JSON_UNESCAPED_UNICODE),$ai,$tokens,json_encode($aiChannels),(int)$row['id']]);
        }
        $pdo->commit();
    } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

/** Add the baseline WhatsApp option to existing explicitly configured paid plans. */
function zynkoUpgradePaidWhatsApp145(PDO $pdo): void {
    $pdo->beginTransaction();
    try {
        $mark=$pdo->prepare("INSERT IGNORE INTO system_settings(setting_key,setting_value) VALUES('commercial_whatsapp_2_31_145','1')");
        $mark->execute();
        if(!$mark->rowCount()){ $pdo->commit(); return; }
        $rows=$pdo->query('SELECT id,allowed_channels_json,max_channels FROM subscription_plans WHERE monthly_price>0 AND is_default_free=0 FOR UPDATE')->fetchAll();
        $change=$pdo->prepare('UPDATE subscription_plans SET allowed_channels_json=?,max_channels=? WHERE id=?');
        foreach($rows as $plan){
            $channels=zynkoDecodeList($plan['allowed_channels_json']??null);
            $limit=$plan['max_channels'];
            // NULL/empty preserves the prior legacy semantics (all channels).
            $newChannels=$channels?array_values(array_unique(array_merge($channels,['webchat','whatsapp']))) : $channels;
            $newLimit=$limit!==null&&(int)$limit===1?2:$limit;
            if($channels!==$newChannels||$newLimit!==$limit){
                $change->execute([$channels!==$newChannels?json_encode($newChannels,JSON_UNESCAPED_SLASHES):$plan['allowed_channels_json'],$newLimit,(int)$plan['id']]);
            }
        }
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

/** Apply the revised commercial Pro catalog only once; preserve pricing and other custom options. */
function zynkoUpgradeCommercialCatalog146(PDO $pdo): void {
    $pdo->beginTransaction();
    try {
        $claim=$pdo->prepare("INSERT IGNORE INTO system_settings(setting_key,setting_value) VALUES('commercial_catalog_2_31_146','1')");
        $claim->execute();
        if(!$claim->rowCount()){ $pdo->commit(); return; }
        $get=$pdo->prepare("SELECT id,allowed_channels_json,external_ai_channels_json FROM subscription_plans WHERE code='pro' FOR UPDATE");$get->execute();
        $set=$pdo->prepare('UPDATE subscription_plans SET allowed_channels_json=?,external_ai_channels_json=? WHERE id=?');
        foreach($get->fetchAll() as $row){
            $channels=zynkoDecodeList($row['allowed_channels_json']);
            if(!$channels)$channels=['webchat','whatsapp','messenger','instagram'];
            $channels=array_values(array_unique(array_merge(array_diff($channels,['telegram','email']),['webchat','whatsapp'])));
            $aiChannels=array_values(array_diff(zynkoDecodeList($row['external_ai_channels_json']),['telegram','email']));
            $set->execute([json_encode($channels),json_encode($aiChannels),(int)$row['id']]);
        }
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

/** One-time commercial channel revision. Catalog publication is independent of plan inclusion. */
function zynkoUpgradeCommercialCatalog152(PDO $pdo): void {
    $pdo->beginTransaction();
    try {
        $mark=$pdo->prepare("INSERT IGNORE INTO system_settings(setting_key,setting_value) VALUES('commercial_catalog_2_31_152','1')");
        $mark->execute();
        if(!$mark->rowCount()){ $pdo->commit(); return; }
        // These connectors exist, but have not been commercially released. Do not disconnect active accounts.
        $pdo->exec("UPDATE channel_connector_catalog SET connector_ready=1,linkable=0 WHERE code IN ('messenger','instagram','telegram','email')");
        $pdo->exec("UPDATE channel_connector_catalog SET visible=1,connector_ready=0,linkable=0 WHERE code='threads'");
        $q=$pdo->query("SELECT * FROM subscription_plans WHERE code IN ('starter','pro','business') ORDER BY id FOR UPDATE");
        $save=$pdo->prepare('UPDATE subscription_plans SET max_channels=?,allowed_channels_json=?,external_ai_channels_json=?,features_json=? WHERE id=?');
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $p){
            $code=(string)$p['code'];
            $channels=match($code){'starter'=>['webchat','whatsapp'],'pro'=>['webchat','whatsapp','messenger','instagram'],default=>['webchat','whatsapp','messenger','instagram','telegram','email','threads','x']};
            if($code==='business')$channels=array_values(array_unique(array_merge($channels,zynkoDecodeList($p['allowed_channels_json']??null))));
            $max=match($code){'starter'=>2,'pro'=>3,default=>11};
            $ai=array_values(array_intersect(zynkoDecodeList($p['external_ai_channels_json']??null),array_merge($channels,['api'])));
            $features=zynkoDecodeList($p['features_json']??null);
            if($code==='pro'){
                $features=array_values(array_filter($features,fn($v)=>!is_string($v)||!preg_match('/(?:hasta|capacidad de hasta)\s+3\s+conexiones externas/iu',$v)));
                $features[]='Hasta 2 conexiones externas: elige entre WhatsApp, Messenger e Instagram';
                $features=array_values(array_unique($features));
            }
            $save->execute([$max,json_encode($channels),json_encode($ai),json_encode($features,JSON_UNESCAPED_UNICODE),(int)$p['id']]);
            $p['max_channels']=$max;$p['allowed_channels_json']=json_encode($channels);
            $tenants=$pdo->prepare('SELECT tenant_id FROM tenant_subscriptions WHERE plan_id=? ORDER BY tenant_id');$tenants->execute([(int)$p['id']]);
            foreach($tenants->fetchAll(PDO::FETCH_COLUMN) as $tid){
                $tid=(int)$tid;if(function_exists('mainTenantId')&&$tid===mainTenantId())continue;
                zynkoReconcilePlanChannels($pdo,$tid,$p);
            }
        }
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

/** v028: commercial defaults, without resetting later Owner edits or disconnecting existing accounts. */
function zynkoUpgradePlanConfiguration154(PDO $pdo): void {
    $pdo->beginTransaction();
    try {
        $mark=$pdo->prepare("INSERT IGNORE INTO system_settings(setting_key,setting_value) VALUES('commercial_plan_config_2_31_154','1')");$mark->execute();
        if($mark->rowCount()===1){
            $sql="UPDATE subscription_plans SET max_channels=CASE code WHEN 'free' THEN 1 WHEN 'starter' THEN 2 WHEN 'pro' THEN 3 WHEN 'business' THEN 5 ELSE max_channels END, distinct_external_types=1 WHERE code IN ('free','starter','pro','business')";
            $pdo->exec($sql);
            // Preserve the catalog selection made by Owner; v022/v025 supplied the standard allowed channel lists.
            $pdo->exec("UPDATE subscription_plans SET max_channels=1,allowed_channels_json=JSON_ARRAY('webchat') WHERE code='free'");
            $pdo->exec("UPDATE subscription_plans SET allowed_channels_json=JSON_ARRAY('webchat','whatsapp') WHERE code='starter'");
            $pdo->exec("UPDATE subscription_plans SET allowed_channels_json=JSON_ARRAY('webchat','whatsapp','messenger','instagram') WHERE code='pro'");
            $pdo->exec("UPDATE subscription_plans SET allowed_channels_json=JSON_ARRAY('webchat','whatsapp','messenger','instagram','telegram','email','threads','x') WHERE code='business' AND (allowed_channels_json IS NULL OR JSON_LENGTH(allowed_channels_json)=0)");
            $q=$pdo->query("SELECT id,features_json FROM subscription_plans WHERE code IN ('starter','pro','business')");$save=$pdo->prepare('UPDATE subscription_plans SET features_json=? WHERE id=?');
            foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row)$save->execute([json_encode(zynkoPlanDisplayFeatures($row),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),(int)$row['id']]);
        }
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
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

/** Shared plan presentation for the admin directory, public cards and comparison. */
function zynkoPlanChannelCatalog(array $rows=[], string $language='es'): array {
    $en=$language==='en';
    $catalog=[
        'webchat'=>['name'=>'NIVO Web Chat','icon'=>'fa-solid fa-message','visible'=>true,'available'=>true],
        'whatsapp'=>['name'=>'WhatsApp Business','icon'=>'fa-brands fa-whatsapp','visible'=>true,'available'=>false],
        'messenger'=>['name'=>'Messenger','icon'=>'fa-brands fa-facebook-messenger','visible'=>true,'available'=>false],
        'instagram'=>['name'=>'Instagram Messaging','icon'=>'fa-brands fa-instagram','visible'=>true,'available'=>false],
        'telegram'=>['name'=>'Telegram','icon'=>'fa-brands fa-telegram','visible'=>true,'available'=>false],
        'email'=>['name'=>$en?'Omnichannel email':'Correo omnicanal','icon'=>'fa-solid fa-envelope','visible'=>true,'available'=>false],
        'threads'=>['name'=>'Threads','icon'=>'fa-brands fa-threads','visible'=>true,'available'=>false],
        'x'=>['name'=>'X / Twitter','icon'=>'fa-brands fa-x-twitter','visible'=>false,'available'=>false],
    ];
    foreach($rows as $row){
        $code=(string)($row['code']??'');
        if(!preg_match('/^[a-z0-9_-]+$/D',$code))continue;
        if(!isset($catalog[$code])){
            $icon=(string)($row['icon_class']??'');
            if(!preg_match('/^fa-(solid|regular|brands) fa-[a-z0-9-]+$/D',$icon))$icon='fa-solid fa-plug';
            $catalog[$code]=['name'=>trim((string)($row['name']??''))?:$code,'icon'=>$icon,'visible'=>false,'available'=>false];
        }
        $catalog[$code]['visible']=!empty($row['visible']);
        $catalog[$code]['available']=zynkoChannelCatalogRowAvailable($row);
    }
    return $catalog;
}
/** A planned connector cannot be advertised or linked merely because a plan includes it. */
function zynkoChannelCatalogRowAvailable(array $row): bool {
    return ($row['code']??'')==='webchat'||(!empty($row['visible'])&&!empty($row['connector_ready'])&&!empty($row['linkable']));
}
function zynkoChannelCatalogAvailable(PDO $pdo,string $channel): bool {
    if($channel==='webchat')return true;
    $q=$pdo->prepare('SELECT code,visible,connector_ready,linkable FROM channel_connector_catalog WHERE code=?');$q->execute([$channel]);
    $row=$q->fetch(PDO::FETCH_ASSOC);return $row?zynkoChannelCatalogRowAvailable($row):false;
}
function zynkoPlanChannelAvailability(array $catalog,string $code,string $language='es'): string {
    $ready=$code==='webchat'||!empty($catalog[$code]['available']);
    return $language==='en'?($ready?'Available':'Coming soon'):($ready?'Disponible':'Próximamente');
}
function zynkoPlanChannelStatus(array $catalog,string $code,string $language='es'): string {
    $ready=$code==='webchat'||!empty($catalog[$code]['available']);
    return '<span class="plan-channel-availability '.($ready?'is-ready':'is-upcoming').'"><i class="fa-solid '.($ready?'fa-circle-check':'fa-clock').'" aria-hidden="true"></i> '.htmlspecialchars(zynkoPlanChannelAvailability($catalog,$code,$language),ENT_QUOTES,'UTF-8').'</span>';
}
function zynkoPlanDisplayChannels(array $plan,array $catalog): array {
    // The free plan has a server-side restriction; legacy paid plans with no
    // explicit matrix allow the visible catalog, as zynkoPlanAllowsChannel does.
    if(!empty($plan['is_default_free'])||($plan['code']??'')==='free')return ['webchat'];
    $codes=zynkoDecodeList($plan['allowed_channels_json']??null);
    if(!$codes)$codes=array_keys(array_filter($catalog,fn($entry)=>!empty($entry['visible'])));
    $codes=array_values(array_unique(array_merge(['webchat'],array_filter($codes,fn($code)=>is_string($code)&&preg_match('/^[a-z0-9_-]+$/D',$code)))));
    $ordered=array_values(array_intersect(array_keys($catalog),$codes));
    return array_merge($ordered,array_values(array_diff($codes,$ordered)));
}
function zynkoPlanChannelDisplay(array $catalog,string $code): array {
    return $catalog[$code]??['name'=>$code,'icon'=>'fa-solid fa-plug','visible'=>false];
}
function zynkoPlanChannelIcon(array $catalog,string $code): string {
    $item=zynkoPlanChannelDisplay($catalog,$code);
    return '<span class="plan-channel-icon" data-channel="'.htmlspecialchars($code,ENT_QUOTES,'UTF-8').'"><i class="'.htmlspecialchars($item['icon'],ENT_QUOTES,'UTF-8').'" aria-hidden="true"></i></span>';
}
/** Capacity uses the real catalog and stored limits; NIVO has its own website quota. */
function zynkoPlanChannelStats(array $plan,array $catalog): array {
    $codes=zynkoPlanDisplayChannels($plan,$catalog);
    $external=array_values(array_diff($codes,['webchat']));
    $count=count($external);$ready=count(array_filter($external,fn($code)=>!empty($catalog[$code]['available'])));
    $limit=zynkoPlanExternalConnectionLimit($plan);$distinct=zynkoPlanDistinctExternalTypes($plan);
    if(!$count){$limit=0;}elseif($distinct){$limit=$limit===null?$count:min($limit,$count);}
    return compact('codes','external','count','ready','limit','distinct')+['visible_total'=>count($codes),'ready_total'=>$ready+(in_array('webchat',$codes,true)?1:0)];
}
function zynkoPlanChannelHeading(array $stats,string $language='es'): string {
    $en=$language==='en';$limit=$stats['limit'];$count=$stats['count'];
    if($limit===0||$count===0)return $en?'No external channels':'Sin canales externos';
    if($limit===1&&$count===1)return $en?'1 external channel':'1 canal externo';
    if($stats['distinct'])return $en?"Choose up to $limit of $count external channels":"Elige hasta $limit de $count canales externos";
    if($limit===null)return $en?'Unlimited external connections':'Conexiones externas ilimitadas';
    return $en?"Up to $limit external connections":"Hasta $limit conexiones externas";
}
function zynkoPlanChannelDetail(array $stats,string $language='es'): string {
    $en=$language==='en';$count=$stats['count'];$limit=$stats['limit'];
    if(!$count||$limit===0)return $en?'NIVO Web Chat included.':'NIVO Web Chat incluido.';
    $rule=$stats['distinct']&&$count>1?($en?'One account per channel type. ':'Una cuenta por tipo de canal. '):(!$stats['distinct']&&$count>1?($en?'Multiple accounts per eligible type allowed. ':'Se permiten varias cuentas por tipo elegible. '):'');
    return $rule.($en?'NIVO Web Chat is included separately; it does not use an external slot.':'NIVO Web Chat se incluye por separado; no consume un cupo externo.');
}
function zynkoPlanChannelCountText(array $stats,string $language='es'): string {
    $en=$language==='en';$r=$stats['ready'];$c=$stats['count'];$rt=$stats['ready_total'];$t=$stats['visible_total'];
    return $en?"$rt of $t channels currently available · $r of $c external":"$rt de $t canales disponibles actualmente · $r de $c externos";
}
function zynkoPlanChannelCapacityText(array $plan,array $catalog,string $language='es'): string {
    $stats=zynkoPlanChannelStats($plan,$catalog);
    return zynkoPlanChannelHeading($stats,$language).'. '.zynkoPlanChannelDetail($stats,$language).' '.zynkoPlanChannelCountText($stats,$language).'.';
}
/** Standard numeric capacity descriptions are rendered dynamically rather than duplicated in benefits. */
function zynkoPlanDisplayFeatures(array $plan): array {
    return array_values(array_filter(zynkoDecodeList($plan['features_json']??null),fn($v)=>is_string($v)&&!preg_match('/^(?:(?:capacidad de )?hasta\s+|elige hasta\s+)?\d+\s+conexi[oó]n(?:es)?\s+externa(?:s)?(?:\b|:)/iu',trim($v))));
}
function zynkoPlanChannelBadges(array $plan,array $catalog,string $label='Canales elegibles',string $language='es'): string {
    $esc=fn($x)=>htmlspecialchars((string)$x,ENT_QUOTES,'UTF-8');$stats=zynkoPlanChannelStats($plan,$catalog);
    $html='<div class="plan-channel-list"><b class="plan-channel-list-title">'.$esc($label).'</b><div class="plan-channel-capacity"><strong>'.$esc(zynkoPlanChannelHeading($stats,$language)).'</strong><small>'.$esc(zynkoPlanChannelDetail($stats,$language)).'</small></div><div class="plan-channel-items">';
    foreach($stats['codes'] as $code){
        $item=zynkoPlanChannelDisplay($catalog,$code);$ready=$code==='webchat'||!empty($catalog[$code]['available']);
        $html.='<span class="plan-channel-chip '.($ready?'is-ready':'is-upcoming').'">'.zynkoPlanChannelIcon($catalog,$code).'<span class="plan-channel-name">'.$esc($item['name']).'</span>'.zynkoPlanChannelStatus($catalog,$code,$language).'</span>';
    }
    $html.='</div><small class="plan-channel-current">'.$esc(zynkoPlanChannelCountText($stats,$language)).'</small>';
    return $html.'</div>';
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
            'max_users'=>$p['max_users']!==null?(int)$p['max_users']:null,'max_channels'=>$p['max_channels']!==null?(int)$p['max_channels']:null,'distinct_external_types'=>(int)($p['distinct_external_types']??(($p['code']??'')==='pro')), 
            'max_webchat_sites'=>$p['max_webchat_sites']!==null?(int)$p['max_webchat_sites']:null,'max_daily_chats'=>$p['max_daily_chats']!==null?(int)$p['max_daily_chats']:null,'max_monthly_chats'=>$p['max_monthly_chats']!==null?(int)$p['max_monthly_chats']:null,
            'allowed_channels'=>$channels,'modules'=>$mods,'is_free'=>((int)($p['is_default_free']??0)===1)||(($p['code']??'')==='free'),'active'=>$active,
            'external_ai_included'=>(int)($p['external_ai_included']??0)===1,'external_ai_monthly_tokens'=>$p['external_ai_monthly_tokens']!==null?(int)$p['external_ai_monthly_tokens']:null,'external_ai_channels'=>$externalAiChannels
        ];
    }catch(Throwable $e){
        error_log('ZYNKO plan lookup failed: '.get_class($e));
        return ['unrestricted'=>false,'has_plan'=>false,'plan_id'=>0,'plan_code'=>'unknown','plan_name'=>'No disponible','subscription_status'=>'unknown','active'=>false,'allowed_channels'=>[],'modules'=>[],'is_free'=>false,'external_ai_included'=>false,'external_ai_channels'=>[]];
    }
}

function zynkoPlanAllowsModule(array $ctx,string $module): bool{
    if(!empty($ctx['unrestricted']))return true;
    if(($ctx['plan_code']??'')==='api_only')return !empty($ctx['active']) && in_array($module,['dashboard','channels','integrations','api','billing'],true) && !empty($ctx['modules'][$module]);
    if(empty($ctx['active']))return $module==='billing';
    if(!empty($ctx['is_free'])&&in_array($module,['chatbot','automations','integrations','api','email'],true))return false;
    if(in_array($module,['dashboard','inbox','channels','webchat','billing','onboarding','surveys','documentation','logs'],true))return true;
    $mods=$ctx['modules']??[];
    return !empty($mods[$module]);
}

function zynkoPlanAllowsPage(array $ctx,string $page): bool{
    return zynkoPlanAllowsModule($ctx,$page);
}

function zynkoPlanAllowsChannel(array $ctx,string $channel): bool{
    if(!empty($ctx['unrestricted']))return true;
    if(empty($ctx['active']))return false;
    if(!empty($ctx['is_free']))return $channel==='webchat';
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
    if(!empty($ctx['unrestricted']))return null;
    if(!empty($ctx['is_free'])||!empty($ctx['is_default_free'])||($ctx['plan_code']??$ctx['code']??'')==='free')return 0;
    $total=zynkoPlanLimit($ctx,'max_channels');
    return $total===null?null:max(0,$total-1);
}
function zynkoPlanDistinctExternalTypes(array $ctx): bool {
    if(!empty($ctx['unrestricted']))return false;
    return array_key_exists('distinct_external_types',$ctx)?!empty($ctx['distinct_external_types']):(($ctx['plan_code']??$ctx['code']??'')==='pro');
}
function zynkoPlanExternalConnectionUsage(PDO $pdo,int $tenantId): int{
    $q=$pdo->prepare("SELECT COUNT(*) FROM channels WHERE tenant_id=? AND type<>'webchat' AND status<>'disconnected'");$q->execute([$tenantId]);return (int)$q->fetchColumn();
}
/** Pure admission decision; pending and warning accounts occupy slots, disconnected accounts do not. */
function zynkoPlanConnectionDenial(array $ctx,string $type,array $connections,int $existingId=0,bool $catalogAvailable=true): ?string {
    if(!zynkoPlanAllowsChannel($ctx,$type))return 'Este canal no está incluido en tu plan actual.';
    if($type==='webchat')return null;
    if(!$catalogAvailable)return 'Este canal está previsto en el catálogo, pero todavía no está habilitado para nuevas conexiones.';
    $active=array_values(array_filter($connections,fn($r)=>($r['type']??'')!=='webchat'&&($r['status']??'')!=='disconnected'&&(int)($r['id']??0)!==$existingId));
    $limit=zynkoPlanExternalConnectionLimit($ctx);
    if($limit!==null&&count($active)>=$limit)return 'Alcanzaste el límite de '.$limit.' conexiones externas de tu plan. Desconecta una conexión para liberar un cupo.';
    if(zynkoPlanDistinctExternalTypes($ctx))foreach($active as $r)if(($r['type']??'')===$type)return 'Tu plan permite una cuenta por tipo de canal. Elige otro canal o solicita un cambio de plan.';
    return null;
}
/** Call under the tenant row lock for admission; an existing enabled connection can still be maintained. */
function zynkoPlanRequireConnection(PDO $pdo,int $tenantId,array $ctx,string $type,int $existingId=0): void {
    if(!zynkoPlanAllowsChannel($ctx,$type))throw new RuntimeException('Este canal no está incluido en tu plan actual.');
    if($type==='webchat')return;
    $q=$pdo->prepare("SELECT id,type,status FROM channels WHERE tenant_id=? AND type<>'webchat' ORDER BY id");$q->execute([$tenantId]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as $row)if((int)$row['id']===$existingId&&$row['type']===$type&&$row['status']!=='disconnected')return;
    $error=zynkoPlanConnectionDenial($ctx,$type,$rows,$existingId,zynkoChannelCatalogAvailable($pdo,$type));
    if($error!==null)throw new RuntimeException($error);
}
/** A reconnect must claim a slot before a remote validation can change status. */
function zynkoPlanReserveReconnect(PDO $pdo,int $tenantId,array $ctx,array $channel): void {
    if(($channel['status']??'')!=='disconnected')return;
    $pdo->beginTransaction();
    try{
        $pdo->prepare('SELECT id FROM tenants WHERE id=? FOR UPDATE')->execute([$tenantId]);
        $current=zynkoPlanContext($pdo,$tenantId,!empty($ctx['unrestricted'])&&($ctx['plan_code']??'')==='platform');
        zynkoPlanRequireConnection($pdo,$tenantId,$current,(string)$channel['type'],(int)$channel['id']);
        $pdo->prepare("UPDATE channels SET status='pending' WHERE tenant_id=? AND id=? AND status='disconnected'")->execute([$tenantId,(int)$channel['id']]);
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
/** Deterministic reconciliation without deleting history or credentials. Connected accounts precede pending ones. */
function zynkoPlanExcessChannels(array $plan,array $rows): array {
    $allowed=zynkoDecodeList($plan['allowed_channels_json']??null);$limit=zynkoPlanExternalConnectionLimit($plan);$seen=[];$keep=0;$excess=[];
    foreach($rows as $r){$type=(string)$r['type'];if($type==='webchat'||$r['status']==='disconnected')continue;
        if(($allowed&&!in_array($type,$allowed,true))||($limit!==null&&$keep>=$limit)||(zynkoPlanDistinctExternalTypes($plan)&&isset($seen[$type]))){$excess[]=(int)$r['id'];continue;}
        $keep++;$seen[$type]=true;
    }return $excess;
}
function zynkoReconcilePlanChannels(PDO $pdo,int $tenantId,array $plan): void {
     $own=!$pdo->inTransaction();if($own)$pdo->beginTransaction();try{
    $pdo->prepare('SELECT id FROM tenants WHERE id=? FOR UPDATE')->execute([$tenantId]);
    $q=$pdo->prepare("SELECT id,type,status FROM channels WHERE tenant_id=? AND type<>'webchat' AND status<>'disconnected' ORDER BY FIELD(status,'connected','warning','pending'),id");$q->execute([$tenantId]);
    $rows=$q->fetchAll(PDO::FETCH_ASSOC);$disconnect=$pdo->prepare("UPDATE channels SET status='disconnected' WHERE id=? AND tenant_id=?");
    foreach(zynkoPlanExcessChannels($plan,$rows) as $id)$disconnect->execute([$id,$tenantId]);
    if($own)$pdo->commit();}catch(Throwable $e){if($own&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function zynkoPlanRequireModule(array $ctx,string $module): void{
    if(!zynkoPlanAllowsModule($ctx,$module)){
        $name=(string)($ctx['plan_name']??'actual');
        if(empty($ctx['active']))throw new RuntimeException('La suscripción de esta empresa no está activa. Revisa Facturación para continuar.');
        throw new RuntimeException('Esta función no está incluida en tu plan '.$name.'.');
    }
}

function zynkoPlanDailyChatUsage(PDO $pdo,int $tenantId): int{
    $q=$pdo->prepare("SELECT COUNT(*) FROM conversations WHERE tenant_id=? AND api_only=0 AND created_at>=CURDATE() AND created_at<DATE_ADD(CURDATE(),INTERVAL 1 DAY)");$q->execute([$tenantId]);return (int)$q->fetchColumn();
}

function zynkoPlanMonthlyChatUsage(PDO $pdo,int $tenantId): int{
    $q=$pdo->prepare("SELECT COUNT(*) FROM conversations WHERE tenant_id=? AND api_only=0 AND created_at>=DATE_FORMAT(CURDATE(),'%Y-%m-01') AND created_at<DATE_ADD(LAST_DAY(CURDATE()),INTERVAL 1 DAY)");$q->execute([$tenantId]);return (int)$q->fetchColumn();
}

function zynkoPlanRequireChatCapacity(PDO $pdo,int $tenantId,array $ctx,string $type): void {
    if(!zynkoPlanAllowsChannel($ctx,$type))throw new RuntimeException('Canal no permitido por la suscripción.');
    if(!empty($ctx['unrestricted']))return;
    $daily=zynkoPlanLimit($ctx,'max_daily_chats');$monthly=zynkoPlanLimit($ctx,'max_monthly_chats');
    if($daily!==null&&zynkoPlanDailyChatUsage($pdo,$tenantId)>=$daily)throw new OverflowException('Límite de '.$daily.' chats nuevos por día alcanzado.');
    if($monthly!==null&&zynkoPlanMonthlyChatUsage($pdo,$tenantId)>=$monthly)throw new OverflowException('Límite de '.$monthly.' chats nuevos por mes alcanzado.');
}
/** For legacy API callers without a transaction, serialize the admission and insert atomically. */
function zynkoPlanCreateChat(PDO $pdo,int $tenantId,int $channelId,int $contactId,string $type,string $uuid,bool $platformOwner=false,bool $technicalApi=false): int {
    $own=!$pdo->inTransaction();if($own)$pdo->beginTransaction();
    try{
        $pdo->prepare('SELECT id FROM tenants WHERE id=? FOR UPDATE')->execute([$tenantId]);
        if(!$technicalApi)zynkoPlanRequireChatCapacity($pdo,$tenantId,zynkoPlanContext($pdo,$tenantId,$platformOwner),$type);
        $pdo->prepare("INSERT INTO conversations(tenant_id,uuid,channel_id,contact_id,status,last_message_at,api_only) VALUES(?,?,?,?,'open',NOW(),?)")->execute([$tenantId,$uuid,$channelId,$contactId,$technicalApi?1:0]);$id=(int)$pdo->lastInsertId();
        if($own)$pdo->commit();return $id;
    }catch(Throwable $e){if($own&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
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
        $sql='SELECT id FROM webchat_installations WHERE tenant_id=? AND enabled=1'.($isPlatformTenant?" AND created_by IS NOT NULL":'').' ORDER BY id';
        $q=$pdo->prepare($sql);$q->execute([$tenantId]);$ids=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
        foreach(array_slice($ids,$maxSites) as $id)$pdo->prepare('UPDATE webchat_installations SET enabled=0 WHERE id=? AND tenant_id=?')->execute([$id,$tenantId]);
    }
    zynkoReconcilePlanChannels($pdo,$tenantId,$p);
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
