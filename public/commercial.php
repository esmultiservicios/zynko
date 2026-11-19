<?php
declare(strict_types=1);

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    exit;
}
// Una ruta fija por documento; no acepta acciones ni identificadores desde la URL.
$_GET = [];
define('ZYNKO_PUBLIC_LEGAL_DOCUMENT', 'commercial_conditions');
require __DIR__.'/index.php';
