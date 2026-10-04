<?php

declare(strict_types=1);

$root = dirname(__DIR__);

function wsEnv(string $path): array
{
    $values = @parse_ini_file($path, false, INI_SCANNER_RAW);
    return is_array($values) ? $values : [];
}

function b64urlDecode(string $value): string|false
{
    $value = strtr($value, '-_', '+/');
    $value .= str_repeat('=', (4 - strlen($value) % 4) % 4);
    return base64_decode($value, true);
}

function verifyToken(string $token, string $hexKey): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/i', $hexKey) || !str_contains($token, '.')) {
        return null;
    }

    [$payload, $signature] = explode('.', $token, 2);
    $raw = b64urlDecode($payload);
    $sig = b64urlDecode($signature);

    if ($raw === false || $sig === false) {
        return null;
    }

    $expected = hash_hmac('sha256', $payload, hex2bin($hexKey), true);
    if (!hash_equals($expected, $sig)) {
        return null;
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || (int) ($data['exp'] ?? 0) < time() || (int) ($data['tenant_id'] ?? 0) < 1) {
        return null;
    }

    $isUser = (int) ($data['user_id'] ?? 0) > 0;
    $isVisitor = ($data['aud'] ?? '') === 'webchat' && (int) ($data['visitor_id'] ?? 0) > 0;

    return ($isUser || $isVisitor) ? $data : null;
}

function frame(string $payload, int $opcode = 1): string
{
    $length = strlen($payload);
    $header = chr(0x80 | $opcode);

    if ($length <= 125) {
        return $header . chr($length) . $payload;
    }

    if ($length <= 65535) {
        return $header . chr(126) . pack('n', $length) . $payload;
    }

    return $header . chr(127) . pack('NN', 0, $length) . $payload;
}

function decodeFrame(string &$buffer): ?array
{
    if (strlen($buffer) < 2) {
        return null;
    }

    $byte1 = ord($buffer[0]);
    $byte2 = ord($buffer[1]);
    $opcode = $byte1 & 0x0f;
    $masked = ($byte2 & 0x80) !== 0;
    $length = $byte2 & 0x7f;
    $position = 2;

    if ($length === 126) {
        if (strlen($buffer) < 4) {
            return null;
        }
        $length = unpack('n', substr($buffer, 2, 2))[1];
        $position = 4;
    } elseif ($length === 127) {
        if (strlen($buffer) < 10) {
            return null;
        }
        $parts = unpack('Nhi/Nlo', substr($buffer, 2, 8));
        if ($parts['hi'] !== 0) {
            return ['opcode' => 8, 'payload' => ''];
        }
        $length = $parts['lo'];
        $position = 10;
    }

    $mask = '';
    if ($masked) {
        if (strlen($buffer) < $position + 4) {
            return null;
        }
        $mask = substr($buffer, $position, 4);
        $position += 4;
    }

    if (strlen($buffer) < $position + $length) {
        return null;
    }

    $payload = substr($buffer, $position, $length);
    $buffer = substr($buffer, $position + $length);

    if ($masked) {
        for ($index = 0; $index < $length; $index++) {
            $payload[$index] = $payload[$index] ^ $mask[$index % 4];
        }
    }

    return ['opcode' => $opcode, 'payload' => $payload];
}

function handshake($socket, string $request, string $appKey): ?array
{
    if (!preg_match('/GET\s+([^\s]+)\s+HTTP\/1\.[01]/', $request, $targetMatch)) {
        return null;
    }

    if (!preg_match('/Sec-WebSocket-Key:\s*(.+)\r?$/mi', $request, $keyMatch)) {
        return null;
    }

    $target = $targetMatch[1];
    $query = parse_url($target, PHP_URL_QUERY) ?: '';
    parse_str($query, $params);
    $auth = verifyToken((string) ($params['token'] ?? ''), $appKey);

    if (!$auth) {
        return null;
    }

    $accept = base64_encode(
        sha1(trim($keyMatch[1]) . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true)
    );

    fwrite(
        $socket,
        "HTTP/1.1 101 Switching Protocols\r\n"
        . "Upgrade: websocket\r\n"
        . "Connection: Upgrade\r\n"
        . "Sec-WebSocket-Accept: {$accept}\r\n\r\n"
    );

    return $auth;
}

function queueFrame(array &$client, string $payload, int $opcode = 1): void
{
    $client['out'] .= frame($payload, $opcode);

    // A slow/disconnected browser must never consume unbounded server memory.
    if (strlen($client['out']) > 2 * 1024 * 1024) {
        $client['drop'] = true;
    }
}

