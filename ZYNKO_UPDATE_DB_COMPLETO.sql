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

-- 12) PLAN GRATIS: IA EXTERNA DESACTIVADA POR DEFECTO
UPDATE `subscription_plans`
SET `external_ai_included`=0,
    `external_ai_monthly_tokens`=NULL,
    `external_ai_channels_json`=JSON_ARRAY()
WHERE `is_default_free`=1;

-- 13) VERSION ACTUAL
INSERT INTO `system_settings` (`setting_key`,`setting_value`)
VALUES ('app_version','2.31.32')
ON DUPLICATE KEY UPDATE `setting_value`=VALUES(`setting_value`);

-- ------------------------------------------------------------
-- 14) VERIFICACION FINAL - BASE ACTUALMENTE SELECCIONADA
-- ------------------------------------------------------------
SELECT 'ZYNKO_DB_UPDATE_OK' AS estado, DATABASE() AS base_datos, '2.31.32' AS version_objetivo;

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
    (TABLE_NAME='subscription_plans' AND COLUMN_NAME IN ('external_ai_included','external_ai_monthly_tokens','external_ai_channels_json')) OR
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
     AND EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='subscription_plans' AND COLUMN_NAME='external_ai_included')
     AND EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='user_preferences' AND COLUMN_NAME='ui_preferences_json')
    THEN 'OK - BASE ACTUALIZADA CORRECTAMENTE'
    ELSE 'REVISAR - FALTA ALGUN CAMBIO'
  END AS resultado_final;

-- FIN
