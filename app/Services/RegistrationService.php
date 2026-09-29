<?php
declare(strict_types=1);
require_once __DIR__.'/NotificationService.php';
require_once __DIR__.'/../Support/Plan.php';

final class RegistrationService{
    public function __construct(private PDO $pdo,private string $root){}

    public function ensureSchema(): void{
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS registration_requests(
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            company_name VARCHAR(160) NOT NULL,
            business_id VARCHAR(80) NULL,
            owner_name VARCHAR(160) NOT NULL,
            phone VARCHAR(50) NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            verification_code_hash VARCHAR(255) NOT NULL,
            code_expires_at DATETIME NOT NULL,
            attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            send_count SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            window_started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            status ENUM('pending','verified','blocked') NOT NULL DEFAULT 'pending',
            ip_address VARCHAR(64) NULL,
            user_agent VARCHAR(500) NULL,
            verified_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX(status,code_expires_at),INDEX(ip_address,last_sent_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        try{$c=$this->pdo->query("SHOW COLUMNS FROM tenants LIKE 'business_id'")->fetch();if(!$c)$this->pdo->exec("ALTER TABLE tenants ADD business_id VARCHAR(80) NULL AFTER name");}catch(Throwable $e){}
        try{$c=$this->pdo->query("SHOW COLUMNS FROM tenants LIKE 'contact_phone'")->fetch();if(!$c)$this->pdo->exec("ALTER TABLE tenants ADD contact_phone VARCHAR(50) NULL AFTER business_id");}catch(Throwable $e){}
        try{$c=$this->pdo->query("SHOW COLUMNS FROM tenants LIKE 'registration_source'")->fetch();if(!$c)$this->pdo->exec("ALTER TABLE tenants ADD registration_source VARCHAR(30) NULL AFTER contact_phone");}catch(Throwable $e){}
        try{$c=$this->pdo->query("SHOW COLUMNS FROM users LIKE 'email_verified_at'")->fetch();if(!$c)$this->pdo->exec("ALTER TABLE users ADD email_verified_at DATETIME NULL AFTER email");}catch(Throwable $e){}
        try{$c=$this->pdo->query("SHOW COLUMNS FROM registration_requests LIKE 'terms_version'")->fetch();if(!$c)$this->pdo->exec("ALTER TABLE registration_requests ADD terms_version INT UNSIGNED NULL AFTER user_agent");}catch(Throwable $e){}
        try{$c=$this->pdo->query("SHOW COLUMNS FROM registration_requests LIKE 'terms_accepted_at'")->fetch();if(!$c)$this->pdo->exec("ALTER TABLE registration_requests ADD terms_accepted_at DATETIME NULL AFTER terms_version");}catch(Throwable $e){}
    }

    private function cleanPhone(string $v): string{return mb_substr(preg_replace('/[^0-9+() .-]/','',trim($v))??'',0,50);}
    private function platformAdminEmail(int $platformTid): string{
        $q=$this->pdo->prepare("SELECT u.email FROM users u JOIN tenant_users tu ON tu.user_id=u.id WHERE tu.tenant_id=? AND u.status='active' ORDER BY tu.is_owner DESC,FIELD(tu.role_code,'owner','admin'),u.id LIMIT 1");
        $q->execute([$platformTid]);return (string)($q->fetchColumn()?:'');
    }
    private function code(): string{return str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);}
    private function slugBase(string $name): string{
        $v=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$name)?:$name;$v=strtolower($v);$v=preg_replace('/[^a-z0-9]+/','-',$v)??'';$v=trim($v,'-');return mb_substr($v!==''?$v:'empresa',0,95);
    }
    private function uniqueSlug(string $name): string{
        $base=$this->slugBase($name);$slug=$base;$n=0;$q=$this->pdo->prepare('SELECT 1 FROM tenants WHERE slug=? LIMIT 1');
        while(true){$q->execute([$slug]);if(!$q->fetchColumn())return $slug;$n++;$slug=$base.'-'.substr(bin2hex(random_bytes(3)),0,6);if($n>10)$slug=$base.'-'.time();}
    }

