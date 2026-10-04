<?php

declare(strict_types=1);

require_once __DIR__ . '/EmailTemplates.php';
require_once __DIR__ . '/EmailValidator.php';

final class NotificationService
{
    private EmailValidator $emailValidator;

    public function __construct(
        private PDO $pdo,
        private string $root
    ) {
        $this->emailValidator = new EmailValidator();
    }

    private function dec(?string $value): string
    {
        if (!$value) {
            return '';
        }

        if (!str_starts_with($value, 'enc:v1:')) {
            return $value;
        }

        $env = @parse_ini_file($this->root . '/.env', false, INI_SCANNER_RAW) ?: [];
        $hex = $env['APP_KEY'] ?? '';
        if (!preg_match('/^[a-f0-9]{64}$/i', $hex)) {
            return '';
        }

        $raw = base64_decode(substr($value, 7), true);
        if ($raw === false || strlen($raw) < 29) {
            return '';
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ciphertext = substr($raw, 28);
        $plain = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            hex2bin($hex),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return $plain === false ? '' : (string)$plain;
    }

    private function cfg(int $tenantId): ?array
    {
        $query = $this->pdo->prepare(
            'SELECT * FROM correo WHERE tenant_id=? AND is_default=1 AND estado=1 ORDER BY correo_id DESC LIMIT 1'
        );
        $query->execute([$tenantId]);

        return $query->fetch() ?: null;
    }

    private function setting(int $tenantId): array
    {
        $defaults = [
            'login_enabled' => 1,
            'message_enabled' => 1,
            'handoff_enabled' => 1,
            'critical_enabled' => 1,
            'cooldown_minutes' => 10,
        ];

        try {
            $query = $this->pdo->prepare(
                'SELECT * FROM notification_runtime_settings WHERE tenant_id=?'
            );
            $query->execute([$tenantId]);

            return $query->fetch() ?: $defaults;
        } catch (Throwable $e) {
            return $defaults;
        }
    }

    private function publicBase(): string
    {
        $env = @parse_ini_file($this->root . '/.env', false, INI_SCANNER_RAW) ?: [];
        $base = trim((string)($env['APP_URL'] ?? ''));

        if ($base === '') {
            $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
                || (string)($_SERVER['SERVER_PORT'] ?? '') === '443';
            $base = ($https ? 'https' : 'http') . '://' . (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        }

        $base = rtrim($base, '/');
        if (str_ends_with(strtolower($base), '/public')) {
            $base = substr($base, 0, -7);
        }

        return rtrim($base, '/');
    }

    public function send(
        int $tenantId,
        string $event,
        string $to,
        string $subject,
        string $message,
        array $meta = []
    ): array {
        $settings = $this->setting($tenantId);
        $map = [
            'login' => 'login_enabled',
            'message' => 'message_enabled',
            'handoff' => 'handoff_enabled',
            'critical' => 'critical_enabled',
        ];
        $column = $map[$event] ?? 'critical_enabled';

        if (empty($settings[$column])) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Notificación desactivada',
            ];
        }

        $cooldown = max(0, (int)($settings['cooldown_minutes'] ?? 10));
        $dedupeKey = (string)($meta['dedupe_key'] ?? $event);

        if ($event === 'message' && $cooldown > 0) {
            $query = $this->pdo->prepare(
                "SELECT id
                 FROM notification_log
                 WHERE tenant_id=?
                   AND channel='email'
                   AND status='sent'
                   AND JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.dedupe_key'))=?
                   AND created_at>=DATE_SUB(NOW(),INTERVAL ? MINUTE)
                 LIMIT 1"
            );
            $query->execute([$tenantId, $dedupeKey, $cooldown]);

            if ($query->fetch()) {
                return [
                    'success' => false,
                    'skipped' => true,
                    'message' => 'Agrupado para evitar spam',
                ];
            }
        }

        $config = $this->cfg($tenantId);
        if (!$config) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Correo no configurado',
            ];
        }

        $query = $this->pdo->prepare(
            'SELECT t.name,b.app_title,b.logo_path
             FROM tenants t
             LEFT JOIN branding_settings b ON b.tenant_id=t.id
             WHERE t.id=?'
        );
        $query->execute([$tenantId]);
        $branding = $query->fetch() ?: [];
        $base = $this->publicBase();

        $html = EmailTemplates::generic(
            strtoupper($event === 'message' ? 'NUEVO MENSAJE' : ($event === 'login' ? 'SEGURIDAD' : 'NIVO')),
            $subject,
            $message,
            [
                'company_name' => $branding['name'] ?? 'Tu empresa',
                'app_title' => $branding['app_title'] ?? 'ZYNKO',
                'logo_url' => !empty($branding['logo_path'])
                    ? $base . '/' . ltrim((string)$branding['logo_path'], '/')
                    : '',
                'app_url' => $base . '/?page=' . ($event === 'message' || $event === 'handoff' ? 'inbox' : 'dashboard'),
            ],
            strtoupper($event)
        );

        $result = $this->deliver($config, $to, $subject, $html);
        $typeCode = $event === 'login'
            ? 'security'
            : ($event === 'message'
                ? 'conversation_alerts'
                : ($event === 'handoff' ? 'nivo_ai' : 'system_alerts'));
        $type = $this->pdo->query(
            "SELECT correo_tipo_id FROM correo_tipo WHERE codigo='" . $typeCode . "' LIMIT 1"
        )->fetchColumn() ?: null;

        $logMeta = $this->deliveryMeta(
            array_merge($meta, [
                'event' => $event,
                'dedupe_key' => $dedupeKey,
            ]),
            $result
        );

        $this->pdo->prepare(
            'INSERT INTO notification_log(
                tenant_id,correo_tipo_id,channel,recipient,subject,status,provider,error_message,metadata_json,sent_at
             ) VALUES(?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $tenantId,
            $type,
            'email',
            $to,
            $subject,
            !empty($result['success']) ? 'sent' : 'failed',
            $config['metodo_envio'] ?? 'SYSTEM',
            !empty($result['success']) ? null : ($result['message'] ?? 'Error'),
            json_encode($logMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            !empty($result['success']) ? date('Y-m-d H:i:s') : null,
        ]);

        return $result;
    }

    public function sendAccountCreated(int $tenantId, string $to): array
    {
        $config = $this->cfg($tenantId);
        if (!$config) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Correo no configurado',
            ];
        }

        $query = $this->pdo->prepare(
            'SELECT t.name,b.app_title,b.logo_path
             FROM tenants t
             LEFT JOIN branding_settings b ON b.tenant_id=t.id
             WHERE t.id=?'
        );
        $query->execute([$tenantId]);
        $branding = $query->fetch() ?: [];
        $base = $this->publicBase();
        $settings = [
            'company_name' => $branding['name'] ?? 'Tu empresa',
            'app_title' => $branding['app_title'] ?? 'ZYNKO',
            'logo_url' => !empty($branding['logo_path'])
                ? $base . '/' . ltrim((string)$branding['logo_path'], '/')
                : '',
            'app_url' => $base . '/',
        ];
        $subject = 'Tu cuenta de ZYNKO fue creada';
        $html = EmailTemplates::accountCreated($settings);
        $result = $this->deliver($config, $to, $subject, $html);

        $this->logSimple(
            $tenantId,
            $to,
            $subject,
            $config,
            $result,
            ['event' => 'account_created']
        );

        return $result;
    }

    public function sendRegistrationCode(
        int $platformTenantId,
        string $to,
        string $code,
        string $company
    ): array {
        $config = $this->cfg($platformTenantId);
        if (!$config) {
            return [
                'success' => false,
                'message' => 'El correo principal de ZYNKO no está configurado.',
            ];
        }

        $query = $this->pdo->prepare(
            'SELECT t.name,b.app_title,b.logo_path
             FROM tenants t
             LEFT JOIN branding_settings b ON b.tenant_id=t.id
             WHERE t.id=?'
        );
        $query->execute([$platformTenantId]);
        $branding = $query->fetch() ?: [];
        $base = $this->publicBase();
        $settings = [
            'company_name' => $branding['name'] ?? 'ZYNKO',
            'app_title' => $branding['app_title'] ?? 'ZYNKO',
            'logo_url' => !empty($branding['logo_path'])
                ? $base . '/' . ltrim((string)$branding['logo_path'], '/')
                : '',
            'app_url' => $base . '/',
        ];
        $subject = 'ZYNKO · Código de verificación';
        $html = EmailTemplates::verificationCode($code, $company, $settings);
        $result = $this->deliver($config, $to, $subject, $html);

        $this->logDirect(
            $platformTenantId,
            $to,
            $subject,
            $config,
            $result,
            [
                'event' => 'registration_verification',
                'company' => $company,
            ]
        );

        return $result;
    }

    public function sendFreeAccountWelcome(int $platformTenantId, string $to, array $account): array
    {
        $config = $this->cfg($platformTenantId);
        if (!$config) {
            return [
                'success' => false,
                'message' => 'El correo principal de ZYNKO no está configurado.',
            ];
        }

        $subject = 'Bienvenido a ZYNKO · Plan Gratis activado';
        $html = EmailTemplates::freeAccountWelcome(
            $account,
            $this->platformSettings($platformTenantId, '/')
        );
        $result = $this->deliver($config, $to, $subject, $html);

        $this->logDirect(
            $platformTenantId,
            $to,
            $subject,
            $config,
            $result,
            [
                'event' => 'free_account_welcome',
                'tenant_id' => $account['tenant_id'] ?? null,
            ]
        );

        return $result;
    }

    public function sendNewCustomerAdmin(int $platformTenantId, string $to, array $customer): array
    {
        $config = $this->cfg($platformTenantId);
        if (!$config) {
            return [
                'success' => false,
                'message' => 'El correo principal de ZYNKO no está configurado.',
            ];
        }

        $subject = 'ZYNKO · Nuevo cliente registrado · ' . ($customer['company_name'] ?? 'Nueva empresa');
        $html = EmailTemplates::newCustomerAdmin(
            $customer,
            $this->platformSettings($platformTenantId, '/?page=billing')
        );
        $result = $this->deliver($config, $to, $subject, $html);

        $this->logDirect(
            $platformTenantId,
            $to,
            $subject,
            $config,
            $result,
            [
                'event' => 'new_customer',
                'tenant_id' => $customer['tenant_id'] ?? null,
                'customer_email' => $customer['email'] ?? '',
            ]
        );

        return $result;
    }

    public function sendPasswordReset(int $tenantId, string $to, string $url): array
    {
        $config = $this->cfg($tenantId);
        if (!$config) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Correo no configurado',
            ];
        }

        $query = $this->pdo->prepare('SELECT name FROM tenants WHERE id=?');
        $query->execute([$tenantId]);
        $company = (string)($query->fetchColumn() ?: 'Tu empresa');
        $subject = 'ZYNKO - Restablecer contraseña';
        $html = EmailTemplates::generic(
            'SEGURIDAD',
            'Restablecer contraseña',
            'Recibimos una solicitud para restablecer tu contraseña. Abre este enlace (válido por 30 minutos): '
                . $url
                . "\n\nSi no solicitaste este cambio, ignora este mensaje.",
            [
                'company_name' => $company,
                'app_title' => 'ZYNKO',
                'app_url' => $url,
            ],
            'SEGURIDAD'
        );
        $result = $this->deliver($config, $to, $subject, $html);

        $this->logDirect(
            $tenantId,
            $to,
            $subject,
            $config,
            $result,
            ['event' => 'password_reset']
        );

        return $result;
    }

    private function platformSettings(int $platformTenantId, string $path = '/?page=billing'): array
    {
        $query = $this->pdo->prepare(
            'SELECT t.name,b.app_title,b.logo_path
             FROM tenants t
             LEFT JOIN branding_settings b ON b.tenant_id=t.id
             WHERE t.id=?'
        );
        $query->execute([$platformTenantId]);
        $branding = $query->fetch() ?: [];
        $base = $this->publicBase();

        return [
            'company_name' => $branding['name'] ?? 'ZYNKO',
            'app_title' => $branding['app_title'] ?? 'ZYNKO',
            'logo_url' => !empty($branding['logo_path'])
                ? $base . '/' . ltrim((string)$branding['logo_path'], '/')
                : '',
            'app_url' => $base . $path,
        ];
    }

    private function directPlatformMail(
        int $platformTenantId,
        string $to,
        string $subject,
        string $html,
        array $meta = []
    ): array {
        $config = $this->cfg($platformTenantId);
        if (!$config) {
            return [
                'success' => false,
                'message' => 'El correo principal de ZYNKO no está configurado.',
            ];
        }

        $result = $this->deliver($config, $to, $subject, $html);
        $this->logDirect($platformTenantId, $to, $subject, $config, $result, $meta);

        return $result;
    }

    public function sendPlanCatalogAdmin(
        int $platformTenantId,
        string $to,
        string $action,
        array $plan
    ): array {
        $verb = [
            'created' => 'creado',
            'updated' => 'actualizado',
            'deleted' => 'eliminado',
        ][$action] ?? 'actualizado';
        $subject = 'ZYNKO · Plan ' . $verb . ' · ' . ($plan['name'] ?? 'Plan');
        $html = EmailTemplates::planCatalogEvent(
            $action,
            $plan,
            $this->platformSettings($platformTenantId)
        );

        return $this->directPlatformMail(
            $platformTenantId,
            $to,
            $subject,
            $html,
            [
                'event' => 'plan_' . $action,
                'plan_id' => $plan['id'] ?? null,
                'plan_name' => $plan['name'] ?? '',
            ]
        );
    }

    public function sendPlanRequestCustomer(int $platformTenantId, string $to, array $data): array
    {
        $subject = 'ZYNKO · Solicitud de plan recibida · ' . ($data['plan_name'] ?? 'Plan');
        $html = EmailTemplates::planRequestCustomer(
            $data,
            $this->platformSettings($platformTenantId, '/?page=billing')
        );

        return $this->directPlatformMail(
            $platformTenantId,
            $to,
            $subject,
            $html,
            [
                'event' => 'plan_request_customer',
                'tenant_id' => $data['tenant_id'] ?? null,
                'plan_id' => $data['plan_id'] ?? null,
                'request_id' => $data['request_id'] ?? null,
            ]
        );
    }

    public function sendPlanRequestAdmin(int $platformTenantId, string $to, array $data): array
    {
        $subject = 'ZYNKO · Nueva solicitud de plan · ' . ($data['company_name'] ?? 'Empresa');
        $html = EmailTemplates::planRequestAdmin(
            $data,
            $this->platformSettings($platformTenantId, '/?page=billing')
        );

        return $this->directPlatformMail(
            $platformTenantId,
            $to,
            $subject,
            $html,
            [
                'event' => 'plan_request_admin',
                'tenant_id' => $data['tenant_id'] ?? null,
                'plan_id' => $data['plan_id'] ?? null,
                'request_id' => $data['request_id'] ?? null,
            ]
        );
    }

    public function sendSubscriptionCustomer(int $platformTenantId, string $to, array $data): array
    {
        $subject = 'ZYNKO · Tu suscripción fue actualizada · ' . ($data['plan_name'] ?? 'Plan');
        $html = EmailTemplates::subscriptionChangedCustomer(
            $data,
            $this->platformSettings($platformTenantId, '/?page=billing')
        );

        return $this->directPlatformMail(
            $platformTenantId,
            $to,
            $subject,
            $html,
            [
                'event' => 'subscription_customer',
                'tenant_id' => $data['tenant_id'] ?? null,
                'plan_id' => $data['plan_id'] ?? null,
                'status' => $data['status'] ?? '',
            ]
        );
    }

    public function sendSubscriptionAdmin(int $platformTenantId, string $to, array $data): array
    {
        $subject = 'ZYNKO · Suscripción actualizada · ' . ($data['company_name'] ?? 'Empresa');
        $html = EmailTemplates::subscriptionChangedAdmin(
            $data,
            $this->platformSettings($platformTenantId, '/?page=billing')
        );

        return $this->directPlatformMail(
            $platformTenantId,
            $to,
            $subject,
            $html,
            [
                'event' => 'subscription_admin',
                'tenant_id' => $data['tenant_id'] ?? null,
                'plan_id' => $data['plan_id'] ?? null,
                'status' => $data['status'] ?? '',
            ]
        );
    }

    public function sendPlanRequestResolved(int $platformTenantId, string $to, array $data): array
    {
        $approved = ($data['resolution'] ?? '') === 'approved';
        $subject = 'ZYNKO · Solicitud de plan '
            . ($approved ? 'aprobada' : 'actualizada')
            . ' · '
            . ($data['plan_name'] ?? 'Plan');
        $html = EmailTemplates::planRequestResolved(
            $data,
            $this->platformSettings($platformTenantId, '/?page=billing')
        );

        return $this->directPlatformMail(
            $platformTenantId,
            $to,
            $subject,
            $html,
            [
                'event' => 'plan_request_' . ($data['resolution'] ?? 'updated'),
                'tenant_id' => $data['tenant_id'] ?? null,
                'plan_id' => $data['plan_id'] ?? null,
                'request_id' => $data['request_id'] ?? null,
            ]
        );
    }

    public function sendUserLifecycle(
        int $platformTenantId,
        string $to,
        string $title,
        string $message,
        array $meta = []
    ): array {
        $html = EmailTemplates::userLifecycle(
            $title,
            $message,
            $this->platformSettings($platformTenantId, '/?page=users')
        );

        return $this->directPlatformMail(
            $platformTenantId,
            $to,
            'ZYNKO · ' . $title,
            $html,
            array_merge(['event' => 'user_lifecycle'], $meta)
        );
    }

    public function sendChannelLifecycle(
        int $platformTenantId,
        string $to,
        string $title,
        string $message,
        array $meta = []
    ): array {
        $html = EmailTemplates::channelLifecycle(
            $title,
            $message,
            $this->platformSettings($platformTenantId, '/?page=channels')
        );

        return $this->directPlatformMail(
            $platformTenantId,
            $to,
            'ZYNKO · ' . $title,
            $html,
            array_merge(['event' => 'channel_lifecycle'], $meta)
        );
    }

    public function sendAdministrativeEvent(
        int $platformTenantId,
        string $to,
        string $title,
        string $message,
        array $meta = []
    ): array {
        $page = preg_replace('/[^a-z0-9_-]/i', '', (string)($meta['page'] ?? 'dashboard')) ?: 'dashboard';
        $settings = $this->platformSettings($platformTenantId, '/?page=' . $page);
        $html = EmailTemplates::generic(
            'ACTIVIDAD DE CUENTA',
            $title,
            $message,
            $settings,
            'ACTUALIZACIÓN'
        );

        return $this->directPlatformMail(
            $platformTenantId,
            $to,
            'ZYNKO · ' . $title,
            $html,
            array_merge(['event' => 'admin_action'], $meta)
        );
    }

    public function sendPublicContactAdmin(int $platformTenantId, string $to, array $data): array
    {
        $subject = 'ZYNKO · Nueva consulta web · ' . ($data['subject_label'] ?? 'Contacto');
        $html = EmailTemplates::publicContactAdmin(
            $data,
            $this->platformSettings($platformTenantId, '/#contacto')
        );

        return $this->directPlatformMail(
            $platformTenantId,
            $to,
            $subject,
            $html,
            [
                'event' => 'public_contact_admin',
                'inquiry_id' => $data['inquiry_id'] ?? null,
                'contact_email' => $data['email'] ?? '',
                'source' => $data['source'] ?? '',
            ]
        );
    }

    public function sendPublicContactConfirmation(int $platformTenantId, string $to, array $data): array
    {
        $subject = 'ZYNKO · Recibimos tu consulta';
        $html = EmailTemplates::publicContactConfirmation(
            $data,
            $this->platformSettings($platformTenantId, '/')
        );

        return $this->directPlatformMail(
            $platformTenantId,
            $to,
            $subject,
            $html,
            [
                'event' => 'public_contact_confirmation',
                'inquiry_id' => $data['inquiry_id'] ?? null,
                'source' => $data['source'] ?? '',
            ]
        );
    }

    public function sendTest(int $tenantId, string $to): array
    {
        $config = $this->cfg($tenantId);
        if (!$config) {
            return [
                'success' => false,
                'message' => 'Primero guarda y activa un proveedor de correo.',
            ];
        }

        $query = $this->pdo->prepare(
            'SELECT t.name,b.app_title,b.logo_path
             FROM tenants t
             LEFT JOIN branding_settings b ON b.tenant_id=t.id
             WHERE t.id=?'
        );
        $query->execute([$tenantId]);
        $branding = $query->fetch() ?: [];
        $base = $this->publicBase();
        $settings = [
            'company_name' => $branding['name'] ?? 'Tu empresa',
            'app_title' => $branding['app_title'] ?? 'ZYNKO',
            'logo_url' => !empty($branding['logo_path'])
                ? $base . '/' . ltrim((string)$branding['logo_path'], '/')
                : '',
            'app_url' => $base . '/',
        ];
        $subject = 'ZYNKO · Prueba de correo';
        $html = EmailTemplates::test($settings);
        $result = $this->deliver($config, $to, $subject, $html);

        $this->logSimple(
            $tenantId,
            $to,
            $subject,
            $config,
            $result,
            ['event' => 'test']
        );

        return $result;
    }

    private function deliver(array $config, string|array $to, string $subject, string $html): array
    {
        $validated = $this->emailValidator->validateRecipients($to);
        $validRecipients = $validated['valid'];
        $rejectedRecipients = $validated['rejected'];

        if ($validRecipients === []) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => $this->validationFailureMessage($rejectedRecipients),
                'sent_recipients' => [],
                'rejected_recipients' => $rejectedRecipients,
            ];
        }

        $result = strtoupper((string)($config['metodo_envio'] ?? '')) === 'GRAPH'
            ? $this->graph($config, $validRecipients, $subject, $html)
            : $this->smtp($config, $validRecipients, $subject, $html);

        $result['rejected_recipients'] = array_merge(
            $rejectedRecipients,
            $result['rejected_recipients'] ?? []
        );
        $result['sent_recipients'] = $result['sent_recipients'] ?? [];
        $result['partial'] = !empty($result['success']) && !empty($result['rejected_recipients']);

        if (!empty($result['success']) && !empty($result['rejected_recipients'])) {
            $result['message'] = 'Enviado a destinatarios válidos; algunos destinatarios fueron rechazados.';
        }

        return $result;
    }

    private function graph(array $config, array $recipients, string $subject, string $html): array
    {
        $tenant = trim((string)($config['tenant_graph_id'] ?? ''));
        $client = trim((string)($config['client_id'] ?? ''));
        $secret = $this->dec($config['client_secret'] ?? '');
        $from = trim((string)($config['graph_user'] ?? ''));

        if (!$tenant || !$client || !$secret || !$from || !function_exists('curl_init')) {
            return [
                'success' => false,
                'message' => 'Microsoft Graph incompleto',
                'sent_recipients' => [],
                'rejected_recipients' => [],
            ];
        }

        $ch = curl_init(
            'https://login.microsoftonline.com/' . rawurlencode($tenant) . '/oauth2/v2.0/token'
        );
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_POSTFIELDS => http_build_query([
                'client_id' => $client,
                'client_secret' => $secret,
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials',
            ]),
        ]);
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $json = json_decode((string)$raw, true);
        if ($code < 200 || $code >= 300 || empty($json['access_token'])) {
            return [
                'success' => false,
                'message' => 'No se pudo autenticar Microsoft Graph',
                'sent_recipients' => [],
                'rejected_recipients' => [],
            ];
        }

        $toRecipients = [];
        foreach ($recipients as $recipient) {
            $toRecipients[] = [
                'emailAddress' => [
                    'address' => $recipient,
                ],
            ];
        }

        $payload = [
            'message' => [
                'subject' => $subject,
                'body' => [
                    'contentType' => 'HTML',
                    'content' => $html,
                ],
                'toRecipients' => $toRecipients,
            ],
            'saveToSentItems' => (bool)($config['save_to_sent_items'] ?? 1),
        ];

        $ch = curl_init(
            'https://graph.microsoft.com/v1.0/users/' . rawurlencode($from) . '/sendMail'
        );
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $json['access_token'],
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
        ]);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 202) {
            return [
                'success' => true,
                'message' => 'Enviado',
                'sent_recipients' => $recipients,
                'rejected_recipients' => [],
            ];
        }

        return [
            'success' => false,
            'message' => 'Error Graph HTTP ' . $code,
            'sent_recipients' => [],
            'rejected_recipients' => [],
        ];
    }

    private function smtp(array $config, array $recipients, string $subject, string $html): array
    {
        $host = trim((string)($config['server'] ?? ''));
        $port = (int)($config['port'] ?? 587);
        $secure = strtolower((string)($config['smtp_secure'] ?? 'tls'));
        $user = trim((string)($config['correo'] ?? ''));
        $password = $this->dec($config['password'] ?? '');

        if (!$host || !$user || !$password) {
            return [
                'success' => false,
                'message' => 'SMTP incompleto',
                'sent_recipients' => [],
                'rejected_recipients' => [],
            ];
        }

        $fp = @stream_socket_client(
            ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port,
            $errorNumber,
            $errorMessage,
            12
        );

        if (!$fp) {
            return [
                'success' => false,
                'message' => 'No se pudo conectar SMTP',
                'sent_recipients' => [],
                'rejected_recipients' => [],
            ];
        }

        stream_set_timeout($fp, 15);

        $read = static function () use ($fp): array {
            $raw = '';
            while (($line = fgets($fp, 515)) !== false) {
                $raw .= $line;
                if (strlen($line) < 4 || $line[3] !== '-') {
                    break;
                }
            }

            return [
                'code' => (int)substr($raw, 0, 3),
                'raw' => trim($raw),
            ];
        };

        $command = static function (string $value, int|array $accepted) use ($fp, $read): array {
            fwrite($fp, $value . "\r\n");
            $response = $read();

            if (!in_array($response['code'], (array)$accepted, true)) {
                throw new RuntimeException('SMTP ' . $response['code']);
            }

            return $response;
        };

        try {
            $read();
            $command('EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), 250);

            if ($secure === 'tls') {
                $command('STARTTLS', 220);
                $cryptoEnabled = stream_socket_enable_crypto(
                    $fp,
                    true,
                    STREAM_CRYPTO_METHOD_TLS_CLIENT
                );
                if ($cryptoEnabled !== true) {
                    throw new RuntimeException('No se pudo iniciar TLS en SMTP');
                }
                $command('EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), 250);
            }

            $command('AUTH LOGIN', 334);
            $command(base64_encode($user), 334);
            $command(base64_encode($password), 235);
            $command('MAIL FROM:<' . $user . '>', 250);

            $acceptedRecipients = [];
            $smtpRejected = [];

            foreach ($recipients as $recipient) {
                fwrite($fp, 'RCPT TO:<' . $recipient . ">\r\n");
                $response = $read();

                if (in_array($response['code'], [250, 251], true)) {
                    $acceptedRecipients[] = $recipient;
                    continue;
                }

                $smtpRejected[] = [
                    'valid' => false,
                    'email' => $recipient,
                    'original' => $recipient,
                    'domain' => strtolower((string)substr(strrchr($recipient, '@') ?: '', 1)),
                    'code' => 'smtp_recipient_rejected',
                    'reason' => 'El servidor SMTP rechazó el destinatario con código ' . $response['code'] . '.',
                    'suggestion' => null,
                    'dns' => 'validated_before_smtp',
                    'smtp_code' => $response['code'],
                ];
            }

            if ($acceptedRecipients === []) {
                @fwrite($fp, "QUIT\r\n");
                fclose($fp);

                return [
                    'success' => false,
                    'message' => 'El servidor SMTP rechazó todos los destinatarios.',
                    'sent_recipients' => [],
                    'rejected_recipients' => $smtpRejected,
                ];
            }

            $command('DATA', 354);
            $headers = [
                'From: ZYNKO <' . $user . '>',
                'To: ' . implode(', ', array_map(
                    static fn(string $recipient): string => '<' . $recipient . '>',
                    $acceptedRecipients
                )),
                'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
            ];

            fwrite(
                $fp,
                implode("\r\n", $headers)
                    . "\r\n\r\n"
                    . chunk_split(base64_encode($html))
                    . "\r\n.\r\n"
            );
            $dataResponse = $read();
            if ($dataResponse['code'] < 200 || $dataResponse['code'] >= 300) {
                throw new RuntimeException('SMTP ' . $dataResponse['code']);
            }

            @fwrite($fp, "QUIT\r\n");
            fclose($fp);

            return [
                'success' => true,
                'message' => 'Enviado',
                'sent_recipients' => $acceptedRecipients,
                'rejected_recipients' => $smtpRejected,
            ];
        } catch (Throwable $e) {
            @fclose($fp);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'sent_recipients' => [],
                'rejected_recipients' => [],
            ];
        }
    }

    private function validationFailureMessage(array $rejectedRecipients): string
    {
        if ($rejectedRecipients === []) {
            return 'No hay destinatarios válidos para enviar el correo.';
        }

        $first = $rejectedRecipients[0];
        $message = (string)($first['reason'] ?? 'Destino inválido.');
        $suggestion = trim((string)($first['suggestion'] ?? ''));

        if ($suggestion !== '') {
            $message .= ' Posible corrección: ' . $suggestion . '.';
        }

        return $message;
    }

    private function deliveryMeta(array $meta, array $result): array
    {
        $meta['email_delivery'] = [
            'sent_recipients' => $result['sent_recipients'] ?? [],
            'rejected_recipients' => $result['rejected_recipients'] ?? [],
            'partial' => !empty($result['partial']),
        ];

        return $meta;
    }

    private function logDirect(
        int $tenantId,
        string $to,
        string $subject,
        array $config,
        array $result,
        array $meta = []
    ): void {
        try {
            $event = (string)($meta['event'] ?? 'system');
            $typeCode = match (true) {
                str_starts_with($event, 'registration_'),
                $event === 'user_lifecycle',
                $event === 'password_reset' => 'security',
                $event === 'email_test' => 'email_tests',
                str_starts_with($event, 'plan_'),
                str_starts_with($event, 'subscription_'),
                $event === 'free_account_welcome',
                $event === 'new_customer' => 'company_lifecycle',
                $event === 'channel_lifecycle' => 'channel_events',
                default => 'system_alerts',
            };

            $query = $this->pdo->prepare(
                'SELECT correo_tipo_id FROM correo_tipo WHERE codigo=? LIMIT 1'
            );
            $query->execute([$typeCode]);
            $type = $query->fetchColumn() ?: null;
            $logMeta = $this->deliveryMeta($meta, $result);

            $this->pdo->prepare(
                'INSERT INTO notification_log(
                    tenant_id,correo_tipo_id,channel,recipient,subject,status,provider,error_message,metadata_json,sent_at
                 ) VALUES(?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $tenantId,
                $type,
                'email',
                $to,
                $subject,
                !empty($result['success']) ? 'sent' : 'failed',
                $config['metodo_envio'] ?? 'SYSTEM',
                !empty($result['success']) ? null : ($result['message'] ?? 'Error'),
                json_encode($logMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                !empty($result['success']) ? date('Y-m-d H:i:s') : null,
            ]);
        } catch (Throwable $e) {
            // El registro nunca debe romper el flujo principal de correo.
        }
    }

    private function logSimple(
        int $tenantId,
        string $to,
        string $subject,
        array $config,
        array $result,
        array $meta
    ): void {
        try {
            $this->pdo->prepare(
                'INSERT INTO notification_log(
                    tenant_id,channel,recipient,subject,status,provider,error_message,metadata_json,sent_at
                 ) VALUES(?,?,?,?,?,?,?,?,?)'
            )->execute([
                $tenantId,
                'email',
                $to,
                $subject,
                !empty($result['success']) ? 'sent' : 'failed',
                $config['metodo_envio'] ?? 'SYSTEM',
                !empty($result['success']) ? null : ($result['message'] ?? 'Error'),
                json_encode(
                    $this->deliveryMeta($meta, $result),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
                !empty($result['success']) ? date('Y-m-d H:i:s') : null,
            ]);
        } catch (Throwable $e) {
            // El registro nunca debe romper el flujo principal de correo.
        }
    }
}
