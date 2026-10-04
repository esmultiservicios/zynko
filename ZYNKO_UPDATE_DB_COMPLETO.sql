-- ============================================================
-- ZYNKO - UPDATE_DB_COMPLETO ACUMULATIVO
-- UPDATE ACUMULATIVO COMPLETO PARA INSTALACIONES EXISTENTES
-- Base objetivo: la base actualmente seleccionada en phpMyAdmin/cliente SQL.
-- NO usa un nombre de BD fijo: funciona en local y producción con nombres distintos.
-- Idempotente: puede ejecutarse más de una vez.
-- ============================================================

SET @db_name := DATABASE();
-- IMPORTANTE: selecciona primero la base de datos de ZYNKO antes de ejecutar este archivo.
SELECT IF(@db_name IS NULL OR @db_name='', 'ERROR: selecciona primero la base de datos de ZYNKO', CONCAT('Base seleccionada: ',@db_name)) AS entorno;

-- ------------------------------------------------------------
-- 0) TABLA DE CONFIGURACION DEL SISTEMA
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `system_settings` (
  `setting_key` VARCHAR(80) NOT NULL PRIMARY KEY,
  `setting_value` VARCHAR(255) NOT NULL,
  `updated_by` BIGINT UNSIGNED NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Helper repetible para agregar una columna solo si no existe.
-- Se usa PREPARE para mantener compatibilidad con MySQL/MariaDB.
-- ------------------------------------------------------------

-- 1) EMPRESAS / TENANTS
SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='tenants' AND COLUMN_NAME='business_id');
SET @sql := IF(@exists=0,'ALTER TABLE `tenants` ADD COLUMN `business_id` VARCHAR(80) NULL AFTER `name`','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='tenants' AND COLUMN_NAME='contact_phone');
SET @sql := IF(@exists=0,'ALTER TABLE `tenants` ADD COLUMN `contact_phone` VARCHAR(50) NULL AFTER `business_id`','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='tenants' AND COLUMN_NAME='registration_source');
SET @sql := IF(@exists=0,'ALTER TABLE `tenants` ADD COLUMN `registration_source` VARCHAR(30) NULL AFTER `contact_phone`','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) NIVO IA OMNICANAL
SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='bot_profiles' AND COLUMN_NAME='channel_policy_json');
SET @sql := IF(@exists=0,'ALTER TABLE `bot_profiles` ADD COLUMN `channel_policy_json` JSON NULL AFTER `business_hours_json`','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) NIVO WEB CHAT - EXPERIENCIA AVANZADA
SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='webchat_widgets' AND COLUMN_NAME='experience_json');
SET @sql := IF(@exists=0,'ALTER TABLE `webchat_widgets` ADD COLUMN `experience_json` JSON NULL AFTER `allow_multiple_domains`','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4) PLANES + IA EXTERNA
SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='subscription_plans' AND COLUMN_NAME='max_monthly_chats');
SET @sql := IF(@exists=0,'ALTER TABLE `subscription_plans` ADD COLUMN `max_monthly_chats` INT NULL AFTER `max_daily_chats`','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='subscription_plans' AND COLUMN_NAME='is_featured');
SET @sql := IF(@exists=0,'ALTER TABLE `subscription_plans` ADD COLUMN `is_featured` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_default_free`','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='subscription_plans' AND COLUMN_NAME='featured_label');
SET @sql := IF(@exists=0,'ALTER TABLE `subscription_plans` ADD COLUMN `featured_label` VARCHAR(60) NULL AFTER `is_featured`','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='subscription_plans' AND COLUMN_NAME='is_available');
SET @sql := IF(@exists=0,'ALTER TABLE `subscription_plans` ADD COLUMN `is_available` TINYINT(1) NOT NULL DEFAULT 0 AFTER `featured_label`','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='subscription_plans' AND COLUMN_NAME='availability_label');
SET @sql := IF(@exists=0,'ALTER TABLE `subscription_plans` ADD COLUMN `availability_label` VARCHAR(80) NULL AFTER `is_available`','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='subscription_plans' AND COLUMN_NAME='availability_message');
SET @sql := IF(@exists=0,'ALTER TABLE `subscription_plans` ADD COLUMN `availability_message` VARCHAR(255) NULL AFTER `availability_label`','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='subscription_plans' AND COLUMN_NAME='external_ai_included');
SET @sql := IF(@exists=0,'ALTER TABLE `subscription_plans` ADD COLUMN `external_ai_included` TINYINT(1) NOT NULL DEFAULT 0 AFTER `features_json`','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='subscription_plans' AND COLUMN_NAME='external_ai_monthly_tokens');
SET @sql := IF(@exists=0,'ALTER TABLE `subscription_plans` ADD COLUMN `external_ai_monthly_tokens` BIGINT UNSIGNED NULL AFTER `external_ai_included`','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='subscription_plans' AND COLUMN_NAME='external_ai_channels_json');
SET @sql := IF(@exists=0,'ALTER TABLE `subscription_plans` ADD COLUMN `external_ai_channels_json` JSON NULL AFTER `external_ai_monthly_tokens`','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5) BANDEJA PREMIUM / ESTADO DE CONVERSACIONES
SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='conversations' AND COLUMN_NAME='archived_at');
SET @sql := IF(@exists=0,'ALTER TABLE `conversations` ADD COLUMN `archived_at` DATETIME NULL AFTER `last_message_at`','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='conversations' AND COLUMN_NAME='deleted_at');
SET @sql := IF(@exists=0,'ALTER TABLE `conversations` ADD COLUMN `deleted_at` DATETIME NULL AFTER `archived_at`','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='conversations' AND COLUMN_NAME='deleted_by');
SET @sql := IF(@exists=0,'ALTER TABLE `conversations` ADD COLUMN `deleted_by` BIGINT UNSIGNED NULL AFTER `deleted_at`','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 6) PREFERENCIAS DE BANDEJA
CREATE TABLE IF NOT EXISTS `inbox_preferences` (
  `user_id` BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  `channel_type` VARCHAR(40) NOT NULL DEFAULT 'all',
  `assignment_filter` VARCHAR(30) NOT NULL DEFAULT 'all',
  `priority_filter` VARCHAR(30) NOT NULL DEFAULT 'all',
  `category_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `state_filter` VARCHAR(30) NOT NULL DEFAULT 'active',
  `attention_filter` VARCHAR(30) NOT NULL DEFAULT 'all',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='inbox_preferences' AND COLUMN_NAME='category_id');
SET @sql := IF(@exists=0,'ALTER TABLE `inbox_preferences` ADD COLUMN `category_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `priority_filter`','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='inbox_preferences' AND COLUMN_NAME='state_filter');
SET @sql := IF(@exists=0,'ALTER TABLE `inbox_preferences` ADD COLUMN `state_filter` VARCHAR(30) NOT NULL DEFAULT ''active'' AFTER `category_id`','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='inbox_preferences' AND COLUMN_NAME='attention_filter');
SET @sql := IF(@exists=0,'ALTER TABLE `inbox_preferences` ADD COLUMN `attention_filter` VARCHAR(30) NOT NULL DEFAULT ''all'' AFTER `state_filter`','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 7) TABLAS DE AUDITORIA / CATEGORIAS
CREATE TABLE IF NOT EXISTS `contact_category_map` (
  `contact_id` BIGINT UNSIGNED NOT NULL,
  `category_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`contact_id`,`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `conversation_audit_logs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `conversation_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `action` VARCHAR(40) NOT NULL,
  `details_json` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_conv_audit` (`tenant_id`,`conversation_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `platform_admin_audit` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `admin_user_id` BIGINT UNSIGNED NOT NULL,
  `tenant_id` BIGINT UNSIGNED NULL,
  `target_user_id` BIGINT UNSIGNED NULL,
  `action` VARCHAR(80) NOT NULL,
  `details_json` JSON NULL,
  `ip_address` VARCHAR(64) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_platform_audit_created` (`created_at`),
  INDEX `idx_platform_audit_tenant` (`tenant_id`,`created_at`),
  INDEX `idx_platform_audit_user` (`target_user_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8) OPENAI / IA EXTERNA
CREATE TABLE IF NOT EXISTS `ai_provider_settings` (
  `id` TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
  `provider` VARCHAR(30) NOT NULL DEFAULT 'openai',
  `enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `api_key_ciphertext` TEXT NULL,
  `admin_key_ciphertext` TEXT NULL,
  `model` VARCHAR(120) NOT NULL DEFAULT 'gpt-6-luna',
  `fallback_only` TINYINT(1) NOT NULL DEFAULT 1,
  `monthly_budget_usd` DECIMAL(12,4) NULL,
  `input_cost_per_million` DECIMAL(12,6) NOT NULL DEFAULT 0.050000,
  `cached_input_cost_per_million` DECIMAL(12,6) NOT NULL DEFAULT 0.005000,
  `output_cost_per_million` DECIMAL(12,6) NOT NULL DEFAULT 0.250000,
  `max_output_tokens` INT UNSIGNED NOT NULL DEFAULT 700,
  `remote_month_cost_usd` DECIMAL(12,4) NULL,
  `remote_cost_refreshed_at` DATETIME NULL,
  `last_test_at` DATETIME NULL,
  `last_test_status` VARCHAR(20) NULL,
  `last_test_message` VARCHAR(500) NULL,
  `updated_by` BIGINT UNSIGNED NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `ai_provider_settings` (`id`,`provider`,`enabled`,`model`,`fallback_only`)
VALUES (1,'openai',0,'gpt-6-luna',1);

CREATE TABLE IF NOT EXISTS `tenant_ai_settings` (
  `tenant_id` BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  `enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `allowed_channels_json` JSON NULL,
  `updated_by` BIGINT UNSIGNED NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_usage_logs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `conversation_id` BIGINT UNSIGNED NULL,
  `provider` VARCHAR(30) NOT NULL DEFAULT 'openai',
  `channel_type` VARCHAR(50) NOT NULL,
  `model` VARCHAR(120) NOT NULL,
  `request_id` VARCHAR(190) NULL,
  `input_tokens` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `cached_input_tokens` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `output_tokens` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `estimated_cost_usd` DECIMAL(14,8) NOT NULL DEFAULT 0,
  `status` ENUM('ok','error','blocked') NOT NULL DEFAULT 'ok',
  `error_message` VARCHAR(1000) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_ai_usage_tenant_month` (`tenant_id`,`created_at`),
  INDEX `idx_ai_usage_provider_month` (`provider`,`created_at`),
  INDEX `idx_ai_usage_conversation` (`conversation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9) API / IDEMPOTENCIA
CREATE TABLE IF NOT EXISTS `api_request_logs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `api_key_id` BIGINT UNSIGNED NOT NULL,
  `endpoint` VARCHAR(190) NOT NULL,
  `idempotency_key` VARCHAR(190) NULL,
  `http_status` SMALLINT NOT NULL,
  `payload_hash` CHAR(64) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (`tenant_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `api_idempotency` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `idempotency_key` VARCHAR(190) NOT NULL,
  `response_json` JSON NOT NULL,
  `http_status` SMALLINT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_api_idem` (`tenant_id`,`idempotency_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10) PREFERENCIAS DE INTERFAZ SINCRONIZADAS POR USUARIO
-- La BD conserva las preferencias para que viajen entre equipos/navegadores.
CREATE TABLE IF NOT EXISTS `user_preferences` (
  `user_id` BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  `theme` ENUM('system','light','dark') NOT NULL DEFAULT 'system',
  `context_help` TINYINT(1) NOT NULL DEFAULT 1,
  `ui_preferences_json` JSON NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='user_preferences' AND COLUMN_NAME='ui_preferences_json');
SET @sql := IF(@exists=0,'ALTER TABLE `user_preferences` ADD COLUMN `ui_preferences_json` JSON NULL AFTER `context_help`','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 11) NIVO WEB CHAT - ETIQUETA RICH TEXT COMPACTA
-- Amplia el campo para soportar texto editable, negrita, cursiva y hasta 2 líneas.
SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='webchat_widgets' AND COLUMN_NAME='launcher_label');
SET @launcher_len := (SELECT COALESCE(MAX(CHARACTER_MAXIMUM_LENGTH),0) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='webchat_widgets' AND COLUMN_NAME='launcher_label');
SET @sql := IF(@exists=0,'ALTER TABLE `webchat_widgets` ADD COLUMN `launcher_label` VARCHAR(255) NULL AFTER `launcher_icon`',IF(@launcher_len<255,'ALTER TABLE `webchat_widgets` MODIFY COLUMN `launcher_label` VARCHAR(255) NULL','SELECT 1'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 11.1) CONECTORES DISPONIBLES ACTUALMENTE
-- Mientras Instagram y otros conectores sigan en desarrollo, no se permite vincularlos.
UPDATE `channel_connector_catalog` SET `connector_ready`=0,`linkable`=0 WHERE `code`='instagram';

-- 12) CATALOGO COMERCIAL OFICIAL ZYNKO
-- Los códigos son estables y permiten ejecutar este UPDATE más de una vez sin duplicar planes.
UPDATE `subscription_plans`
SET `code`='free'
WHERE `is_default_free`=1 AND (`code` IS NULL OR `code`='')
ORDER BY `id` LIMIT 1;

UPDATE `subscription_plans` SET `code`='starter' WHERE LOWER(`name`)='starter' AND (`code` IS NULL OR `code`='') ORDER BY `id` LIMIT 1;
UPDATE `subscription_plans` SET `code`='pro' WHERE LOWER(`name`)='pro' AND (`code` IS NULL OR `code`='') ORDER BY `id` LIMIT 1;
UPDATE `subscription_plans` SET `code`='business' WHERE LOWER(`name`)='business' AND (`code` IS NULL OR `code`='') ORDER BY `id` LIMIT 1;

INSERT INTO `subscription_plans` (`code`,`name`,`monthly_price`,`currency`,`max_users`,`max_channels`,`max_webchat_sites`,`max_daily_chats`,`max_monthly_chats`,`allowed_channels_json`,`module_access_json`,`features_json`,`external_ai_included`,`external_ai_monthly_tokens`,`external_ai_channels_json`,`is_default_free`,`is_featured`,`featured_label`,`active`)
VALUES
('free','Gratis',0,'USD',NULL,1,1,5,NULL,JSON_ARRAY('webchat'),JSON_OBJECT('dashboard',1,'inbox',1,'channels',1,'webchat',1,'billing',1,'onboarding',1,'users',1,'chatbot',0,'integrations',0,'email',0,'settings',0,'api',0),JSON_ARRAY('NIVO Web Chat incluido','1 sitio autorizado para NIVO Web Chat','5 chats nuevos por día','Mensajes ilimitados dentro de cada chat','Usuarios de ZYNKO ilimitados'),0,NULL,JSON_ARRAY(),1,0,NULL,1),
('starter','Starter',19,'USD',NULL,2,2,NULL,500,JSON_ARRAY('webchat','whatsapp','messenger'),JSON_OBJECT('dashboard',1,'inbox',1,'channels',1,'webchat',1,'billing',1,'onboarding',1,'users',1,'chatbot',0,'integrations',1,'email',1,'settings',1,'api',1),JSON_ARRAY('NIVO Web Chat incluido','2 sitios autorizados para NIVO Web Chat','1 conexión externa a elegir: WhatsApp o Messenger','500 chats nuevos por mes','API para conectar sitios y sistemas externos','Bandeja omnicanal y contactos','Usuarios de ZYNKO ilimitados'),0,NULL,JSON_ARRAY(),0,0,NULL,1),
('pro','Pro',49,'USD',NULL,4,5,NULL,3000,JSON_ARRAY('webchat','whatsapp','messenger'),JSON_OBJECT('dashboard',1,'inbox',1,'channels',1,'webchat',1,'billing',1,'onboarding',1,'users',1,'chatbot',1,'integrations',1,'email',1,'settings',1,'api',1),JSON_ARRAY('NIVO Web Chat incluido','5 sitios autorizados para NIVO Web Chat','Capacidad de hasta 3 conexiones externas según canales habilitados','3,000 chats nuevos por mes','API completa para integraciones externas','NIVO IA y automatizaciones','Asignación de conversaciones, reportes y auditoría','Usuarios de ZYNKO ilimitados'),1,NULL,JSON_ARRAY('webchat','whatsapp','messenger','api'),0,1,'Más popular',1),
('business','Business',99,'USD',NULL,11,10,NULL,10000,JSON_ARRAY('webchat','whatsapp','messenger'),JSON_OBJECT('dashboard',1,'inbox',1,'channels',1,'webchat',1,'billing',1,'onboarding',1,'users',1,'chatbot',1,'integrations',1,'email',1,'settings',1,'api',1),JSON_ARRAY('NIVO Web Chat incluido','10 sitios autorizados para NIVO Web Chat','Capacidad de hasta 10 conexiones externas según canales habilitados','10,000 chats nuevos por mes','API completa con mayor capacidad','NIVO IA y automatizaciones avanzadas','Reportes avanzados y auditoría completa','Soporte prioritario','Usuarios de ZYNKO ilimitados'),1,NULL,JSON_ARRAY('webchat','whatsapp','messenger','api'),0,0,NULL,1)
ON DUPLICATE KEY UPDATE
`name`=VALUES(`name`),`monthly_price`=VALUES(`monthly_price`),`currency`=VALUES(`currency`),`max_users`=VALUES(`max_users`),`max_channels`=VALUES(`max_channels`),`max_webchat_sites`=VALUES(`max_webchat_sites`),`max_daily_chats`=VALUES(`max_daily_chats`),`max_monthly_chats`=VALUES(`max_monthly_chats`),`allowed_channels_json`=VALUES(`allowed_channels_json`),`module_access_json`=VALUES(`module_access_json`),`features_json`=VALUES(`features_json`),`external_ai_included`=VALUES(`external_ai_included`),`external_ai_monthly_tokens`=VALUES(`external_ai_monthly_tokens`),`external_ai_channels_json`=VALUES(`external_ai_channels_json`),`is_default_free`=VALUES(`is_default_free`),`is_featured`=VALUES(`is_featured`),`featured_label`=VALUES(`featured_label`),`active`=VALUES(`active`);

-- Evita que exista más de un Plan Gratis predeterminado.
UPDATE `subscription_plans` SET `is_default_free`=0 WHERE `code`<>'free' AND `is_default_free`=1;
UPDATE `subscription_plans` SET `is_available`=1, `availability_label`='Disponible', `availability_message`=NULL WHERE `code`='free' OR `is_default_free`=1;


-- 12.5) NIVO WEB CHAT - CODIGO UNICO POR SITIO AUTORIZADO
-- Cada instalación recibe una clave propia. El mismo código no puede reutilizarse en otro dominio.
CREATE TABLE IF NOT EXISTS `webchat_installations` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `widget_id` BIGINT UNSIGNED NOT NULL,
  `installation_key` CHAR(40) NULL,
  `domain` VARCHAR(255) NOT NULL,
  `label` VARCHAR(120) NULL,
  `enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` BIGINT UNSIGNED NULL,
  `first_seen_at` DATETIME NULL,
  `last_seen_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_widget_domain` (`widget_id`,`domain`),
  INDEX (`tenant_id`,`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cada instalación recibe una clave propia. El mismo código no puede reutilizarse en otro dominio.
SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='webchat_installations' AND COLUMN_NAME='installation_key');
SET @sql := IF(@exists=0,'ALTER TABLE `webchat_installations` ADD COLUMN `installation_key` CHAR(40) NULL AFTER `widget_id`','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `webchat_installations`
SET `installation_key`=SHA1(CONCAT(UUID(),'-',`id`,'-',RAND()))
WHERE `installation_key` IS NULL OR `installation_key`='';

ALTER TABLE `webchat_installations` MODIFY COLUMN `installation_key` CHAR(40) NOT NULL;
SET @idx_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='webchat_installations' AND INDEX_NAME='uq_installation_key');
SET @sql := IF(@idx_exists=0,'ALTER TABLE `webchat_installations` ADD UNIQUE KEY `uq_installation_key` (`installation_key`)','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 13) VERSION ACTUAL
INSERT INTO `system_settings` (`setting_key`,`setting_value`)
VALUES ('app_version','2.31.45')
ON DUPLICATE KEY UPDATE `setting_value`=VALUES(`setting_value`);

-- ------------------------------------------------------------
-- 14) VERIFICACION FINAL - BASE ACTUALMENTE SELECCIONADA
-- ------------------------------------------------------------
SELECT 'ZYNKO_DB_UPDATE_OK' AS estado, DATABASE() AS base_datos, '2.31.45' AS version_objetivo;

SELECT
  TABLE_NAME,
  COLUMN_NAME,
  COLUMN_TYPE,
  IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA=@db_name
  AND (
    (TABLE_NAME='tenants' AND COLUMN_NAME IN ('business_id','contact_phone','registration_source')) OR
    (TABLE_NAME='bot_profiles' AND COLUMN_NAME='channel_policy_json') OR
    (TABLE_NAME='webchat_widgets' AND COLUMN_NAME IN ('experience_json','launcher_label')) OR
    (TABLE_NAME='webchat_installations' AND COLUMN_NAME='installation_key') OR
    (TABLE_NAME='subscription_plans' AND COLUMN_NAME IN ('max_monthly_chats','is_featured','featured_label','is_available','availability_label','availability_message','external_ai_included','external_ai_monthly_tokens','external_ai_channels_json')) OR
    (TABLE_NAME='conversations' AND COLUMN_NAME IN ('archived_at','deleted_at','deleted_by')) OR
    (TABLE_NAME='inbox_preferences' AND COLUMN_NAME IN ('category_id','state_filter','attention_filter')) OR
    (TABLE_NAME='user_preferences' AND COLUMN_NAME='ui_preferences_json')
  )
ORDER BY TABLE_NAME,COLUMN_NAME;

SELECT `setting_key`,`setting_value`
FROM `system_settings`
WHERE `setting_key`='app_version';

SELECT
  CASE
    WHEN EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='tenants' AND COLUMN_NAME='business_id')
     AND EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='tenants' AND COLUMN_NAME='contact_phone')
     AND EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='tenants' AND COLUMN_NAME='registration_source')
     AND EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='bot_profiles' AND COLUMN_NAME='channel_policy_json')
     AND EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='webchat_widgets' AND COLUMN_NAME='experience_json')
     AND EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='webchat_widgets' AND COLUMN_NAME='launcher_label' AND CHARACTER_MAXIMUM_LENGTH>=255)
     AND EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='webchat_installations' AND COLUMN_NAME='installation_key')
     AND EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='subscription_plans' AND COLUMN_NAME='external_ai_included')
     AND EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='user_preferences' AND COLUMN_NAME='ui_preferences_json')
    THEN 'OK - BASE ACTUALIZADA CORRECTAMENTE'
    ELSE 'REVISAR - FALTA ALGUN CAMBIO'
  END AS resultado_final;

-- FIN

-- =============================================================
-- ZYNKO V2.31.47 · FUENTES WEB DE CONOCIMIENTO PARA NIVO
-- =============================================================
CREATE TABLE IF NOT EXISTS nivo_knowledge_websites (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(180) NOT NULL,
  base_url VARCHAR(500) NOT NULL,
  crawl_scope ENUM('page','domain') NOT NULL DEFAULT 'domain',
  exclude_paths TEXT NULL,
  max_pages SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  refresh_hours SMALLINT UNSIGNED NOT NULL DEFAULT 24,
  auto_sync TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sync_status ENUM('never','syncing','ready','error') NOT NULL DEFAULT 'never',
  pages_count INT UNSIGNED NOT NULL DEFAULT 0,
  last_error VARCHAR(1000) NULL,
  last_synced_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_nivo_web_source(tenant_id,base_url),
  INDEX idx_nivo_web_due(tenant_id,active,auto_sync,last_synced_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================
-- ZYNKO V2.31.54 · SEGURIDAD PREMIUM NIVO IA / API / INTEGRACIONES
-- =============================================================
SET @db_name := DATABASE();

CREATE TABLE IF NOT EXISTS `api_client_policies` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `api_key_id` BIGINT UNSIGNED NOT NULL,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `allowed_origins_json` JSON NULL,
  `allowed_ips_json` JSON NULL,
  `rate_limit_per_minute` INT UNSIGNED NOT NULL DEFAULT 120,
  `require_https` TINYINT(1) NOT NULL DEFAULT 1,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_api_client_policy_key` (`api_key_id`),
  INDEX `idx_api_policy_tenant` (`tenant_id`,`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `api_rate_limits` (
  `api_key_id` BIGINT UNSIGNED NOT NULL,
  `window_start` DATETIME NOT NULL,
  `request_count` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`api_key_id`,`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='tenant_ai_settings' AND COLUMN_NAME='allowed_origins_json');
SET @sql := IF(@exists=0,'ALTER TABLE `tenant_ai_settings` ADD COLUMN `allowed_origins_json` JSON NULL AFTER `allowed_channels_json`','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='tenant_ai_settings' AND COLUMN_NAME='max_requests_per_minute');
SET @sql := IF(@exists=0,'ALTER TABLE `tenant_ai_settings` ADD COLUMN `max_requests_per_minute` INT UNSIGNED NOT NULL DEFAULT 60 AFTER `allowed_origins_json`','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='tenant_ai_settings' AND COLUMN_NAME='redact_sensitive');
SET @sql := IF(@exists=0,'ALTER TABLE `tenant_ai_settings` ADD COLUMN `redact_sensitive` TINYINT(1) NOT NULL DEFAULT 1 AFTER `max_requests_per_minute`','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='tenant_ai_settings' AND COLUMN_NAME='require_approved_knowledge');
SET @sql := IF(@exists=0,'ALTER TABLE `tenant_ai_settings` ADD COLUMN `require_approved_knowledge` TINYINT(1) NOT NULL DEFAULT 1 AFTER `redact_sensitive`','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='tenant_ai_settings' AND COLUMN_NAME='log_decisions');
SET @sql := IF(@exists=0,'ALTER TABLE `tenant_ai_settings` ADD COLUMN `log_decisions` TINYINT(1) NOT NULL DEFAULT 1 AFTER `require_approved_knowledge`','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO `system_settings` (`setting_key`,`setting_value`) VALUES ('app_version','2.31.54')
ON DUPLICATE KEY UPDATE `setting_value`=VALUES(`setting_value`);

SELECT 'ZYNKO_DB_UPDATE_OK' AS estado, DATABASE() AS base_datos, '2.31.54' AS version_objetivo;


-- =============================================================
-- ZYNKO V2.31.72 · RESPUESTAS RÁPIDAS PREMIUM
-- =============================================================
SET @db_name := DATABASE();

CREATE TABLE IF NOT EXISTS `quick_replies` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `shortcut` VARCHAR(80) NOT NULL,
  `title` VARCHAR(120) NOT NULL,
  `body` TEXT NOT NULL,
  `media_json` JSON NULL,
  `team_id` BIGINT UNSIGNED NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_qr` (`tenant_id`,`shortcut`),
  INDEX `idx_qr_tenant` (`tenant_id`,`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='quick_replies' AND COLUMN_NAME='media_json');
SET @sql := IF(@exists=0,'ALTER TABLE `quick_replies` ADD COLUMN `media_json` JSON NULL AFTER `body`','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='quick_replies' AND COLUMN_NAME='created_at');
SET @sql := IF(@exists=0,'ALTER TABLE `quick_replies` ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `active`, ADD COLUMN `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO `system_settings` (`setting_key`,`setting_value`) VALUES ('app_version','2.31.72')
ON DUPLICATE KEY UPDATE `setting_value`=VALUES(`setting_value`);

SELECT 'ZYNKO_DB_UPDATE_OK' AS estado, DATABASE() AS base_datos, '2.31.72' AS version_objetivo;

-- =============================================================
-- ZYNKO V2.31.79 · CIERRE DE CHAT Y ENCUESTAS DE SATISFACCIÓN
-- =============================================================
CREATE TABLE IF NOT EXISTS `conversation_surveys` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `conversation_id` BIGINT UNSIGNED NOT NULL,
  `visitor_id` BIGINT UNSIGNED NULL,
  `rating` TINYINT UNSIGNED NULL,
  `comment` VARCHAR(1000) NULL,
  `requested_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `responded_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_conversation_survey` (`tenant_id`,`conversation_id`),
  INDEX `idx_survey_tenant` (`tenant_id`,`responded_at`,`requested_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `system_settings` (`setting_key`,`setting_value`) VALUES ('app_version','2.31.79')
ON DUPLICATE KEY UPDATE `setting_value`=VALUES(`setting_value`);

SELECT 'ZYNKO_DB_UPDATE_OK' AS estado, DATABASE() AS base_datos, '2.31.79' AS version_objetivo;



-- =============================================================
-- ZYNKO V2.31.83 · UTF8MB4 / EMOJIS SEGUROS EN CHAT Y NIVO
-- =============================================================
-- Corrige instalaciones antiguas donde columnas de texto heredaron
-- latin1/utf8mb3 y provocaban SQLSTATE 1366 al guardar emojis.
SET @db_name := DATABASE();

SET @sql := CONCAT('ALTER DATABASE `', REPLACE(@db_name,'`','``'), '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='messages' AND COLUMN_NAME='body');
SET @sql := IF(@exists>0,'ALTER TABLE `messages` MODIFY `body` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='conversation_notes' AND COLUMN_NAME='body');
SET @sql := IF(@exists>0,'ALTER TABLE `conversation_notes` MODIFY `body` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='quick_replies' AND COLUMN_NAME='body');
SET @sql := IF(@exists>0,'ALTER TABLE `quick_replies` MODIFY `body` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='conversation_surveys' AND COLUMN_NAME='comment');
SET @sql := IF(@exists>0,'ALTER TABLE `conversation_surveys` MODIFY `comment` VARCHAR(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='contacts' AND COLUMN_NAME='name');
SET @sql := IF(@exists>0,'ALTER TABLE `contacts` MODIFY `name` VARCHAR(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='webchat_visitors' AND COLUMN_NAME='name');
SET @sql := IF(@exists>0,'ALTER TABLE `webchat_visitors` MODIFY `name` VARCHAR(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO `system_settings` (`setting_key`,`setting_value`) VALUES ('app_version','2.31.83')
ON DUPLICATE KEY UPDATE `setting_value`=VALUES(`setting_value`);

SELECT 'ZYNKO_DB_UPDATE_OK' AS estado, DATABASE() AS base_datos, '2.31.83' AS version_objetivo;
