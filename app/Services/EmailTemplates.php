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

    private static function shell(string $eyebrow,string $title,string $content,array $settings,string $badge='NOTIFICACIÓN',?string $tone=null): string
    {
        $company=self::setting($settings,'company_name','Tu empresa');
        $app=self::setting($settings,'app_title','ZYNKO');
        $logo=self::setting($settings,'logo_url','');
        $support=self::setting($settings,'support_email','');
        $url=self::setting($settings,'app_url','');
        $year=date('Y');
        // Explicit outcome takes precedence over words such as "incidente recuperado".
        // Do not classify successful recovery emails as failures.
        $tone=$tone??self::noticeTone($title.' '.$badge);
        $palettes=[
            'danger'=>['#C83535','#FFF1F0','#F4CCCA','#A92323'],
            'warning'=>['#B66B00','#FFF8E9','#F4DCA7','#865000'],
            'success'=>[self::TEAL,self::SOFT,'#D4EEE8','#08705C'],
            'info'=>['#3879BB','#EEF6FF','#CCE2FA','#245F99'],
        ];
        [$accent,$accentBg,$accentBorder,$accentText]=$palettes[$tone]??$palettes['info'];
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
        // CID attachment is included by NotificationService for both Graph and SMTP.
        // Use an external URL only if the mascot asset cannot be read server-side.
        $nivoFile=dirname(__DIR__,2).'/public/assets/img/nivo-email-mail.png';
        $nivoAsset=is_file($nivoFile)&&is_readable($nivoFile)?'cid:zynko-nivo':'';
        if($nivoAsset===''&&$url!==''){
            $u=parse_url($url);
            if(is_array($u)&&!empty($u['host'])){
                $scheme=(string)($u['scheme']??'https');
                $port=isset($u['port'])?':'.(int)$u['port']:'';
                $path=rtrim((string)($u['path']??''),'/');
                if(str_ends_with(strtolower($path),'/public'))$path=substr($path,0,-7);
                $nivoAsset=$scheme.'://'.$u['host'].$port.$path.'/assets/img/nivo-email.png';
            }
        }
        $nivoVisual=$nivoAsset!==''
            ? '<img src="'.self::e($nivoAsset).'" alt="NIVO" width="96" style="display:block;width:96px;max-width:96px;height:auto;border:0;border-radius:14px">'
            : '<div style="width:54px;height:54px;line-height:54px;text-align:center;border-radius:14px;background:'.self::NAVY.';color:#fff;font-size:14px;font-weight:900">NIVO</div>';
        $nivoHtml='<div style="margin-top:24px;padding:16px 18px;background:#F7FBFA;border:1px solid #DCEEEA;border-radius:13px"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td width="96" valign="middle" style="width:96px">'.$nivoVisual.'</td><td valign="middle" style="padding-left:16px"><div style="font-size:14px;line-height:1.35;font-weight:900;color:'.self::NAVY.';white-space:nowrap">NIVO · Asistente inteligente de '.self::e($app).'</div><div style="margin-top:6px;font-size:12px;line-height:1.55;color:'.self::MUTED.'">Atiende por chat, apoya las funciones de IA, organiza conversaciones y facilita la transferencia a una persona cuando sea necesario.</div></td></tr></table></div>';
        return '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light only"><title>'.self::e($title).'</title><style>@media only screen and (max-width:640px){.z-wrap{width:100%!important}.z-pad{padding-left:22px!important;padding-right:22px!important}.z-title{font-size:27px!important;white-space:normal!important}.z-one-line{white-space:normal!important}.z-meta{display:block!important;text-align:left!important;padding-top:14px!important}}</style></head><body style="margin:0;padding:0;background:'.self::BG.';font-family:Arial,Helvetica,sans-serif;color:'.self::TEXT.'"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:'.self::BG.'"><tr><td align="center" style="padding:34px 14px"><table role="presentation" class="z-wrap" width="760" cellspacing="0" cellpadding="0" border="0" style="width:760px;max-width:760px;background:#fff;border:1px solid '.self::BORDER.';border-radius:18px;border-collapse:separate;overflow:hidden;box-shadow:0 12px 34px rgba(11,22,37,.07)"><tr><td class="z-pad" style="padding:25px 34px;border-top:4px solid '.$accent.';border-bottom:1px solid '.self::BORDER.'"><table role="presentation" width="100%"><tr><td>'.$logoHtml.'</td><td align="right"><div style="font-size:16px;font-weight:900;color:'.self::NAVY.'">'.self::e($company).'</div><div style="margin-top:4px;font-size:12px;color:'.self::MUTED.'">Comunicaciones mediante '.self::e($app).'</div></td></tr></table></td></tr><tr><td class="z-pad" style="padding:34px 34px 10px"><table role="presentation" width="100%"><tr><td><div style="font-size:11px;font-weight:900;letter-spacing:1.5px;color:'.self::TEAL.';text-transform:uppercase">'.self::e($eyebrow).'</div><h1 class="z-title z-one-line" style="margin:10px 0 0;font-size:32px;line-height:1.18;white-space:normal;overflow-wrap:break-word;color:'.self::NAVY.';letter-spacing:-.5px">'.self::e($title).'</h1></td><td class="z-meta" align="right" valign="top"><span class="z-one-line" style="display:inline-block;padding:7px 14px;border-radius:999px;background:'.$accentBg.';border:1px solid '.$accentBorder.';color:'.$accentText.';font-size:10px;font-weight:900;letter-spacing:.7px;white-space:nowrap">'.self::e($badge).'</span></td></tr></table></td></tr><tr><td class="z-pad" style="padding:14px 34px 36px;font-size:15px;line-height:1.7">'.$content.$nivoHtml.$cta.'</td></tr><tr><td class="z-pad" style="padding:22px 34px 26px;background:#F9FBFC;border-top:1px solid '.self::BORDER.'"><div style="font-size:12px;line-height:1.7;color:'.self::MUTED.'"><strong style="color:'.self::NAVY.'">'.self::e($company).'</strong>'.$supportHtml.'<div style="margin-top:12px">Este mensaje fue generado automáticamente por '.self::e($app).'.</div><div style="margin-top:3px">© '.$year.' '.self::e($company).'.</div></div></td></tr></table></td></tr></table></body></html>';
    }

    /** Keep one consistent severity vocabulary for all transactional notices. */
    private static function noticeTone(string $text): string {
        $t=strtolower($text);
        if(preg_match('/recuperad|restablecid|resuelt|exitos|correctament|aprobada|aprobado/u',$t))return 'success';
        if(preg_match('/fallid|error|fuera de servicio|no disponible|ca[ií]da|bloquead|suspendid|rechazad|no entregad|acceso no reconocid|requiere atenci[oó]n|incidente activo/u',$t))return 'danger';
        if(preg_match('/advertencia|warning|degradad|pendiente|pr[oó]xim[oa] a vencer|intento|revisar|alerta|eliminad|cambio de contrase[nñ]a/u',$t))return 'warning';
        if(preg_match('/seguridad|sesi[oó]n|verificaci[oó]n|acceso|usuario|canal|actividad|informaci[oó]n/u',$t))return 'info';
        return 'success';
    }

    private static function noticeCard(string $heading,string $detail,string $tone='info'): string {
        $colors=['danger'=>['#C83535','#FFF5F4'],'warning'=>['#B66B00','#FFFAED'],'success'=>[self::TEAL,'#F2FBF8'],'info'=>['#3879BB','#F5F9FE']];
        [$edge,$bg]=$colors[$tone]??$colors['info'];
        return '<div style="margin-top:16px;padding:14px 16px;border:1px solid '.self::BORDER.';border-left:4px solid '.$edge.';border-radius:11px;background:'.$bg.'">'
            .'<div style="font-size:13px;font-weight:900;color:'.self::NAVY.'">'.self::e($heading).'</div>'
            .'<div style="margin-top:7px;font-size:13px;line-height:1.65;overflow-wrap:anywhere">'.nl2br(self::e($detail)).'</div></div>';
    }

    /** Show source facts verbatim; never manufacture a root cause or a completed action. */
    private static function eventBody(string $message,string $title,string $category): string {
        $message=trim($message);
        $tone=self::noticeTone($title.' '.$message);
        if($message==='')return self::noticeCard('Detalle del evento','No se proporcionaron detalles adicionales.',$tone);
        $content=self::noticeCard('Qué ocurrió',$message,$tone);
        $lower=strtolower($title.' '.$message);
        if(preg_match('/no reconoces|no solicitaste|sesi[oó]n|contrase[nñ]a|inicio de sesi[oó]n/u',$lower)){
            $content.=self::noticeCard('Qué hacer','Si reconoces la actividad, no necesitas realizar ninguna acción. Si no la reconoces, revisa tus accesos, cambia tu contraseña y contacta a soporte.','info');
        } elseif(preg_match('/error|fallid|rechazad|no disponible|fuera de servicio|degradad/u',$lower)){
            $content.=self::noticeCard('Qué hacer','Revisa el estado actual y el detalle del evento en ZYNKO. Si continúa, contacta a soporte con la fecha y el mensaje de error; no compartas claves ni tokens.','warning');
        } elseif(preg_match('/recuperad|restablecid|resuelt/u',$lower)){
            $content.=self::noticeCard('Seguimiento','El aviso indica una recuperación. Confirma el estado actual en ZYNKO; no se requiere intervención si el servicio permanece operativo.','success');
        }
        return $content;
    }

    /** Status pill and before/after comparison shared by monitoring and plan notices. */
    private static function statePill(string $value,bool $isNew=false): string {
        $v=strtolower(trim($value));
        $ok=in_array($v,['up','active','activo','activa','enabled','habilitado','aprobado'],true);
        $bad=in_array($v,['down','error','failed','fallido','rechazado','suspendido','cancelado'],true);
        $warn=in_array($v,['degraded','degradado','warning','advertencia','pending','pendiente','gracia','período de gracia'],true);
        $neutral=in_array($v,['inactive','inactivo','inactiva','disabled','deshabilitado'],true);
        $bg=$ok?'#E7F7F0':($bad?'#FFF0EF':($warn?'#FFF8E9':($neutral?'#F2F5F8':($isNew?'#E7F7F0':'#F2F5F8'))));
        $fg=$ok?'#08765C':($bad?'#B4232C':($warn?'#865000':($neutral?'#516173':($isNew?'#08765C':'#516173'))));
        $bd=$ok?'#C6EBDC':($bad?'#F5CAC8':($warn?'#F4DCA7':($neutral?'#DEE5EB':($isNew?'#C6EBDC':'#DEE5EB'))));
        $text=['up'=>'OPERATIVO','down'=>'FUERA DE SERVICIO','degraded'=>'DEGRADADO','active'=>'ACTIVO','inactive'=>'INACTIVO'][$v]??strtoupper($value);
        return '<span style="display:inline-block;padding:5px 10px;margin:3px 0;border-radius:7px;background:'.$bg.';color:'.$fg.';border:1px solid '.$bd.';font-size:12px;font-weight:800;line-height:1.4">'.self::e($text).'</span>';
    }
    private static function comparison(string $label,string $before,string $after,string $beforeLabel='ANTES',string $afterLabel='AHORA'): string {
        return '<div style="padding:12px 0;border-bottom:1px solid #E3EAF0">'
            .'<div style="font-weight:800;color:'.self::NAVY.';margin-bottom:7px">'.self::e($label).'</div>'
            .'<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr>'
            .'<td width="48%" valign="top" style="font-size:12px;color:'.self::MUTED.';padding-right:5px">'.self::e($beforeLabel).'<br>'.self::statePill($before).'</td>'
            .'<td width="4%" align="center" valign="middle" style="font-size:14px;color:'.self::MUTED.'">→</td>'
            .'<td width="48%" valign="top" style="font-size:12px;color:'.self::MUTED.';padding-left:5px">'.self::e($afterLabel).'<br>'.self::statePill($after,true).'</td>'
            .'</tr></table></div>';
    }

    public static function serviceMonitor(string $title,string $message,array $changes,array $settings): string {
        // The same run can include UP -> DOWN -> UP. The LAST observed status
        // determines the headline; earlier changes remain available as history.
        $timeline=[];$last=[];$first=[];
        foreach($changes as $change){
            if(!is_array($change))continue;
            $key=(string)($change['key']??$change['name']??'service');
            $name=trim((string)($change['name']??'Servicio'));
            $old=strtolower(trim((string)($change['old']??'')));
            $new=strtolower(trim((string)($change['new']??'')));
            $detail=trim((string)($change['detail']??''));
            $item=['key'=>$key,'name'=>$name,'old'=>$old,'new'=>$new,'detail'=>$detail];
            $timeline[]=$item;
            if(!isset($first[$key]))$first[$key]=$item;
            $last[$key]=$item;
        }
        // Final summary from the monitor is more authoritative than the title.
        $observed=[];
        if(preg_match('/Resumen actual:(.*?)(?:Revisi[oó]n:|$)/su',$message,$m)){
            foreach(preg_split('/\R/u',trim($m[1]))?:[] as $line){
                if(preg_match('/^\s*[•-]\s*(.+?):\s*(UP|DOWN|DEGRADED|INACTIVE)\s*$/iu',trim($line),$parts)){
                    $observed[trim($parts[1])]=strtolower($parts[2]);
                }
            }
        }
        $issues=0;$recovered=0;
        foreach($last as $key=>$item){
            $status=$observed[$item['name']]??$item['new'];
            if(in_array($status,['down','degraded'],true))$issues++;
            elseif($status==='up' && array_filter($timeline, static fn($event) => $event['key']===$key && $event['new']==='up' && in_array($event['old'],['down','degraded'],true)))$recovered++;
        }
        $tone=$issues>0?'danger':($recovered>0?'success':'info');
        $badge=$issues>0?'REQUIERE ATENCIÓN':($recovered>0?'RECUPERADO':'ESTADO ACTUALIZADO');
        $heading=$issues>0?'Servicios con incidencias activas':($recovered>0?'Servicios recuperados':'Cambios en los servicios');
        $summary=$issues>0
            ?'Se detectaron '.count($last).' servicio(s) con cambios; '.$issues.' continúan fuera de servicio o degradados al finalizar la revisión.'
            :($recovered>0?'Los servicios afectados volvieron a estar operativos al finalizar la revisión. El historial de la interrupción se conserva debajo.':'Se registraron cambios en la supervisión de servicios.');
        $content=self::noticeCard('Resultado final',$summary,$tone);
        foreach($last as $key=>$item){
            $final=$observed[$item['name']]??$item['new'];
            $content.='<div style="margin-top:14px;padding:14px 16px;border:1px solid '.self::BORDER.';border-radius:11px">'
                .'<div style="font-weight:900;color:'.self::NAVY.'">'.self::e($item['name']).'</div>'
                .self::comparison('Estado inicial y resultado final', $first[$key]['old'], $final);
            $detail=$item['detail'];
            if($detail!=='')$content.='<div style="margin-top:8px;font-size:12px;color:'.self::MUTED.';overflow-wrap:anywhere"><strong>Resultado de la comprobación:</strong> '.nl2br(self::e($detail)).'</div>';
            $content.='</div>';
        }
        if(count($timeline)>1){
            $content.='<div style="margin-top:18px"><strong>Historial de cambios detectados</strong>';
            foreach($timeline as $item){
                $content.='<div style="margin-top:9px;font-size:12px;line-height:1.6;color:'.self::MUTED.'">'
                    .'<strong>'.self::e($item['name']).':</strong> '.self::e(strtoupper($item['old'])).' → '.self::e(strtoupper($item['new']))
                    .($item['detail']!==''?' · '.self::e($item['detail']):'').'</div>';
            }
            $content.='</div>';
        }
        if($observed){
            $content.='<div style="margin-top:18px"><strong>Estado actual de los servicios</strong>';
            foreach($observed as $name=>$status)$content.='<div style="margin-top:8px;font-size:13px">'.self::e($name).': '.self::statePill($status).'</div>';
            $content.='</div>';
        }
        if($issues>0){
            $content.=self::noticeCard('Acción recomendada','Consulta Configuración → Estado de los servicios y revisa los registros correspondientes. Comprueba la disponibilidad actual antes de reiniciar un proceso; la causa no está confirmada por este aviso.','warning');
        } elseif($recovered>0){
            $content.=self::noticeCard('Seguimiento','Los servicios implicados se muestran operativos en la última revisión. Si el incidente se repite, consulta los registros y los eventos del monitor.','success');
        }
        if(preg_match('/Revisi[oó]n:\s*([^\r\n]+)/u',$message,$m))$content.='<p style="font-size:12px;color:'.self::MUTED.'">Última revisión: '.self::e($m[1]).'</p>';
        // The email subject supplied by the caller is unchanged, while the visual
        // heading and badge always reflect the final measured status.
        $visualTitle=$issues>0?'ZYNKO requiere atención: '.implode(', ',array_map(static fn($x)=>$x['name'],$last))
            :($recovered>0?'Servicios recuperados: '.implode(', ',array_map(static fn($x)=>$x['name'],$last)):$title);
        return self::shell('ESTADO DE LOS SERVICIOS',$visualTitle,$content,$settings,$badge,$tone);
    }

    public static function test(array $settings): string { return self::shell('CONFIGURACIÓN DE CORREO','Prueba enviada correctamente','<p style="margin:0">Tu configuración de correo está funcionando. ZYNKO ya puede entregar notificaciones transaccionales utilizando el proveedor configurado.</p><div style="margin-top:20px;padding:16px 18px;background:'.self::SOFT.';border:1px solid #D4EEE8;border-left:4px solid '.self::TEAL.';border-radius:11px"><strong style="color:'.self::NAVY.'">Conexión verificada</strong><div style="margin-top:5px;color:'.self::MUTED.';font-size:13px">No necesitas realizar ninguna acción adicional.</div></div>',$settings,'VERIFICADO'); }
    public static function accountCreated(array $settings): string { return self::shell('BIENVENIDO A ZYNKO','Tu cuenta de ZYNKO fue creada','<p style="margin:0">Tu cuenta principal y tu empresa fueron creadas correctamente. Ya puedes ingresar a ZYNKO con el correo registrado.</p><div style="margin-top:20px;padding:16px 18px;background:'.self::SOFT.';border:1px solid #D4EEE8;border-left:4px solid '.self::TEAL.';border-radius:11px"><strong style="color:'.self::NAVY.'">Todo listo para comenzar</strong><div style="margin-top:5px;color:'.self::MUTED.';font-size:13px">Configura tus canales, tu equipo y NIVO desde el panel de ZYNKO.</div></div>',$settings,'CUENTA CREADA'); }
    public static function companyCreated(array $company,array $settings): string { $name=self::e($company['name']??'Nueva empresa'); return self::shell('EMPRESAS','Empresa creada en ZYNKO','<p style="margin:0">La empresa <strong>'.$name.'</strong> fue creada correctamente y ya está disponible en la plataforma.</p>',$settings,'EMPRESA'); }
    public static function security(string $message,array $settings): string { return self::shell('SEGURIDAD','Actividad de seguridad',self::eventBody($message,'Actividad de seguridad','security'),$settings,'SEGURIDAD',self::noticeTone($message)); }
    public static function billing(string $title,string $message,array $settings): string { return self::shell('FACTURACIÓN',$title,self::eventBody($message,$title,'billing'),$settings,'FACTURACIÓN',self::noticeTone($title.' '.$message)); }

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


    /** Present saved values in business terms, not storage-specific reserved slots. */
    private static function planCatalogValues(array $plan): array {
        $limit=static fn($v)=>$v===null||(int)$v===0?'Ilimitados':number_format((int)$v);
        $yes=static fn($v)=>!empty($v)?'Sí':'No';
        $labels=['webchat'=>'NIVO Web Chat','whatsapp'=>'WhatsApp Business','messenger'=>'Messenger','instagram'=>'Instagram','telegram'=>'Telegram','email'=>'Correo','threads'=>'Threads','x'=>'X / Twitter','api'=>'API','dashboard'=>'Dashboard','inbox'=>'Bandeja','channels'=>'Canales','billing'=>'Suscripción','onboarding'=>'Inicio guiado','users'=>'Usuarios','chatbot'=>'NIVO IA','automations'=>'Automatizaciones','integrations'=>'Integraciones','settings'=>'Configuración'];
        $list=static function($v,bool $map=false) use($labels): string {
            $a=is_array($v)?$v:json_decode((string)$v,true);
            if(!is_array($a))return '—';
            if($map){$a=array_keys(array_filter($a));}
            $a=array_map('strval',$a);sort($a,SORT_STRING);
            return $a?implode(', ',array_map(static fn($x)=>$labels[$x]??$x,$a)):'—';
        };
        $external=(!empty($plan['is_default_free'])||($plan['code']??'')==='free')?'0':(empty($plan['max_channels'])?'Ilimitadas':(string)max(0,(int)$plan['max_channels']-1));
        return [
            'Nombre'=>$plan['name']??'Plan',
            'Precio mensual'=>($plan['currency']??'HNL').' '.number_format((float)($plan['monthly_price']??0),2),
            'Estado'=>!empty($plan['active'])?'Activo':'Inactivo',
            'Disponible para contratar'=>$yes($plan['is_available']??0),
            'Etiqueta de disponibilidad'=>$plan['availability_label']??'—',
            'Mensaje de disponibilidad'=>$plan['availability_message']??'—',
            'Usuarios'=>$limit($plan['max_users']??null),
            'Conexiones externas'=>$external,
            'Una cuenta por tipo de canal'=>$yes($plan['distinct_external_types']??0),
            'Canales incluidos'=>$list($plan['allowed_channels_json']??null),
            'Sitios Web Chat'=>$limit($plan['max_webchat_sites']??null),
            'Chats nuevos por día'=>$limit($plan['max_daily_chats']??null),
            'Chats nuevos por mes'=>$limit($plan['max_monthly_chats']??null),
            'Módulos habilitados'=>$list($plan['module_access_json']??null,true),
            'Beneficios'=>$list($plan['features_json']??null),
            'IA externa'=>$yes($plan['external_ai_included']??0),
            'Tokens mensuales de IA'=>empty($plan['external_ai_included'])?'No incluido':$limit($plan['external_ai_monthly_tokens']??null),
            'Canales IA externa'=>$list($plan['external_ai_channels_json']??null),
            'Plan gratuito predeterminado'=>$yes($plan['is_default_free']??0),
            'Plan destacado'=>$yes($plan['is_featured']??0),
            'Etiqueta destacada'=>$plan['featured_label']??'—',
        ];
    }

    public static function planCatalogEvent(string $action,array $plan,array $settings,?array $previousPlan=null): string {
        $name=self::e($plan['name']??'Plan');
        $verb=['created'=>'creado','updated'=>'actualizado','deleted'=>'eliminado'][$action]??'actualizado';
        $values=self::planCatalogValues($plan);
        $content='<p style="margin:0">El plan <strong>'.$name.'</strong> fue '.$verb.' en ZYNKO.</p>';
        $card='margin-top:20px;padding:16px 18px;background:#F7FBFA;border:1px solid #DCEEEA;border-radius:12px;font-size:14px;line-height:1.7';
        if($action==='updated'&&$previousPlan!==null){
            $before=self::planCatalogValues($previousPlan);$changes='';
            foreach($values as $label=>$value){
                if((string)$before[$label]===(string)$value)continue;
                $changes.=self::comparison($label,(string)$before[$label],(string)$value);
            }
            $content.='<div style="'.$card.'"><strong>Cambios realizados</strong>'.($changes!==''?$changes:'<p>No se detectaron cambios en la configuración del plan.</p>').'</div>';
        }
        $content.='<div style="'.$card.'"><strong>'.($action==='deleted'?'Última configuración':'Configuración actual').'</strong>';
        foreach($values as $label=>$value)$content.='<br><strong>'.self::e($label).':</strong> '.self::e($value);
        $content.='</div>';
        return self::shell('PLANES Y SUSCRIPCIONES','Plan '.$verb,$content,$settings,'PLAN '.strtoupper($verb));
    }

    private static function requestedPlanComparison(array $data): string {
        $current = trim((string)($data['previous_plan_name'] ?? ''));
        return self::comparison('Planes de la empresa', $current !== '' ? $current : 'Sin plan registrado',
            (string)($data['plan_name'] ?? 'Plan'), 'PLAN ACTUAL', 'PLAN SOLICITADO');
    }

    public static function planRequestCustomer(array $data,array $settings): string {
        $plan=self::e($data['plan_name']??'Plan');
        $company=self::e($data['company_name']??'Tu empresa');
        $currency=$data['currency']??'HNL';
        $price=self::e($currency.' '.number_format((float)($data['monthly_price']??0),2));
        if(!empty($data['promotion']['price']))$price=self::e($currency.' '.$data['promotion']['price'].' el primer mes. Después, '.$currency.' '.number_format((float)$data['monthly_price'],2));
        $content='<p style="margin:0">Recibimos la solicitud de <strong>'.$company.'</strong> para cambiar al plan <strong>'.$plan.'</strong>.</p>'
            .'<div style="margin-top:20px;padding:16px 18px;background:'.self::SOFT.';border:1px solid #D4EEE8;border-left:4px solid '.self::TEAL.';border-radius:11px">'
            .'<strong style="color:'.self::NAVY.'">Solicitud recibida</strong><div style="margin-top:7px;color:'.self::MUTED.';font-size:13px;line-height:1.6">Plan solicitado: <strong>'.$plan.'</strong> · '.$price.'/mes. Tu plan actual no cambia hasta que la solicitud sea aprobada.</div></div>';
        return self::shell('SOLICITUD DE PLAN','Recibimos tu solicitud de plan',$content.self::requestedPlanComparison($data),$settings,'SOLICITUD RECIBIDA');
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
        return self::shell('SOLICITUD COMERCIAL','Nueva solicitud de plan',$content.self::requestedPlanComparison($data),$settings,'REQUIERE ATENCIÓN');
    }

    private static function subscriptionComparison(array $data): string {
        $previousPlan = trim((string)($data['previous_plan_name'] ?? ''));
        $previousStatus = trim((string)($data['previous_status_label'] ?? ''));
        $plan = (string)($data['plan_name'] ?? 'Plan');
        $status = (string)($data['status_label'] ?? $data['status'] ?? 'Activo');
        return self::comparison('Plan de la empresa', $previousPlan !== '' ? $previousPlan : 'Sin plan anterior registrado', $plan)
            . self::comparison('Estado de suscripción', $previousStatus !== '' ? $previousStatus : 'Sin estado anterior registrado', $status);
    }

    public static function subscriptionChangedCustomer(array $data,array $settings): string {
        $company = self::e($data['company_name'] ?? 'Tu empresa');
        $content = '<p style="margin:0">La suscripción de <strong>'.$company.'</strong> fue actualizada.</p>'
            . self::subscriptionComparison($data)
            . '<p style="margin:18px 0 0;color:'.self::MUTED.';font-size:13px">Consulta en Suscripción los canales, módulos y límites disponibles para tu empresa.</p>';
        return self::shell('TU SUSCRIPCIÓN','Tu suscripción de ZYNKO fue actualizada',$content,$settings,'SUSCRIPCIÓN');
    }

    public static function subscriptionChangedAdmin(array $data,array $settings): string {
        $company = self::e($data['company_name'] ?? 'Empresa');
        $source = self::e($data['source_label'] ?? 'Asignación manual');
        $content = '<p style="margin:0">Se actualizó la suscripción de <strong>'.$company.'</strong>.</p>'
            . self::subscriptionComparison($data)
            . '<p style="margin:18px 0 0"><strong>Origen:</strong> '.$source.'</p>';
        return self::shell('CONTROL COMERCIAL','Suscripción actualizada',$content,$settings,'SUSCRIPCIÓN');
    }

    public static function planRequestResolved(array $data,array $settings): string {
        $approved=($data['resolution']??'')==='approved';
        $plan=self::e($data['plan_name']??'Plan');
        $note=self::e($data['note']??'');
        $content='<p style="margin:0">Tu solicitud para el plan <strong>'.$plan.'</strong> fue <strong>'.($approved?'aprobada':'rechazada').'</strong>.</p>'
            .($approved ? self::subscriptionComparison($data) : '<p style="margin:18px 0 0">La suscripción actual se mantiene.</p>'.self::requestedPlanComparison($data))
            .($note!==''?'<p style="margin:18px 0 0"><strong>Comentario:</strong> '.$note.'</p>':'');
        return self::shell('SOLICITUD DE PLAN',$approved?'Tu solicitud fue aprobada':'Actualización de tu solicitud',$content,$settings,$approved?'APROBADA':'RECHAZADA');
    }

    public static function userLifecycle(string $title,string $message,array $settings,string $badge='USUARIO'): string {
        return self::shell('SEGURIDAD Y ACCESO',$title,self::eventBody($message,$title,'security'),$settings,$badge,self::noticeTone($title.' '.$message));
    }

    public static function channelLifecycle(string $title,string $message,array $settings): string {
        return self::shell('CANALES E INTEGRACIONES',$title,self::eventBody($message,$title,'channel'),$settings,'CANAL',self::noticeTone($title.' '.$message));
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

    /** Classify administrative emails by the event that actually occurred. */
    public static function administrativeNotice(string $title,string $message,array $settings,string $event=''): string {
        $category=strtolower($title.' '.$event);
        $tone=self::noticeTone($title.' '.$message);
        if(preg_match('/seguridad|sesi[oó]n|contrase[nñ]a|acceso|bloqueo|usuario/u',$category)){
            $eyebrow='SEGURIDAD Y ACCESO';
            $badge=$tone==='danger'?'ALERTA DE SEGURIDAD':'SEGURIDAD';
        } elseif(preg_match('/correo|notificaci[oó]n|mail|smtp|graph/u',$category)){
            $eyebrow='CORREO Y NOTIFICACIONES';
            $badge=$tone==='danger'?'ERROR':($tone==='warning'?'ADVERTENCIA':'INFORMACIÓN');
        } elseif(preg_match('/canal|whatsapp|integraci[oó]n|websocket|api|servicio|nivo/u',$category)){
            $eyebrow='SERVICIOS E INTEGRACIONES';
            $badge=$tone==='danger'?'REQUIERE ATENCIÓN':($tone==='warning'?'ADVERTENCIA':'ACTUALIZACIÓN');
        } else {
            $eyebrow='ACTIVIDAD DE CUENTA';
            $badge=$tone==='danger'?'REQUIERE ATENCIÓN':($tone==='warning'?'ADVERTENCIA':'ACTUALIZACIÓN');
        }
        return self::shell($eyebrow,$title,self::eventBody($message,$title,$event),$settings,$badge,$tone);
    }

    public static function generic(string $eyebrow,string $title,string $message,array $settings,string $badge='NOTIFICACIÓN'): string { return self::shell($eyebrow,$title,self::eventBody($message,$title,$eyebrow),$settings,$badge,self::noticeTone($title.' '.$message)); }
}
