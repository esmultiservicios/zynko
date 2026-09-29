<?php
declare(strict_types=1);

/** ZYNKO transactional email templates. Tenant branding is supplied at runtime. */
final class EmailTemplates
{
    private const NAVY='#0B1625';
    private const TEAL='#13A88A';
    private const TEXT='#1B2A3A';
    private const MUTED='#6F7F91';
    private const BORDER='#E3EAF0';
    private const BG='#F4F7FA';
    private const SOFT='#ECF8F5';

    private static function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
    private static function setting(array $s,string $key,string $fallback=''): string { $v=trim((string)($s[$key]??'')); return $v!==''?$v:$fallback; }

    private static function shell(string $eyebrow,string $title,string $content,array $settings,string $badge='NOTIFICACIÓN'): string
    {
        $company=self::setting($settings,'company_name','Tu empresa');
        $app=self::setting($settings,'app_title','ZYNKO');
        $logo=self::setting($settings,'logo_url','');
        $support=self::setting($settings,'support_email','');
        $url=self::setting($settings,'app_url','');
        $year=date('Y');
        $logoHtml=$logo!==''?'<img src="'.self::e($logo).'" alt="'.self::e($company).'" style="display:block;max-width:150px;max-height:46px;width:auto;height:auto;border:0">':'<div style="width:44px;height:44px;line-height:44px;text-align:center;border-radius:13px;background:'.self::TEAL.';color:#fff;font-weight:900;font-size:15px">ZY</div>';
        $cta='';
        if($url!==''){
            $host=(string)(parse_url($url,PHP_URL_HOST)??'');
            $isLocal=$host===''||$host==='localhost'||$host==='127.0.0.1'||$host==='::1'||str_ends_with(strtolower($host),'.test');
            if(!$isLocal){
                // Table-based CTA is intentionally used for Outlook/Microsoft 365 compatibility.
                $cta='<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin-top:24px"><tr><td bgcolor="'.self::TEAL.'" style="border-radius:10px"><a href="'.self::e($url).'" target="_blank" style="display:inline-block;padding:13px 20px;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:18px;font-weight:800;color:#ffffff;text-decoration:none;border-radius:10px">Abrir '.self::e($app).'</a></td></tr></table>';
            }
        }
        $supportHtml=$support!==''?'<div style="margin-top:5px">Soporte: <a href="mailto:'.self::e($support).'" style="color:'.self::TEAL.';text-decoration:none">'.self::e($support).'</a></div>':'';
        return '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light only"><title>'.self::e($title).'</title><style>@media only screen and (max-width:640px){.z-wrap{width:100%!important}.z-pad{padding-left:22px!important;padding-right:22px!important}.z-title{font-size:27px!important}.z-meta{display:block!important;text-align:left!important;padding-top:14px!important}}</style></head><body style="margin:0;padding:0;background:'.self::BG.';font-family:Arial,Helvetica,sans-serif;color:'.self::TEXT.'"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:'.self::BG.'"><tr><td align="center" style="padding:34px 14px"><table role="presentation" class="z-wrap" width="640" cellspacing="0" cellpadding="0" border="0" style="width:640px;max-width:640px;background:#fff;border:1px solid '.self::BORDER.';border-radius:18px;border-collapse:separate;overflow:hidden;box-shadow:0 12px 34px rgba(11,22,37,.07)"><tr><td class="z-pad" style="padding:25px 34px;border-top:4px solid '.self::TEAL.';border-bottom:1px solid '.self::BORDER.'"><table role="presentation" width="100%"><tr><td>'.$logoHtml.'</td><td align="right"><div style="font-size:16px;font-weight:900;color:'.self::NAVY.'">'.self::e($company).'</div><div style="margin-top:4px;font-size:12px;color:'.self::MUTED.'">Comunicaciones mediante '.self::e($app).'</div></td></tr></table></td></tr><tr><td class="z-pad" style="padding:34px 34px 10px"><table role="presentation" width="100%"><tr><td><div style="font-size:11px;font-weight:900;letter-spacing:1.5px;color:'.self::TEAL.';text-transform:uppercase">'.self::e($eyebrow).'</div><h1 class="z-title" style="margin:10px 0 0;font-size:32px;line-height:1.18;color:'.self::NAVY.';letter-spacing:-.5px">'.self::e($title).'</h1></td><td class="z-meta" align="right" valign="top"><span style="display:inline-block;padding:7px 10px;border-radius:999px;background:'.self::SOFT.';border:1px solid #D4EEE8;color:#08705C;font-size:10px;font-weight:900;letter-spacing:.7px">'.self::e($badge).'</span></td></tr></table></td></tr><tr><td class="z-pad" style="padding:14px 34px 36px;font-size:15px;line-height:1.7">'.$content.$cta.'</td></tr><tr><td class="z-pad" style="padding:22px 34px 26px;background:#F9FBFC;border-top:1px solid '.self::BORDER.'"><div style="font-size:12px;line-height:1.7;color:'.self::MUTED.'"><strong style="color:'.self::NAVY.'">'.self::e($company).'</strong>'.$supportHtml.'<div style="margin-top:12px">Este mensaje fue generado automáticamente por '.self::e($app).'.</div><div style="margin-top:3px">© '.$year.' '.self::e($company).'.</div></div></td></tr></table></td></tr></table></body></html>';
    }

    public static function test(array $settings): string { return self::shell('CONFIGURACIÓN DE CORREO','Prueba enviada correctamente','<p style="margin:0">Tu configuración de correo está funcionando. ZYNKO ya puede entregar notificaciones transaccionales utilizando el proveedor configurado.</p><div style="margin-top:20px;padding:16px 18px;background:'.self::SOFT.';border:1px solid #D4EEE8;border-left:4px solid '.self::TEAL.';border-radius:11px"><strong style="color:'.self::NAVY.'">Conexión verificada</strong><div style="margin-top:5px;color:'.self::MUTED.';font-size:13px">No necesitas realizar ninguna acción adicional.</div></div>',$settings,'VERIFICADO'); }
    public static function companyCreated(array $company,array $settings): string { $name=self::e($company['name']??'Nueva empresa'); return self::shell('EMPRESAS','Empresa creada en ZYNKO','<p style="margin:0">La empresa <strong>'.$name.'</strong> fue creada correctamente y ya está disponible en la plataforma.</p>',$settings,'EMPRESA'); }
    public static function security(string $message,array $settings): string { return self::shell('SEGURIDAD','Actividad de seguridad','<p style="margin:0">'.nl2br(self::e($message)).'</p>',$settings,'SEGURIDAD'); }
    public static function billing(string $title,string $message,array $settings): string { return self::shell('FACTURACIÓN',$title,'<p style="margin:0">'.nl2br(self::e($message)).'</p>',$settings,'FACTURACIÓN'); }
    public static function generic(string $eyebrow,string $title,string $message,array $settings,string $badge='NOTIFICACIÓN'): string { return self::shell($eyebrow,$title,'<p style="margin:0">'.nl2br(self::e($message)).'</p>',$settings,$badge); }
}
