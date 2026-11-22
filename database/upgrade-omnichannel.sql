
-- ZYNKO: script unificado de actualización (v031; MySQL 8 / MariaDB).
-- Seleccione la base de datos ZYNKO existente y realice un respaldo antes de ejecutar.
-- Las tablas nuevas se crean si no existen. La sección comercial reafirma los límites estándar de los planes.
-- No utilizar schema.sql para actualizar una instalación existente.
CREATE TABLE IF NOT EXISTS channel_event_receipts (
            channel_id BIGINT UNSIGNED NOT NULL, tenant_id BIGINT UNSIGNED NOT NULL,
            event_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(channel_id,event_key), INDEX(tenant_id,created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS channel_message_receipts (
            channel_id BIGINT UNSIGNED NOT NULL, tenant_id BIGINT UNSIGNED NOT NULL,
            provider_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            provider_id VARCHAR(512) NOT NULL, destination VARCHAR(190) NOT NULL DEFAULT '',
            message_id BIGINT UNSIGNED NOT NULL, delivery_status VARCHAR(20) NOT NULL DEFAULT 'sent',
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY(channel_id,provider_key), INDEX(tenant_id,message_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS channel_outbox (
            message_id BIGINT UNSIGNED NOT NULL PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
            conversation_id BIGINT UNSIGNED NOT NULL, channel_id BIGINT UNSIGNED NOT NULL,
            state VARCHAR(20) NOT NULL DEFAULT 'queued', attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, claimed_at DATETIME NULL,
            last_error VARCHAR(500) NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX(state,next_attempt_at), INDEX(tenant_id,channel_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS channel_pending_status (
            channel_id BIGINT UNSIGNED NOT NULL, tenant_id BIGINT UNSIGNED NOT NULL,
            provider_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            delivery_status VARCHAR(20) NOT NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY(channel_id,provider_key), INDEX(tenant_id,updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Additional omnichannel tables. Existing data is not modified.
CREATE TABLE IF NOT EXISTS channel_verified_phones (
            phone VARCHAR(15) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY, channel_id BIGINT UNSIGNED NOT NULL UNIQUE, tenant_id BIGINT UNSIGNED NOT NULL, verified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(tenant_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS channel_media_assets (
            channel_id BIGINT UNSIGNED NOT NULL, tenant_id BIGINT UNSIGNED NOT NULL, asset_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            source_ciphertext TEXT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, cached_at DATETIME NULL, mime VARCHAR(190) NULL,
            PRIMARY KEY(channel_id,asset_key), INDEX(tenant_id,created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS channel_oauth_accounts (
            channel_id BIGINT UNSIGNED PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, provider VARCHAR(20) NOT NULL,
            refresh_ciphertext TEXT NULL, baseline_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, cursor_value VARCHAR(190) NULL,
            last_poll_at DATETIME NULL, next_poll_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, last_error VARCHAR(255) NULL,
            INDEX(provider,next_poll_at), INDEX(tenant_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS channel_template_events (
            channel_id BIGINT UNSIGNED NOT NULL, tenant_id BIGINT UNSIGNED NOT NULL, provider_template_id VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            template_name VARCHAR(160) NOT NULL, language VARCHAR(20) NULL, event_status VARCHAR(40) NOT NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY(channel_id,provider_template_id), INDEX(tenant_id,updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS channel_structured_outbox (
    message_id BIGINT UNSIGNED NOT NULL PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
    channel_id BIGINT UNSIGNED NOT NULL, payload_json JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(tenant_id,channel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Remitentes por propósito (Graph). Misma definición que schema.sql.
CREATE TABLE IF NOT EXISTS mail_sender_routes (tenant_id BIGINT UNSIGNED NOT NULL, purpose VARCHAR(24) NOT NULL, mailbox VARCHAR(190) NOT NULL, verified_at DATETIME NULL, PRIMARY KEY(tenant_id,purpose)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fase 2: contratos y activación asistida
CREATE TABLE IF NOT EXISTS commercial_document_versions (
 document_key VARCHAR(80) NOT NULL, version INT UNSIGNED NOT NULL, title VARCHAR(190) NOT NULL,
 content LONGTEXT NOT NULL, title_en VARCHAR(190) NULL, content_en LONGTEXT NULL, published_at DATETIME NOT NULL, PRIMARY KEY(document_key,version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS commercial_acceptances (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, context VARCHAR(30) NOT NULL, reference_id BIGINT UNSIGNED NOT NULL,
 tenant_id BIGINT UNSIGNED NULL, user_id BIGINT UNSIGNED NULL, documents_json JSON NOT NULL, plan_json JSON NULL,
 accepted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_commercial_acceptance(context,reference_id), INDEX(tenant_id,accepted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS commercial_services (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name_es VARCHAR(160) NOT NULL, name_en VARCHAR(160) NOT NULL,
 description_es VARCHAR(1000) NOT NULL, description_en VARCHAR(1000) NOT NULL, pricing_mode VARCHAR(20) NOT NULL DEFAULT 'quote',
 price DECIMAL(12,2) NULL, currency CHAR(3) NOT NULL DEFAULT 'USD', active TINYINT(1) NOT NULL DEFAULT 1,
 sort_order INT NOT NULL DEFAULT 0, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS channel_activation_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, requested_by BIGINT UNSIGNED NOT NULL,
 method VARCHAR(20) NOT NULL, phone VARCHAR(20) NULL, progress_json JSON NOT NULL, assistance TINYINT(1) NOT NULL DEFAULT 0,
 status VARCHAR(30) NOT NULL DEFAULT 'pending', assigned_to BIGINT UNSIGNED NULL, followup_at DATETIME NULL,
 channel_id BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX(tenant_id,status), INDEX(status,followup_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS channel_activation_history (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, request_id BIGINT UNSIGNED NOT NULL, actor_id BIGINT UNSIGNED NOT NULL,
 status VARCHAR(30) NOT NULL, note VARCHAR(1000) NOT NULL DEFAULT '', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX(request_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS commercial_plan_copy (
 plan_id BIGINT UNSIGNED NOT NULL PRIMARY KEY, summary_es VARCHAR(250) NOT NULL DEFAULT '', summary_en VARCHAR(250) NOT NULL DEFAULT '',
 note_es VARCHAR(1000) NOT NULL DEFAULT '', note_en VARCHAR(1000) NOT NULL DEFAULT '', translations_json JSON NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2.31.152: placeholder catalog entry, not an implemented connector.
-- The runtime migration zynkoUpgradeCommercialCatalog152 applies the commercial revision once,
-- including reconciliation of existing subscriptions. Keep application startup enabled after deployment.
INSERT IGNORE INTO channel_connector_catalog(code,name,icon_class,icon_style,description,connector_ready,visible,linkable,sort_order)
VALUES ('threads','Threads','fa-brands fa-threads','instagram','Canal previsto; requiere un conector real y autorización del proveedor.',0,1,0,80);

-- v028: traducciones en la misma revisión de los documentos legales.
SET @zynko_ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='legal_documents' AND COLUMN_NAME='title_en'), 'SELECT 1', 'ALTER TABLE `legal_documents` ADD COLUMN `title_en` VARCHAR(190) NULL AFTER `content`');
PREPARE zynko_stmt FROM @zynko_ddl;
EXECUTE zynko_stmt;
DEALLOCATE PREPARE zynko_stmt;
SET @zynko_ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='legal_documents' AND COLUMN_NAME='content_en'), 'SELECT 1', 'ALTER TABLE `legal_documents` ADD COLUMN `content_en` LONGTEXT NULL AFTER `title_en`');
PREPARE zynko_stmt FROM @zynko_ddl;
EXECUTE zynko_stmt;
DEALLOCATE PREPARE zynko_stmt;
SET @zynko_ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='commercial_document_versions' AND COLUMN_NAME='title_en'), 'SELECT 1', 'ALTER TABLE `commercial_document_versions` ADD COLUMN `title_en` VARCHAR(190) NULL AFTER `content`');
PREPARE zynko_stmt FROM @zynko_ddl;
EXECUTE zynko_stmt;
DEALLOCATE PREPARE zynko_stmt;
SET @zynko_ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='commercial_document_versions' AND COLUMN_NAME='content_en'), 'SELECT 1', 'ALTER TABLE `commercial_document_versions` ADD COLUMN `content_en` LONGTEXT NULL AFTER `title_en`');
PREPARE zynko_stmt FROM @zynko_ddl;
EXECUTE zynko_stmt;
DEALLOCATE PREPARE zynko_stmt;

-- v028: política de conexiones configurable; max_channels mantiene el cupo reservado para Web Chat.
-- Respalde antes de actualizar. No se habilitan conectores ni se desconectan cuentas existentes.
SET @zynko_ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='subscription_plans' AND COLUMN_NAME='distinct_external_types'), 'SELECT 1', 'ALTER TABLE `subscription_plans` ADD COLUMN `distinct_external_types` TINYINT(1) NOT NULL DEFAULT 0 AFTER `max_channels`');
PREPARE zynko_stmt FROM @zynko_ddl;
EXECUTE zynko_stmt;
DEALLOCATE PREPARE zynko_stmt;
START TRANSACTION;
INSERT IGNORE INTO system_settings(setting_key,setting_value) VALUES('commercial_plan_config_2_31_154','1');
SET @zynko_apply_plan_config_154 := ROW_COUNT();
UPDATE subscription_plans SET max_channels=CASE code WHEN 'free' THEN 1 WHEN 'starter' THEN 2 WHEN 'pro' THEN 3 WHEN 'business' THEN 5 ELSE max_channels END,distinct_external_types=1 WHERE @zynko_apply_plan_config_154=1 AND code IN ('free','starter','pro','business');
UPDATE subscription_plans SET max_channels=1,allowed_channels_json=JSON_ARRAY('webchat') WHERE @zynko_apply_plan_config_154=1 AND code='free';
UPDATE subscription_plans SET allowed_channels_json=JSON_ARRAY('webchat','whatsapp') WHERE @zynko_apply_plan_config_154=1 AND code='starter';
UPDATE subscription_plans SET allowed_channels_json=JSON_ARRAY('webchat','whatsapp','messenger','instagram') WHERE @zynko_apply_plan_config_154=1 AND code='pro';
UPDATE subscription_plans SET allowed_channels_json=JSON_ARRAY('webchat','whatsapp','messenger','instagram','telegram','email','threads','x') WHERE @zynko_apply_plan_config_154=1 AND code='business' AND (allowed_channels_json IS NULL OR JSON_LENGTH(allowed_channels_json)=0);
-- Eliminar únicamente los beneficios numéricos obsoletos conocidos; conservar los personalizados.
UPDATE subscription_plans SET features_json=JSON_REMOVE(features_json,JSON_UNQUOTE(JSON_SEARCH(features_json,'one','1 conexión externa: WhatsApp Business'))) WHERE @zynko_apply_plan_config_154=1 AND code IN ('starter','pro','business') AND JSON_SEARCH(features_json,'one','1 conexión externa: WhatsApp Business') IS NOT NULL;
UPDATE subscription_plans SET features_json=JSON_REMOVE(features_json,JSON_UNQUOTE(JSON_SEARCH(features_json,'one','Hasta 2 conexiones externas: elige entre WhatsApp, Messenger e Instagram'))) WHERE @zynko_apply_plan_config_154=1 AND code IN ('starter','pro','business') AND JSON_SEARCH(features_json,'one','Hasta 2 conexiones externas: elige entre WhatsApp, Messenger e Instagram') IS NOT NULL;
UPDATE subscription_plans SET features_json=JSON_REMOVE(features_json,JSON_UNQUOTE(JSON_SEARCH(features_json,'one','Capacidad de hasta 10 conexiones externas según canales habilitados'))) WHERE @zynko_apply_plan_config_154=1 AND code IN ('starter','pro','business') AND JSON_SEARCH(features_json,'one','Capacidad de hasta 10 conexiones externas según canales habilitados') IS NOT NULL;
UPDATE subscription_plans SET features_json=JSON_REMOVE(features_json,JSON_UNQUOTE(JSON_SEARCH(features_json,'one','Hasta 10 conexiones externas según canales habilitados'))) WHERE @zynko_apply_plan_config_154=1 AND code IN ('starter','pro','business') AND JSON_SEARCH(features_json,'one','Hasta 10 conexiones externas según canales habilitados') IS NOT NULL;
UPDATE subscription_plans SET features_json=JSON_REMOVE(features_json,JSON_UNQUOTE(JSON_SEARCH(features_json,'one','Capacidad de hasta 3 conexiones externas según canales habilitados'))) WHERE @zynko_apply_plan_config_154=1 AND code IN ('starter','pro','business') AND JSON_SEARCH(features_json,'one','Capacidad de hasta 3 conexiones externas según canales habilitados') IS NOT NULL;
UPDATE subscription_plans SET features_json=JSON_REMOVE(features_json,JSON_UNQUOTE(JSON_SEARCH(features_json,'one','Hasta 3 conexiones externas según canales habilitados'))) WHERE @zynko_apply_plan_config_154=1 AND code IN ('starter','pro','business') AND JSON_SEARCH(features_json,'one','Hasta 3 conexiones externas según canales habilitados') IS NOT NULL;
COMMIT;
SELECT code,name,monthly_price,max_channels-1 AS cupos_externos,distinct_external_types,allowed_channels_json FROM subscription_plans ORDER BY monthly_price;
SELECT code,visible,connector_ready,linkable FROM channel_connector_catalog ORDER BY sort_order;
-- Revisar cuentas heredadas por encima del nuevo cupo; no se desconectan automáticamente en esta actualización.
SELECT ts.tenant_id,sp.code,COUNT(ch.id) AS conexiones_externas,sp.max_channels-1 AS cupos FROM tenant_subscriptions ts JOIN subscription_plans sp ON sp.id=ts.plan_id JOIN channels ch ON ch.tenant_id=ts.tenant_id AND ch.type<>'webchat' AND ch.status<>'disconnected' WHERE sp.max_channels IS NOT NULL GROUP BY ts.tenant_id,sp.code,sp.max_channels HAVING COUNT(ch.id)>sp.max_channels-1;


-- Gestión departamental: las cuentas existentes conservan su configuración.
CREATE TABLE IF NOT EXISTS department_members (
 tenant_id BIGINT UNSIGNED NOT NULL,
 team_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NOT NULL,
 is_supervisor TINYINT(1) NOT NULL DEFAULT 0,
 max_active SMALLINT UNSIGNED NOT NULL DEFAULT 5,
 enabled TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(tenant_id,team_id,user_id),
 INDEX idx_dm_user(tenant_id,user_id),
 INDEX idx_dm_team(tenant_id,team_id,enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS department_routes (
 tenant_id BIGINT UNSIGNED NOT NULL,
 team_id BIGINT UNSIGNED NOT NULL,
 routing_mode ENUM('least_load','manual') NOT NULL DEFAULT 'least_load',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(tenant_id,team_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ZYNKO 2.31.155 — Plan API y registro separado de envíos (aditivo; no borra registros)
-- MariaDB 10.11+: ejecutar sobre la base ZYNKO, una vez; seguro ante reejecución.
ALTER TABLE api_keys
  ADD COLUMN IF NOT EXISTS channel_id BIGINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS delivery_mode VARCHAR(20) NOT NULL DEFAULT 'inbox';
ALTER TABLE conversations
  ADD COLUMN IF NOT EXISTS api_only TINYINT(1) NOT NULL DEFAULT 0;
CREATE INDEX IF NOT EXISTS idx_conv_api_visibility ON conversations(tenant_id,api_only);
CREATE TABLE IF NOT EXISTS api_message_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  api_key_id BIGINT UNSIGNED NOT NULL,
  channel_id BIGINT UNSIGNED NOT NULL,
  message_id BIGINT UNSIGNED NOT NULL,
  destination VARCHAR(80) NOT NULL,
  delivery_mode VARCHAR(20) NOT NULL DEFAULT 'independent',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_api_history_message(message_id),
  INDEX idx_api_history_tenant(tenant_id,created_at),
  INDEX idx_api_history_key(tenant_id,api_key_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO subscription_plans
  (code,name,monthly_price,currency,max_users,max_channels,distinct_external_types,max_webchat_sites,max_daily_chats,max_monthly_chats,allowed_channels_json,module_access_json,features_json,is_default_free,is_featured,is_available,availability_label,active)
SELECT 'api_only','ZYNKO API',0,'USD',1,1,1,0,NULL,NULL,
  JSON_ARRAY('whatsapp'),
  JSON_OBJECT('dashboard',1,'channels',1,'integrations',1,'api',1,'billing',1),
  JSON_ARRAY('API de WhatsApp individual y masiva','Vinculación de número propio','Claves API por canal','Historial de envíos independiente','Sin bandeja omnicanal'),
  0,0,0,'Próximamente',1
WHERE NOT EXISTS (SELECT 1 FROM subscription_plans WHERE code='api_only');
