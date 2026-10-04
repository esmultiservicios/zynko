<?php

declare(strict_types=1);

final class EmailValidator
{
    private const DOMAIN_TYPO_MAP = [
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
        '10minutemail.com',
        '10minutemail.net',
        '10minutemail.org',
        '20minutemail.com',
        '33mail.com',
        'anonbox.net',
        'anonymbox.com',
        'bouncr.com',
        'burnermail.io',
        'byom.de',
        'crazymailing.com',
        'dispostable.com',
        'dropmail.me',
        'emailnator.com',
        'emailondeck.com',
        'emailtemporario.com.br',
        'emkei.cz',
        'fakeinbox.com',
        'fakemail.net',
        'generator.email',
        'getairmail.com',
        'getnada.com',
        'guerrillamail.com',
        'guerrillamail.net',
        'guerrillamail.org',
        'guerrillamailblock.com',
        'inboxkitten.com',
        'mail-temporaire.fr',
        'mail.tm',
        'mailcatch.com',
        'maildrop.cc',
        'mailinator.com',
        'mailinator.net',
        'mailnesia.com',
        'mailpoof.com',
        'mailsac.com',
        'minuteinbox.com',
        'mintemail.com',
        'moakt.com',
        'mohmal.com',
        'mytemp.email',
        'nada.email',
        'sharklasers.com',
        'spam4.me',
        'spamgourmet.com',
        'temp-mail.io',
        'temp-mail.org',
        'tempail.com',
        'tempemail.net',
        'tempinbox.com',
        'tempmail.com',
        'tempmail.net',
        'tempmailaddress.com',
        'tempmailo.com',
        'throwawaymail.com',
        'trashmail.com',
        'trashmail.de',
        'yopmail.com',
        'yopmail.fr',
        'yopmail.net',
    ];

    private const RESERVED_EXAMPLE_DOMAINS = [
        'example.com',
        'example.net',
        'example.org',
    ];

    private const OBVIOUS_FAKE_ADDRESSES = [
        'alguien@algo.com',
        'prueba@prueba.com',
        'test@test.com',
        'usuario@example.com',
        'correo@correo.com',
    ];

    /**
     * Valida una dirección sin consultar APIs externas ni realizar SMTP probing.
     *
     * La comprobación DNS acepta MX y, como fallback compatible con RFC, A/AAAA.
     * Si el servidor PHP no dispone de funciones DNS o el resolver falla de forma
     * indeterminada, la validación no bloquea el correo únicamente por ese motivo.
     */
    public function validate(string $email): array
    {
        $original = $email;
        $email = trim($email);

        $base = [
            'valid' => false,
            'email' => $email,
            'original' => $original,
            'domain' => '',
            'code' => 'invalid_format',
            'reason' => 'Formato de correo inválido.',
            'suggestion' => null,
            'dns' => 'not_checked',
        ];

        if ($email === '') {
            return array_merge($base, [
                'code' => 'empty',
                'reason' => 'La dirección de correo está vacía.',
            ]);
        }

        if (preg_match('/\s/u', $email) === 1) {
            return array_merge($base, [
                'code' => 'contains_spaces',
                'reason' => 'La dirección contiene espacios.',
            ]);
        }

        if (substr_count($email, '@') !== 1 || strlen($email) > 254) {
            return $base;
        }

        [$local, $domain] = explode('@', $email, 2);
        $local = trim($local);
        $domain = strtolower(trim($domain));

        if ($local === '' || $domain === '') {
            return $base;
        }

        if (strlen($local) > 64 || str_contains($domain, '..')) {
            return $base;
        }

        $asciiDomain = $this->toAsciiDomain($domain);
        $normalized = $local . '@' . $asciiDomain;
        $base['email'] = $normalized;
        $base['domain'] = $asciiDomain;

        if (!filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            return $base;
        }

        if (isset(self::DOMAIN_TYPO_MAP[$asciiDomain])) {
            $suggested = $local . '@' . self::DOMAIN_TYPO_MAP[$asciiDomain];

            return array_merge($base, [
                'code' => 'domain_typo',
                'reason' => 'El dominio parece contener un error de escritura.',
                'suggestion' => $suggested,
            ]);
        }

        if ($this->isObviousFake($normalized, $local, $asciiDomain)) {
            return array_merge($base, [
                'code' => 'obvious_fake',
                'reason' => 'La dirección parece ser ficticia, de prueba o de ejemplo.',
            ]);
        }

        if ($this->isDisposableDomain($asciiDomain)) {
            return array_merge($base, [
                'code' => 'disposable_domain',
                'reason' => 'El dominio corresponde a un servicio de correo temporal o desechable.',
            ]);
        }

        $dns = $this->validateDomainDns($asciiDomain);
        if ($dns['valid'] === false) {
            return array_merge($base, [
                'code' => 'domain_without_mail_dns',
                'reason' => 'El dominio no publica registros MX ni un fallback A/AAAA válido.',
                'dns' => $dns['status'],
            ]);
        }

        return array_merge($base, [
            'valid' => true,
            'code' => 'valid',
            'reason' => 'Correo válido para intentar envío.',
            'dns' => $dns['status'],
        ]);
    }

