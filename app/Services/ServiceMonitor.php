<?php

declare(strict_types=1);

require_once __DIR__ . '/NotificationService.php';
require_once dirname(__DIR__) . '/Support/ServerHealth.php';

final class ZynkoServiceMonitor
{
    public function __construct(
        private PDO $pdo,
        private string $root,
        private array $env,
        private int $platformTenantId
    ) {}

    public function settings(): array
    {
        $defaults = [
            'monitor_enabled' => '1',
            'monitor_email' => '',
            'monitor_auto_recover_ws' => '1',
            'monitor_notify_recovery' => '1',
        ];
        try {
            $q = $this->pdo->query("SELECT setting_key,setting_value FROM system_settings WHERE setting_key LIKE 'monitor_%'");
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $defaults[(string)$row['setting_key']] = (string)$row['setting_value'];
            }
        } catch (Throwable $e) {}
        return $defaults;
    }

    public function currentStates(): array
    {
        try {
            $q = $this->pdo->query("SELECT service_key,service_name,current_status,detail,last_checked_at,last_changed_at,last_notified_at FROM service_monitor_state ORDER BY service_name");
            return $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    public function recentEvents(int $limit = 12): array
    {
        try {
            $limit = max(1, min(50, $limit));
            $q = $this->pdo->query("SELECT service_key,service_name,old_status,new_status,detail,created_at FROM service_monitor_events ORDER BY id DESC LIMIT {$limit}");
            return $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    public function run(bool $sendNotifications = true, bool $allowRecovery = true): array
    {
        $settings = $this->settings();
        $services = $this->probeServices();
        $changes = [];

        if ($allowRecovery && ($settings['monitor_auto_recover_ws'] ?? '1') === '1') {
            foreach ($services as $svc) {
                if ($svc['key'] === 'websocket' && $svc['status'] === 'down') {
                    $recovery = $this->recoverWebSocket();
                    if ($recovery['attempted']) {
                        usleep(450000);
                        $services = $this->probeServices();
                    }
                    break;
                }
            }
        }

        foreach ($services as $service) {
            $previous = $this->previousState($service['key']);
            $this->saveState($service, $previous);
            if ($previous !== null && $previous !== $service['status']) {
                $changes[] = [
                    'key' => $service['key'],
                    'name' => $service['name'],
                    'old' => $previous,
                    'new' => $service['status'],
                    'detail' => $service['detail'],
                ];
                $this->logEvent($service, $previous);
            }
        }

        if ($sendNotifications && ($settings['monitor_enabled'] ?? '1') === '1' && $changes) {
            $notifyRecovery = ($settings['monitor_notify_recovery'] ?? '1') === '1';
            $relevant = array_values(array_filter($changes, static function(array $change) use ($notifyRecovery): bool {
                if ($notifyRecovery) return true;
                return !in_array($change['new'], ['up','inactive'], true);
            }));
            if ($relevant) {
                $this->sendSummary($services, $relevant, $settings);
            }
        }

        $counts = ['up'=>0,'degraded'=>0,'down'=>0,'inactive'=>0];
        foreach ($services as $s) $counts[$s['status']] = ($counts[$s['status']] ?? 0) + 1;
        return [
            'services' => $services,
            'changes' => $changes,
            'counts' => $counts,
            'checked_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function probeServices(): array
    {
        $services = [];
        $health = (new ZynkoServerHealth($this->root, $this->pdo, $this->env, $this->platformTenantId))->report();
        $healthMap = [];
        foreach (($health['items'] ?? []) as $item) $healthMap[(string)($item['key'] ?? '')] = $item;

        $ws = $healthMap['ws_state'] ?? [];
        $services[] = $this->service('websocket', 'WebSocket · Tiempo real', (($ws['status'] ?? '') === 'ok') ? 'up' : 'down', (string)($ws['detail'] ?? 'Sin diagnóstico WebSocket.'));

        $db = $healthMap['db'] ?? [];
        $services[] = $this->service('database', 'Base de datos', (($db['status'] ?? '') === 'ok') ? 'up' : 'down', (string)($db['detail'] ?? 'Sin diagnóstico de base de datos.'));

        $base = rtrim((string)($this->env['APP_URL'] ?? ''), '/');
        if ($base !== '') {
            $api = $this->httpReachable($base . '/api.php');
            $services[] = $this->service('api', 'API de ZYNKO', $api['ok'] ? 'up' : 'down', $api['detail']);
            $webchatApi = $this->httpReachable($base . '/webchat-api.php');
            $widgetEnabled = $this->scalar("SELECT COUNT(*) FROM webchat_widgets WHERE tenant_id=? AND enabled=1", [$this->platformTenantId]) > 0;
            $services[] = $this->service('nivo_webchat', 'NIVO Web Chat', !$widgetEnabled ? 'inactive' : ($webchatApi['ok'] ? 'up' : 'down'), !$widgetEnabled ? 'El Widget está desactivado por configuración.' : $webchatApi['detail']);
        }

        $nivoEnabled = $this->scalar("SELECT COUNT(*) FROM bot_profiles WHERE tenant_id=? AND enabled=1", [$this->platformTenantId]) > 0;
        $services[] = $this->service('nivo_ai', 'NIVO IA', $nivoEnabled ? 'up' : 'inactive', $nivoEnabled ? 'El asistente está habilitado en la empresa principal.' : 'NIVO IA está desactivado por configuración.');

        try {
            $q = $this->pdo->prepare("SELECT id,type,name,status FROM channels WHERE tenant_id=? AND type<>'webchat' ORDER BY id");
            $q->execute([$this->platformTenantId]);
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $channel) {
                $status = (string)($channel['status'] ?? 'pending');
                $mapped = $status === 'connected' ? 'up' : ($status === 'warning' ? 'degraded' : 'down');
                $services[] = $this->service('channel_' . (int)$channel['id'], 'Canal · ' . (string)$channel['name'], $mapped, 'Estado registrado: ' . $status . ' · tipo ' . (string)$channel['type'] . '.');
            }
        } catch (Throwable $e) {}

        return $services;
    }

    private function service(string $key, string $name, string $status, string $detail): array
    {
        return compact('key','name','status','detail');
    }

    private function httpReachable(string $url): array
    {
        if (!function_exists('curl_init')) return ['ok'=>false,'detail'=>'cURL no está disponible para comprobar ' . $url . '.'];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY => false,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => 'ZYNKO-Service-Monitor/2.31.115',
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $ok = $errno === 0 && $code > 0 && $code < 500;
        return ['ok'=>$ok,'detail'=>$ok ? 'Endpoint accesible. HTTP ' . $code . '.' : 'Endpoint no disponible. HTTP ' . ($code ?: 'sin respuesta') . ($error !== '' ? ' · ' . $error : '') . '.'];
    }

    private function scalar(string $sql, array $params = []): int
    {
        try { $q = $this->pdo->prepare($sql); $q->execute($params); return (int)($q->fetchColumn() ?: 0); }
        catch (Throwable $e) { return 0; }
    }

    private function previousState(string $key): ?string
    {
        try {
            $q = $this->pdo->prepare('SELECT current_status FROM service_monitor_state WHERE service_key=? LIMIT 1');
            $q->execute([$key]);
            $value = $q->fetchColumn();
            return is_string($value) ? $value : null;
        } catch (Throwable $e) { return null; }
    }

    private function saveState(array $service, ?string $previous): void
    {
        try {
            $changed = $previous === null || $previous !== $service['status'];
            $sql = "INSERT INTO service_monitor_state(service_key,service_name,current_status,detail,last_checked_at,last_changed_at)
                    VALUES(?,?,?,?,NOW(),NOW())
                    ON DUPLICATE KEY UPDATE service_name=VALUES(service_name),last_changed_at=IF(current_status<>VALUES(current_status),NOW(),last_changed_at),current_status=VALUES(current_status),detail=VALUES(detail),last_checked_at=NOW()";
            $this->pdo->prepare($sql)->execute([$service['key'],$service['name'],$service['status'],$service['detail']]);
        } catch (Throwable $e) {}
    }

    private function logEvent(array $service, string $previous): void
    {
        try {
            $this->pdo->prepare('INSERT INTO service_monitor_events(service_key,service_name,old_status,new_status,detail,created_at) VALUES(?,?,?,?,?,NOW())')
                ->execute([$service['key'],$service['name'],$previous,$service['status'],$service['detail']]);
        } catch (Throwable $e) {}
    }

    private function recoverWebSocket(): array
    {
        $script = $this->root . '/bin/manage-websocket.sh';
        if (!is_file($script)) return ['attempted'=>false,'ok'=>false,'detail'=>'Script de servicio no disponible.'];
        $cmd = '/bin/bash ' . escapeshellarg($script) . ' start 2>&1';
        $out = [];$code = 1;
        if (function_exists('exec')) {
            @exec($cmd, $out, $code);
        } elseif (function_exists('shell_exec')) {
            $raw = (string)@shell_exec($cmd . '; printf "\n__EXIT__%s" "$?"');
            if (preg_match('/__EXIT__(\d+)\s*$/', $raw, $m)) $code = (int)$m[1];
            $out[] = trim((string)preg_replace('/\n__EXIT__\d+\s*$/', '', $raw));
        } else {
            return ['attempted'=>false,'ok'=>false,'detail'=>'El hosting no permite ejecutar el script de recuperación desde PHP.'];
        }
        return ['attempted'=>true,'ok'=>$code===0,'detail'=>trim(implode("\n",$out))];
    }

    private function sendSummary(array $services, array $changes, array $settings): void
    {
        $to = trim((string)($settings['monitor_email'] ?? ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) $to = $this->platformAdminEmail();
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return;

        $hasFailure = false;
        foreach ($changes as $c) if (in_array($c['new'], ['down','degraded'], true)) $hasFailure = true;
        $title = $hasFailure ? 'Alerta de servicio: ZYNKO requiere atención' : 'Servicio recuperado: ZYNKO volvió a estado operativo';
        $lines = [];
        $lines[] = 'Cambios detectados:';
        foreach ($changes as $c) $lines[] = '• ' . $c['name'] . ': ' . strtoupper($c['old']) . ' → ' . strtoupper($c['new']) . '. ' . $c['detail'];
        $lines[] = '';
        $lines[] = 'Resumen actual:';
        foreach ($services as $s) $lines[] = '• ' . $s['name'] . ': ' . strtoupper($s['status']);
        $lines[] = '';
        $lines[] = 'Revisión: ' . date('d/m/Y H:i:s') . '.';
        try {
            (new NotificationService($this->pdo, $this->root))->sendAdministrativeEvent(
                $this->platformTenantId,
                $to,
                $title,
                implode("\n", $lines),
                ['page'=>'settings','event'=>'service_monitor','changes'=>$changes]
            );
            $keys = array_column($changes, 'key');
            if ($keys) {
                $marks = implode(',', array_fill(0, count($keys), '?'));
                $this->pdo->prepare("UPDATE service_monitor_state SET last_notified_at=NOW() WHERE service_key IN ($marks)")->execute($keys);
            }
        } catch (Throwable $e) {}
    }

    private function platformAdminEmail(): string
    {
        try {
            $q = $this->pdo->prepare("SELECT u.email FROM users u JOIN tenant_users tu ON tu.user_id=u.id WHERE tu.tenant_id=? AND u.status='active' ORDER BY tu.is_owner DESC,FIELD(tu.role_code,'owner','admin','supervisor','agent'),u.id LIMIT 1");
            $q->execute([$this->platformTenantId]);
            $value = trim((string)($q->fetchColumn() ?: ''));
            return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : '';
        } catch (Throwable $e) { return ''; }
    }
}