    public function request(array $data,string $ip,string $ua,int $platformTid): array{
        $this->ensureSchema();zynkoEnsurePlanSchema($this->pdo);
        if(trim((string)($data['website']??''))!=='')throw new RuntimeException('No se pudo procesar el registro.');
        $company=mb_substr(trim((string)($data['company_name']??'')),0,160);
        $business=mb_substr(trim((string)($data['business_id']??'')),0,80);
        $owner=mb_substr(trim((string)($data['owner_name']??'')),0,160);
        $phone=$this->cleanPhone((string)($data['phone']??''));
        $email=strtolower(trim((string)($data['email']??'')));
        $pass=(string)($data['password']??'');$confirm=(string)($data['password_confirm']??'');
        if(mb_strlen($company)<2)throw new RuntimeException('Ingresa el nombre de la empresa.');
        if(mb_strlen($owner)<3)throw new RuntimeException('Ingresa el nombre del propietario o administrador.');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Ingresa un correo válido.');
        if(strlen($pass)<8)throw new RuntimeException('La contraseña debe tener al menos 8 caracteres.');
        if($pass!==$confirm)throw new RuntimeException('Las contraseñas no coinciden.');
        if(empty($data['terms']))throw new RuntimeException('Debes aceptar los términos para crear la cuenta.');
        $terms=$this->pdo->query("SELECT version FROM legal_documents WHERE document_key='terms_conditions' LIMIT 1")->fetch();$currentTermsVersion=(int)($terms['version']??0);$submittedTermsVersion=(int)($data['terms_version']??0);if(!$currentTermsVersion)throw new RuntimeException('Los Términos y Condiciones no están disponibles en este momento.');if($submittedTermsVersion!==$currentTermsVersion)throw new RuntimeException('Los Términos y Condiciones fueron actualizados. Recarga la página, revísalos y vuelve a aceptarlos.');
        $q=$this->pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');$q->execute([$email]);if($q->fetchColumn())throw new RuntimeException('Este correo ya tiene una cuenta en ZYNKO.');
        if($business!==''){$q=$this->pdo->prepare("SELECT id FROM tenants WHERE business_id=? LIMIT 1");$q->execute([$business]);if($q->fetchColumn())throw new RuntimeException('Esta identificación fiscal ya está vinculada a una empresa en ZYNKO.');}
        $q=$this->pdo->prepare("SELECT COALESCE(SUM(send_count),0) FROM registration_requests WHERE ip_address=? AND last_sent_at>=DATE_SUB(NOW(),INTERVAL 1 HOUR)");$q->execute([$ip]);if((int)$q->fetchColumn()>=8)throw new RuntimeException('Se alcanzó el límite temporal de registros desde esta conexión. Intenta más tarde.');

        $code=$this->code();$hash=password_hash($code,PASSWORD_DEFAULT);$passwordHash=password_hash($pass,PASSWORD_DEFAULT);
        $q=$this->pdo->prepare('SELECT * FROM registration_requests WHERE email=? LIMIT 1');$q->execute([$email]);$existing=$q->fetch();
        if($existing){
            if(($existing['status']??'')==='verified')throw new RuntimeException('Este correo ya fue verificado. Inicia sesión.');
            if(strtotime((string)$existing['last_sent_at'])>time()-60)throw new RuntimeException('Ya enviamos un código a este correo. Espera 60 segundos antes de solicitar otro.');
            $windowStart=strtotime((string)$existing['window_started_at']);$within=$windowStart && $windowStart>time()-3600;
            $sendCount=$within?(int)$existing['send_count']:0;if($sendCount>=5)throw new RuntimeException('Solicitaste demasiados códigos. Espera una hora antes de intentarlo nuevamente.');
            $st=$this->pdo->prepare("UPDATE registration_requests SET company_name=?,business_id=?,owner_name=?,phone=?,password_hash=?,verification_code_hash=?,code_expires_at=DATE_ADD(NOW(),INTERVAL 10 MINUTE),attempts=0,send_count=?,window_started_at=?,last_sent_at=NOW(),status='pending',ip_address=?,user_agent=?,terms_version=?,terms_accepted_at=NOW() WHERE id=?");
            $st->execute([$company,$business?:null,$owner,$phone?:null,$passwordHash,$hash,$sendCount+1,$within?$existing['window_started_at']:date('Y-m-d H:i:s'),$ip,mb_substr($ua,0,500),$currentTermsVersion,$existing['id']]);$id=(int)$existing['id'];
        }else{
            $st=$this->pdo->prepare("INSERT INTO registration_requests(company_name,business_id,owner_name,phone,email,password_hash,verification_code_hash,code_expires_at,ip_address,user_agent,terms_version,terms_accepted_at) VALUES(?,?,?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 10 MINUTE),?,?,?,NOW())");
            $st->execute([$company,$business?:null,$owner,$phone?:null,$email,$passwordHash,$hash,$ip,mb_substr($ua,0,500),$currentTermsVersion]);$id=(int)$this->pdo->lastInsertId();
        }
        $mail=(new NotificationService($this->pdo,$this->root))->sendRegistrationCode($platformTid,$email,$code,$company);
        if(empty($mail['success']))throw new RuntimeException('No pudimos enviar el código de verificación. '.$mail['message']);
        return ['request_id'=>$id,'email'=>$email,'company_name'=>$company];
    }

