<?php

final class PublicEmailValidationService
{
    private const COMMON_DOMAINS = [
        'gmail.com',
        'hotmail.com',
        'outlook.com',
        'live.com',
        'yahoo.com',
        'icloud.com',
        'proton.me',
        'protonmail.com',
        'aol.com',
        'msn.com',
        'gmx.com',
        'zoho.com',
    ];

    private const TYPO_MAP = [
        'gmail.con' => 'gmail.com',
        'gmial.com' => 'gmail.com',
        'gmai.com' => 'gmail.com',
        'gmal.com' => 'gmail.com',
        'gmail.co' => 'gmail.com',
        'hotmal.com' => 'hotmail.com',
        'hotmai.com' => 'hotmail.com',
        'hotmail.con' => 'hotmail.com',
        'outlook.con' => 'outlook.com',
        'outlok.com' => 'outlook.com',
        'outloo.com' => 'outlook.com',
        'yahoo.con' => 'yahoo.com',
        'yaho.com' => 'yahoo.com',
        'icloud.con' => 'icloud.com',
        'protonmail.con' => 'protonmail.com',
    ];

    private const DISPOSABLE_DOMAINS = [
        '10minutemail.com','10minutemail.net','10minutemail.org','20minutemail.com','33mail.com',
        'anonbox.net','anonymbox.com','bouncr.com','burnermail.io','byom.de','crazymailing.com',
        'dispostable.com','dropmail.me','emailondeck.com','emailtemporario.com.br','emkei.cz',
        'fakeinbox.com','fakemail.net','getnada.com','guerrillamail.com','guerrillamail.net',
        'guerrillamail.org','guerrillamailblock.com','inboxkitten.com','maildrop.cc','mailinator.com',
        'mailinator.net','mailnesia.com','mailpoof.com','mailsac.com','mintemail.com','moakt.com',
        'mohmal.com','mytemp.email','nada.email','sharklasers.com','spam4.me','spamgourmet.com',
        'temp-mail.org','temp-mail.io','tempail.com','tempemail.net','tempinbox.com','tempmail.com',
        'tempmail.net','tempmailo.com','throwawaymail.com','trashmail.com','trashmail.de','yopmail.com',
        'yopmail.fr','yopmail.net','mail.tm','emailnator.com','generator.email','minuteinbox.com',
        'tempmailaddress.com','disposablemail.com','mail-temporaire.fr','mailcatch.com','getairmail.com',
    ];

    private array $env;

    public function __construct(array $env = [])
    {
        $this->env = $env;
    }

    public function validate(string $email, bool $checkDns = true, bool $checkExternal = true): array
    {
        $email = trim($email);
        $result = [
            'valid' => false,
            'code' => 'invalid_format',
            'message' => 'Ingresa un correo electrónico válido. Ejemplo: nombre@empresa.com',
            'suggestion' => null,
            'email' => $email,
            'domain' => '',
            'mx_valid' => null,
            'temporary' => false,
            'external_checked' => false,
            'external_fallback' => false,
            'dns_fallback' => false,
        ];

        if ($email === '' || preg_match('/\s/u', $email)) {
            return $result;
        }

        if (substr_count($email, '@') !== 1 || strlen($email) > 190) {
            return $result;
        }

        [$local, $domain] = explode('@', $email, 2);
        $local = trim($local);
        $domain = strtolower(trim($domain));
        $result['domain'] = $domain;

        if ($local === '' || $domain === '' || str_contains($domain, '..') || !str_contains($domain, '.')) {
            return $result;
        }

        if (!preg_match('/^[A-Za-z0-9.!#$%&\'*+\/=?^_`{|}~-]+$/', $local)) {
            return $result;
        }

        $asciiDomain = $domain;
        if (function_exists('idn_to_ascii')) {
            $variant = defined('INTL_IDNA_VARIANT_UTS46') ? (int)constant('INTL_IDNA_VARIANT_UTS46') : 1;
            $converted = @idn_to_ascii($domain, IDNA_DEFAULT, $variant);
            if (is_string($converted) && $converted !== '') {
                $asciiDomain = strtolower($converted);
            }
        }

        $normalized = $local . '@' . $asciiDomain;
        if (!filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            return $result;
        }

        if ($this->isDisposableDomain($asciiDomain)) {
            return array_merge($result, [
                'code' => 'temporary_email',
                'temporary' => true,
                'message' => 'Utiliza un correo electrónico permanente para continuar.',
            ]);
        }

        $suggestedDomain = $this->suggestDomain($asciiDomain);
        if ($suggestedDomain !== null && $suggestedDomain !== $asciiDomain) {
            $suggestion = $local . '@' . $suggestedDomain;
            return array_merge($result, [
                'code' => 'suggestion',
                'message' => '¿Quisiste escribir ' . $suggestion . '?',
                'suggestion' => $suggestion,
            ]);
        }

        if ($checkDns) {
            $mxValid = $this->hasMxRecords($asciiDomain);
            $result['mx_valid'] = $mxValid;
            if ($mxValid === false) {
                return array_merge($result, [
                    'code' => 'invalid_domain',
                    'message' => 'El dominio de este correo no parece válido. Revisa la dirección e inténtalo nuevamente.',
                ]);
            }
            if ($mxValid === null) {
                $result['dns_fallback'] = true;
            }
        }

        if ($checkExternal) {
            $external = $this->checkExternalVerifier($normalized);
            if ($external['checked']) {
                $result['external_checked'] = true;
                if ($external['temporary'] === true) {
                    return array_merge($result, [
                        'code' => 'temporary_email',
                        'temporary' => true,
                        'message' => 'Utiliza un correo electrónico permanente para continuar.',
                    ]);
                }
                if ($external['deliverable'] === false) {
                    return array_merge($result, [
                        'code' => 'undeliverable',
                        'message' => 'Este correo no parece disponible para recibir mensajes. Revisa la dirección e inténtalo nuevamente.',
                    ]);
                }
            } elseif ($external['configured']) {
                $result['external_fallback'] = true;
            }
        }

        return array_merge($result, [
            'valid' => true,
            'code' => 'valid',
            'message' => 'Correo válido',
            'email' => $normalized,
            'domain' => $asciiDomain,
        ]);
    }

