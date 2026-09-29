<?php
declare(strict_types=1);

/**
 * ZYNKO root front controller.
 * Allows the application to work when the hosting document root points
 * to the project root instead of /public.
 */
$publicDir = __DIR__ . '/public';
$entry = $publicDir . '/index.php';

if (!is_file($entry)) {
    http_response_code(500);
    exit('ZYNKO: public/index.php no está disponible.');
}

chdir($publicDir);
require $entry;
