CREATE DATABASE IF NOT EXISTS zynko CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE zynko;

CREATE TABLE tenants (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, uuid CHAR(36) NOT NULL UNIQUE, name VARCHAR(160) NOT NULL, slug VARCHAR(120) NOT NULL UNIQUE, status ENUM('trial','active','past_due','suspended','closed') NOT NULL DEFAULT 'trial', plan_id BIGINT UNSIGNED NULL, logo_path VARCHAR(255), logo_dark_path VARCHAR(255), favicon_path VARCHAR(255), primary_color VARCHAR(20) DEFAULT '#0F766E', secondary_color VARCHAR(20) DEFAULT '#0F172A', timezone VARCHAR(64) NOT NULL DEFAULT 'America/Tegucigalpa', locale ENUM('es','en') NOT NULL DEFAULT 'es', bot_name VARCHAR(100) NOT NULL DEFAULT 'NIVO', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP);
CREATE TABLE users (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, uuid CHAR(36) NOT NULL UNIQUE, name VARCHAR(160) NOT NULL, email VARCHAR(190) NOT NULL UNIQUE, avatar_path VARCHAR(500) NULL, password_hash VARCHAR(255) NOT NULL, locale ENUM('es','en') NOT NULL DEFAULT 'es', status ENUM('invited','active','disabled') NOT NULL DEFAULT 'active', mfa_enabled TINYINT(1) NOT NULL DEFAULT 0, last_login_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP);
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
('instagram','Instagram Messaging','fa-brands fa-instagram','instagram','Mensajes de Instagram mediante la autorización oficial de Meta.',1,1,1,30),
('webchat','NIVO Web Chat','fa-solid fa-message','webchat','Chat inteligente propio de ZYNKO para instalar en sitios y portales.',1,1,1,40),
('telegram','Telegram','fa-brands fa-telegram','telegram','Mensajería mediante bots y API oficial de Telegram.',0,1,0,50),
('email','Correo','fa-solid fa-envelope','email','Centraliza conversaciones recibidas por correo electrónico.',0,1,0,60);