    public function resend(int $requestId,string $ip,int $platformTid): array{
        $this->ensureSchema();$q=$this->pdo->prepare("SELECT * FROM registration_requests WHERE id=? AND status='pending' LIMIT 1");$q->execute([$requestId]);$r=$q->fetch();if(!$r)throw new RuntimeException('El registro ya no está disponible.');
        if(strtotime((string)$r['last_sent_at'])>time()-60)throw new RuntimeException('Espera 60 segundos antes de solicitar otro código.');
        $within=strtotime((string)$r['window_started_at'])>time()-3600;$count=$within?(int)$r['send_count']:0;if($count>=5)throw new RuntimeException('Alcanzaste el máximo de códigos por hora. Intenta más tarde.');
        $code=$this->code();$hash=password_hash($code,PASSWORD_DEFAULT);$this->pdo->prepare("UPDATE registration_requests SET verification_code_hash=?,code_expires_at=DATE_ADD(NOW(),INTERVAL 10 MINUTE),attempts=0,send_count=?,window_started_at=?,last_sent_at=NOW(),ip_address=? WHERE id=?")->execute([$hash,$count+1,$within?$r['window_started_at']:date('Y-m-d H:i:s'),$ip,$requestId]);
        $mail=(new NotificationService($this->pdo,$this->root))->sendRegistrationCode($platformTid,(string)$r['email'],$code,(string)$r['company_name']);if(empty($mail['success']))throw new RuntimeException('No se pudo reenviar el código. '.$mail['message']);
        return ['email'=>$r['email']];
    }

