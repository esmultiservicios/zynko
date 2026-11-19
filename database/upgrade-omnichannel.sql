
-- Omnichannel: additive upgrade, safe to execute again.
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
 content LONGTEXT NOT NULL, published_at DATETIME NOT NULL, PRIMARY KEY(document_key,version)
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
