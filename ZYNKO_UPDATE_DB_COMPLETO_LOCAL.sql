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
