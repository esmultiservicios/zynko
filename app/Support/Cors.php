<?php

declare(strict_types=1);

/**
 * Utilidades CORS compartidas por las APIs públicas de ZYNKO.
 *
 * Nunca usa cookies ni Access-Control-Allow-Credentials. La autorización real
 * siempre sigue dependiendo de la key/token correspondiente a cada endpoint.
 */
final class ZynkoCors
{
    public static function requestOrigin(): string
    {
        $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
        if ($origin === '') {
            return '';
        }

        $parts = parse_url($origin);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return '';
        }

        $normalized = $scheme . '://' . strtolower((string) $parts['host']);
        if (!empty($parts['port'])) {
            $normalized .= ':' . (int) $parts['port'];
        }

        return $normalized;
    }

    public static function originHost(string $origin): string
    {
        if ($origin === '') {
            return '';
        }

        $parts = parse_url($origin);
        if (!is_array($parts) || empty($parts['host'])) {
            return '';
        }

        $host = strtolower((string) $parts['host']);
        if (!empty($parts['port'])) {
            $host .= ':' . (int) $parts['port'];
        }

        return $host;
    }

    public static function isPreflight(): bool
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'OPTIONS';
    }

    public static function send(
        string $origin,
        array $methods = ['GET', 'POST', 'OPTIONS'],
        array $headers = ['Content-Type', 'Accept']
    ): void {
        if ($origin === '') {
            return;
        }

        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: ' . implode(', ', $methods));
        header('Access-Control-Allow-Headers: ' . implode(', ', $headers));
        header('Access-Control-Max-Age: 600');
        header('Vary: Origin, Access-Control-Request-Method, Access-Control-Request-Headers');
    }

    /**
     * Responde el preflight de APIs autenticadas por Bearer/API key.
     * CORS solamente permite que el navegador intente la petición; NO sustituye
     * la autenticación del endpoint real.
     */
    public static function handleAuthenticatedApiPreflight(): void
    {
        if (!self::isPreflight()) {
            return;
        }

        $origin = self::requestOrigin();
        if ($origin === '') {
            http_response_code(400);
            exit;
        }

        self::send(
            $origin,
            ['GET', 'POST', 'OPTIONS'],
            ['Authorization', 'Content-Type', 'Accept', 'Idempotency-Key', 'X-Requested-With']
        );
        http_response_code(204);
        exit;
    }
}
