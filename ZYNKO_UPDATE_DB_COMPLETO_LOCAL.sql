-- ============================================================
-- ZYNKO - UPDATE LOCAL MYSQL 8.0.30 / PHPMYADMIN
-- Version objetivo: 2.31.99
-- Base esperada: zynko (o la base actualmente seleccionada)
--
-- IMPORTANTE:
-- 1) Este archivo NO usa PREPARE / EXECUTE.
-- 2) Este archivo NO usa ALTER TABLE ... ADD COLUMN IF NOT EXISTS,
--    porque MySQL 8.0.30 no admite esa sintaxis.
-- 3) Fue preparado contra el dump local zynko.sql enviado el 04-oct-2026.
-- ============================================================

SET @db_name := DATABASE();

SELECT
    DATABASE() AS base_seleccionada,
    VERSION() AS motor_version,
    '2.31.99' AS version_objetivo;

-- ------------------------------------------------------------
-- 1) CORREGIR NOMBRE DEL TENANT PRINCIPAL
-- En ambos dumps actuales el nombre venia como "ES MULTSIERVICIOS".
-- No se modifica el slug para no alterar URLs o referencias existentes.
-- ------------------------------------------------------------
UPDATE `tenants`
SET `name`='ES MULTISERVICIOS'
WHERE LOWER(REPLACE(TRIM(`name`),' ',''))='esmultsiervicios';