function currentVisitorConversation(PDO $pdo, int $tenantId, int $visitorId): int
{
    if ($tenantId < 1 || $visitorId < 1) {
        return 0;
    }

    $statement = $pdo->prepare(
        'SELECT conversation_id FROM webchat_visitors WHERE id=? AND tenant_id=? LIMIT 1'
    );
    $statement->execute([$visitorId, $tenantId]);

    return (int) ($statement->fetchColumn() ?: 0);
}

function webchatMayReceiveEvent(PDO $pdo, array &$client, array $payload, array $event): bool
{
    if (($client['auth']['aud'] ?? '') !== 'webchat') {
        return true;
    }

    $tenantId = (int) ($client['auth']['tenant_id'] ?? 0);
    $visitorId = (int) ($client['auth']['visitor_id'] ?? 0);
    $eventVisitorId = (int) ($payload['data']['visitor_id'] ?? 0);
    $eventConversationId = (int) (
        $payload['data']['conversation_id']
        ?? (($event['entity_type'] ?? '') === 'conversation' ? $event['entity_id'] : 0)
    );

    if ($eventVisitorId > 0 && $eventVisitorId === $visitorId) {
        if ($eventConversationId > 0) {
            $client['auth']['conversation_id'] = $eventConversationId;
        }
        return true;
    }

    if ($eventConversationId < 1) {
        return false;
    }

    $knownConversationId = (int) ($client['auth']['conversation_id'] ?? 0);
    if ($knownConversationId === $eventConversationId) {
        return true;
    }

    // The first Web Chat socket is normally opened before the first message exists,
    // therefore its signed token legitimately has conversation_id=0. Resolve the
    // visitor's current conversation dynamically instead of forcing a reconnect.
    try {
        $resolvedConversationId = currentVisitorConversation($pdo, $tenantId, $visitorId);
        if ($resolvedConversationId > 0) {
            $client['auth']['conversation_id'] = $resolvedConversationId;
        }
        return $resolvedConversationId === $eventConversationId;
    } catch (Throwable $error) {
        error_log('WebSocket visitor conversation lookup failed: ' . $error->getMessage());
        return false;
    }
}

$env = wsEnv($root . '/.env');
$host = $env['WS_HOST'] ?? '127.0.0.1';
$port = (int) ($env['WS_PORT'] ?? 8080);
$appKey = $env['APP_KEY'] ?? '';

if (!preg_match('/^[a-f0-9]{64}$/i', $appKey)) {
    fwrite(STDERR, "APP_KEY inválida. Ejecuta primero el instalador.\n");
    exit(1);
}

$dsn = 'mysql:host=' . ($env['DB_HOST'] ?? '127.0.0.1')
    . ';port=' . ($env['DB_PORT'] ?? '3306')
    . ';dbname=' . ($env['DB_DATABASE'] ?? 'zynko')
    . ';charset=utf8mb4';