    public function verify(int $requestId,string $code,int $platformTid): array{
        $this->ensureSchema();zynkoEnsurePlanSchema($this->pdo);
        if(!preg_match('/^\d{6}$/',$code))throw new RuntimeException('Ingresa el código de 6 dígitos.');
        $q=$this->pdo->prepare("SELECT * FROM registration_requests WHERE id=? AND status='pending' LIMIT 1");$q->execute([$requestId]);$r=$q->fetch();if(!$r)throw new RuntimeException('El registro ya no está disponible.');
        if((int)$r['attempts']>=5){$this->pdo->prepare("UPDATE registration_requests SET status='blocked' WHERE id=?")->execute([$requestId]);throw new RuntimeException('Se agotaron los intentos de verificación. Solicita un nuevo registro.');}
        if(strtotime((string)$r['code_expires_at'])<time())throw new RuntimeException('El código venció. Solicita uno nuevo.');
        if(!password_verify($code,(string)$r['verification_code_hash'])){$this->pdo->prepare('UPDATE registration_requests SET attempts=attempts+1 WHERE id=?')->execute([$requestId]);throw new RuntimeException('El código ingresado no es correcto.');}
        $q=$this->pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');$q->execute([$r['email']]);if($q->fetchColumn())throw new RuntimeException('Este correo ya tiene una cuenta en ZYNKO.');
        $freePlan=zynkoFreePlanId($this->pdo);if(!$freePlan)throw new RuntimeException('No se encontró el Plan Gratis del sistema.');
        $slug=$this->uniqueSlug((string)$r['company_name']);
        $this->pdo->beginTransaction();
        try{
            $this->pdo->prepare("INSERT INTO tenants(uuid,name,business_id,contact_phone,registration_source,slug,status,locale,bot_name) VALUES(?,?,?,?,?,?, 'active','es','NIVO')")->execute([self::uuid4(),$r['company_name'],$r['business_id']?:null,$r['phone']?:null,'self_service',$slug]);$tenantId=(int)$this->pdo->lastInsertId();
            $this->pdo->prepare("INSERT INTO users(uuid,name,email,email_verified_at,password_hash,locale,status) VALUES(?,?,?,NOW(),?,'es','active')")->execute([self::uuid4(),$r['owner_name'],$r['email'],$r['password_hash']]);$userId=(int)$this->pdo->lastInsertId();
            $this->pdo->prepare("INSERT INTO tenant_users(tenant_id,user_id,role_code,is_owner) VALUES(?,?,'owner',1)")->execute([$tenantId,$userId]);
            $this->pdo->prepare("INSERT INTO tenant_subscriptions(tenant_id,plan_id,status,starts_at) VALUES(?,?,'active',NOW())")->execute([$tenantId,$freePlan]);
            zynkoApplyPlanEntitlements($this->pdo,$tenantId,$freePlan);
            $this->pdo->prepare("INSERT INTO channels(tenant_id,uuid,type,name,display_address,status,settings_json) VALUES(?,?, 'webchat','NIVO Web Chat','NIVO Web Chat','connected','{}')")->execute([$tenantId,self::uuid4()]);$channelId=(int)$this->pdo->lastInsertId();
            $publicKey=bin2hex(random_bytes(20));
            $this->pdo->prepare("INSERT INTO webchat_widgets(tenant_id,channel_id,name,public_key,enabled,position,display_mode,accent_color,welcome_title,assistant_subtitle,welcome_message,ask_name,ask_email,profile_required,allow_multiple_domains,created_by) VALUES(?,?,'NIVO Web Chat',?,1,'bottom-right','launcher','#0F766E','¡Hola! Soy NIVO',?,'¿En qué puedo ayudarte hoy?',1,0,0,0,?)")->execute([$tenantId,$channelId,$publicKey,'Asistente virtual de '.$r['company_name'],$userId]);
            $this->pdo->prepare("UPDATE registration_requests SET status='verified',verified_at=NOW() WHERE id=?")->execute([$requestId]);
            $this->pdo->commit();
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
        $customer=['tenant_id'=>$tenantId,'company_name'=>$r['company_name'],'business_id'=>$r['business_id'],'owner_name'=>$r['owner_name'],'phone'=>$r['phone'],'email'=>$r['email']];
        $notifications=new NotificationService($this->pdo,$this->root);
        try{$notifications->sendFreeAccountWelcome($platformTid,(string)$r['email'],$customer);}catch(Throwable $e){}
        $admin=$this->platformAdminEmail($platformTid);if($admin!==''){try{$notifications->sendNewCustomerAdmin($platformTid,$admin,$customer);}catch(Throwable $e){}}
        return $customer;
    }

    private static function uuid4(): string{$d=random_bytes(16);$d[6]=chr((ord($d[6])&0x0f)|0x40);$d[8]=chr((ord($d[8])&0x3f)|0x80);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($d),4));}
}
