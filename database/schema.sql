CREATE DATABASE IF NOT EXISTS zynko CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE zynko;

CREATE TABLE tenants (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, uuid CHAR(36) NOT NULL UNIQUE, name VARCHAR(160) NOT NULL, business_id VARCHAR(80) NULL, contact_phone VARCHAR(50) NULL, registration_source VARCHAR(30) NULL, slug VARCHAR(120) NOT NULL UNIQUE, status ENUM('trial','active','past_due','suspended','closed') NOT NULL DEFAULT 'trial', plan_id BIGINT UNSIGNED NULL, logo_path VARCHAR(255), logo_dark_path VARCHAR(255), favicon_path VARCHAR(255), primary_color VARCHAR(20) DEFAULT '#0F766E', secondary_color VARCHAR(20) DEFAULT '#0F172A', timezone VARCHAR(64) NOT NULL DEFAULT 'America/Tegucigalpa', locale ENUM('es','en') NOT NULL DEFAULT 'es', bot_name VARCHAR(100) NOT NULL DEFAULT 'NIVO', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP);
CREATE TABLE users (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, uuid CHAR(36) NOT NULL UNIQUE, name VARCHAR(160) NOT NULL, email VARCHAR(190) NOT NULL UNIQUE, email_verified_at DATETIME NULL, avatar_path VARCHAR(500) NULL, password_hash VARCHAR(255) NOT NULL, locale ENUM('es','en') NOT NULL DEFAULT 'es', status ENUM('invited','active','disabled') NOT NULL DEFAULT 'active', mfa_enabled TINYINT(1) NOT NULL DEFAULT 0, last_login_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP);
CREATE TABLE teams (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, uuid CHAR(36) NOT NULL UNIQUE, name VARCHAR(100) NOT NULL, active TINYINT(1) NOT NULL DEFAULT 1, INDEX(tenant_id));
CREATE TABLE tenant_users (tenant_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, role_code VARCHAR(40) NOT NULL DEFAULT 'agent', team_id BIGINT UNSIGNED NULL, is_owner TINYINT(1) NOT NULL DEFAULT 0, PRIMARY KEY(tenant_id,user_id), INDEX(team_id));
CREATE TABLE roles (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NULL, code VARCHAR(40) NOT NULL, name VARCHAR(80) NOT NULL, permissions_json JSON NOT NULL, UNIQUE KEY uq_role(tenant_id,code));
CREATE TABLE plans (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code VARCHAR(50) NOT NULL UNIQUE, name VARCHAR(100) NOT NULL, monthly_price DECIMAL(12,2) NOT NULL DEFAULT 0, currency CHAR(3) NOT NULL DEFAULT 'HNL', max_users INT NULL, max_channels INT NULL, features_json JSON NULL, active TINYINT(1) NOT NULL DEFAULT 1);
CREATE TABLE subscriptions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, plan_id BIGINT UNSIGNED NOT NULL, status ENUM('trial','active','past_due','suspended','cancelled') NOT NULL DEFAULT 'trial', starts_at DATETIME NOT NULL, renews_at DATETIME NULL, grace_until DATETIME NULL, monthly_amount DECIMAL(12,2) NOT NULL, currency CHAR(3) NOT NULL DEFAULT 'HNL', notes VARCHAR(500), INDEX(tenant_id,status));

