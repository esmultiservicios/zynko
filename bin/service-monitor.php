#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$env = @parse_ini_file($root . '/.env', false, INI_SCANNER_RAW) ?: [];
if (!is_file($root . '/storage/installed.lock')) { fwrite(STDERR, "ZYNKO no está instalado.\n"); exit(2); }
$dsn = 'mysql:host=' . ($env['DB_HOST'] ?? '127.0.0.1') . ';port=' . ($env['DB_PORT'] ?? 3306) . ';dbname=' . ($env['DB_DATABASE'] ?? 'zynko') . ';charset=utf8mb4';
try {
    $pdo = new PDO($dsn, $env['DB_USERNAME'] ?? 'root', $env['DB_PASSWORD'] ?? '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
    $q = $pdo->query("SELECT id FROM tenants WHERE LOWER(REPLACE(TRIM(name),' ','')) IN ('esmultiservicios','esmultsiervicios') OR LOWER(slug) LIKE 'es-multiservicios%' OR LOWER(slug) LIKE 'es-multsiervicios%' ORDER BY id ASC LIMIT 1");
    $tenantId = (int)($q->fetchColumn() ?: 0);
    if ($tenantId <= 0) $tenantId = (int)($pdo->query('SELECT MIN(id) FROM tenants')->fetchColumn() ?: 0);
    if ($tenantId <= 0) throw new RuntimeException('No existe empresa principal para monitoreo.');
    require_once $root . '/app/Services/ServiceMonitor.php';
    $result = (new ZynkoServiceMonitor($pdo, $root, $env, $tenantId))->run(true, true);
    echo 'ZYNKO_MONITOR_OK ' . json_encode($result['counts'], JSON_UNESCAPED_UNICODE) . ' ' . $result['checked_at'] . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'ZYNKO_MONITOR_ERROR ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