CREATE TABLE tenant_channel_entitlements (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, channel_type VARCHAR(50) NOT NULL, enabled TINYINT(1) NOT NULL DEFAULT 0, monthly_amount DECIMAL(12,2) NOT NULL DEFAULT 0, UNIQUE KEY uq_entitlement(tenant_id,channel_type));
CREATE TABLE channels (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, uuid CHAR(36) NOT NULL UNIQUE, type VARCHAR(50) NOT NULL, name VARCHAR(120) NOT NULL, external_account_id VARCHAR(190), external_phone_id VARCHAR(190), display_address VARCHAR(190), token_ciphertext TEXT, token_expires_at DATETIME NULL, status ENUM('pending','connected','warning','disconnected') NOT NULL DEFAULT 'pending', settings_json JSON NULL, last_event_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(tenant_id,type,status));
CREATE TABLE contacts (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, uuid CHAR(36) NOT NULL UNIQUE, name VARCHAR(160), phone VARCHAR(40), email VARCHAR(190), avatar_url VARCHAR(500), custom_fields JSON NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX(tenant_id,phone), INDEX(tenant_id,email));
CREATE TABLE contact_identities (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, contact_id BIGINT UNSIGNED NOT NULL, channel_id BIGINT UNSIGNED NOT NULL, external_id VARCHAR(190) NOT NULL, profile_json JSON NULL, UNIQUE KEY uq_identity(channel_id,external_id));
CREATE TABLE contact_external_refs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, contact_id BIGINT UNSIGNED NOT NULL, external_system VARCHAR(80) NOT NULL, external_id VARCHAR(190) NOT NULL, UNIQUE KEY uq_ext(tenant_id,external_system,external_id));
CREATE TABLE conversations (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, uuid CHAR(36) NOT NULL UNIQUE, channel_id BIGINT UNSIGNED NOT NULL, contact_id BIGINT UNSIGNED NOT NULL, assigned_user_id BIGINT UNSIGNED NULL, team_id BIGINT UNSIGNED NULL, status ENUM('open','pending','resolved','closed') NOT NULL DEFAULT 'open', priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal', unread_count INT NOT NULL DEFAULT 0, last_message_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(tenant_id,status,last_message_at));
CREATE TABLE messages (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, conversation_id BIGINT UNSIGNED NOT NULL, uuid CHAR(36) NOT NULL UNIQUE, external_message_id VARCHAR(190) NULL, direction ENUM('in','out') NOT NULL, sender_type ENUM('contact','user','bot','system') NOT NULL, sender_user_id BIGINT UNSIGNED NULL, type VARCHAR(40) NOT NULL DEFAULT 'text', body TEXT NULL, media_json JSON NULL, status VARCHAR(40) NULL, sent_at DATETIME NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_external(tenant_id,external_message_id), INDEX(conversation_id,sent_at));
CREATE TABLE media_library (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, uuid CHAR(36) NOT NULL UNIQUE, original_name VARCHAR(255) NOT NULL, stored_path VARCHAR(500) NOT NULL, mime_type VARCHAR(120) NOT NULL, size_bytes BIGINT UNSIGNED NOT NULL, purpose ENUM('branding','chat','template','attachment') NOT NULL DEFAULT 'attachment', uploaded_by BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(tenant_id,purpose));
CREATE TABLE conversation_notes (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, conversation_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, body TEXT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE tags (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, name VARCHAR(80) NOT NULL, color VARCHAR(20), UNIQUE KEY uq_tag(tenant_id,name));
CREATE TABLE conversation_tags (conversation_id BIGINT UNSIGNED NOT NULL, tag_id BIGINT UNSIGNED NOT NULL, PRIMARY KEY(conversation_id,tag_id));
CREATE TABLE quick_replies (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, shortcut VARCHAR(80) NOT NULL, title VARCHAR(120) NOT NULL, body TEXT NOT NULL, team_id BIGINT UNSIGNED NULL, active TINYINT(1) DEFAULT 1, UNIQUE KEY uq_qr(tenant_id,shortcut));
CREATE TABLE bot_profiles (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, name VARCHAR(100) NOT NULL DEFAULT 'NIVO', enabled TINYINT(1) NOT NULL DEFAULT 0, mode ENUM('rules','ai','hybrid') NOT NULL DEFAULT 'hybrid', provider VARCHAR(60) NULL, model VARCHAR(100) NULL, system_prompt TEXT NULL, fallback_message TEXT NULL, handoff_rules_json JSON NULL, business_hours_json JSON NULL, knowledge_enabled TINYINT(1) NOT NULL DEFAULT 0, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uq_bot_tenant(tenant_id));
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
 name VARCHAR(120) NOT NULL, monthly_price DECIMAL(12,2) NOT NULL DEFAULT 0,
 currency VARCHAR(8) NOT NULL DEFAULT 'HNL', max_users INT NULL, max_channels INT NULL,
 features_json JSON NULL, active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS tenant_subscriptions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL,
 plan_id BIGINT UNSIGNED NOT NULL, status VARCHAR(30) NOT NULL DEFAULT 'active',
 starts_at DATETIME NULL, ends_at DATETIME NULL, UNIQUE KEY uq_tenant_subscription(tenant_id)
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

-- Preferencias personales de interfaz (tema/ayuda). Runtime también la crea para instalaciones existentes.
CREATE TABLE IF NOT EXISTS user_preferences (
  user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  theme ENUM('system','light','dark') NOT NULL DEFAULT 'system',
  context_help TINYINT(1) NOT NULL DEFAULT 1,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


-- Preferencias premium de la bandeja omnicanal
CREATE TABLE IF NOT EXISTS inbox_preferences (
  user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  channel_type VARCHAR(40) NOT NULL DEFAULT 'all',
  assignment_filter VARCHAR(30) NOT NULL DEFAULT 'all',
  priority_filter VARCHAR(30) NOT NULL DEFAULT 'all',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


-- NIVO Web Chat
CREATE TABLE IF NOT EXISTS webchat_widgets(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,channel_id BIGINT UNSIGNED NULL,name VARCHAR(120) NOT NULL DEFAULT 'NIVO Web Chat',public_key CHAR(40) NOT NULL UNIQUE,enabled TINYINT(1) NOT NULL DEFAULT 1,position VARCHAR(30) NOT NULL DEFAULT 'bottom-right',display_mode ENUM('launcher','open') NOT NULL DEFAULT 'launcher',offset_x INT NOT NULL DEFAULT 24,offset_y INT NOT NULL DEFAULT 24,accent_color VARCHAR(20) NOT NULL DEFAULT '#0F766E',launcher_icon VARCHAR(30) NOT NULL DEFAULT 'nivo',launcher_label VARCHAR(80) NULL,sound_enabled TINYINT(1) NOT NULL DEFAULT 1,privacy_enabled TINYINT(1) NOT NULL DEFAULT 0,privacy_text VARCHAR(240) NULL,privacy_url VARCHAR(500) NULL,welcome_title VARCHAR(160) NOT NULL DEFAULT '¡Hola! Soy NIVO',assistant_subtitle VARCHAR(190) NULL,welcome_message VARCHAR(500) NOT NULL DEFAULT '¿En qué puedo ayudarte hoy?',ask_name TINYINT(1) NOT NULL DEFAULT 1,ask_email TINYINT(1) NOT NULL DEFAULT 0,profile_required TINYINT(1) NOT NULL DEFAULT 0,allow_multiple_domains TINYINT(1) NOT NULL DEFAULT 1,created_by BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX(tenant_id,enabled));
CREATE TABLE IF NOT EXISTS webchat_installations(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,widget_id BIGINT UNSIGNED NOT NULL,domain VARCHAR(255) NOT NULL,label VARCHAR(120) NULL,enabled TINYINT(1) NOT NULL DEFAULT 1,created_by BIGINT UNSIGNED NULL,first_seen_at DATETIME NULL,last_seen_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_widget_domain(widget_id,domain),INDEX(tenant_id,enabled));
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
INSERT IGNORE INTO system_settings(setting_key,setting_value) VALUES('app_version','2.25.6');