CREATE TABLE channel_connector_catalog (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(50) NOT NULL UNIQUE,
 name VARCHAR(120) NOT NULL,
 icon_class VARCHAR(120) NOT NULL,
 icon_style VARCHAR(50) NOT NULL,
 description VARCHAR(500) NULL,
 connector_ready TINYINT(1) NOT NULL DEFAULT 0,
 visible TINYINT(1) NOT NULL DEFAULT 1,
 linkable TINYINT(1) NOT NULL DEFAULT 0,
 sort_order INT NOT NULL DEFAULT 100,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
INSERT INTO channel_connector_catalog(code,name,icon_class,icon_style,description,connector_ready,visible,linkable,sort_order) VALUES
('whatsapp','WhatsApp Business','fa-brands fa-whatsapp','whatsapp','Mensajes, multimedia, documentos y atención en tiempo real.',1,1,1,10),
('messenger','Messenger','fa-brands fa-facebook-messenger','messenger','Conversaciones de páginas de Facebook conectadas a la empresa.',1,1,1,20),
('instagram','Instagram Messaging','fa-brands fa-instagram','instagram','Mensajes de Instagram mediante la autorización oficial de Meta.',0,1,0,30),
('webchat','NIVO Web Chat','fa-solid fa-message','webchat','Chat inteligente propio de ZYNKO para instalar en sitios y portales.',1,1,1,40),
('telegram','Telegram','fa-brands fa-telegram','telegram','Mensajería mediante bots y API oficial de Telegram.',0,1,0,50),
('email','Correo','fa-solid fa-envelope','email','Centraliza conversaciones recibidas por correo electrónico.',0,1,0,60);

CREATE TABLE tenant_channel_entitlements (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, channel_type VARCHAR(50) NOT NULL, enabled TINYINT(1) NOT NULL DEFAULT 0, monthly_amount DECIMAL(12,2) NOT NULL DEFAULT 0, UNIQUE KEY uq_entitlement(tenant_id,channel_type));
CREATE TABLE channels (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, uuid CHAR(36) NOT NULL UNIQUE, type VARCHAR(50) NOT NULL, name VARCHAR(120) NOT NULL, external_account_id VARCHAR(190), external_phone_id VARCHAR(190), display_address VARCHAR(190), token_ciphertext TEXT, token_expires_at DATETIME NULL, status ENUM('pending','connected','warning','disconnected') NOT NULL DEFAULT 'pending', settings_json JSON NULL, last_event_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(tenant_id,type,status));
CREATE TABLE contacts (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, uuid CHAR(36) NOT NULL UNIQUE, name VARCHAR(160), phone VARCHAR(40), email VARCHAR(190), avatar_url VARCHAR(500), custom_fields JSON NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX(tenant_id,phone), INDEX(tenant_id,email));
CREATE TABLE contact_identities (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, contact_id BIGINT UNSIGNED NOT NULL, channel_id BIGINT UNSIGNED NOT NULL, external_id VARCHAR(190) NOT NULL, profile_json JSON NULL, UNIQUE KEY uq_identity(channel_id,external_id));
CREATE TABLE contact_external_refs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, contact_id BIGINT UNSIGNED NOT NULL, external_system VARCHAR(80) NOT NULL, external_id VARCHAR(190) NOT NULL, UNIQUE KEY uq_ext(tenant_id,external_system,external_id));
CREATE TABLE conversations (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, uuid CHAR(36) NOT NULL UNIQUE, channel_id BIGINT UNSIGNED NOT NULL, contact_id BIGINT UNSIGNED NOT NULL, assigned_user_id BIGINT UNSIGNED NULL, team_id BIGINT UNSIGNED NULL, status ENUM('open','pending','resolved','closed') NOT NULL DEFAULT 'open', priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal', unread_count INT NOT NULL DEFAULT 0, last_message_at DATETIME NULL, archived_at DATETIME NULL, deleted_at DATETIME NULL, deleted_by BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(tenant_id,status,last_message_at), INDEX(tenant_id,archived_at), INDEX(tenant_id,deleted_at));
CREATE TABLE messages (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, conversation_id BIGINT UNSIGNED NOT NULL, uuid CHAR(36) NOT NULL UNIQUE, external_message_id VARCHAR(190) NULL, direction ENUM('in','out') NOT NULL, sender_type ENUM('contact','user','bot','system') NOT NULL, sender_user_id BIGINT UNSIGNED NULL, type VARCHAR(40) NOT NULL DEFAULT 'text', body TEXT NULL, media_json JSON NULL, status VARCHAR(40) NULL, sent_at DATETIME NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_external(tenant_id,external_message_id), INDEX(conversation_id,sent_at));
CREATE TABLE media_library (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, uuid CHAR(36) NOT NULL UNIQUE, original_name VARCHAR(255) NOT NULL, stored_path VARCHAR(500) NOT NULL, mime_type VARCHAR(120) NOT NULL, size_bytes BIGINT UNSIGNED NOT NULL, purpose ENUM('branding','chat','template','attachment') NOT NULL DEFAULT 'attachment', uploaded_by BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(tenant_id,purpose));
CREATE TABLE conversation_notes (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, conversation_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, body TEXT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE tags (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, name VARCHAR(80) NOT NULL, color VARCHAR(20), UNIQUE KEY uq_tag(tenant_id,name));
CREATE TABLE conversation_tags (conversation_id BIGINT UNSIGNED NOT NULL, tag_id BIGINT UNSIGNED NOT NULL, PRIMARY KEY(conversation_id,tag_id));
CREATE TABLE quick_replies (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, shortcut VARCHAR(80) NOT NULL, title VARCHAR(120) NOT NULL, body TEXT NOT NULL, team_id BIGINT UNSIGNED NULL, active TINYINT(1) DEFAULT 1, UNIQUE KEY uq_qr(tenant_id,shortcut));
CREATE TABLE bot_profiles (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, name VARCHAR(100) NOT NULL DEFAULT 'NIVO', enabled TINYINT(1) NOT NULL DEFAULT 0, mode ENUM('rules','ai','hybrid') NOT NULL DEFAULT 'hybrid', provider VARCHAR(60) NULL, model VARCHAR(100) NULL, system_prompt TEXT NULL, fallback_message TEXT NULL, handoff_rules_json JSON NULL, business_hours_json JSON NULL, channel_policy_json JSON NULL, knowledge_enabled TINYINT(1) NOT NULL DEFAULT 0, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uq_bot_tenant(tenant_id));
CREATE TABLE bot_flows (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, uuid CHAR(36) NOT NULL UNIQUE, name VARCHAR(140) NOT NULL, version INT NOT NULL DEFAULT 1, status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft', definition_json JSON NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP);
CREATE TABLE knowledge_sources (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, solution_id BIGINT UNSIGNED NULL, module_id BIGINT UNSIGNED NULL, name VARCHAR(180) NOT NULL, source_type ENUM('text','url','file','faq','integration') NOT NULL, source_ref VARCHAR(500), content LONGTEXT NULL, status ENUM('pending','ready','error') NOT NULL DEFAULT 'pending', approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved', updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX(tenant_id,status));
CREATE TABLE api_keys (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, uuid CHAR(36) NOT NULL UNIQUE, name VARCHAR(120) NOT NULL, key_prefix VARCHAR(20) NOT NULL, key_hash VARCHAR(255) NOT NULL, scopes_json JSON NOT NULL, expires_at DATETIME NULL, revoked_at DATETIME NULL, last_used_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(tenant_id));
CREATE TABLE outgoing_webhooks (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, uuid CHAR(36) NOT NULL UNIQUE, name VARCHAR(120) NOT NULL, url VARCHAR(500) NOT NULL, secret_ciphertext TEXT NOT NULL, events_json JSON NOT NULL, active TINYINT(1) DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE webhook_events (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NULL, provider VARCHAR(40) NOT NULL, event_key VARCHAR(190) NOT NULL, payload_json JSON NOT NULL, signature_valid TINYINT(1) NOT NULL DEFAULT 0, status ENUM('received','processing','processed','failed') NOT NULL DEFAULT 'received', attempts INT NOT NULL DEFAULT 0, received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, processed_at DATETIME NULL, UNIQUE KEY uq_event(provider,event_key), INDEX(status,received_at));
CREATE TABLE branding_settings (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL UNIQUE, app_title VARCHAR(120), logo_path VARCHAR(255), logo_dark_path VARCHAR(255), favicon_path VARCHAR(255), login_image_path VARCHAR(255), primary_color VARCHAR(20), secondary_color VARCHAR(20), surface_radius SMALLINT NOT NULL DEFAULT 14, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP);
CREATE TABLE audit_logs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NULL, user_id BIGINT UNSIGNED NULL, action VARCHAR(120) NOT NULL, entity_type VARCHAR(80), entity_id VARCHAR(190), ip_address VARCHAR(64), user_agent VARCHAR(500), metadata_json JSON NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(tenant_id,created_at));
CREATE TABLE user_sessions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, token_hash VARCHAR(255) NOT NULL UNIQUE, remember_me TINYINT(1) NOT NULL DEFAULT 0, ip_address VARCHAR(64), user_agent VARCHAR(500), expires_at DATETIME NOT NULL, revoked_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP);

INSERT IGNORE INTO plans(code,name,monthly_price,currency,max_users,max_channels,features_json) VALUES
('starter','Starter',0,'HNL',3,1,JSON_OBJECT('inbox',true,'bot','rules')),
('business','Business',0,'HNL',15,3,JSON_OBJECT('inbox',true,'bot','hybrid','api',true));

-- ZYNKO transactional email and notification center
CREATE TABLE correo_tipo (
  correo_tipo_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(60) NOT NULL UNIQUE,
  nombre VARCHAR(120) NOT NULL,
  descripcion VARCHAR(255) NULL,
  destinatario ENUM('internal','user','customer','owner','custom') NOT NULL DEFAULT 'internal',
  activo TINYINT(1) NOT NULL DEFAULT 1,
  orden SMALLINT UNSIGNED NOT NULL DEFAULT 0
);

CREATE TABLE correo (
  correo_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  correo_tipo_id INT UNSIGNED NOT NULL,
  nombre VARCHAR(120) NOT NULL DEFAULT 'Principal',
  metodo_envio ENUM('SMTP','GRAPH') NOT NULL DEFAULT 'SMTP',
  server VARCHAR(190) NULL,
  correo VARCHAR(190) NULL,
  destinatario VARCHAR(190) NULL,
  copia VARCHAR(1000) NULL,
  password TEXT NULL,
  port INT UNSIGNED NOT NULL DEFAULT 587,
  smtp_secure ENUM('tls','ssl') NOT NULL DEFAULT 'tls',
  tenant_graph_id VARCHAR(190) NULL,
  client_id VARCHAR(190) NULL,
  client_secret TEXT NULL,
  graph_user VARCHAR(190) NULL,
  save_to_sent_items TINYINT(1) NOT NULL DEFAULT 1,
  estado TINYINT(1) NOT NULL DEFAULT 1,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  last_test_at DATETIME NULL,
  last_test_status ENUM('ok','error') NULL,
  last_test_message VARCHAR(500) NULL,
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_correo_tenant_tipo (tenant_id,correo_tipo_id,estado)
);

CREATE TABLE notification_preferences (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  correo_tipo_id INT UNSIGNED NOT NULL,
  email_enabled TINYINT(1) NOT NULL DEFAULT 1,
  in_app_enabled TINYINT(1) NOT NULL DEFAULT 1,
  recipient_override VARCHAR(190) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_notification_pref (tenant_id,correo_tipo_id)
);

CREATE TABLE notification_runtime_settings (
  tenant_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  login_enabled TINYINT(1) NOT NULL DEFAULT 1,
  message_enabled TINYINT(1) NOT NULL DEFAULT 1,
  handoff_enabled TINYINT(1) NOT NULL DEFAULT 1,
  critical_enabled TINYINT(1) NOT NULL DEFAULT 1,
  cooldown_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE notification_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NULL,
  correo_tipo_id INT UNSIGNED NULL,
  channel ENUM('email','in_app') NOT NULL,
  recipient VARCHAR(190) NULL,
  subject VARCHAR(255) NULL,
  status ENUM('queued','sent','failed','skipped') NOT NULL DEFAULT 'queued',
  provider ENUM('SMTP','GRAPH','SYSTEM') NOT NULL DEFAULT 'SYSTEM',
  error_message VARCHAR(1000) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at DATETIME NULL,
  INDEX idx_notification_log (tenant_id,status,created_at)
);

CREATE TABLE user_navigation_preferences (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  sidebar_mode ENUM('expanded','collapsed','hidden') NOT NULL DEFAULT 'expanded',
  pinned_menu_json JSON NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_nav (tenant_id,user_id)
);

INSERT IGNORE INTO correo_tipo(codigo,nombre,descripcion,destinatario,orden) VALUES
('system_alerts','Alertas del sistema','Errores críticos, salud del sistema e integraciones.','owner',10),
('security','Seguridad y accesos','Inicios de sesión, cambios de contraseña y eventos de seguridad.','user',20),
('company_lifecycle','Empresas y suscripciones','Alta de empresas, cambios de plan, pagos, vencimientos y bloqueos.','owner',30),
('channel_events','Canales e integraciones','Conexión, desconexión y fallos de WhatsApp, Messenger y futuros canales.','owner',40),
('user_management','Usuarios y equipo','Invitaciones, altas, bajas y cambios de acceso.','user',50),
('conversation_alerts','Conversaciones','Asignaciones, escalaciones y eventos que requieren atención.','user',60),
('nivo_ai','NIVO e IA','Alertas del asistente, handoff, conocimiento y automatizaciones.','owner',70),
('billing','Facturación y cobros','Recibos, recordatorios, vencimientos y suspensión por pago.','owner',80),
('reports','Reportes programados','Resúmenes y reportes enviados por correo.','custom',90),
('email_tests','Pruebas de correo','Mensajes de prueba para validar SMTP o Microsoft Graph.','custom',100);

-- Realtime/WebSocket event bus. The WebSocket daemon consumes pending events and
-- broadcasts them only to clients belonging to the same tenant.
CREATE TABLE realtime_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(80) NOT NULL,
  entity_type VARCHAR(80) NULL,
  entity_id VARCHAR(190) NULL,
  payload_json JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_realtime_tenant_id (tenant_id,id),
  INDEX idx_realtime_created (created_at)
);

-- Commercial plan enforcement and external API hardening
CREATE TABLE IF NOT EXISTS subscription_plans (
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
);
CREATE TABLE IF NOT EXISTS tenant_subscriptions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
 plan_id BIGINT UNSIGNED NOT NULL, status VARCHAR(30) NOT NULL DEFAULT 'active',
 starts_at DATETIME NULL, ends_at DATETIME NULL, UNIQUE KEY uq_tenant_subscription(tenant_id)
);

CREATE TABLE IF NOT EXISTS plan_upgrade_requests (
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
);

CREATE TABLE IF NOT EXISTS registration_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 company_name VARCHAR(160) NOT NULL,
 business_id VARCHAR(80) NULL,
 owner_name VARCHAR(160) NOT NULL,
 phone VARCHAR(50) NULL,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 verification_code_hash VARCHAR(255) NOT NULL,
 code_expires_at DATETIME NOT NULL,
 attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 send_count SMALLINT UNSIGNED NOT NULL DEFAULT 1,
 window_started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 last_sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 status ENUM('pending','verified','blocked') NOT NULL DEFAULT 'pending',
 ip_address VARCHAR(64) NULL,
 user_agent VARCHAR(500) NULL,
 terms_version INT UNSIGNED NULL,
 terms_accepted_at DATETIME NULL,
 verified_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX(status,code_expires_at), INDEX(ip_address,last_sent_at)
);

