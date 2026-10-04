<?php
/** Publish an event that the WebSocket daemon will broadcast to this tenant. */
function zynkoRealtimePublish(PDO $pdo, int $tenantId, string $eventType, array $payload = [], ?string $entityType = null, ?string $entityId = null): int
{
    if ($tenantId < 1) throw new InvalidArgumentException('tenantId inválido.');
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $st = $pdo->prepare('INSERT INTO realtime_events(tenant_id,event_type,entity_type,entity_id,payload_json) VALUES(?,?,?,?,?)');
    $st->execute([$tenantId, $eventType, $entityType, $entityId, $json]);
    return (int)$pdo->lastInsertId();
}


/** Publish without breaking the primary chat flow when realtime storage is temporarily unavailable. */
function zynkoRealtimePublishSafe(PDO $pdo, int $tenantId, string $eventType, array $payload = [], ?string $entityType = null, ?string $entityId = null): int
{
    try {
        return zynkoRealtimePublish($pdo, $tenantId, $eventType, $payload, $entityType, $entityId);
    } catch (Throwable $error) {
        error_log('ZYNKO realtime publish failed: ' . $error->getMessage());
        return 0;
    }
}
