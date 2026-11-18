
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