INSERT IGNORE INTO subscription_plans(code,name,monthly_price,currency,max_users,max_channels,max_webchat_sites,max_daily_chats,max_monthly_chats,allowed_channels_json,module_access_json,features_json,external_ai_included,external_ai_monthly_tokens,external_ai_channels_json,is_default_free,is_featured,featured_label,active) VALUES
('free','Gratis',0,'USD',NULL,1,1,5,NULL,JSON_ARRAY('webchat'),JSON_OBJECT('dashboard',1,'inbox',1,'channels',1,'webchat',1,'billing',1,'onboarding',1,'users',1,'chatbot',0,'integrations',0,'email',0,'settings',0,'api',0),JSON_ARRAY('NIVO Web Chat incluido','1 sitio autorizado para NIVO Web Chat','5 chats nuevos por día','Mensajes ilimitados dentro de cada chat','Usuarios de ZYNKO ilimitados'),0,NULL,JSON_ARRAY(),1,0,NULL,1),
('starter','Starter',19,'USD',NULL,2,2,NULL,500,JSON_ARRAY('webchat','whatsapp','messenger'),JSON_OBJECT('dashboard',1,'inbox',1,'channels',1,'webchat',1,'billing',1,'onboarding',1,'users',1,'chatbot',0,'integrations',1,'email',1,'settings',1,'api',1),JSON_ARRAY('NIVO Web Chat incluido','2 sitios autorizados para NIVO Web Chat','1 conexión externa a elegir: WhatsApp o Messenger','500 chats nuevos por mes','API para conectar sitios y sistemas externos','Bandeja omnicanal y contactos','Usuarios de ZYNKO ilimitados'),0,NULL,JSON_ARRAY(),0,0,NULL,1),
('pro','Pro',49,'USD',NULL,4,5,NULL,3000,JSON_ARRAY('webchat','whatsapp','messenger'),JSON_OBJECT('dashboard',1,'inbox',1,'channels',1,'webchat',1,'billing',1,'onboarding',1,'users',1,'chatbot',1,'integrations',1,'email',1,'settings',1,'api',1),JSON_ARRAY('NIVO Web Chat incluido','5 sitios autorizados para NIVO Web Chat','Capacidad de hasta 3 conexiones externas según canales habilitados','3,000 chats nuevos por mes','API completa para integraciones externas','NIVO IA y automatizaciones','Asignación de conversaciones, reportes y auditoría','Usuarios de ZYNKO ilimitados'),1,NULL,JSON_ARRAY('webchat','whatsapp','messenger','api'),0,1,'Más popular',1),
('business','Business',99,'USD',NULL,11,10,NULL,10000,JSON_ARRAY('webchat','whatsapp','messenger'),JSON_OBJECT('dashboard',1,'inbox',1,'channels',1,'webchat',1,'billing',1,'onboarding',1,'users',1,'chatbot',1,'integrations',1,'email',1,'settings',1,'api',1),JSON_ARRAY('NIVO Web Chat incluido','10 sitios autorizados para NIVO Web Chat','Capacidad de hasta 10 conexiones externas según canales habilitados','10,000 chats nuevos por mes','API completa con mayor capacidad','NIVO IA y automatizaciones avanzadas','Reportes avanzados y auditoría completa','Soporte prioritario','Usuarios de ZYNKO ilimitados'),1,NULL,JSON_ARRAY('webchat','whatsapp','messenger','api'),0,0,NULL,1);