-- ------------------------------------------------------------
-- 2) APRENDIZAJE SUPERVISADO DE NIVO
-- Esta tabla existe en servidor pero faltaba en el dump local.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `nivo_learning_queue` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `conversation_id` BIGINT UNSIGNED NULL,
  `channel_type` VARCHAR(40) NULL,
  `question` VARCHAR(1200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `normalized_question` VARCHAR(1200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `suggested_answer` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `source_hint` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `occurrences` INT UNSIGNED NOT NULL DEFAULT 1,
  `status` ENUM('pending','review','approved','rejected') NOT NULL DEFAULT 'pending',
  `first_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_by` BIGINT UNSIGNED NULL,
  `reviewed_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_nivo_learning_tenant_status` (`tenant_id`,`status`,`last_seen_at`),
  KEY `idx_nivo_learning_conversation` (`tenant_id`,`conversation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3) PRESENCIA DE AGENTES PARA HANDOFF HUMANO
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `agent_presence` (
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `status` ENUM('online','busy','offline') NOT NULL DEFAULT 'online',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tenant_id`,`user_id`),
  KEY `idx_agent_presence_status` (`tenant_id`,`status`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Evita que una pregunta desconocida mande el chat a humano automaticamente.
UPDATE `bot_profiles`
SET `handoff_rules_json` = JSON_SET(
    COALESCE(`handoff_rules_json`, JSON_OBJECT()),
    '$.auto_handoff',
    0
)
WHERE `handoff_rules_json` IS NULL
   OR JSON_EXTRACT(`handoff_rules_json`, '$.auto_handoff') IS NULL
   OR JSON_EXTRACT(`handoff_rules_json`, '$.auto_handoff') = TRUE;

-- ------------------------------------------------------------
-- 4) FAMILIA ES MULTISERVICIOS
-- El conocimiento se comparte dentro del mismo tenant,
-- sin importar si la pregunta llega desde ZYNKO, IZZY o el sitio corporativo.
-- ------------------------------------------------------------

INSERT INTO `nivo_solutions` (`tenant_id`,`name`,`code`,`description`,`active`)
SELECT t.id,'ZYNKO','zynko',
       'ZYNKO es la plataforma omnicanal de ES MULTISERVICIOS para centralizar conversaciones, NIVO Web Chat, NIVO IA, usuarios, asignaciones e integraciones.',
       1
FROM `tenants` t
WHERE (
    LOWER(REPLACE(TRIM(t.name),' ','')) IN ('esmultiservicios','esmultsiervicios')
    OR LOWER(t.slug) LIKE 'es-multiservicios%'
    OR LOWER(t.slug) LIKE 'es-multsiervicios%'
)
  AND NOT EXISTS (
      SELECT 1 FROM `nivo_solutions` s
      WHERE s.tenant_id=t.id AND s.code='zynko'
  );

INSERT INTO `nivo_rules` (`tenant_id`,`name`,`keywords`,`response`,`priority`,`active`)
SELECT t.id,
       'ES MULTISERVICIOS · Identidad',
       'que es es multiservicios,qué es es multiservicios,quien es es multiservicios,quién es es multiservicios,que hace es multiservicios,qué hace es es multiservicios',
       'ES MULTISERVICIOS desarrolla software, sitios web, integraciones y soluciones digitales para empresas. Es la empresa creadora de IZZY, CAMI y ZYNKO.',
       15,1
FROM `tenants` t
WHERE (
    LOWER(REPLACE(TRIM(t.name),' ','')) IN ('esmultiservicios','esmultsiervicios')
    OR LOWER(t.slug) LIKE 'es-multiservicios%'
    OR LOWER(t.slug) LIKE 'es-multsiervicios%'
)
  AND NOT EXISTS (
      SELECT 1 FROM `nivo_rules` r
      WHERE r.tenant_id=t.id AND r.name='ES MULTISERVICIOS · Identidad'
  );

INSERT INTO `nivo_rules` (`tenant_id`,`name`,`keywords`,`response`,`priority`,`active`)
SELECT t.id,
       'ES MULTISERVICIOS · Familia de soluciones',
       'productos de es multiservicios,soluciones de es multiservicios,sistemas de es multiservicios,que sistemas tiene es multiservicios,qué sistemas tiene es multiservicios,que soluciones tiene es multiservicios,qué soluciones tiene es multiservicios',
       'ES MULTISERVICIOS reúne una familia de soluciones que incluye IZZY, CAMI y ZYNKO. NIVO puede orientarte sobre cualquiera de ellas usando las reglas, fuentes web y conocimiento aprobado de esta empresa.',
       16,1
FROM `tenants` t
WHERE (
    LOWER(REPLACE(TRIM(t.name),' ','')) IN ('esmultiservicios','esmultsiervicios')
    OR LOWER(t.slug) LIKE 'es-multiservicios%'
    OR LOWER(t.slug) LIKE 'es-multsiervicios%'
)
  AND NOT EXISTS (
      SELECT 1 FROM `nivo_rules` r
      WHERE r.tenant_id=t.id AND r.name='ES MULTISERVICIOS · Familia de soluciones'
  );

INSERT INTO `nivo_rules` (`tenant_id`,`name`,`keywords`,`response`,`priority`,`active`)
SELECT t.id,
       'IZZY · Identidad',
       'que es izzy,qué es izzy,para que sirve izzy,para qué sirve izzy,que hace izzy,qué hace izzy,funciones de izzy,funcionalidades de izzy',
       'IZZY es la solución empresarial de ES MULTISERVICIOS para facturación, inventario, POS, restaurantes y gestión administrativa. NIVO puede ampliar la respuesta con el contenido sincronizado de las fuentes web del mismo tenant.',
       20,1
FROM `tenants` t
WHERE (
    LOWER(REPLACE(TRIM(t.name),' ','')) IN ('esmultiservicios','esmultsiervicios')
    OR LOWER(t.slug) LIKE 'es-multiservicios%'
    OR LOWER(t.slug) LIKE 'es-multsiervicios%'
)
  AND NOT EXISTS (
      SELECT 1 FROM `nivo_rules` r
      WHERE r.tenant_id=t.id AND r.name='IZZY · Identidad'
  );

INSERT INTO `nivo_rules` (`tenant_id`,`name`,`keywords`,`response`,`priority`,`active`)
SELECT t.id,
       'ZYNKO · Identidad',
       'que es zynko,qué es zynko,para que sirve zynko,para qué sirve zynko,que hace zynko,qué hace zynko,funciones de zynko,funcionalidades de zynko',
       'ZYNKO es la plataforma omnicanal de ES MULTISERVICIOS. Centraliza conversaciones y trabaja con NIVO Web Chat, NIVO IA, usuarios, asignaciones e integraciones.',
       21,1
FROM `tenants` t
WHERE (
    LOWER(REPLACE(TRIM(t.name),' ','')) IN ('esmultiservicios','esmultsiervicios')
    OR LOWER(t.slug) LIKE 'es-multiservicios%'
    OR LOWER(t.slug) LIKE 'es-multsiervicios%'
)
  AND NOT EXISTS (
      SELECT 1 FROM `nivo_rules` r
      WHERE r.tenant_id=t.id AND r.name='ZYNKO · Identidad'
  );

INSERT INTO `nivo_rules` (`tenant_id`,`name`,`keywords`,`response`,`priority`,`active`)
SELECT t.id,
       'NIVO Web Chat · Función',
       'que es nivo web chat,qué es nivo web chat,para que sirve nivo web chat,para qué sirve nivo web chat,webchat de nivo,chat de nivo',
       'NIVO Web Chat es el canal web de ZYNKO. Recibe mensajes de visitantes, los registra en la Bandeja y trabaja junto con NIVO IA para responder en tiempo real o transferir la conversación a una persona cuando corresponde.',
       22,1
FROM `tenants` t
WHERE (
    LOWER(REPLACE(TRIM(t.name),' ','')) IN ('esmultiservicios','esmultsiervicios')
    OR LOWER(t.slug) LIKE 'es-multiservicios%'
    OR LOWER(t.slug) LIKE 'es-multsiervicios%'
)
  AND NOT EXISTS (
      SELECT 1 FROM `nivo_rules` r
      WHERE r.tenant_id=t.id AND r.name='NIVO Web Chat · Función'
  );

INSERT INTO `nivo_rules` (`tenant_id`,`name`,`keywords`,`response`,`priority`,`active`)
SELECT t.id,
       'NIVO IA · Función',
       'que es nivo ia,qué es nivo ia,para que sirve nivo ia,para qué sirve nivo ia,como aprende nivo,cómo aprende nivo,como responde nivo,cómo responde nivo',
       'NIVO IA es el asistente de ZYNKO. Usa reglas de respuesta, fuentes web sincronizadas, conocimiento aprobado y aprendizaje supervisado del mismo tenant. Si no encuentra una respuesta segura, registra la pregunta para revisión en lugar de inventar información.',
       23,1
FROM `tenants` t
WHERE (
    LOWER(REPLACE(TRIM(t.name),' ','')) IN ('esmultiservicios','esmultsiervicios')
    OR LOWER(t.slug) LIKE 'es-multiservicios%'
    OR LOWER(t.slug) LIKE 'es-multsiervicios%'
)
  AND NOT EXISTS (
      SELECT 1 FROM `nivo_rules` r
      WHERE r.tenant_id=t.id AND r.name='NIVO IA · Función'
  );

INSERT INTO `nivo_rules` (`tenant_id`,`name`,`keywords`,`response`,`priority`,`active`)
SELECT t.id,
       'CAMI · Identidad',
       'que es cami,qué es cami,para que sirve cami,para qué sirve cami,que hace cami,qué hace cami',
       'CAMI es una solución de ES MULTISERVICIOS orientada a clínicas y centros médicos. El detalle funcional se irá ampliando con las fuentes y el conocimiento aprobado que se agregue específicamente para CAMI.',
       24,1
FROM `tenants` t
WHERE (
    LOWER(REPLACE(TRIM(t.name),' ','')) IN ('esmultiservicios','esmultsiervicios')
    OR LOWER(t.slug) LIKE 'es-multiservicios%'
    OR LOWER(t.slug) LIKE 'es-multsiervicios%'
)
  AND NOT EXISTS (
      SELECT 1 FROM `nivo_rules` r
      WHERE r.tenant_id=t.id AND r.name='CAMI · Identidad'
  );

INSERT INTO `nivo_rules` (`tenant_id`,`name`,`keywords`,`response`,`priority`,`active`)
SELECT t.id,
       'Conocimiento compartido por tenant',
       'puedes hablar de izzy desde zynko,puedes hablar de zynko desde izzy,puedes hablar de es multiservicios desde izzy,puedes hablar de es multiservicios desde zynko,conoces todas las soluciones',
       'Sí. Dentro del tenant de ES MULTISERVICIOS, NIVO usa el conocimiento aprobado de toda la familia sin importar desde cuál sitio autorizado se haga la pregunta. Esa información no se comparte con tenants de clientes.',
       25,1
FROM `tenants` t
WHERE (
    LOWER(REPLACE(TRIM(t.name),' ','')) IN ('esmultiservicios','esmultsiervicios')
    OR LOWER(t.slug) LIKE 'es-multiservicios%'
    OR LOWER(t.slug) LIKE 'es-multsiervicios%'
)
  AND NOT EXISTS (
      SELECT 1 FROM `nivo_rules` r
      WHERE r.tenant_id=t.id AND r.name='Conocimiento compartido por tenant'
  );

-- ------------------------------------------------------------
-- 5) VERSION
-- ------------------------------------------------------------
INSERT INTO `system_settings` (`setting_key`,`setting_value`)
VALUES ('app_version','2.31.99')
ON DUPLICATE KEY UPDATE `setting_value`=VALUES(`setting_value`);

-- ------------------------------------------------------------
-- 6) VERIFICACION
-- ------------------------------------------------------------
SELECT
    'ZYNKO_DB_UPDATE_OK' AS estado,
    DATABASE() AS base_datos,
    VERSION() AS motor_version,
    '2.31.99' AS version_objetivo;