    /**
     * Acepta un string único, listas separadas por coma/punto y coma o un array.
     * Devuelve destinatarios válidos deduplicados y rechazos con su motivo.
     */
    public function validateRecipients(string|array $recipients): array
    {
        $items = is_array($recipients)
            ? $recipients
            : (preg_split('/[;,]+/', $recipients) ?: []);

        $valid = [];
        $rejected = [];
        $seen = [];

        foreach ($items as $item) {
            $candidate = trim((string)$item);
            if ($candidate === '') {
                continue;
            }

            $result = $this->validate($candidate);
            if (!$result['valid']) {
                $rejected[] = $result;
                continue;
            }

            $key = strtolower((string)$result['email']);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $valid[] = (string)$result['email'];
        }

        return [
            'valid' => $valid,
            'rejected' => $rejected,
        ];
    }

    private function toAsciiDomain(string $domain): string
    {
        if (!function_exists('idn_to_ascii')) {
            return strtolower($domain);
        }

        $flags = defined('IDNA_DEFAULT') ? IDNA_DEFAULT : 0;
        $variant = defined('INTL_IDNA_VARIANT_UTS46')
            ? (int)constant('INTL_IDNA_VARIANT_UTS46')
            : 1;
        $converted = @idn_to_ascii($domain, $flags, $variant);

        return is_string($converted) && $converted !== ''
            ? strtolower($converted)
            : strtolower($domain);
    }

    private function isDisposableDomain(string $domain): bool
    {
        foreach (self::DISPOSABLE_DOMAINS as $blocked) {
            if ($domain === $blocked || str_ends_with($domain, '.' . $blocked)) {
                return true;
            }
        }

        return false;
    }

    private function isObviousFake(string $email, string $local, string $domain): bool
    {
        $emailLower = strtolower($email);
        if (in_array($emailLower, self::OBVIOUS_FAKE_ADDRESSES, true)) {
            return true;
        }

        if (in_array($domain, self::RESERVED_EXAMPLE_DOMAINS, true)) {
            return true;
        }

        $domainLabel = explode('.', $domain, 2)[0] ?? '';
        $localLower = strtolower($local);
        $placeholderNames = ['test', 'prueba', 'correo', 'email', 'usuario', 'user', 'ejemplo', 'example'];

        return $domainLabel !== ''
            && $localLower === strtolower($domainLabel)
            && in_array($localLower, $placeholderNames, true);
    }

    /**
     * @return array{valid:?bool,status:string}
     */
    private function validateDomainDns(string $domain): array
    {
        if (function_exists('dns_get_record')) {
            if (function_exists('error_clear_last')) {
                error_clear_last();
            }

            $types = DNS_MX;
            if (defined('DNS_A')) {
                $types |= DNS_A;
            }
            if (defined('DNS_AAAA')) {
                $types |= DNS_AAAA;
            }

            $records = @dns_get_record($domain, $types);
            $lastError = error_get_last();

            if (is_array($records)) {
                $hasMx = false;
                $hasAddress = false;

                foreach ($records as $record) {
                    $type = strtoupper((string)($record['type'] ?? ''));
                    if ($type === 'MX') {
                        $hasMx = true;
                    }
                    if ($type === 'A' || $type === 'AAAA') {
                        $hasAddress = true;
                    }
                }

                if ($hasMx) {
                    return ['valid' => true, 'status' => 'mx'];
                }
                if ($hasAddress) {
                    return ['valid' => true, 'status' => 'a_aaaa_fallback'];
                }

                if (count($records) === 0 && empty($lastError)) {
                    return ['valid' => false, 'status' => 'no_mx_a_aaaa'];
                }
            }

            $errorMessage = strtolower((string)($lastError['message'] ?? ''));
            if ($errorMessage !== '' && $this->looksLikeTransientDnsError($errorMessage)) {
                return ['valid' => null, 'status' => 'dns_temporarily_unavailable'];
            }
        }

        if (function_exists('checkdnsrr')) {
            if (@checkdnsrr($domain, 'MX')) {
                return ['valid' => true, 'status' => 'mx'];
            }
            if (@checkdnsrr($domain, 'A') || @checkdnsrr($domain, 'AAAA')) {
                return ['valid' => true, 'status' => 'a_aaaa_fallback'];
            }

            return ['valid' => false, 'status' => 'no_mx_a_aaaa'];
        }

        return ['valid' => null, 'status' => 'dns_functions_unavailable'];
    }

    private function looksLikeTransientDnsError(string $message): bool
    {
        foreach (['temporary', 'timed out', 'timeout', 'server failure', 'server error', 'try again'] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }
}