    private function suggestDomain(string $domain): ?string
    {
        if (isset(self::TYPO_MAP[$domain])) {
            return self::TYPO_MAP[$domain];
        }

        foreach (self::COMMON_DOMAINS as $candidate) {
            $distance = levenshtein($domain, $candidate);
            if ($distance > 0 && $distance <= 2) {
                return $candidate;
            }
        }

        return null;
    }

    private function isDisposableDomain(string $domain): bool
    {
        $domain = strtolower($domain);
        foreach (self::DISPOSABLE_DOMAINS as $blocked) {
            if ($domain === $blocked || str_ends_with($domain, '.' . $blocked)) {
                return true;
            }
        }
        return false;
    }

    private function hasMxRecords(string $domain): ?bool
    {
        if (function_exists('dns_get_record')) {
            if (function_exists('error_clear_last')) {
                error_clear_last();
            }
            $records = @dns_get_record($domain, DNS_MX);
            $error = error_get_last();
            if (is_array($records)) {
                return count($records) > 0;
            }
            $message = strtolower((string)($error['message'] ?? ''));
            if ($message !== '' && (str_contains($message, 'temporary') || str_contains($message, 'server error') || str_contains($message, 'timed out'))) {
                return null;
            }
        }

        if (function_exists('checkdnsrr')) {
            return @checkdnsrr($domain, 'MX');
        }

        return null;
    }

    private function checkExternalVerifier(string $email): array
    {
        $url = trim((string)($this->env['EMAIL_VALIDATION_API_URL'] ?? ''));
        $key = trim((string)($this->env['EMAIL_VALIDATION_API_KEY'] ?? ''));
        $timeout = max(2, min(12, (int)($this->env['EMAIL_VALIDATION_API_TIMEOUT'] ?? 5)));

        $base = [
            'configured' => $url !== '',
            'checked' => false,
            'deliverable' => null,
            'temporary' => null,
        ];

        if ($url === '') {
            return $base;
        }

        $target = str_contains($url, '{email}')
            ? str_replace('{email}', rawurlencode($email), $url)
            : $url . (str_contains($url, '?') ? '&' : '?') . 'email=' . rawurlencode($email);

        try {
            $raw = '';
            $http = 0;
            if (function_exists('curl_init')) {
                $headers = ['Accept: application/json'];
                if ($key !== '') {
                    $headers[] = 'Authorization: Bearer ' . $key;
                    $headers[] = 'X-API-Key: ' . $key;
                }
                $ch = curl_init($target);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => $timeout,
                    CURLOPT_TIMEOUT => $timeout,
                    CURLOPT_HTTPHEADER => $headers,
                ]);
                $raw = (string)curl_exec($ch);
                $http = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                curl_close($ch);
            } else {
                $headers = "Accept: application/json\r\n";
                if ($key !== '') {
                    $headers .= 'Authorization: Bearer ' . $key . "\r\n";
                    $headers .= 'X-API-Key: ' . $key . "\r\n";
                }
                $context = stream_context_create(['http' => [
                    'method' => 'GET',
                    'header' => $headers,
                    'timeout' => $timeout,
                    'ignore_errors' => true,
                ]]);
                $raw = (string)@file_get_contents($target, false, $context);
                if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
                    $http = (int)$m[1];
                }
            }

            if ($raw === '' || $http < 200 || $http >= 300) {
                return $base;
            }

            $json = json_decode($raw, true);
            if (!is_array($json)) {
                return $base;
            }

            $deliverable = null;
            foreach (['deliverable', 'is_deliverable', 'valid'] as $keyName) {
                if (array_key_exists($keyName, $json) && is_bool($json[$keyName])) {
                    $deliverable = $json[$keyName];
                    break;
                }
            }
            if ($deliverable === null) {
                $status = strtolower((string)($json['result'] ?? $json['status'] ?? ''));
                if (in_array($status, ['deliverable', 'valid', 'ok', 'safe'], true)) {
                    $deliverable = true;
                } elseif (in_array($status, ['undeliverable', 'invalid', 'rejected'], true)) {
                    $deliverable = false;
                }
            }

            $temporary = null;
            foreach (['disposable', 'is_disposable', 'temporary', 'is_temporary'] as $keyName) {
                if (array_key_exists($keyName, $json) && is_bool($json[$keyName])) {
                    $temporary = $json[$keyName];
                    break;
                }
            }

            return [
                'configured' => true,
                'checked' => true,
                'deliverable' => $deliverable,
                'temporary' => $temporary,
            ];
        } catch (Throwable $e) {
            return $base;
        }
    }
}