SELECT
    t.id,
    t.name,
    t.slug
FROM `tenants` t
WHERE (
    LOWER(REPLACE(TRIM(t.name),' ','')) IN ('esmultiservicios','esmultsiervicios')
    OR LOWER(t.slug) LIKE 'es-multiservicios%'
    OR LOWER(t.slug) LIKE 'es-multsiervicios%'
);

SELECT
    (SELECT COUNT(*) FROM `nivo_learning_queue`) AS aprendizaje_registros,
    (SELECT COUNT(*) FROM `nivo_solutions` s JOIN `tenants` t ON t.id=s.tenant_id WHERE (
    LOWER(REPLACE(TRIM(t.name),' ','')) IN ('esmultiservicios','esmultsiervicios')
    OR LOWER(t.slug) LIKE 'es-multiservicios%'
    OR LOWER(t.slug) LIKE 'es-multsiervicios%'
)) AS soluciones_tenant_principal,
    (SELECT COUNT(*) FROM `nivo_rules` r JOIN `tenants` t ON t.id=r.tenant_id WHERE (
    LOWER(REPLACE(TRIM(t.name),' ','')) IN ('esmultiservicios','esmultsiervicios')
    OR LOWER(t.slug) LIKE 'es-multiservicios%'
    OR LOWER(t.slug) LIKE 'es-multsiervicios%'
)) AS reglas_tenant_principal;


