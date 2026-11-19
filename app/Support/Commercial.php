<?php
declare(strict_types=1);

function zynkoCommercialText(): string {
 return <<<'LEGAL'
1. Mensualidad de ZYNKO / ZYNKO subscription
La mensualidad cubre el acceso a ZYNKO, su infraestructura, mantenimiento y funciones y límites del plan contratado. / The subscription covers access to ZYNKO, its infrastructure, maintenance and the features and limits of the selected plan.

2. Servicios externos / External services
El precio no incluye los cargos facturables de Meta, WhatsApp ni otros proveedores externos. Cada empresa conserva sus activos y paga su consumo directamente al proveedor mediante su propio método de pago. Las tarifas dependen del proveedor, país, categoría y consumo; pueden variar sin intervención de ZYNKO. / The price excludes billable charges from Meta, WhatsApp and other external providers. Each company retains its assets and pays its provider directly using its own payment method. Rates depend on provider, country, category and usage and may change independently of ZYNKO.

3. Activación / Activation
Tener WhatsApp instalado no equivale a tener acceso a Cloud API. La empresa debe registrar o autorizar su número y completar las validaciones y requisitos de Meta. Coexistence depende de elegibilidad. La suscripción no garantiza la aprobación de proveedores ni la disponibilidad de canales externos. / Installing WhatsApp does not grant Cloud API access. The business must register or authorize its number and complete Meta requirements. Coexistence depends on eligibility. Subscription does not guarantee provider approval or external channel availability.

4. Acompañamiento / Assistance
La orientación inicial, vinculación oficial y configuración básica guiada están incluidas en los planes comerciales. La asistencia no implica asumir cargos de Meta ni guardar contraseñas, códigos de verificación o datos de tarjeta. Migraciones complejas, capacitación avanzada e implementaciones especiales requieren cotización y autorización previa; no se cobran automáticamente. / Initial guidance, official connection and basic guided setup are included with paid plans. Assistance does not cover Meta charges or require storing passwords, verification codes or card data. Complex migrations, advanced training and custom implementation require a quote and prior approval; no automatic charge applies.

5. Contratación / Contracting
El registro comienza con el plan Gratis. Los planes comerciales están sujetos a disponibilidad y solicitud de contratación. Los límites de chats, canales y tokens se consultan en el catálogo vigente; no equivalen a créditos de mensajería Meta. / Registration starts with the Free plan. Paid plans are subject to availability and a subscription request. Chat, channel and token limits are shown in the current catalog; they do not represent Meta messaging credits.
LEGAL;
}
function zynkoCommercialEnsure(PDO $pdo): void {
 // DDL shared exactly with schema.sql and upgrade-omnichannel.sql.
 $sql=<<<'SQL'
CREATE TABLE IF NOT EXISTS commercial_document_versions (
 document_key VARCHAR(80) NOT NULL, version INT UNSIGNED NOT NULL, title VARCHAR(190) NOT NULL,
 content LONGTEXT NOT NULL, published_at DATETIME NOT NULL, PRIMARY KEY(document_key,version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS commercial_acceptances (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, context VARCHAR(30) NOT NULL, reference_id BIGINT UNSIGNED NOT NULL,
 tenant_id BIGINT UNSIGNED NULL, user_id BIGINT UNSIGNED NULL, documents_json JSON NOT NULL, plan_json JSON NULL,
 accepted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_commercial_acceptance(context,reference_id), INDEX(tenant_id,accepted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS commercial_services (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name_es VARCHAR(160) NOT NULL, name_en VARCHAR(160) NOT NULL,
 description_es VARCHAR(1000) NOT NULL, description_en VARCHAR(1000) NOT NULL, pricing_mode VARCHAR(20) NOT NULL DEFAULT 'quote',
 price DECIMAL(12,2) NULL, currency CHAR(3) NOT NULL DEFAULT 'USD', active TINYINT(1) NOT NULL DEFAULT 1,
 sort_order INT NOT NULL DEFAULT 0, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS channel_activation_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, requested_by BIGINT UNSIGNED NOT NULL,
 method VARCHAR(20) NOT NULL, phone VARCHAR(20) NULL, progress_json JSON NOT NULL, assistance TINYINT(1) NOT NULL DEFAULT 0,
 status VARCHAR(30) NOT NULL DEFAULT 'pending', assigned_to BIGINT UNSIGNED NULL, followup_at DATETIME NULL,
 channel_id BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX(tenant_id,status), INDEX(status,followup_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS channel_activation_history (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, request_id BIGINT UNSIGNED NOT NULL, actor_id BIGINT UNSIGNED NOT NULL,
 status VARCHAR(30) NOT NULL, note VARCHAR(1000) NOT NULL DEFAULT '', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX(request_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS commercial_plan_copy (
 plan_id BIGINT UNSIGNED NOT NULL PRIMARY KEY, summary_es VARCHAR(250) NOT NULL DEFAULT '', summary_en VARCHAR(250) NOT NULL DEFAULT '',
 note_es VARCHAR(1000) NOT NULL DEFAULT '', note_en VARCHAR(1000) NOT NULL DEFAULT '', translations_json JSON NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
 foreach(explode(';',$sql) as $statement)if(trim($statement)!=='')$pdo->exec($statement);
 $pdo->prepare("INSERT IGNORE INTO legal_documents(document_key,title,content,version,published_at) VALUES('commercial_conditions','Condiciones comerciales y activación / Commercial conditions',?,1,NOW())")->execute([zynkoCommercialText()]);
 // One-time append, preserving Owner-edited documents. Archive before publication.
 $pdo->beginTransaction();
 try{
  $q=$pdo->prepare("INSERT IGNORE INTO system_settings(setting_key,setting_value) VALUES('commercial_phase2_legal','1')");$q->execute();
  if($q->rowCount()===1){
   $notes=[
    'terms_conditions'=>'<h3>Condiciones comerciales y activación de canales</h3><p>La suscripción cubre exclusivamente las funcionalidades y límites de ZYNKO. La mensajería facturable y los servicios externos se pagan directamente al proveedor por el cliente. Las tarifas externas pueden variar. La conexión oficial requiere autorización y validación de la cuenta; no se garantiza aprobación. La asistencia básica de vinculación está incluida en los planes comerciales; servicios especiales requieren cotización y aceptación previa. Consulte las <a href="/commercial.php">Condiciones comerciales</a>.</p>',
    'privacy_policy'=>'<h3>Activación asistida y aceptación de condiciones</h3><p>Para gestionar la vinculación conservamos el número de contacto, empresa, estado, responsable, observaciones y registro de aceptación de versiones de documentos. No solicitamos contraseñas, códigos de verificación ni datos completos de tarjetas. El pago y la validación del proveedor se efectúan en sus propios sistemas.</p>',
    'data_deletion'=>'<h3>Solicitudes de activación y registros contractuales</h3><p>Puede solicitar la eliminación de solicitudes de activación y sus datos asociados. Los registros de aceptación y facturación pueden requerir conservación por obligaciones legales y seguridad. La desvinculación de ZYNKO no cancela automáticamente los cargos, cuentas o métodos de pago del proveedor externo.</p>'
   ];
   foreach($notes as $key=>$html){$q=$pdo->prepare('SELECT * FROM legal_documents WHERE document_key=? FOR UPDATE');$q->execute([$key]);$doc=$q->fetch();if(!$doc)throw new RuntimeException('Documento legal no disponible: '.$key);zynkoCommercialArchive($pdo,$doc);$base=zynkoLegalContentToHtml((string)$doc['content']);$pdo->prepare('UPDATE legal_documents SET content=?,version=version+1,published_at=NOW() WHERE document_key=?')->execute([$base.$html,$key]);}
  }
  $pdo->commit();
 }catch(Throwable $e){$pdo->rollBack();throw $e;}
 // Seed additional services once; future edits remain untouched.
 $pdo->beginTransaction();try{$q=$pdo->prepare("INSERT IGNORE INTO system_settings(setting_key,setting_value) VALUES('commercial_phase2_services','1')");$q->execute();if($q->rowCount()===1){$ins=$pdo->prepare('INSERT INTO commercial_services(name_es,name_en,description_es,description_en,pricing_mode,sort_order) VALUES(?,?,?,?,?,?)');foreach([
 ['Orientación inicial','Initial guidance','Explicación de requisitos y selección del plan.','Requirements and plan selection.','free',10],
 ['Activación asistida básica','Basic assisted activation','Configuración guiada, pruebas y capacitación inicial en planes comerciales.','Guided setup, testing and initial training on paid plans.','included',20],
 ['Migraciones complejas','Complex migrations','Alcance y precio sujetos a cotización.','Scope and price subject to quote.','quote',30],
 ['Capacitación avanzada','Advanced training','Capacitación personalizada, previa aprobación.','Custom training, subject to approval.','quote',40],
 ['Implementación especial','Custom implementation','Integraciones a medida, previa cotización.','Custom integrations, subject to quote.','quote',50]
 ] as $row)$ins->execute($row);} $pdo->commit();}catch(Throwable $e){$pdo->rollBack();throw $e;}
}
function zynkoCommercialArchive(PDO $pdo,array $doc): void {
 $pdo->prepare('INSERT IGNORE INTO commercial_document_versions(document_key,version,title,content,published_at) VALUES(?,?,?,?,?)')->execute([$doc['document_key'],$doc['version'],$doc['title'],$doc['content'],$doc['published_at']]);
}
function zynkoCommercialDocs(PDO $pdo): array {
 $rows=$pdo->query("SELECT document_key,title,content,version,published_at FROM legal_documents WHERE document_key IN ('terms_conditions','privacy_policy','commercial_conditions')")->fetchAll();$out=[];foreach($rows as $row)$out[$row['document_key']]=$row;return $out;
}
function zynkoCommercialValidate(PDO $pdo,array $data): array {
 $docs=zynkoCommercialDocs($pdo);$versions=[];
 foreach(['terms_conditions'=>'terms','privacy_policy'=>'privacy','commercial_conditions'=>'commercial'] as $key=>$field){$doc=$docs[$key]??null;if(!$doc||trim((string)$doc['content'])===''||($data[$field]??null)!=='1'||(int)($data[$field.'_version']??0)!==(int)$doc['version'])throw new RuntimeException('Debes revisar y aceptar los documentos vigentes. Actualiza el formulario si cambiaron.');}
 if(($data['activation_terms']??null)!=='1')throw new RuntimeException('Debes aceptar los requisitos de activación y los costos externos.');
 foreach($docs as $key=>$doc){zynkoCommercialArchive($pdo,$doc);$versions[$key]=['version'=>(int)$doc['version'],'sha256'=>hash('sha256',(string)$doc['content'])];}return $versions;
}
function zynkoCommercialPlanSnapshot(array $plan): array {
 $out=[];foreach(['id','code','name','monthly_price','currency','max_users','max_channels','max_webchat_sites','max_daily_chats','max_monthly_chats','allowed_channels_json','module_access_json','features_json','external_ai_included','external_ai_monthly_tokens','external_ai_channels_json'] as $key)$out[$key]=(string)($plan[$key]??'');return $out;
}
function zynkoCommercialPlanHash(array $plan): string {return hash('sha256',json_encode(zynkoCommercialPlanSnapshot($plan),JSON_THROW_ON_ERROR));}
function zynkoCommercialRecord(PDO $pdo,string $context,int $reference,array $versions,?int $tenant=null,?int $user=null,?array $plan=null): void {
 $sql='INSERT INTO commercial_acceptances(context,reference_id,tenant_id,user_id,documents_json,plan_json) VALUES(?,?,?,?,?,?)';
 if($context==='registration')$sql.=' ON DUPLICATE KEY UPDATE documents_json=IF(tenant_id IS NULL,VALUES(documents_json),documents_json),accepted_at=IF(tenant_id IS NULL,NOW(),accepted_at)';
 $pdo->prepare($sql)->execute([$context,$reference,$tenant,$user,json_encode($versions,JSON_THROW_ON_ERROR),$plan===null?null:json_encode($plan,JSON_THROW_ON_ERROR)]);
}
function zynkoCommercialServices(PDO $pdo,bool $all=false): array {return $pdo->query('SELECT * FROM commercial_services'.($all?'':' WHERE active=1').' ORDER BY sort_order,id')->fetchAll();}
function zynkoActivationStates(): array {return ['pending'=>'Pendiente','advising'=>'En asesoramiento','meta_validation'=>'Validación Meta','billing'=>'Configuración de pago','testing'=>'En pruebas','active'=>'Activado','attention'=>'Requiere atención','rejected'=>'Rechazado','cancelled'=>'Cancelado'];}
function zynkoActivationSteps(): array {return [
 ['company','Preparar cuenta empresarial','Prepare business account','Crea o selecciona tu portafolio en Meta Business Suite.','Create or choose your Meta business portfolio.','https://business.facebook.com/'],
 ['authorization','Autorizar el acceso','Authorize access','Usa la conexión oficial de ZYNKO cuando esté disponible; nunca compartas contraseñas.','Use ZYNKO official connection when available; never share passwords.',''],
 ['number','Registrar o verificar el número','Register or verify your number','Coexistence para el número existente si es elegible; Cloud API para un número disponible.','Coexistence for an eligible existing number; Cloud API for an available number.','https://business.facebook.com/wa/manage/'],
 ['validation','Completar requisitos de Meta','Complete Meta requirements','Revisa los requisitos de empresa y nombre comercial solicitados por Meta.','Review any business and display name requirements from Meta.','https://business.facebook.com/'],
 ['payment','Configurar facturación','Configure billing','Añade tu método de pago en WhatsApp Manager. ZYNKO no paga ese consumo.','Add your payment method in WhatsApp Manager. ZYNKO does not pay these charges.','https://business.facebook.com/wa/manage/'],
 ['tests','Solicitar pruebas','Request tests','Comprueba envío, recepción y plantillas con el equipo de ZYNKO. Marcar este paso no activa el canal.','Check sending, receiving and templates with ZYNKO. Checking this step does not activate the channel.','']
 ];}
function zynkoCommercialEscape(mixed $v): string {return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function zynkoCommercialLanguage(): string {
 $candidate=$_SESSION['lang']??$_SESSION['user']['locale']??$_COOKIE['zynko_public_lang']??null;
 return $candidate==='en'||($candidate===null&&str_starts_with(strtolower((string)($_SERVER['HTTP_ACCEPT_LANGUAGE']??'')),'en'))?'en':'es';
}
function zynkoCommercialPlanCopies(PDO $pdo): array {$out=[];foreach($pdo->query('SELECT * FROM commercial_plan_copy')->fetchAll() as $row)$out[(int)$row['plan_id']]=$row;return $out;}
function zynkoCommercialFeatureText(string $text,string $lang,array $copy=[]): string {
 if($lang!=='en')return $text;
 $custom=json_decode((string)($copy['translations_json']??'{}'),true)?:[];if(isset($custom[$text])&&is_string($custom[$text])&&trim($custom[$text])!=='')return $custom[$text];
 $map=[
 'NIVO Web Chat incluido'=>'NIVO Web Chat included','Mensajes ilimitados dentro de cada chat'=>'Unlimited messages within each chat','Usuarios de ZYNKO ilimitados'=>'Unlimited ZYNKO users',
 '1 sitio autorizado para NIVO Web Chat'=>'1 authorized NIVO Web Chat website','2 sitios autorizados para NIVO Web Chat'=>'2 authorized NIVO Web Chat websites','5 sitios autorizados para NIVO Web Chat'=>'5 authorized NIVO Web Chat websites','10 sitios autorizados para NIVO Web Chat'=>'10 authorized NIVO Web Chat websites',
 '5 chats nuevos por día'=>'5 new chats per day','500 chats nuevos por mes'=>'500 new chats per month','3,000 chats nuevos por mes'=>'3,000 new chats per month','10,000 chats nuevos por mes'=>'10,000 new chats per month',
 '1 conexión externa: WhatsApp Business'=>'1 external connection: WhatsApp Business','Capacidad de hasta 3 conexiones externas según canales habilitados'=>'Up to 3 external connections, subject to enabled channels','Capacidad de hasta 10 conexiones externas según canales habilitados'=>'Up to 10 external connections, subject to enabled channels',
 'API para conectar sitios y sistemas externos'=>'API for websites and external systems','Bandeja omnicanal y contactos'=>'Omnichannel inbox and contacts','API completa para integraciones externas'=>'Full external integration API','NIVO IA y automatizaciones'=>'NIVO AI and automations','Asignación de conversaciones, reportes y auditoría'=>'Conversation assignment, reports and auditing','API completa con mayor capacidad'=>'Full API with increased capacity','NIVO IA y automatizaciones avanzadas'=>'NIVO AI and advanced automations','Reportes avanzados y auditoría completa'=>'Advanced reports and full auditing','Soporte prioritario'=>'Priority support',
 'Próximamente'=>'Coming soon','Disponible'=>'Available','Más popular'=>'Most popular','Estamos trabajando para habilitar todos los canales y capacidades de este plan. Estará disponible muy pronto.'=>'We are working to enable all channels and capabilities of this plan. It will be available soon.'
 ];return $map[$text]??$text;
}
function zynkoCommercialPlanSummary(array $plan,array $copy,string $lang): string {
 $value=trim((string)($copy['summary_'.$lang]??''));if($value!=='')return $value;
 $pair=match((string)($plan['code']??'')){'free'=>['Comienza con NIVO Web Chat','Get started with NIVO Web Chat'],'starter'=>['Ideal para emprendedores','Ideal for entrepreneurs'],'pro'=>['Para equipos que quieren crecer','For growing teams'],'business'=>['Para operaciones empresariales','For business operations'],default=>['Una solución para tu empresa','A solution for your business']};return $pair[$lang==='en'?1:0];
}
function zynkoCommercialHasWhatsApp(array $plan,array $catalog): bool {return in_array('whatsapp',zynkoPlanDisplayChannels($plan,$catalog),true);}
function zynkoCommercialPlanCopySave(PDO $pdo,array $data): void {
 $id=(int)($data['plan_id']??0);$q=$pdo->prepare('SELECT features_json FROM subscription_plans WHERE id=?');$q->execute([$id]);$row=$q->fetch();if(!$row)throw new RuntimeException('Plan no encontrado.');
 $values=[];foreach(['summary_es'=>250,'summary_en'=>250,'note_es'=>1000,'note_en'=>1000] as $key=>$limit){$v=trim((string)($data[$key]??''));if(mb_strlen($v)>$limit)throw new RuntimeException('El texto supera el límite permitido.');$values[]=$v;}
 $translations=[];$features=json_decode((string)($row['features_json']??'[]'),true)?:[];foreach(array_values($features) as $index=>$feature){$en=trim((string)($data['feature_en'][$index]??''));if(mb_strlen($en)>500)throw new RuntimeException('Traducción demasiado larga.');if($en!=='')$translations[(string)$feature]=$en;}
 $pdo->prepare('INSERT INTO commercial_plan_copy(plan_id,summary_es,summary_en,note_es,note_en,translations_json) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE summary_es=VALUES(summary_es),summary_en=VALUES(summary_en),note_es=VALUES(note_es),note_en=VALUES(note_en),translations_json=VALUES(translations_json)')->execute([$id,...$values,json_encode($translations,JSON_THROW_ON_ERROR)]);
}