CREATE TABLE IF NOT EXISTS ai_provider_settings(
 id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
 provider VARCHAR(30) NOT NULL DEFAULT 'openai', enabled TINYINT(1) NOT NULL DEFAULT 0,
 api_key_ciphertext TEXT NULL, admin_key_ciphertext TEXT NULL,
 model VARCHAR(120) NOT NULL DEFAULT 'gpt-6-luna', fallback_only TINYINT(1) NOT NULL DEFAULT 1,
 monthly_budget_usd DECIMAL(12,4) NULL,
 input_cost_per_million DECIMAL(12,6) NOT NULL DEFAULT 0.050000,
 cached_input_cost_per_million DECIMAL(12,6) NOT NULL DEFAULT 0.005000,
 output_cost_per_million DECIMAL(12,6) NOT NULL DEFAULT 0.250000,
 max_output_tokens INT UNSIGNED NOT NULL DEFAULT 700,
 remote_month_cost_usd DECIMAL(12,4) NULL, remote_cost_refreshed_at DATETIME NULL,
 last_test_at DATETIME NULL, last_test_status VARCHAR(20) NULL, last_test_message VARCHAR(500) NULL,
 updated_by BIGINT UNSIGNED NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
INSERT IGNORE INTO ai_provider_settings(id,provider,enabled,model,fallback_only) VALUES(1,'openai',0,'gpt-6-luna',1);
CREATE TABLE IF NOT EXISTS tenant_ai_settings(
 tenant_id BIGINT UNSIGNED NOT NULL PRIMARY KEY, enabled TINYINT(1) NOT NULL DEFAULT 0,
 allowed_channels_json JSON NULL, updated_by BIGINT UNSIGNED NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS ai_usage_logs(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
 conversation_id BIGINT UNSIGNED NULL, provider VARCHAR(30) NOT NULL DEFAULT 'openai',
 channel_type VARCHAR(50) NOT NULL, model VARCHAR(120) NOT NULL, request_id VARCHAR(190) NULL,
 input_tokens BIGINT UNSIGNED NOT NULL DEFAULT 0, cached_input_tokens BIGINT UNSIGNED NOT NULL DEFAULT 0,
 output_tokens BIGINT UNSIGNED NOT NULL DEFAULT 0, estimated_cost_usd DECIMAL(14,8) NOT NULL DEFAULT 0,
 status ENUM('ok','error','blocked') NOT NULL DEFAULT 'ok', error_message VARCHAR(1000) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_ai_usage_tenant_month(tenant_id,created_at), INDEX idx_ai_usage_provider_month(provider,created_at), INDEX idx_ai_usage_conversation(conversation_id)
);

CREATE TABLE IF NOT EXISTS api_request_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
 api_key_id BIGINT UNSIGNED NOT NULL, endpoint VARCHAR(190) NOT NULL,
 idempotency_key VARCHAR(190) NULL, http_status SMALLINT NOT NULL, payload_hash CHAR(64) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(tenant_id,created_at)
);
CREATE TABLE IF NOT EXISTS api_idempotency (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
 idempotency_key VARCHAR(190) NOT NULL, response_json JSON NOT NULL, http_status SMALLINT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_api_idem(tenant_id,idempotency_key)
);

CREATE TABLE nivo_rules (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,name VARCHAR(160) NOT NULL,keywords VARCHAR(500) NOT NULL,response TEXT NOT NULL,priority INT NOT NULL DEFAULT 100,active TINYINT(1) NOT NULL DEFAULT 1,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX idx_nivo_rules_tenant(tenant_id,active,priority)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CRM ligero de contactos y seguimiento
CREATE TABLE IF NOT EXISTS contact_categories (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, name VARCHAR(80) NOT NULL, color VARCHAR(20) NULL, active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_contact_category(tenant_id,name));
CREATE TABLE IF NOT EXISTS contact_category_map (contact_id BIGINT UNSIGNED NOT NULL, category_id BIGINT UNSIGNED NOT NULL, PRIMARY KEY(contact_id,category_id));
CREATE TABLE IF NOT EXISTS conversation_followups (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, conversation_id BIGINT UNSIGNED NOT NULL, follow_up_at DATETIME NOT NULL, status ENUM('pending','done','cancelled') NOT NULL DEFAULT 'pending', note VARCHAR(500) NULL, created_by BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(tenant_id,status,follow_up_at));
CREATE TABLE IF NOT EXISTS contact_activity (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, contact_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NULL, action VARCHAR(80) NOT NULL, detail VARCHAR(500) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(tenant_id,contact_id,created_at));

-- Preferencias personales de interfaz sincronizadas por usuario (tema, ayuda y estado visual entre dispositivos). Runtime también la crea/actualiza para instalaciones existentes.
CREATE TABLE IF NOT EXISTS user_preferences (
  user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  theme ENUM('system','light','dark') NOT NULL DEFAULT 'system',
  context_help TINYINT(1) NOT NULL DEFAULT 1,
  ui_preferences_json JSON NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


-- Preferencias premium de la bandeja omnicanal
CREATE TABLE IF NOT EXISTS inbox_preferences (
  user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  channel_type VARCHAR(40) NOT NULL DEFAULT 'all',
  assignment_filter VARCHAR(30) NOT NULL DEFAULT 'all',
  priority_filter VARCHAR(30) NOT NULL DEFAULT 'all',
  category_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  state_filter VARCHAR(30) NOT NULL DEFAULT 'active',
  attention_filter VARCHAR(30) NOT NULL DEFAULT 'all',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS conversation_audit_logs(
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  conversation_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  action VARCHAR(40) NOT NULL,
  details_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_conv_audit(tenant_id,conversation_id,created_at)
);


-- NIVO Web Chat
CREATE TABLE IF NOT EXISTS webchat_widgets(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,channel_id BIGINT UNSIGNED NULL,name VARCHAR(120) NOT NULL DEFAULT 'NIVO Web Chat',public_key CHAR(40) NOT NULL UNIQUE,enabled TINYINT(1) NOT NULL DEFAULT 1,position VARCHAR(30) NOT NULL DEFAULT 'bottom-right',display_mode ENUM('launcher','open') NOT NULL DEFAULT 'launcher',offset_x INT NOT NULL DEFAULT 24,offset_y INT NOT NULL DEFAULT 24,accent_color VARCHAR(20) NOT NULL DEFAULT '#0F766E',launcher_icon VARCHAR(30) NOT NULL DEFAULT 'nivo',launcher_label VARCHAR(255) NULL,sound_enabled TINYINT(1) NOT NULL DEFAULT 1,privacy_enabled TINYINT(1) NOT NULL DEFAULT 0,privacy_text VARCHAR(240) NULL,privacy_url VARCHAR(500) NULL,welcome_title VARCHAR(160) NOT NULL DEFAULT '¡Hola! Soy NIVO',assistant_subtitle VARCHAR(190) NULL,welcome_message VARCHAR(500) NOT NULL DEFAULT '¿En qué puedo ayudarte hoy?',ask_name TINYINT(1) NOT NULL DEFAULT 1,ask_email TINYINT(1) NOT NULL DEFAULT 0,profile_required TINYINT(1) NOT NULL DEFAULT 0,allow_multiple_domains TINYINT(1) NOT NULL DEFAULT 1,experience_json JSON NULL,created_by BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX(tenant_id,enabled));
CREATE TABLE IF NOT EXISTS webchat_installations(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,widget_id BIGINT UNSIGNED NOT NULL,installation_key CHAR(40) NOT NULL,domain VARCHAR(255) NOT NULL,label VARCHAR(120) NULL,enabled TINYINT(1) NOT NULL DEFAULT 1,created_by BIGINT UNSIGNED NULL,first_seen_at DATETIME NULL,last_seen_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_installation_key(installation_key),UNIQUE KEY uq_widget_domain(widget_id,domain),INDEX(tenant_id,enabled));
CREATE TABLE IF NOT EXISTS webchat_visitors(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,widget_id BIGINT UNSIGNED NOT NULL,visitor_token CHAR(64) NOT NULL UNIQUE,contact_id BIGINT UNSIGNED NULL,conversation_id BIGINT UNSIGNED NULL,name VARCHAR(160) NULL,email VARCHAR(190) NULL,origin_domain VARCHAR(255) NULL,last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX(tenant_id,widget_id),INDEX(conversation_id));

-- ZYNKO V2.17 · NIVO Knowledge Hub
CREATE TABLE IF NOT EXISTS nivo_solutions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(120) NOT NULL, code VARCHAR(80) NOT NULL, description VARCHAR(700) NULL,
 active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_nivo_solution(tenant_id,code), INDEX(tenant_id,active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS nivo_solution_modules (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
 solution_id BIGINT UNSIGNED NOT NULL, name VARCHAR(120) NOT NULL, description VARCHAR(500) NULL,
 active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_nivo_module(solution_id,name), INDEX(tenant_id,solution_id,active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS nivo_contact_solutions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
 contact_id BIGINT UNSIGNED NOT NULL, solution_id BIGINT UNSIGNED NOT NULL,
 external_customer_code VARCHAR(120) NULL, external_base_url VARCHAR(500) NULL,
 active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_nivo_contact_solution(contact_id,solution_id), INDEX(tenant_id,contact_id,active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS dashboard_preferences (
 user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 widgets_json TEXT NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE system_settings (setting_key VARCHAR(80) PRIMARY KEY, setting_value VARCHAR(255) NOT NULL, updated_by BIGINT UNSIGNED NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP);
INSERT IGNORE INTO system_settings(setting_key,setting_value) VALUES('app_version','2.31.41');
INSERT IGNORE INTO system_settings(setting_key,setting_value) VALUES
('seo_site_name','ZYNKO'),
('seo_description','Plataforma SaaS omnicanal para centralizar conversaciones, Web Chat, automatización y atención humana.'),
('seo_site_url',''),
('seo_locale','es_HN'),
('seo_twitter',''),
('seo_google_verification',''),
('seo_bing_verification',''),
('public_parent_name','ES MULTISERVICIOS'),
('public_parent_url','https://esmultiservicios.com/'),
('public_social_facebook_url','https://www.facebook.com/esmultiserv'),
('public_social_facebook_enabled','1'),
('public_social_facebook_order','1'),
('public_social_instagram_url',''),
('public_social_instagram_enabled','0'),
('public_social_instagram_order','2'),
('public_social_tiktok_url','https://www.tiktok.com/@evelasquez91'),
('public_social_tiktok_enabled','1'),
('public_social_tiktok_order','3'),
('public_social_youtube_url',''),
('public_social_youtube_enabled','0'),
('public_social_youtube_order','4'),
('public_social_linkedin_url',''),
('public_social_linkedin_enabled','0'),
('public_social_linkedin_order','5'),
('public_social_float_enabled','1'),
('public_social_float_side','right'),
('public_social_float_vertical','center'),
('public_social_footer_enabled','1'),
('public_turnstile_enabled','0'),
('public_turnstile_site_key',''),
('public_turnstile_secret',''),
('public_turnstile_hostname','');


-- V2.27.3 · Términos y Condiciones administrables
CREATE TABLE IF NOT EXISTS legal_documents(
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  document_key VARCHAR(80) NOT NULL UNIQUE,
  title VARCHAR(190) NOT NULL,
  content LONGTEXT NOT NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  updated_by BIGINT UNSIGNED NULL,
  published_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO legal_documents(document_key,title,content,version,updated_by,published_at) VALUES
('terms_conditions','Términos y Condiciones','1. Objeto y alcance
Estos Términos y Condiciones regulan el acceso, registro y uso de ZYNKO, una plataforma SaaS omnicanal para la gestión de conversaciones, canales, contactos, usuarios y herramientas asociadas. Al registrar una empresa o utilizar el servicio, la persona usuaria acepta cumplir estas condiciones y las políticas vigentes que resulten aplicables.

2. Registro, cuenta y verificación
La persona que registra una empresa declara que la información proporcionada es verdadera, actual y suficiente, y que cuenta con autorización para actuar en nombre de la empresa cuando corresponda. El correo electrónico debe verificarse mediante el código enviado por ZYNKO antes de completar la activación. Cada cuenta es personal y las credenciales no deben compartirse con personas no autorizadas.

3. Administración de la empresa y usuarios autorizados
La empresa es responsable de administrar sus usuarios, roles, permisos y accesos internos. Las acciones realizadas por usuarios autorizados se considerarán efectuadas dentro del ámbito de la cuenta empresarial, salvo evidencia de acceso no autorizado reportado oportunamente. La empresa debe retirar accesos cuando una persona deje de estar autorizada.

4. Uso aceptable del servicio
ZYNKO debe utilizarse de forma lícita, responsable y conforme a las reglas de los canales conectados. No se permite fraude, suplantación, spam, campañas no autorizadas, abuso, acoso, distribución de contenido ilícito, intento de acceso a cuentas ajenas, extracción masiva no autorizada, malware, ingeniería inversa abusiva ni actividades destinadas a degradar, interrumpir o evadir la seguridad o los límites del servicio.

5. Mensajería, consentimiento y canales de terceros
La empresa es responsable de contar con las autorizaciones, bases legales y consentimientos necesarios para contactar a sus clientes y procesar sus datos. WhatsApp, Meta, correo electrónico, sitios web y otros canales o integraciones pueden estar sujetos a términos, políticas, revisiones, límites y disponibilidad de terceros. ZYNKO no controla cambios, suspensiones o restricciones impuestas directamente por dichos proveedores.

6. Plan Gratis y límites de uso
El Plan Gratis incluye únicamente las capacidades, canales y límites mostrados en el registro o dentro de ZYNKO. Los límites pueden incluir cantidad de sitios, usuarios, conversaciones nuevas, almacenamiento, integraciones u otras capacidades. Cuando se alcance un límite, determinadas funciones pueden quedar restringidas hasta el siguiente período aplicable o hasta realizar un cambio de plan. Las condiciones visibles en el sistema prevalecen para determinar las capacidades vigentes del plan.

7. Planes de pago, facturación y renovaciones
Cuando la empresa contrate un plan de pago, se aplicarán el precio, moneda, ciclo, fecha de renovación, impuestos y condiciones de cobro mostrados al momento de la contratación o acordados comercialmente. La continuidad de funciones de pago puede depender de que la suscripción se encuentre activa y al día. Cualquier condición comercial especial acordada por escrito se aplicará únicamente a la cuenta correspondiente.

8. Datos, propiedad y responsabilidad de la empresa
La empresa conserva la responsabilidad sobre la información que incorpora, envía, recibe o administra mediante ZYNKO. Debe garantizar que cuenta con los derechos y autorizaciones necesarios sobre contactos, mensajes, archivos y demás contenido tratado en la plataforma. La empresa es responsable de la exactitud de sus datos y de las decisiones tomadas a partir de ellos.

9. Privacidad y seguridad
ZYNKO aplica medidas técnicas y organizativas razonables para proteger la confidencialidad, integridad y disponibilidad de la información, incluyendo separación lógica entre empresas y controles de acceso. Ningún sistema puede garantizar seguridad absoluta. La empresa debe proteger sus credenciales, utilizar contraseñas seguras y comunicar de inmediato cualquier sospecha de acceso no autorizado o incidente relacionado con su cuenta.

10. Integraciones, API y servicios externos
Las funciones conectadas con servicios externos, APIs, webhooks o proveedores de mensajería dependen de la disponibilidad y condiciones de dichos servicios. La empresa es responsable de custodiar claves, tokens y credenciales de integración que administre. ZYNKO puede limitar o revocar una integración que genere riesgo de seguridad, abuso o incumplimiento.

11. Disponibilidad, mantenimiento y evolución del servicio
ZYNKO puede realizar mantenimiento, correcciones, actualizaciones, mejoras técnicas, cambios de interfaz o ajustes necesarios para seguridad, rendimiento y continuidad. Cuando sea razonablemente posible, los mantenimientos que puedan afectar significativamente la disponibilidad serán gestionados buscando reducir el impacto operativo. Las funciones pueden evolucionar, ser sustituidas o reorganizadas entre versiones.

12. Suspensión, restricciones y cierre de cuenta
ZYNKO puede limitar, suspender o cerrar el acceso cuando exista incumplimiento grave de estos términos, uso abusivo, fraude, riesgo de seguridad, falta de pago en servicios contratados, requerimiento legal o afectación a terceros o a la infraestructura. Cuando corresponda y sea razonablemente posible, se comunicará la situación a la empresa para que pueda corregirla.

13. Conservación, exportación y eliminación de información
La disponibilidad de exportaciones, respaldos, retención y eliminación de información dependerá de las funciones del plan, la configuración de la cuenta y las obligaciones legales aplicables. Antes de cerrar definitivamente una cuenta, la empresa debe obtener las copias o exportaciones que necesite cuando la función esté disponible.

14. Propiedad intelectual
ZYNKO, su interfaz, software, componentes, documentación, marcas y elementos propios están protegidos por los derechos correspondientes. Estos términos no transfieren propiedad intelectual sobre la plataforma. La empresa conserva los derechos que le correspondan sobre su propio contenido y datos.

15. Limitación razonable de responsabilidad
ZYNKO busca ofrecer un servicio estable y seguro, pero pueden existir interrupciones, errores, eventos externos o fallas de proveedores de terceros. En la medida permitida por la normativa aplicable, ZYNKO no será responsable por pérdidas indirectas derivadas de usos indebidos, credenciales comprometidas por la empresa, fallas de servicios externos o decisiones tomadas exclusivamente con base en información ingresada por usuarios. Esta cláusula no excluye responsabilidades que legalmente no puedan limitarse.

16. Cambios en estos Términos y Condiciones
Estos términos pueden actualizarse para reflejar cambios legales, operativos, comerciales, de seguridad o funcionalidad. Cada publicación genera una nueva versión con su fecha correspondiente. Durante el registro se conserva la versión aceptada y la fecha de aceptación. Cuando un cambio requiera una nueva aceptación de usuarios existentes, ZYNKO podrá solicitarla dentro de la plataforma.

17. Ley aplicable y disposiciones obligatorias
La relación se interpretará conforme a la legislación aplicable a la entidad que presta el servicio y a las normas imperativas que correspondan al cliente. Si alguna disposición resulta inválida o inaplicable, las demás continuarán vigentes en la medida permitida por la ley.

18. Contacto y soporte
Las consultas relacionadas con estos Términos y Condiciones, seguridad, privacidad o administración de la cuenta deben realizarse mediante los canales oficiales de soporte informados dentro de ZYNKO o por el proveedor del servicio.',1,NULL,NOW());

-- V2.29.4 · Formulario público de contacto
CREATE TABLE IF NOT EXISTS public_contact_inquiries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  company VARCHAR(160) NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(50) NULL,
  subject_code VARCHAR(60) NOT NULL,
  subject_label VARCHAR(160) NOT NULL,
  source_code VARCHAR(60) NOT NULL,
  source_label VARCHAR(190) NOT NULL,
  message TEXT NOT NULL,
  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(500) NULL,
  admin_mail_status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  confirmation_mail_status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_public_contact_created(created_at),
  INDEX idx_public_contact_email(email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- V2.29.7 · Analítica de visitas del sitio público
CREATE TABLE IF NOT EXISTS public_site_visits (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  visitor_id CHAR(40) NOT NULL,
  ip_hash CHAR(64) NULL,
  referrer_host VARCHAR(190) NULL,
  device_type VARCHAR(20) NOT NULL DEFAULT 'desktop',
  browser VARCHAR(40) NULL,
  user_agent VARCHAR(500) NULL,
  visited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_public_visit_date(visited_at),
  INDEX idx_public_visit_visitor(visitor_id,visited_at),
  INDEX idx_public_visit_device(device_type,visited_at),
  INDEX idx_public_visit_referrer(referrer_host,visited_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO system_settings(setting_key,setting_value) VALUES
('public_whatsapp_enabled','1'),
('public_whatsapp_number','+504 8913-6844'),
('public_whatsapp_message','Hola, quiero información sobre ZYNKO.');
