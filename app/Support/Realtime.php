<?php
/**
 * Realtime outbox helpers.
 *
 * Messages are always persisted first. The realtime_events table is the durable
 * outbox consumed by the WebSocket daemon. When callers wrap the message insert
 * and zynkoRealtimePublishMessage() in the same DB transaction, persistence and
 * realtime delivery intent become atomic.
 */

/** Publish an event that the WebSocket daemon will broadcast to this tenant. */
function zynkoRealtimePublish(PDO $pdo, int $tenantId, string $eventType, array $payload = [], ?string $entityType = null, ?string $entityId = null): int
{
    if ($tenantId < 1) {
        throw new InvalidArgumentException('tenantId inválido.');
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $statement = $pdo->prepare(
        'INSERT INTO realtime_events(tenant_id,event_type,entity_type,entity_id,payload_json) VALUES(?,?,?,?,?)'
    );
    $statement->execute([$tenantId, $eventType, $entityType, $entityId, $json]);

    return (int) $pdo->lastInsertId();
}

/**
 * Return the canonical representation of one persisted message.
 * This exact shape is consumed by both the admin inbox and NIVO Web Chat.
 */
function zynkoRealtimeMessage(PDO $pdo, int $tenantId, int $messageId): ?array
{
    if ($tenantId < 1 || $messageId < 1) {
        return null;
    }

    $statement = $pdo->prepare(
        "SELECT
            m.id,
            m.uuid,
            m.conversation_id,
            m.direction,
            m.sender_type,
            m.sender_user_id,
            u.name AS sender_name,
            m.type,
            m.body,
            m.media_json,
            m.status,
            m.sent_at,
            m.created_at
         FROM messages m
         LEFT JOIN users u ON u.id=m.sender_user_id
         WHERE m.tenant_id=? AND m.id=?
         LIMIT 1"
    );
    $statement->execute([$tenantId, $messageId]);
    $message = $statement->fetch(PDO::FETCH_ASSOC);

    return $message ?: null;
}

/** Resolve the Web Chat visitor attached to a conversation, when applicable. */
function zynkoRealtimeVisitorId(PDO $pdo, int $tenantId, int $conversationId): int
{
    if ($tenantId < 1 || $conversationId < 1) {
        return 0;
    }

    try {
        $statement = $pdo->prepare(
            'SELECT id FROM webchat_visitors WHERE tenant_id=? AND conversation_id=? ORDER BY id DESC LIMIT 1'
        );
        $statement->execute([$tenantId, $conversationId]);
        return (int) ($statement->fetchColumn() ?: 0);
    } catch (Throwable $error) {
        return 0;
    }
}

/**
 * Persist a durable message.created event containing the full canonical message.
 * Call this inside the same DB transaction as the message insert whenever possible.
 */
function zynkoRealtimePublishMessage(PDO $pdo, int $tenantId, int $messageId, array $extra = []): int
{
    $message = zynkoRealtimeMessage($pdo, $tenantId, $messageId);
    if (!$message) {
        throw new RuntimeException('No se pudo preparar el evento realtime del mensaje.');
    }

    $conversationId = (int) ($message['conversation_id'] ?? 0);
    $visitorId = isset($extra['visitor_id'])
        ? (int) $extra['visitor_id']
        : zynkoRealtimeVisitorId($pdo, $tenantId, $conversationId);

    $payload = array_merge([
        'conversation_id' => $conversationId,
        'visitor_id' => $visitorId,
        'message_id' => (int) $message['id'],
        'message' => $message,
        'preview' => mb_substr(trim((string) ($message['body'] ?? '')), 0, 180)
    ], $extra);

    return zynkoRealtimePublish(
        $pdo,
        $tenantId,
        'message.created',
        $payload,
        'message',
        (string) $messageId
    );
}

/** Publish without breaking non-message secondary flows when realtime storage is temporarily unavailable. */
function zynkoRealtimePublishSafe(PDO $pdo, int $tenantId, string $eventType, array $payload = [], ?string $entityType = null, ?string $entityId = null): int
{
    try {
        return zynkoRealtimePublish($pdo, $tenantId, $eventType, $payload, $entityType, $entityId);
    } catch (Throwable $error) {
        error_log('ZYNKO realtime publish failed: ' . $error->getMessage());
        return 0;
    }
}