try {
    $pdo = new PDO(
        $dsn,
        $env['DB_USERNAME'] ?? 'root',
        $env['DB_PASSWORD'] ?? '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    $pdo->query('SELECT id FROM realtime_events LIMIT 1');
} catch (Throwable $error) {
    fwrite(STDERR, "Base de datos/WebSocket no preparada: {$error->getMessage()}\n");
    exit(1);
}

$server = @stream_socket_server("tcp://{$host}:{$port}", $errno, $errstr);
if (!$server) {
    fwrite(STDERR, "No se pudo abrir {$host}:{$port}: {$errstr}\n");
    exit(1);
}

stream_set_blocking($server, false);
echo "ZYNKO WebSocket activo en ws://{$host}:{$port}\n";

$clients = [];
$lastEvent = (int) ($pdo->query('SELECT COALESCE(MAX(id),0) FROM realtime_events')->fetchColumn());
$lastPoll = 0.0;
$lastPing = time();

while (true) {
    $read = [$server];
    $write = [];
    $except = [];

    foreach ($clients as $client) {
        $read[] = $client['socket'];
        if ($client['out'] !== '') {
            $write[] = $client['socket'];
        }
    }

    @stream_select($read, $write, $except, 0, 200000);

    foreach ($read as $socket) {
        if ($socket === $server) {
            $new = @stream_socket_accept($server, 0);
            if ($new) {
                stream_set_blocking($new, false);
                $clients[(int) $new] = [
                    'socket' => $new,
                    'handshake' => false,
                    'buffer' => '',
                    'out' => '',
                    'drop' => false,
                    'auth' => null,
                    'connected' => time()
                ];
            }
            continue;
        }

        $id = (int) $socket;
        if (!isset($clients[$id])) {
            continue;
        }

        $chunk = @fread($socket, 8192);
        if ($chunk === '' && feof($socket)) {
            @fclose($socket);
            unset($clients[$id]);
            continue;
        }

        $clients[$id]['buffer'] .= $chunk;

        if (!$clients[$id]['handshake']) {
            if (!str_contains($clients[$id]['buffer'], "\r\n\r\n")) {
                continue;
            }

            $auth = handshake($socket, $clients[$id]['buffer'], $appKey);
            if (!$auth) {
                @fwrite($socket, "HTTP/1.1 401 Unauthorized\r\nConnection: close\r\n\r\n");
                @fclose($socket);
                unset($clients[$id]);
                continue;
            }

            $clients[$id]['handshake'] = true;
            $clients[$id]['auth'] = $auth;
            $clients[$id]['buffer'] = '';

            if (($auth['aud'] ?? '') === 'webchat' && (int) ($auth['conversation_id'] ?? 0) < 1) {
                try {
                    $clients[$id]['auth']['conversation_id'] = currentVisitorConversation(
                        $pdo,
                        (int) $auth['tenant_id'],
                        (int) $auth['visitor_id']
                    );
                } catch (Throwable $ignore) {
                }
            }

            queueFrame(
                $clients[$id],
                json_encode([
                    'type' => 'connected',
                    'tenant_id' => (int) $auth['tenant_id'],
                    'user_id' => (int) ($auth['user_id'] ?? 0),
                    'visitor_id' => (int) ($auth['visitor_id'] ?? 0),
                    'conversation_id' => (int) ($clients[$id]['auth']['conversation_id'] ?? 0),
                    'at' => date(DATE_ATOM)
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
            continue;
        }

        while (($decoded = decodeFrame($clients[$id]['buffer'])) !== null) {
            if ($decoded['opcode'] === 8) {
                @fclose($socket);
                unset($clients[$id]);
                break;
            }

            if ($decoded['opcode'] === 9) {
                queueFrame($clients[$id], $decoded['payload'], 10);
                continue;
            }

            if ($decoded['opcode'] !== 1) {
                continue;
            }

            $message = json_decode($decoded['payload'], true);
            if (($message['type'] ?? '') === 'ping') {
                queueFrame(
                    $clients[$id],
                    json_encode(['type' => 'pong', 'at' => date(DATE_ATOM)], JSON_UNESCAPED_SLASHES)
                );
            }
        }
    }

    foreach ($write as $socket) {
        $id = (int) $socket;
        if (!isset($clients[$id]) || $clients[$id]['out'] === '') {
            continue;
        }

        $written = @fwrite($socket, $clients[$id]['out']);
        if ($written === false) {
            @fclose($socket);
            unset($clients[$id]);
            continue;
        }

        if ($written > 0) {
            $clients[$id]['out'] = substr($clients[$id]['out'], $written);
        }
    }

    foreach ($clients as $id => $client) {
        if (!empty($client['drop'])) {
            @fclose($client['socket']);
            unset($clients[$id]);
        }
    }

    $now = microtime(true);
    if ($now - $lastPoll >= 0.20) {
        $lastPoll = $now;

        try {
            $statement = $pdo->prepare(
                'SELECT id,tenant_id,event_type,entity_type,entity_id,payload_json,created_at '
                . 'FROM realtime_events WHERE id>? ORDER BY id ASC LIMIT 500'
            );
            $statement->execute([$lastEvent]);

            foreach ($statement as $event) {
                $lastEvent = max($lastEvent, (int) $event['id']);
                $data = json_decode((string) $event['payload_json'], true);
                if (!is_array($data)) {
                    $data = [];
                }

                $payload = [
                    'type' => 'event',
                    'id' => (int) $event['id'],
                    'event' => $event['event_type'],
                    'entity_type' => $event['entity_type'],
                    'entity_id' => $event['entity_id'],
                    'data' => $data,
                    'created_at' => $event['created_at']
                ];
                $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                foreach ($clients as $clientId => &$client) {
                    if (!$client['handshake']) {
                        continue;
                    }
                    if ((int) ($client['auth']['tenant_id'] ?? 0) !== (int) $event['tenant_id']) {
                        continue;
                    }
                    if (!webchatMayReceiveEvent($pdo, $client, $payload, $event)) {
                        continue;
                    }

                    queueFrame($client, $encoded);
                }
                unset($client);
            }
        } catch (Throwable $error) {
            fwrite(STDERR, 'Realtime outbox: ' . $error->getMessage() . "\n");
        }
    }

    if (time() - $lastPing >= 30) {
        $lastPing = time();
        foreach ($clients as &$client) {
            if ($client['handshake']) {
                queueFrame($client, 'zynko', 9);
            }
        }
        unset($client);
    }
}