-- ============================================================
-- ZYNKO V2.31.101 · Automatizaciones, campañas y social automation
-- Seguro para ejecutar más de una vez.
-- ============================================================
CREATE TABLE IF NOT EXISTS `automation_flows` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `uuid` CHAR(36) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `trigger_type` VARCHAR(60) NOT NULL DEFAULT 'message_received',
  `channel_type` VARCHAR(50) NOT NULL DEFAULT 'all',
  `status` ENUM('draft','active','paused','archived') NOT NULL DEFAULT 'draft',
  `definition_json` JSON NOT NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_automation_uuid` (`uuid`), KEY `idx_automation_tenant` (`tenant_id`,`status`,`trigger_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `social_automation_rules` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `channel_type` VARCHAR(30) NOT NULL,
  `rule_type` VARCHAR(40) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `keywords` VARCHAR(1000) NULL,
  `response_text` TEXT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `settings_json` JSON NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `idx_social_auto_tenant` (`tenant_id`,`channel_type`,`rule_type`,`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `outbound_campaigns` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `uuid` CHAR(36) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `channel_type` VARCHAR(30) NOT NULL DEFAULT 'whatsapp',
  `audience_type` VARCHAR(40) NOT NULL DEFAULT 'all_contacts',
  `template_name` VARCHAR(160) NULL,
  `message_body` TEXT NOT NULL,
  `status` ENUM('draft','scheduled','running','paused','completed','cancelled') NOT NULL DEFAULT 'draft',
  `scheduled_at` DATETIME NULL,
  `total_recipients` INT NOT NULL DEFAULT 0,
  `queued_count` INT NOT NULL DEFAULT 0,
  `sent_count` INT NOT NULL DEFAULT 0,
  `delivered_count` INT NOT NULL DEFAULT 0,
  `failed_count` INT NOT NULL DEFAULT 0,
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_campaign_uuid` (`uuid`), KEY `idx_campaign_tenant` (`tenant_id`,`status`,`scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `campaign_recipients` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `campaign_id` BIGINT UNSIGNED NOT NULL,
  `contact_id` BIGINT UNSIGNED NOT NULL,
  `destination` VARCHAR(190) NULL,
  `status` ENUM('pending','queued','sent','delivered','failed','skipped') NOT NULL DEFAULT 'pending',
  `provider_message_id` VARCHAR(190) NULL,
  `error_message` VARCHAR(500) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_campaign_contact` (`campaign_id`,`contact_id`), KEY `idx_campaign_recipient` (`tenant_id`,`campaign_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `whatsapp_ai_call_profiles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `channel_id` BIGINT UNSIGNED NULL,
  `enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `provider` VARCHAR(80) NOT NULL DEFAULT 'meta',
  `voice_name` VARCHAR(120) NULL,
  `system_prompt` TEXT NULL,
  `handoff_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `settings_json` JSON NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `idx_ai_calls_tenant` (`tenant_id`,`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE `subscription_plans`
SET `module_access_json`=JSON_SET(COALESCE(`module_access_json`,JSON_OBJECT()),'$.automations',IF(`code` IN ('pro','business'),1,0))
WHERE `code` IN ('free','starter','pro','business');

INSERT INTO `system_settings` (`setting_key`,`setting_value`) VALUES ('app_version','2.31.101')
ON DUPLICATE KEY UPDATE `setting_value`=VALUES(`setting_value`);


-- ============================================================
-- ZYNKO V2.31.103 · Conectores reales, WhatsApp QR y llamadas IA
-- Seguro para ejecutar más de una vez.
-- ============================================================
UPDATE `channel_connector_catalog` SET `connector_ready`=1,`visible`=1,`linkable`=1 WHERE `code` IN ('instagram','telegram');

CREATE TABLE IF NOT EXISTS `whatsapp_ai_calls` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `profile_id` BIGINT UNSIGNED NOT NULL,
  `channel_id` BIGINT UNSIGNED NOT NULL,
  `external_call_id` VARCHAR(190) NULL,
  `caller` VARCHAR(190) NULL,
  `status` ENUM('ringing','answered','completed','failed','rejected') NOT NULL DEFAULT 'ringing',
  `event_json` JSON NULL,
  `started_at` DATETIME NULL,
  `answered_at` DATETIME NULL,
  `ended_at` DATETIME NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ai_call_external` (`tenant_id`,`external_call_id`),
  KEY `idx_ai_call_status` (`tenant_id`,`status`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `system_settings` (`setting_key`,`setting_value`) VALUES ('app_version','2.31.103')
ON DUPLICATE KEY UPDATE `setting_value`=VALUES(`setting_value`);

SELECT 'ZYNKO_DB_UPDATE_OK' AS estado, DATABASE() AS base_datos, '2.31.103' AS version_objetivo;

-- ZYNKO V2.31.103 · Mensajería Web Chat resiliente y respuesta no silenciosa
-- No agrega tablas: conserva el esquema V2.31.102 y actualiza únicamente la versión objetivo.


-- ZYNKO V2.31.110 · reconciliación bilateral durable (sin cambios estructurales)
INSERT INTO `system_settings` (`setting_key`,`setting_value`) VALUES ('app_version','2.31.110')
ON DUPLICATE KEY UPDATE `setting_value`=VALUES(`setting_value`);



-- ZYNKO V2.31.110 · categorías por conversación (CRM)
CREATE TABLE IF NOT EXISTS `conversation_category_map` (
  `conversation_id` BIGINT UNSIGNED NOT NULL,
  `category_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`conversation_id`,`category_id`),
  KEY `idx_conversation_category` (`category_id`,`conversation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Migra categorías existentes del contacto a sus conversaciones para conservar el historial CRM.
INSERT IGNORE INTO `conversation_category_map` (`conversation_id`,`category_id`)
SELECT c.id, ccm.category_id FROM conversations c JOIN contact_category_map ccm ON ccm.contact_id=c.contact_id WHERE c.deleted_at IS NULL;
INSERT INTO `system_settings` (`setting_key`,`setting_value`) VALUES ('app_version','2.31.110')
ON DUPLICATE KEY UPDATE `setting_value`=VALUES(`setting_value`);

SELECT 'ZYNKO_DB_UPDATE_OK' AS estado, DATABASE() AS base_datos, '2.31.110' AS version_objetivo;


-- ZYNKO V2.31.114 · Dashboard operativo administrable y centro de servicios
SET @db_name := DATABASE();
SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='dashboard_preferences' AND COLUMN_NAME='quick_actions_json');
SET @sql := IF(@exists=0,'ALTER TABLE `dashboard_preferences` ADD `quick_actions_json` TEXT NULL AFTER `widgets_json`','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
INSERT INTO `system_settings` (`setting_key`,`setting_value`) VALUES ('app_version','2.31.114')
ON DUPLICATE KEY UPDATE `setting_value`=VALUES(`setting_value`);
SELECT 'ZYNKO_DB_UPDATE_OK' AS estado, DATABASE() AS base_datos, '2.31.114' AS version_objetivo;

-- ZYNKO V2.31.114 · Sin cambios estructurales de BD; sincronización operativa WebSocket/UI.

-- ============================================================
-- ZYNKO V2.31.115 · Monitoreo operativo, alertas y autorrecuperación
-- Seguro para ejecutar más de una vez.
-- ============================================================
CREATE TABLE IF NOT EXISTS `service_monitor_state` (
  `service_key` VARCHAR(120) NOT NULL,
  `service_name` VARCHAR(190) NOT NULL,
  `current_status` ENUM('up','degraded','down','inactive') NOT NULL DEFAULT 'inactive',
  `detail` VARCHAR(1000) NULL,
  `last_checked_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_changed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_notified_at` DATETIME NULL,
  PRIMARY KEY (`service_key`),
  KEY `idx_service_monitor_status` (`current_status`,`last_checked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `service_monitor_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `service_key` VARCHAR(120) NOT NULL,
  `service_name` VARCHAR(190) NOT NULL,
  `old_status` ENUM('up','degraded','down','inactive') NOT NULL,
  `new_status` ENUM('up','degraded','down','inactive') NOT NULL,
  `detail` VARCHAR(1000) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_service_monitor_events` (`service_key`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `system_settings` (`setting_key`,`setting_value`) VALUES
('monitor_enabled','1'),
('monitor_email',''),
('monitor_auto_recover_ws','1'),
('monitor_notify_recovery','1')
ON DUPLICATE KEY UPDATE `setting_value`=`setting_value`;

INSERT INTO `system_settings` (`setting_key`,`setting_value`) VALUES ('app_version','2.31.116')
ON DUPLICATE KEY UPDATE `setting_value`=VALUES(`setting_value`);

SELECT 'ZYNKO_DB_UPDATE_OK' AS estado, DATABASE() AS base_datos, '2.31.116' AS version_objetivo;


-- ============================================================
-- ZYNKO V2.31.117 · Logs centralizados y correo entrante IMAP/Graph
-- Seguro para ejecutar más de una vez.
-- ============================================================
CREATE TABLE IF NOT EXISTS `system_event_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `level` ENUM('info','warning','error') NOT NULL DEFAULT 'info',
  `module` VARCHAR(120) NOT NULL DEFAULT 'system',
  `message` VARCHAR(500) NOT NULL,
  `context_json` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_system_logs_tenant` (`tenant_id`,`created_at`),
  KEY `idx_system_logs_level` (`tenant_id`,`level`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @db_name := DATABASE();
SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='correo' AND COLUMN_NAME='inbound_method');
SET @sql := IF(@exists=0,"ALTER TABLE `correo` ADD `inbound_method` ENUM('NONE','IMAP','GRAPH') NOT NULL DEFAULT 'NONE' AFTER `save_to_sent_items`",'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='correo' AND COLUMN_NAME='imap_host');
SET @sql := IF(@exists=0,"ALTER TABLE `correo` ADD `imap_host` VARCHAR(190) NULL AFTER `inbound_method`, ADD `imap_port` INT UNSIGNED NOT NULL DEFAULT 993 AFTER `imap_host`, ADD `imap_secure` ENUM('ssl','tls','none') NOT NULL DEFAULT 'ssl' AFTER `imap_port`, ADD `imap_username` VARCHAR(190) NULL AFTER `imap_secure`, ADD `imap_password` TEXT NULL AFTER `imap_username`, ADD `imap_folder` VARCHAR(120) NOT NULL DEFAULT 'INBOX' AFTER `imap_password`, ADD `inbound_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `imap_folder`, ADD `inbound_last_test_at` DATETIME NULL AFTER `inbound_enabled`, ADD `inbound_last_test_status` ENUM('ok','error') NULL AFTER `inbound_last_test_at`, ADD `inbound_last_test_message` VARCHAR(500) NULL AFTER `inbound_last_test_status`",'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
UPDATE `channel_connector_catalog` SET `connector_ready`=1 WHERE `code`='email';
INSERT INTO `system_settings` (`setting_key`,`setting_value`) VALUES ('app_version','2.31.117') ON DUPLICATE KEY UPDATE `setting_value`=VALUES(`setting_value`);
SELECT 'ZYNKO_DB_UPDATE_OK' AS estado, DATABASE() AS base_datos, '2.31.117' AS version_objetivo;
