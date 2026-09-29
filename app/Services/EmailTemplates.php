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
        $nivoAsset='';
        if($url!==''){
            // Build the public asset URL from APP_URL without query strings (e.g. ?page=dashboard).
            $u=parse_url($url);
            if(is_array($u)&&!empty($u['host'])){
                $scheme=(string)($u['scheme']??'https');
                $port=isset($u['port'])?':'.(int)$u['port']:'';
                $path=rtrim((string)($u['path']??''),'/');
                if(str_ends_with(strtolower($path),'/public'))$path=substr($path,0,-7);
                $base=$scheme.'://'.$u['host'].$port.$path;
                $nivoAsset=rtrim($base,'/').'/assets/img/nivo-email.png';
            }
        }
        $nivoVisual=$nivoAsset!==''
            ? '<img src="'.self::e($nivoAsset).'" alt="NIVO" width="96" style="display:block;width:96px;max-width:96px;height:auto;border:0;border-radius:14px">'
            : '<div style="width:54px;height:54px;line-height:54px;text-align:center;border-radius:14px;background:'.self::NAVY.';color:#fff;font-size:14px;font-weight:900">NI</div>';
        $nivoHtml='<div style="margin-top:24px;padding:16px 18px;background:#F7FBFA;border:1px solid #DCEEEA;border-radius:13px"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td width="96" valign="middle" style="width:96px">'.$nivoVisual.'</td><td valign="middle" style="padding-left:16px"><div style="font-size:14px;line-height:1.35;font-weight:900;color:'.self::NAVY.';white-space:nowrap">NIVO · Asistente inteligente de '.self::e($app).'</div><div style="margin-top:6px;font-size:12px;line-height:1.55;color:'.self::MUTED.'">Atiende por chat, apoya las funciones de IA, organiza conversaciones y facilita la transferencia a una persona cuando sea necesario.</div></td></tr></table></div>';
        return '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light only"><title>'.self::e($title).'</title><style>@media only screen and (max-width:640px){.z-wrap{width:100%!important}.z-pad{padding-left:22px!important;padding-right:22px!important}.z-title{font-size:27px!important;white-space:normal!important}.z-one-line{white-space:normal!important}.z-meta{display:block!important;text-align:left!important;padding-top:14px!important}}</style></head><body style="margin:0;padding:0;background:'.self::BG.';font-family:Arial,Helvetica,sans-serif;color:'.self::TEXT.'"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:'.self::BG.'"><tr><td align="center" style="padding:34px 14px"><table role="presentation" class="z-wrap" width="760" cellspacing="0" cellpadding="0" border="0" style="width:760px;max-width:760px;background:#fff;border:1px solid '.self::BORDER.';border-radius:18px;border-collapse:separate;overflow:hidden;box-shadow:0 12px 34px rgba(11,22,37,.07)"><tr><td class="z-pad" style="padding:25px 34px;border-top:4px solid '.self::TEAL.';border-bottom:1px solid '.self::BORDER.'"><table role="presentation" width="100%"><tr><td>'.$logoHtml.'</td><td align="right"><div style="font-size:16px;font-weight:900;color:'.self::NAVY.'">'.self::e($company).'</div><div style="margin-top:4px;font-size:12px;color:'.self::MUTED.'">Comunicaciones mediante '.self::e($app).'</div></td></tr></table></td></tr><tr><td class="z-pad" style="padding:34px 34px 10px"><table role="presentation" width="100%"><tr><td><div style="font-size:11px;font-weight:900;letter-spacing:1.5px;color:'.self::TEAL.';text-transform:uppercase">'.self::e($eyebrow).'</div><h1 class="z-title z-one-line" style="margin:10px 0 0;font-size:32px;line-height:1.18;white-space:nowrap;color:'.self::NAVY.';letter-spacing:-.5px">'.self::e($title).'</h1></td><td class="z-meta" align="right" valign="top"><span class="z-one-line" style="display:inline-block;padding:7px 14px;border-radius:999px;background:'.self::SOFT.';border:1px solid #D4EEE8;color:#08705C;font-size:10px;font-weight:900;letter-spacing:.7px;white-space:nowrap">'.self::e($badge).'</span></td></tr></table></td></tr><tr><td class="z-pad" style="padding:14px 34px 36px;font-size:15px;line-height:1.7">'.$content.$nivoHtml.$cta.'</td></tr><tr><td class="z-pad" style="padding:22px 34px 26px;background:#F9FBFC;border-top:1px solid '.self::BORDER.'"><div style="font-size:12px;line-height:1.7;color:'.self::MUTED.'"><strong style="color:'.self::NAVY.'">'.self::e($company).'</strong>'.$supportHtml.'<div style="margin-top:12px">Este mensaje fue generado automáticamente por '.self::e($app).'.</div><div style="margin-top:3px">© '.$year.' '.self::e($company).'.</div></div></td></tr></table></td></tr></table></body></html>';
    }

    public static function test(array $settings): string { return self::shell('CONFIGURACIÓN DE CORREO','Prueba enviada correctamente','<p style="margin:0">Tu configuración de correo está funcionando. ZYNKO ya puede entregar notificaciones transaccionales utilizando el proveedor configurado.</p><div style="margin-top:20px;padding:16px 18px;background:'.self::SOFT.';border:1px solid #D4EEE8;border-left:4px solid '.self::TEAL.';border-radius:11px"><strong style="color:'.self::NAVY.'">Conexión verificada</strong><div style="margin-top:5px;color:'.self::MUTED.';font-size:13px">No necesitas realizar ninguna acción adicional.</div></div>',$settings,'VERIFICADO'); }
    public static function accountCreated(array $settings): string { return self::shell('BIENVENIDO A ZYNKO','Tu cuenta de ZYNKO fue creada','<p style="margin:0">Tu cuenta principal y tu empresa fueron creadas correctamente. Ya puedes ingresar a ZYNKO con el correo registrado.</p><div style="margin-top:20px;padding:16px 18px;background:'.self::SOFT.';border:1px solid #D4EEE8;border-left:4px solid '.self::TEAL.';border-radius:11px"><strong style="color:'.self::NAVY.'">Todo listo para comenzar</strong><div style="margin-top:5px;color:'.self::MUTED.';font-size:13px">Configura tus canales, tu equipo y NIVO desde el panel de ZYNKO.</div></div>',$settings,'CUENTA CREADA'); }
    public static function companyCreated(array $company,array $settings): string { $name=self::e($company['name']??'Nueva empresa'); return self::shell('EMPRESAS','Empresa creada en ZYNKO','<p style="margin:0">La empresa <strong>'.$name.'</strong> fue creada correctamente y ya está disponible en la plataforma.</p>',$settings,'EMPRESA'); }
    public static function security(string $message,array $settings): string { return self::shell('SEGURIDAD','Actividad de seguridad','<p style="margin:0">'.nl2br(self::e($message)).'</p>',$settings,'SEGURIDAD'); }
    public static function billing(string $title,string $message,array $settings): string { return self::shell('FACTURACIÓN',$title,'<p style="margin:0">'.nl2br(self::e($message)).'</p>',$settings,'FACTURACIÓN'); }

    public static function verificationCode(string $code,string $company,array $settings): string {
        $safeCompany=self::e($company);
        $safeCode=self::e($code);
        $content='<p style="margin:0">Recibimos una solicitud para crear una cuenta de <strong>'.$safeCompany.'</strong> en ZYNKO. Usa este código para confirmar que el correo te pertenece.</p>'
            .'<div style="margin:22px 0;padding:20px;text-align:center;background:'.self::SOFT.';border:1px solid #D4EEE8;border-radius:14px">'
            .'<div style="font-size:12px;color:'.self::MUTED.';font-weight:700">CÓDIGO DE VERIFICACIÓN</div>'
            .'<div style="margin-top:8px;font-size:34px;line-height:1;font-weight:900;letter-spacing:8px;color:'.self::NAVY.'">'.$safeCode.'</div>'
            .'<div style="margin-top:10px;font-size:12px;color:'.self::MUTED.'">Válido por 10 minutos. No lo compartas con nadie.</div></div>'
            .'<p style="margin:0;color:'.self::MUTED.';font-size:13px">Si no solicitaste esta cuenta, puedes ignorar este correo.</p>';
        return self::shell('CONFIRMACIÓN DE CORREO','Verifica tu cuenta de ZYNKO',$content,$settings,'VERIFICACIÓN');
    }

    public static function freeAccountWelcome(array $account,array $settings): string {
        $company=self::e($account['company_name']??'Tu empresa');
        $content='<p style="margin:0">La cuenta de <strong>'.$company.'</strong> fue verificada y ya está activa en ZYNKO.</p>'
            .'<div style="margin-top:20px;padding:16px 18px;background:'.self::SOFT.';border:1px solid #D4EEE8;border-left:4px solid '.self::TEAL.';border-radius:11px">'
            .'<strong style="color:'.self::NAVY.'">Plan Gratis activado</strong>'
            .'<div style="margin-top:7px;color:'.self::MUTED.';font-size:13px;line-height:1.6">Incluye NIVO Web Chat en <strong>1 sitio web</strong> y hasta <strong>5 chats nuevos por día</strong>. Las conversaciones ya iniciadas pueden continuar sin límite de mensajes.</div></div>'
            .'<p style="margin:18px 0 0">Cuando necesites más canales, más sitios o funciones avanzadas de NIVO, podrás pasar a un plan superior desde ZYNKO.</p>';
        return self::shell('BIENVENIDO A ZYNKO','Tu cuenta gratis está lista',$content,$settings,'PLAN GRATIS');
    }

    public static function newCustomerAdmin(array $customer,array $settings): string {
        $company=self::e($customer['company_name']??'Nueva empresa');
        $owner=self::e($customer['owner_name']??'');
        $email=self::e($customer['email']??'');
        $phone=self::e($customer['phone']??'');
        $business=self::e($customer['business_id']??'');
        $content='<p style="margin:0">Se registró y verificó una nueva empresa en ZYNKO.</p>'
            .'<div style="margin-top:20px;padding:16px 18px;background:#F7FBFA;border:1px solid #DCEEEA;border-radius:12px">'
            .'<div style="font-size:14px;line-height:1.7"><strong>Empresa:</strong> '.$company.'<br><strong>Propietario:</strong> '.$owner.'<br><strong>Correo:</strong> '.$email
            .($phone!==''?'<br><strong>Teléfono:</strong> '.$phone:'')
            .($business!==''?'<br><strong>Identificación fiscal:</strong> '.$business:'')
            .'<br><strong>Plan:</strong> Gratis · NIVO Web Chat</div></div>';
        return self::shell('NUEVO CLIENTE','Nueva cuenta creada en ZYNKO',$content,$settings,'NUEVO CLIENTE');
    }


    public static function planCatalogEvent(string $action,array $plan,array $settings): string {
        $name=self::e($plan['name']??'Plan');
        $price=self::e(($plan['currency']??'HNL').' '.number_format((float)($plan['monthly_price']??0),2));
        $status=!empty($plan['active'])?'Activo':'Inactivo';
        $verb=['created'=>'creado','updated'=>'actualizado','deleted'=>'eliminado'][$action]??'actualizado';
        $content='<p style="margin:0">El plan <strong>'.$name.'</strong> fue '.$verb.' en ZYNKO.</p>'
            .'<div style="margin-top:20px;padding:16px 18px;background:#F7FBFA;border:1px solid #DCEEEA;border-radius:12px">'
            .'<div style="font-size:14px;line-height:1.75"><strong>Plan:</strong> '.$name.'<br><strong>Precio:</strong> '.$price.'<br><strong>Estado:</strong> '.self::e($status)
            .'<br><strong>Sitios Web Chat:</strong> '.self::e(($plan['max_webchat_sites']??null)?:'Ilimitados')
            .'<br><strong>Chats nuevos/día:</strong> '.self::e(($plan['max_daily_chats']??null)?:'Ilimitados').'</div></div>';
        return self::shell('PLANES Y SUSCRIPCIONES','Plan '.$verb,$content,$settings,'PLAN '.strtoupper($verb));
    }

    public static function planRequestCustomer(array $data,array $settings): string {
        $plan=self::e($data['plan_name']??'Plan');
        $company=self::e($data['company_name']??'Tu empresa');
        $price=self::e(($data['currency']??'HNL').' '.number_format((float)($data['monthly_price']??0),2));
        $content='<p style="margin:0">Recibimos la solicitud de <strong>'.$company.'</strong> para cambiar al plan <strong>'.$plan.'</strong>.</p>'
            .'<div style="margin-top:20px;padding:16px 18px;background:'.self::SOFT.';border:1px solid #D4EEE8;border-left:4px solid '.self::TEAL.';border-radius:11px">'
            .'<strong style="color:'.self::NAVY.'">Solicitud recibida</strong><div style="margin-top:7px;color:'.self::MUTED.';font-size:13px;line-height:1.6">Plan solicitado: <strong>'.$plan.'</strong> · '.$price.'/mes. Tu plan actual no cambia hasta que la solicitud sea aprobada.</div></div>';
        return self::shell('SOLICITUD DE PLAN','Recibimos tu solicitud de plan',$content,$settings,'SOLICITUD RECIBIDA');
    }

    public static function planRequestAdmin(array $data,array $settings): string {
        $company=self::e($data['company_name']??'Empresa');
        $plan=self::e($data['plan_name']??'Plan');
        $owner=self::e($data['owner_name']??'');
        $email=self::e($data['email']??'');
        $content='<p style="margin:0">Una empresa solicitó un cambio de plan desde ZYNKO.</p>'
            .'<div style="margin-top:20px;padding:16px 18px;background:#F7FBFA;border:1px solid #DCEEEA;border-radius:12px">'
            .'<div style="font-size:14px;line-height:1.75"><strong>Empresa:</strong> '.$company.'<br><strong>Solicitó:</strong> '.$plan
            .($owner!==''?'<br><strong>Propietario:</strong> '.$owner:'').($email!==''?'<br><strong>Correo:</strong> '.$email:'').'</div></div>';
        return self::shell('SOLICITUD COMERCIAL','Nueva solicitud de plan',$content,$settings,'REQUIERE ATENCIÓN');
    }

    public static function subscriptionChangedCustomer(array $data,array $settings): string {
        $company=self::e($data['company_name']??'Tu empresa');
        $plan=self::e($data['plan_name']??'Plan');
        $previous=self::e($data['previous_plan_name']??'');
        $status=self::e($data['status_label']??($data['status']??'Activo'));
        $content='<p style="margin:0">La suscripción de <strong>'.$company.'</strong> fue actualizada.</p>'
            .'<div style="margin-top:20px;padding:16px 18px;background:'.self::SOFT.';border:1px solid #D4EEE8;border-left:4px solid '.self::TEAL.';border-radius:11px">'
            .'<div style="font-size:14px;line-height:1.75"><strong>Plan actual:</strong> '.$plan
            .($previous!==''&&$previous!==$plan?'<br><strong>Plan anterior:</strong> '.$previous:'')
            .'<br><strong>Estado:</strong> '.$status.'</div></div>'
            .'<p style="margin:18px 0 0;color:'.self::MUTED.';font-size:13px">Los permisos, canales y límites de ZYNKO ya reflejan esta actualización.</p>';
        return self::shell('TU SUSCRIPCIÓN','Tu plan de ZYNKO fue actualizado',$content,$settings,'SUSCRIPCIÓN');
    }

    public static function subscriptionChangedAdmin(array $data,array $settings): string {
        $company=self::e($data['company_name']??'Empresa');
        $plan=self::e($data['plan_name']??'Plan');
        $previous=self::e($data['previous_plan_name']??'');
        $status=self::e($data['status_label']??($data['status']??'Activo'));
        $source=self::e($data['source_label']??'Asignación manual');
        $content='<p style="margin:0">Se actualizó una suscripción en ZYNKO.</p>'
            .'<div style="margin-top:20px;padding:16px 18px;background:#F7FBFA;border:1px solid #DCEEEA;border-radius:12px">'
            .'<div style="font-size:14px;line-height:1.75"><strong>Empresa:</strong> '.$company.'<br><strong>Plan:</strong> '.$plan
            .($previous!==''&&$previous!==$plan?'<br><strong>Anterior:</strong> '.$previous:'')
            .'<br><strong>Estado:</strong> '.$status.'<br><strong>Origen:</strong> '.$source.'</div></div>';
        return self::shell('CONTROL COMERCIAL','Suscripción actualizada',$content,$settings,'SUSCRIPCIÓN');
    }

    public static function planRequestResolved(array $data,array $settings): string {
        $approved=($data['resolution']??'')==='approved';
        $plan=self::e($data['plan_name']??'Plan');
        $note=self::e($data['note']??'');
        $content='<p style="margin:0">Tu solicitud para el plan <strong>'.$plan.'</strong> fue <strong>'.($approved?'aprobada':'rechazada').'</strong>.</p>'
            .($approved?'<div style="margin-top:20px;padding:16px 18px;background:'.self::SOFT.';border:1px solid #D4EEE8;border-left:4px solid '.self::TEAL.';border-radius:11px"><strong style="color:'.self::NAVY.'">Plan activado</strong><div style="margin-top:7px;color:'.self::MUTED.';font-size:13px">Tus permisos y límites ya fueron actualizados.</div></div>':'')
            .($note!==''?'<p style="margin:18px 0 0"><strong>Comentario:</strong> '.$note.'</p>':'');
        return self::shell('SOLICITUD DE PLAN',$approved?'Tu solicitud fue aprobada':'Actualización de tu solicitud',$content,$settings,$approved?'APROBADA':'RECHAZADA');
    }

    public static function userLifecycle(string $title,string $message,array $settings,string $badge='USUARIO'): string {
        return self::shell('SEGURIDAD Y ACCESO',$title,'<p style="margin:0">'.nl2br(self::e($message)).'</p>',$settings,$badge);
    }

    public static function channelLifecycle(string $title,string $message,array $settings): string {
        return self::shell('CANALES E INTEGRACIONES',$title,'<p style="margin:0">'.nl2br(self::e($message)).'</p>',$settings,'CANAL');
    }

    public static function publicContactAdmin(array $data,array $settings): string {
        $name=self::e($data['name']??'');
        $company=self::e($data['company']??'');
        $email=self::e($data['email']??'');
        $phone=self::e($data['phone']??'');
        $subject=self::e($data['subject_label']??($data['subject']??'Consulta general'));
        $source=self::e($data['source_label']??($data['source']??'No indicado'));
        $message=nl2br(self::e($data['message']??''));
        $content='<p style="margin:0">Se recibió una nueva consulta desde el formulario público de ZYNKO.</p>'
            .'<div style="margin-top:20px;padding:17px 18px;background:#F7FBFA;border:1px solid #DCEEEA;border-radius:12px">'
            .'<div style="font-size:14px;line-height:1.8"><strong>Nombre:</strong> '.$name
            .($company!==''?'<br><strong>Empresa:</strong> '.$company:'')
            .'<br><strong>Correo:</strong> '.$email
            .($phone!==''?'<br><strong>Teléfono:</strong> '.$phone:'')
            .'<br><strong>Consulta:</strong> '.$subject
            .'<br><strong>Cómo conoció ZYNKO:</strong> '.$source.'</div></div>'
            .'<div style="margin-top:18px;padding:17px 18px;background:'.self::SOFT.';border:1px solid #D4EEE8;border-left:4px solid '.self::TEAL.';border-radius:11px">'
            .'<strong style="color:'.self::NAVY.'">Mensaje</strong><div style="margin-top:8px;color:'.self::TEXT.';font-size:14px;line-height:1.7">'.$message.'</div></div>';
        return self::shell('CONTACTO PÚBLICO','Nueva consulta desde ZYNKO',$content,$settings,'NUEVA CONSULTA');
    }

    public static function publicContactConfirmation(array $data,array $settings): string {
        $name=self::e($data['name']??'');
        $subject=self::e($data['subject_label']??($data['subject']??'tu consulta'));
        $content='<p style="margin:0">Hola'.($name!==''?', <strong>'.$name.'</strong>':'').'. Recibimos correctamente tu consulta sobre <strong>'.$subject.'</strong>.</p>'
            .'<div style="margin-top:20px;padding:16px 18px;background:'.self::SOFT.';border:1px solid #D4EEE8;border-left:4px solid '.self::TEAL.';border-radius:11px">'
            .'<strong style="color:'.self::NAVY.'">Tu mensaje ya está en revisión</strong>'
            .'<div style="margin-top:7px;color:'.self::MUTED.';font-size:13px;line-height:1.6">El equipo de ES MULTISERVICIOS recibió la información que enviaste desde el sitio de ZYNKO. Te responderemos utilizando los datos de contacto proporcionados.</div></div>'
            .'<p style="margin:18px 0 0;color:'.self::MUTED.';font-size:13px">Este correo confirma únicamente la recepción de tu solicitud.</p>';
        return self::shell('CONFIRMACIÓN DE CONTACTO','Recibimos tu consulta',$content,$settings,'RECIBIDO');
    }

    public static function generic(string $eyebrow,string $title,string $message,array $settings,string $badge='NOTIFICACIÓN'): string { return self::shell($eyebrow,$title,'<p style="margin:0">'.nl2br(self::e($message)).'</p>',$settings,$badge); }
}
