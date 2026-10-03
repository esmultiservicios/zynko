<?php
$title='Bandeja de entrada'; require __DIR__.'/partials/top.php';
$pdo=appDb(); $tid=(int)$_SESSION['user']['tenant_id']; $uid=(int)$_SESSION['user']['id'];
$convs=[];$agents=[];$channels=[];$categories=[];$selected=null;$messages=[];$selectedCategories=[];$followup=null;$notes=[];$survey=null;$quickReplies=[];$nivoEnabled=false;
$channelFilter=$_GET['channel']??'';$assignmentFilter=$_GET['assignment']??'';$priorityFilter=$_GET['priority']??'';$categoryFilter=(int)($_GET['category']??0);$stateFilter=$_GET['state']??'';$attentionFilter=$_GET['attention']??'';
$canDelete=in_array((string)($_SESSION['user']['role']??''),['owner','admin'],true);
try{
 if($channelFilter===''&&$assignmentFilter===''&&$priorityFilter===''&&!$categoryFilter&&$stateFilter===''&&$attentionFilter===''){
  try{$q=$pdo->prepare('SELECT channel_type,assignment_filter,priority_filter,category_id,state_filter,attention_filter FROM inbox_preferences WHERE user_id=?');$q->execute([$uid]);if($pr=$q->fetch()){$channelFilter=$pr['channel_type'];$assignmentFilter=$pr['assignment_filter'];$priorityFilter=$pr['priority_filter'];$categoryFilter=(int)($pr['category_id']??0);$stateFilter=$pr['state_filter']??'active';$attentionFilter=$pr['attention_filter']??'all';}}catch(Throwable $e){}
 }
 $channelFilter=$channelFilter?:'all';$assignmentFilter=$assignmentFilter?:'all';$priorityFilter=$priorityFilter?:'all';$stateFilter=$stateFilter?:'active';$attentionFilter=$attentionFilter?:'all';
 if(!in_array($stateFilter,['active','resolved','archived'],true))$stateFilter='active';
 if(!in_array($attentionFilter,['all','unread','followup','waiting'],true))$attentionFilter='all';
 $q=$pdo->prepare("SELECT u.id,u.name FROM users u JOIN tenant_users tu ON tu.user_id=u.id WHERE tu.tenant_id=? AND u.status='active' ORDER BY u.name");$q->execute([$tid]);$agents=$q->fetchAll();
 $q=$pdo->prepare("SELECT id,name,type,status FROM channels WHERE tenant_id=? ORDER BY type,name");$q->execute([$tid]);$channels=$q->fetchAll();
 $q=$pdo->prepare('SELECT id,name,color FROM contact_categories WHERE tenant_id=? AND active=1 ORDER BY name');$q->execute([$tid]);$categories=$q->fetchAll();
 try{
  $pdo->exec("CREATE TABLE IF NOT EXISTS quick_replies (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,shortcut VARCHAR(80) NOT NULL,title VARCHAR(120) NOT NULL,body TEXT NOT NULL,media_json JSON NULL,team_id BIGINT UNSIGNED NULL,active TINYINT(1) DEFAULT 1,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_qr(tenant_id,shortcut),INDEX idx_qr_tenant(tenant_id,active)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  try{if(!$pdo->query("SHOW COLUMNS FROM quick_replies LIKE 'media_json'")->fetch())$pdo->exec("ALTER TABLE quick_replies ADD media_json JSON NULL AFTER body");}catch(Throwable $ignore){}
  try{if(!$pdo->query("SHOW COLUMNS FROM quick_replies LIKE 'created_at'")->fetch())$pdo->exec("ALTER TABLE quick_replies ADD created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER active, ADD updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");}catch(Throwable $ignore){}
  $qr=$pdo->prepare('SELECT id,shortcut,title,body,media_json,active FROM quick_replies WHERE tenant_id=? AND active=1 ORDER BY title,shortcut');$qr->execute([$tid]);$quickReplies=$qr->fetchAll();
 }catch(Throwable $ignore){}
 try{$bp=$pdo->prepare('SELECT enabled FROM bot_profiles WHERE tenant_id=? LIMIT 1');$bp->execute([$tid]);$nivoEnabled=(int)($bp->fetchColumn()?:0)===1;}catch(Throwable $ignore){}

 $sql="SELECT c.id,c.uuid,c.contact_id,c.assigned_user_id,c.status,c.unread_count,c.last_message_at,c.archived_at,c.priority,c.created_at,ct.name contact_name,ct.phone,ct.email,ct.avatar_url,ch.type channel_type,ch.name channel_name,u.name agent_name,
 (SELECT body FROM messages m WHERE m.conversation_id=c.id ORDER BY m.sent_at DESC,m.id DESC LIMIT 1) last_body,
 (SELECT direction FROM messages m WHERE m.conversation_id=c.id ORDER BY m.sent_at DESC,m.id DESC LIMIT 1) last_direction,
 (SELECT GROUP_CONCAT(cc.name ORDER BY cc.name SEPARATOR ' · ') FROM contact_category_map ccm JOIN contact_categories cc ON cc.id=ccm.category_id WHERE ccm.contact_id=c.contact_id AND cc.active=1) category_names
 FROM conversations c JOIN contacts ct ON ct.id=c.contact_id JOIN channels ch ON ch.id=c.channel_id LEFT JOIN users u ON u.id=c.assigned_user_id WHERE c.tenant_id=? AND c.deleted_at IS NULL";$params=[$tid];
 if($stateFilter==='active')$sql.=" AND c.archived_at IS NULL AND c.status IN ('open','pending')";
 elseif($stateFilter==='resolved')$sql.=" AND c.archived_at IS NULL AND c.status IN ('resolved','closed')";
 else $sql.=" AND c.archived_at IS NOT NULL";
 if($channelFilter!=='all'){$sql.=' AND ch.type=?';$params[]=$channelFilter;}
 if($assignmentFilter==='mine'){$sql.=' AND c.assigned_user_id=?';$params[]=$uid;}elseif($assignmentFilter==='unassigned')$sql.=' AND c.assigned_user_id IS NULL';
 if(in_array($priorityFilter,['low','normal','high','urgent'],true)){$sql.=' AND c.priority=?';$params[]=$priorityFilter;}
 if($categoryFilter>0){$sql.=' AND EXISTS(SELECT 1 FROM contact_category_map ccmf JOIN contact_categories ccf ON ccf.id=ccmf.category_id WHERE ccmf.contact_id=c.contact_id AND ccf.tenant_id=? AND ccf.active=1 AND ccf.id=?)';$params[]=$tid;$params[]=$categoryFilter;}
 if($attentionFilter==='unread')$sql.=' AND c.unread_count>0';
 elseif($attentionFilter==='followup')$sql.=" AND EXISTS(SELECT 1 FROM conversation_followups cf WHERE cf.tenant_id=c.tenant_id AND cf.conversation_id=c.id AND cf.status='pending')";
 elseif($attentionFilter==='waiting')$sql.=" AND c.last_message_at IS NOT NULL AND TIMESTAMPDIFF(MINUTE,c.last_message_at,NOW())>=15 AND (SELECT direction FROM messages lm WHERE lm.conversation_id=c.id ORDER BY lm.sent_at DESC,lm.id DESC LIMIT 1)='in'";
 $sql.=' ORDER BY COALESCE(c.last_message_at,c.created_at) DESC';
 $q=$pdo->prepare($sql);$q->execute($params);$convs=$q->fetchAll();
 $cid=(int)($_GET['conversation']??($convs[0]['id']??0));foreach($convs as $c)if((int)$c['id']===$cid)$selected=$c;
 if(!$selected&&$cid){$q=$pdo->prepare("SELECT c.id,c.uuid,c.contact_id,c.assigned_user_id,c.status,c.unread_count,c.last_message_at,c.archived_at,c.priority,c.created_at,ct.name contact_name,ct.phone,ct.email,ct.avatar_url,ch.type channel_type,ch.name channel_name,u.name agent_name FROM conversations c JOIN contacts ct ON ct.id=c.contact_id JOIN channels ch ON ch.id=c.channel_id LEFT JOIN users u ON u.id=c.assigned_user_id WHERE c.id=? AND c.tenant_id=? AND c.deleted_at IS NULL LIMIT 1");$q->execute([$cid,$tid]);$selected=$q->fetch()?:null;}
 if($selected){
  if(isset($_GET['conversation'])&&(int)$selected['unread_count']>0){$pdo->prepare('UPDATE conversations SET unread_count=0 WHERE id=? AND tenant_id=?')->execute([$selected['id'],$tid]);$selected['unread_count']=0;}
  // V2.31.78 · Conversaciones Web Chat antiguas también muestran el saludo inicial en la Bandeja.
  if(($selected['channel_type']??'')==='webchat'){
   try{
    $firstIn=$pdo->prepare("SELECT sent_at FROM messages WHERE tenant_id=? AND conversation_id=? AND direction='in' ORDER BY sent_at,id LIMIT 1");$firstIn->execute([$tid,$selected['id']]);$firstAt=$firstIn->fetchColumn();
    $hasBot=$pdo->prepare("SELECT 1 FROM messages WHERE tenant_id=? AND conversation_id=? AND direction='out' AND sender_type='bot' AND (type='greeting' OR body LIKE '%Soy NIVO%')".($firstAt?" AND sent_at<=?":"")." ORDER BY sent_at,id LIMIT 1");$ha=[$tid,$selected['id']];if($firstAt)$ha[]=$firstAt;$hasBot->execute($ha);
    if(!$hasBot->fetchColumn()){
     $brand=(string)($company?:'Tu empresa');try{$pt=(int)($pdo->query('SELECT MIN(id) FROM tenants')->fetchColumn()?:0);if($tid===$pt)$brand='ES MULTISERVICIOS';}catch(Throwable $ignoreBrand){}$greeting='¡Hola! 👋 Soy NIVO, el asistente virtual de '.$brand.'. ¿En qué puedo ayudarte hoy?';$sentAt=$firstAt?date('Y-m-d H:i:s',max(0,strtotime((string)$firstAt)-1)):date('Y-m-d H:i:s');
     $pdo->prepare("INSERT INTO messages(tenant_id,conversation_id,uuid,direction,sender_type,type,body,status,sent_at) VALUES(?,?,?,'out','bot','greeting',?,'sent',?)")->execute([$tid,$selected['id'],bin2hex(random_bytes(16)),$greeting,$sentAt]);
    }
   }catch(Throwable $ignore){}
  }
  $q=$pdo->prepare('SELECT m.*,u.name sender_name FROM messages m LEFT JOIN users u ON u.id=m.sender_user_id WHERE m.tenant_id=? AND m.conversation_id=? ORDER BY m.sent_at,m.id');$q->execute([$tid,$selected['id']]);$messages=$q->fetchAll();
  $q=$pdo->prepare('SELECT category_id FROM contact_category_map WHERE contact_id=?');$q->execute([$selected['contact_id']]);$selectedCategories=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
  $q=$pdo->prepare("SELECT follow_up_at,note FROM conversation_followups WHERE tenant_id=? AND conversation_id=? AND status='pending' ORDER BY id DESC LIMIT 1");$q->execute([$tid,$selected['id']]);$followup=$q->fetch()?:null;
  $q=$pdo->prepare('SELECT cn.body,cn.created_at,u.name FROM conversation_notes cn JOIN users u ON u.id=cn.user_id WHERE cn.tenant_id=? AND cn.conversation_id=? ORDER BY cn.id DESC LIMIT 5');$q->execute([$tid,$selected['id']]);$notes=$q->fetchAll();
  try{$q=$pdo->prepare('SELECT rating,comment,requested_at,responded_at FROM conversation_surveys WHERE tenant_id=? AND conversation_id=? LIMIT 1');$q->execute([$tid,$selected['id']]);$survey=$q->fetch()?:null;}catch(Throwable $ignoreSurvey){$survey=null;}
 }
}catch(Throwable $e){}
function channelMeta(string $type):array{return match($type){'whatsapp'=>['fa-brands fa-whatsapp','WhatsApp'],'messenger'=>['fa-brands fa-facebook-messenger','Messenger'],'instagram'=>['fa-brands fa-instagram','Instagram'],'webchat'=>['fa-solid fa-message','NIVO Web Chat'],default=>['fa-solid fa-comments',ucfirst($type)]};}
$filterQuery='&channel='.urlencode($channelFilter).'&assignment='.urlencode($assignmentFilter).'&priority='.urlencode($priorityFilter).'&category='.(int)$categoryFilter.'&state='.urlencode($stateFilter).'&attention='.urlencode($attentionFilter);
?>
<div class="inbox-premium-toolbar">
 <div class="inbox-view-title"><div><b>Bandeja omnicanal</b><small>Todos tus canales, categorías, seguimiento y prioridades en una sola vista.</small></div><span class="view-count"><?=count($convs)?> conversaciones</span></div>
 <form class="inbox-filters inbox-filters-advanced" id="inboxFilters" method="get"><input type="hidden" name="page" value="inbox">
  <label><span>Canal</span><select name="channel" class="select2 inbox-filter"><option value="all">Todos los canales</option><?php $filterTypes=array_values(array_unique(array_merge(['webchat'],array_column($channels,'type')))); foreach($filterTypes as $type):$cm=channelMeta($type);?><option value="<?=htmlspecialchars($type)?>" <?=$channelFilter===$type?'selected':''?>><?=htmlspecialchars($cm[1])?></option><?php endforeach?></select></label>
  <label><span>Asignación</span><select name="assignment" class="select2 inbox-filter"><option value="all" <?=$assignmentFilter==='all'?'selected':''?>>Todos</option><option value="mine" <?=$assignmentFilter==='mine'?'selected':''?>>Mis conversaciones</option><option value="unassigned" <?=$assignmentFilter==='unassigned'?'selected':''?>>Sin asignar</option></select></label>
  <label><span>Prioridad</span><select name="priority" class="select2 inbox-filter"><option value="all" <?=$priorityFilter==='all'?'selected':''?>>Todas</option><option value="low" <?=$priorityFilter==='low'?'selected':''?>>Baja</option><option value="normal" <?=$priorityFilter==='normal'?'selected':''?>>Normal</option><option value="high" <?=$priorityFilter==='high'?'selected':''?>>Alta</option><option value="urgent" <?=$priorityFilter==='urgent'?'selected':''?>>Urgente</option></select></label>
  <label><span>Categoría</span><select name="category" class="select2 inbox-filter category-filter"><option value="0">Todas las categorías</option><?php foreach($categories as $cat):?><option value="<?=$cat['id']?>" <?=$categoryFilter===(int)$cat['id']?'selected':''?>><?=htmlspecialchars($cat['name'])?></option><?php endforeach?></select></label>
  <label><span>Vista</span><select name="state" class="select2 inbox-filter"><option value="active" <?=$stateFilter==='active'?'selected':''?>>Activas</option><option value="resolved" <?=$stateFilter==='resolved'?'selected':''?>>Resueltas</option><option value="archived" <?=$stateFilter==='archived'?'selected':''?>>Archivadas</option></select></label>
  <label><span>Atención</span><select name="attention" class="select2 inbox-filter"><option value="all" <?=$attentionFilter==='all'?'selected':''?>>Todas</option><option value="unread" <?=$attentionFilter==='unread'?'selected':''?>>Sin leer</option><option value="followup" <?=$attentionFilter==='followup'?'selected':''?>>Con seguimiento</option><option value="waiting" <?=$attentionFilter==='waiting'?'selected':''?>>Esperando +15 min</option></select></label>
  <div class="inbox-reset-field"><span class="inbox-filter-label-spacer" aria-hidden="true">&nbsp;</span><button class="soft clear-inbox-filters" type="button" title="Limpiar filtros"><i class="fa-solid fa-rotate-left"></i><span>Restablecer</span></button></div>
 </form>
</div>
<div class="inbox-layout">
<section class="panel conv-list">
 <div class="panel-head"><div><b>Conversaciones</b><small>Vista guardada automáticamente</small></div><div class="head-actions"><button class="soft" data-open="newConversationModal"><i class="fa-solid fa-plus"></i> Nueva</button><button class="soft" id="bulkToggle"><i class="fa-solid fa-list-check"></i> Seleccionar</button></div></div>
 <div class="search-wrap inbox-search"><i class="fa-solid fa-magnifying-glass"></i><input class="list-search" placeholder="Buscar cliente, teléfono, mensaje o categoría"><button class="clear-search" hidden><i class="fa-solid fa-xmark"></i></button></div>
 <div class="bulk-bar" id="bulkBar" hidden><b><span id="bulkCount">0</span> seleccionadas</b><button class="soft" id="bulkAssign" type="button"><i class="fa-solid fa-user-check"></i> Asignarme</button><button class="soft" id="bulkResolve" type="button"><i class="fa-solid fa-check"></i> Resolver</button><button class="soft" id="bulkArchive" type="button"><i class="fa-solid fa-box-archive"></i> Archivar</button></div>
 <div class="conversation-scroll">
 <?php if(!$convs):?>
  <div class="inbox-empty-state inbox-empty-list"><div class="empty-orbit" aria-hidden="true"><span class="orbit-ring"></span><span class="orbit-core"><i class="fa-regular fa-comments"></i></span><span class="orbit-channel wa"><i class="fa-brands fa-whatsapp"></i></span><span class="orbit-channel msg"><i class="fa-brands fa-facebook-messenger"></i></span><span class="orbit-channel ig"><i class="fa-brands fa-instagram"></i></span></div><div class="empty-copy"><span class="empty-eyebrow"><i class="fa-solid fa-sparkles"></i> Bandeja preparada</span><b>No hay conversaciones en esta vista</b><span>Prueba otro filtro o espera nuevos mensajes de tus canales conectados.</span></div></div>
 <?php else:foreach($convs as $c):$cm=channelMeta($c['channel_type']);$wait=$c['last_message_at']?max(0,time()-strtotime($c['last_message_at'])):0;$sla=$c['last_direction']==='in'&&$wait>900;?>
  <a class="conversation searchable <?=($selected&&$selected['id']===$c['id'])?'active':''?>" href="?page=inbox&conversation=<?=$c['id']?><?=$filterQuery?>" data-conversation-id="<?=$c['id']?>" data-conversation-status="<?=htmlspecialchars($c['status'])?>" data-conversation-archived="<?=!empty($c['archived_at'])?'1':'0'?>" data-search="<?=htmlspecialchars(mb_strtolower(($c['contact_name']??'').' '.($c['phone']??'').' '.($c['last_body']??'').' '.($c['category_names']??'')))?>">
   <span class="bulk-check"><input type="checkbox" tabindex="-1"></span><span class="avatar channel-avatar"><?=htmlspecialchars(strtoupper(substr($c['contact_name']?:'C',0,1)))?><i class="<?=$cm[0]?> channel-badge <?=htmlspecialchars($c['channel_type'])?>"></i></span>
   <div class="conversation-copy"><div class="conversation-line"><b><?=htmlspecialchars($c['contact_name']?:'Contacto')?></b><?php if((int)$c['unread_count']>0):?><span class="unread-badge"><?=(int)$c['unread_count']?></span><?php endif?></div><p><?=htmlspecialchars($c['last_body']?:'Sin mensajes todavía')?></p>
    <div class="conversation-meta"><span><i class="<?=$cm[0]?>"></i><?=htmlspecialchars($cm[1])?></span><?php if($c['agent_name']):?><span><i class="fa-solid fa-user"></i><?=htmlspecialchars($c['agent_name'])?></span><?php else:?><span class="unassigned"><i class="fa-regular fa-circle"></i>Sin asignar</span><?php endif?><?php if($sla):?><span class="sla-risk"><i class="fa-solid fa-clock"></i>Esperando <?=floor($wait/60)?> min</span><?php endif?></div>
    <?php if(!empty($c['category_names'])):?><div class="conversation-category"><i class="fa-solid fa-tags"></i><?=htmlspecialchars($c['category_names'])?></div><?php endif?>
   </div><span class="priority-dot <?=htmlspecialchars($c['priority'])?>" title="Prioridad <?=htmlspecialchars($c['priority'])?>"></span>
  </a>
 <?php endforeach;endif?>
 </div>
</section>
<section class="panel chat"<?=$selected?' data-current-conversation="'.(int)$selected['id'].'" data-current-status="'.htmlspecialchars($selected['status']).'" data-current-archived="'.(!empty($selected['archived_at'])?'1':'0').'"':''?>>
<?php if(!$selected):?>
 <div class="inbox-empty-state inbox-empty-chat"><div class="empty-hero-icon" aria-hidden="true"><i class="fa-solid fa-inbox"></i><span><i class="fa-solid fa-bolt"></i></span></div><div class="empty-copy"><span class="empty-eyebrow"><i class="fa-solid fa-circle-check"></i> Centro omnicanal listo</span><h2>Las conversaciones llegarán aquí</h2><p>Selecciona una conversación para responder, asignar, resumir, archivar o gestionar el seguimiento.</p><div class="empty-steps"><span><i>1</i><b>Recibe</b><small>Todos tus canales en una bandeja.</small></span><span><i>2</i><b>Organiza</b><small>Categorías, prioridad y responsable.</small></span><span><i>3</i><b>Atiende</b><small>Equipo y NIVO en el mismo flujo.</small></span></div></div></div>
<?php else:$scm=channelMeta($selected['channel_type']);?>
 <div class="chat-head"><div class="chat-identity"><b><?=htmlspecialchars($selected['contact_name']?:'Contacto')?></b><small><i class="<?=$scm[0]?>"></i> <?=htmlspecialchars($scm[1].' · '.($selected['phone']?:$selected['channel_name']))?></small></div><div class="head-actions chat-actions"><button class="soft history-jump" type="button" data-chat-scroll="start" title="Ir al inicio del chat"><i class="fa-solid fa-arrow-up"></i> Inicio</button><button class="soft history-jump" type="button" data-chat-scroll="end" title="Ir al último mensaje"><i class="fa-solid fa-arrow-down"></i> Último</button><button class="soft" id="autoAssignBtn" data-conversation="<?=$selected['id']?>"><i class="fa-solid fa-shuffle"></i> Autoasignar</button><button class="soft" data-open="assignModal"><i class="fa-solid fa-user-check"></i> Asignar</button><button class="soft" id="nivoSummaryBtn" type="button"><i class="fa-solid fa-wand-magic-sparkles"></i> Resumir</button><button class="soft" type="button" data-open="conversationActionsModal"><i class="fa-solid fa-ellipsis"></i> Más</button></div></div>
 <div class="nivo-summary" id="nivoSummary" hidden></div>
 <div class="messages" id="messages"><?php foreach($messages as $m):?><div class="message-wrap <?=$m['direction']==='out'?'out':'in'?> <?=(($m['sender_type']??'')==='bot')?'is-bot':''?>"><?php if($m['direction']==='out' && !empty($m['sender_name'])):?><b class="message-sender"><?=htmlspecialchars(mb_strtoupper($m['sender_name'].' · '.$company))?></b><?php elseif(($m['sender_type']??'')==='bot'):?><b class="message-sender"><?=htmlspecialchars('NIVO · '.mb_strtoupper($company))?></b><?php endif?><p class="<?=$m['direction']==='out'?'me':'them'?>"><?=nl2br(htmlspecialchars($m['body']??''))?></p><?php $msgMedia=json_decode((string)($m['media_json']??''),true)?:[];if($msgMedia):?><div class="message-media"><?php foreach($msgMedia as $asset):$mime=(string)($asset['mime']??'');$url=(string)($asset['url']??'');$name=(string)($asset['name']??'Adjunto');if(str_starts_with($mime,'image/')):?><a href="<?=htmlspecialchars($url)?>" target="_blank" rel="noopener"><img src="<?=htmlspecialchars($url)?>" alt="<?=htmlspecialchars($name)?>"></a><?php else:?><a class="message-file" href="<?=htmlspecialchars($url)?>" target="_blank" rel="noopener"><i class="fa-solid fa-paperclip"></i><span><?=htmlspecialchars($name)?></span></a><?php endif;endforeach?></div><?php endif?></div><?php endforeach?></div>
 <form class="composer pro-composer" id="messageForm"><input type="hidden" name="action" value="message_send"><input type="hidden" name="conversation_id" value="<?=$selected['id']?>"><input type="hidden" name="quick_reply_id" id="quickReplyId" value=""><div class="composer-tools"><button type="button" title="Emoji" id="emojiBtn"><i class="fa-regular fa-face-smile"></i></button><button type="button" title="Adjuntar" id="attachBtn"><i class="fa-solid fa-paperclip"></i></button><button type="button" title="Respuestas rápidas" id="quickReplyBtn"><i class="fa-solid fa-bolt"></i></button><button type="button" title="<?= $nivoEnabled ? 'Sugerir respuesta con NIVO IA' : 'NIVO IA está inactivo' ?>" id="nivoAssist" class="<?= $nivoEnabled ? 'is-active' : 'is-inactive' ?>" data-nivo-enabled="<?= $nivoEnabled ? '1' : '0' ?>"><i class="fa-solid fa-wand-magic-sparkles"></i> NIVO IA</button><span class="composer-tip"><kbd>Ctrl</kbd> + <kbd>Enter</kbd> para enviar</span><input type="file" id="chatFile" multiple hidden accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.txt"></div><div class="attachment-preview" id="attachmentPreview"></div><div class="composer-line"><textarea name="body" id="messageInput" placeholder="Escribe un mensaje…" rows="2"></textarea><button class="primary composer-send-btn" type="submit" title="Enviar mensaje"><i class="fa-solid fa-paper-plane"></i><span>Enviar</span></button></div><div class="emoji-picker emoji-picker-premium" id="emojiPicker" hidden>
 <div class="emoji-tabs" role="tablist" aria-label="Categorías de emojis">
  <button type="button" class="emoji-tab active" data-emoji-tab="frecuentes" title="Frecuentes">😀</button>
  <button type="button" class="emoji-tab" data-emoji-tab="caras" title="Caras">😊</button>
  <button type="button" class="emoji-tab" data-emoji-tab="gestos" title="Gestos">👍</button>
  <button type="button" class="emoji-tab" data-emoji-tab="corazones" title="Corazones">❤️</button>
  <button type="button" class="emoji-tab" data-emoji-tab="objetos" title="Objetos">🎉</button>
 </div>
 <div class="emoji-groups">
  <div class="emoji-group active" data-emoji-group="frecuentes"><button type="button" data-emoji="😀">😀</button><button type="button" data-emoji="😂">😂</button><button type="button" data-emoji="😊">😊</button><button type="button" data-emoji="😍">😍</button><button type="button" data-emoji="👍">👍</button><button type="button" data-emoji="🙏">🙏</button><button type="button" data-emoji="❤️">❤️</button><button type="button" data-emoji="✅">✅</button><button type="button" data-emoji="🎉">🎉</button><button type="button" data-emoji="👋">👋</button><button type="button" data-emoji="🔥">🔥</button><button type="button" data-emoji="😎">😎</button></div>
  <div class="emoji-group" data-emoji-group="caras"><button type="button" data-emoji="😀">😀</button><button type="button" data-emoji="😃">😃</button><button type="button" data-emoji="😄">😄</button><button type="button" data-emoji="😁">😁</button><button type="button" data-emoji="😆">😆</button><button type="button" data-emoji="😅">😅</button><button type="button" data-emoji="😂">😂</button><button type="button" data-emoji="🤣">🤣</button><button type="button" data-emoji="😊">😊</button><button type="button" data-emoji="😇">😇</button><button type="button" data-emoji="🙂">🙂</button><button type="button" data-emoji="😉">😉</button><button type="button" data-emoji="😍">😍</button><button type="button" data-emoji="😘">😘</button><button type="button" data-emoji="🤔">🤔</button><button type="button" data-emoji="😢">😢</button><button type="button" data-emoji="😭">😭</button><button type="button" data-emoji="😎">😎</button></div>
  <div class="emoji-group" data-emoji-group="gestos"><button type="button" data-emoji="👍">👍</button><button type="button" data-emoji="👎">👎</button><button type="button" data-emoji="👏">👏</button><button type="button" data-emoji="🙌">🙌</button><button type="button" data-emoji="🙏">🙏</button><button type="button" data-emoji="🤝">🤝</button><button type="button" data-emoji="👌">👌</button><button type="button" data-emoji="✌️">✌️</button><button type="button" data-emoji="🤞">🤞</button><button type="button" data-emoji="👋">👋</button><button type="button" data-emoji="💪">💪</button><button type="button" data-emoji="☝️">☝️</button></div>
  <div class="emoji-group" data-emoji-group="corazones"><button type="button" data-emoji="❤️">❤️</button><button type="button" data-emoji="🩷">🩷</button><button type="button" data-emoji="🧡">🧡</button><button type="button" data-emoji="💛">💛</button><button type="button" data-emoji="💚">💚</button><button type="button" data-emoji="💙">💙</button><button type="button" data-emoji="💜">💜</button><button type="button" data-emoji="🤍">🤍</button><button type="button" data-emoji="🖤">🖤</button><button type="button" data-emoji="💕">💕</button><button type="button" data-emoji="💯">💯</button></div>
  <div class="emoji-group" data-emoji-group="objetos"><button type="button" data-emoji="🎉">🎉</button><button type="button" data-emoji="🎊">🎊</button><button type="button" data-emoji="✅">✅</button><button type="button" data-emoji="❌">❌</button><button type="button" data-emoji="⚠️">⚠️</button><button type="button" data-emoji="📌">📌</button><button type="button" data-emoji="📎">📎</button><button type="button" data-emoji="💡">💡</button><button type="button" data-emoji="🚀">🚀</button><button type="button" data-emoji="🔥">🔥</button><button type="button" data-emoji="⭐">⭐</button><button type="button" data-emoji="📞">📞</button><button type="button" data-emoji="💬">💬</button><button type="button" data-emoji="📧">📧</button></div>
 </div>
</div></form>
<?php endif?>
</section>
<section class="panel info">
<?php if($selected):?>
 <div class="info-title"><b>Cliente 360°</b><span class="channel-health-mini"><i class="<?=$scm[0]?>"></i><?=htmlspecialchars($scm[1])?></span></div><div class="profile"><span class="avatar big"><?=htmlspecialchars(strtoupper(substr($selected['contact_name']?:'C',0,1)))?></span><h3><?=htmlspecialchars($selected['contact_name']?:'Contacto')?></h3><small><?=htmlspecialchars($selected['phone']?:($selected['email']?:'Sin datos de contacto'))?></small></div>
 <div class="customer-facts"><span class="fact-card"><small><i class="fa-solid fa-user-check"></i> Asignado</small><b><?=htmlspecialchars($selected['agent_name']?:'Sin asignar')?></b></span><span class="fact-card"><small><i class="fa-solid fa-flag"></i> Prioridad</small><b><?=htmlspecialchars(ucfirst($selected['priority']))?></b></span><span class="fact-card"><small><i class="fa-solid fa-circle-info"></i> Estado</small><b><?=!empty($selected['archived_at'])?'Archivada':htmlspecialchars(ucfirst($selected['status']))?></b></span></div><?php if($survey):?><div class="customer-survey-status"><span><i class="fa-solid fa-star"></i></span><div><small>Satisfacción</small><b><?=!empty($survey['responded_at'])?((int)$survey['rating'].' / 5'):'Encuesta enviada'?></b><?php if(!empty($survey['comment'])):?><em><?=htmlspecialchars($survey['comment'])?></em><?php endif?></div></div><?php endif?>
 <form id="contactProfileForm" class="info-form"><input type="hidden" name="action" value="contact_update"><input type="hidden" name="contact_id" value="<?=$selected['contact_id']?>"><input type="hidden" name="conversation_id" value="<?=$selected['id']?>"><div class="field"><label>Nombre</label><input name="name" value="<?=htmlspecialchars($selected['contact_name']??'')?>" required></div><div class="field"><label>WhatsApp / teléfono</label><input name="phone" value="<?=htmlspecialchars($selected['phone']??'')?>" placeholder="+504…"></div><div class="field"><label>Prioridad</label><select name="priority" class="select2"><option value="low" <?=$selected['priority']==='low'?'selected':''?>>Baja</option><option value="normal" <?=$selected['priority']==='normal'?'selected':''?>>Normal</option><option value="high" <?=$selected['priority']==='high'?'selected':''?>>Alta</option><option value="urgent" <?=$selected['priority']==='urgent'?'selected':''?>>Urgente</option></select></div><button class="soft wide"><i class="fa-solid fa-floppy-disk"></i> Guardar contacto</button></form>
 <div class="client360-more-wrap">
  <button type="button" class="client360-more-launch" data-open="client360DetailsModal">
   <span class="client360-more-launch-icon"><i class="fa-solid fa-sliders"></i></span>
   <span class="client360-more-launch-copy"><b>Más opciones</b><small>Categorías, seguimiento y notas internas</small></span>
   <span class="client360-more-launch-arrow"><i class="fa-solid fa-arrow-up-right-from-square"></i></span>
  </button>
 </div>
<?php else:?><div class="empty-compact"><i class="fa-solid fa-circle-info"></i><span>Información del contacto</span></div><?php endif?>
</section>
</div>
<div class="zynko-context-menu" id="conversationContextMenu" role="menu" aria-hidden="true">
 <div class="context-menu-head"><span class="context-menu-icon"><i class="fa-solid fa-comments"></i></span><div><b>Conversación</b><small>Acciones rápidas</small></div></div>
 <button type="button" data-context-action="open"><i class="fa-solid fa-arrow-up-right-from-square"></i><span><b>Abrir conversación</b><small>Ver el historial completo</small></span></button>
 <button type="button" data-context-action="unread"><i class="fa-regular fa-envelope"></i><span><b>Marcar como no leída</b><small>Destacarla nuevamente</small></span></button>
 <button type="button" data-context-action="resolve"><i class="fa-solid fa-circle-check"></i><span><b>Finalizar chat</b><small>Cierra la atención y conserva todo el historial</small></span></button>
 <button type="button" data-context-action="archive"><i class="fa-solid fa-box-archive"></i><span><b>Archivar</b><small>Ocultar de la vista activa</small></span></button>
 <?php if($canDelete):?><div class="context-menu-separator"></div><button type="button" class="danger" data-context-action="delete"><i class="fa-solid fa-trash-can"></i><span><b>Eliminar con autorización</b><small>Solicita contraseña y audita</small></span></button><?php endif?>
</div>
<div class="modal-shell" id="newConversationModal"><div class="modal-card small-modal"><div class="modal-head"><div><b>Iniciar conversación</b><small>Crea una conversación desde un canal configurado.</small></div><button class="modal-close"><i class="fa-solid fa-xmark"></i></button></div><form id="conversationCreateForm"><input type="hidden" name="action" value="conversation_create"><div class="field"><label>Contacto</label><input name="contact_name" required placeholder="Nombre del cliente"></div><div class="field"><label>Teléfono / identificador</label><input name="address" placeholder="+504… o identificador del canal"></div><div class="field"><label>Canal</label><select name="channel_id" class="select2" required><option value="">Seleccionar…</option><?php foreach($channels as $ch):?><option value="<?=$ch['id']?>"><?=htmlspecialchars($ch['name'].' · '.ucfirst($ch['type']))?></option><?php endforeach?></select></div><div class="modal-actions"><button type="button" class="soft modal-close"><i class="fa-solid fa-xmark"></i> Cancelar</button><button class="primary" <?=$channels?'':'disabled'?>> <i class="fa-solid fa-comment-medical"></i> Crear conversación</button></div></form></div></div>
<?php if($selected):?>
<div class="modal-shell" id="assignModal"><div class="modal-card small-modal"><div class="modal-head"><div><b>Asignar conversación</b><small>Selecciona un usuario activo.</small></div><button class="modal-close"><i class="fa-solid fa-xmark"></i></button></div><form id="assignForm"><input type="hidden" name="action" value="conversation_assign"><input type="hidden" name="conversation_id" value="<?=$selected['id']?>"><div class="field"><label>Usuario</label><select name="user_id" class="select2" required><option value="">Seleccionar…</option><?php foreach($agents as $a):?><option value="<?=$a['id']?>" <?=$selected['assigned_user_id']==$a['id']?'selected':''?>><?=htmlspecialchars($a['name'])?></option><?php endforeach?></select></div><div class="modal-actions"><button type="button" class="soft modal-close"><i class="fa-solid fa-xmark"></i> Cancelar</button><button class="primary"><i class="fa-solid fa-user-check"></i> Asignar</button></div></form></div></div>
<div class="modal-shell" id="conversationActionsModal"><div class="modal-card small-modal conversation-actions-modal"><div class="modal-head"><div><b>Gestionar conversación</b><small>Ordena el ciclo de atención sin perder el historial.</small></div><button class="modal-close"><i class="fa-solid fa-xmark"></i></button></div><div class="conversation-action-grid">
 <button type="button" class="conversation-state-action" data-action="unread" data-conversation="<?=$selected['id']?>"><i class="fa-regular fa-envelope"></i><span><b>Marcar como no leída</b><small>La vuelve a destacar en la bandeja.</small></span></button>
 <?php if(in_array($selected['status'],['resolved','closed'],true)):?><button type="button" class="conversation-state-action" data-action="reopen" data-conversation="<?=$selected['id']?>"><i class="fa-solid fa-arrow-rotate-left"></i><span><b>Reabrir</b><small>Devuelve la conversación a atención activa.</small></span></button><?php else:?><button type="button" class="conversation-state-action" data-action="resolve" data-conversation="<?=$selected['id']?>"><i class="fa-solid fa-circle-check"></i><span><b>Finalizar chat</b><small>Cierra la atención, conserva el historial y solicita satisfacción en Web Chat.</small></span></button><?php endif?>
 <?php if(!empty($selected['archived_at'])):?><button type="button" class="conversation-state-action" data-action="restore" data-conversation="<?=$selected['id']?>"><i class="fa-solid fa-box-open"></i><span><b>Restaurar</b><small>Regresa la conversación a la bandeja.</small></span></button><?php else:?><button type="button" class="conversation-state-action" data-action="archive" data-conversation="<?=$selected['id']?>"><i class="fa-solid fa-box-archive"></i><span><b>Archivar</b><small>Ocúltala de la vista activa sin eliminarla.</small></span></button><?php endif?>
 <?php if($canDelete):?><button type="button" class="conversation-delete-action danger-action" data-conversation="<?=$selected['id']?>"><i class="fa-solid fa-trash-can"></i><span><b>Eliminar con autorización</b><small>Requiere tu contraseña y conserva auditoría.</small></span></button><?php endif?>
</div><div class="modal-actions"><button type="button" class="soft modal-close"><i class="fa-solid fa-xmark"></i> Cerrar</button></div></div></div>
<?php endif?>

<?php if($selected):?>
<div class="modal-shell" id="client360DetailsModal" aria-hidden="true">
 <div class="modal-card client360-details-modal">
  <div class="modal-head">
   <div class="modal-title-with-icon"><span class="modal-title-icon"><i class="fa-solid fa-address-card"></i></span><span class="modal-title-copy"><b>Opciones de Cliente 360°</b><small>Categorías, seguimiento y notas internas de <?=htmlspecialchars($selected['contact_name']?:'este contacto')?>.</small></span></div>
   <button type="button" class="modal-close"><i class="fa-solid fa-xmark"></i></button>
  </div>
  <div class="client360-details-body">
   <section class="client360-modal-section">
    <div class="client360-modal-section-head"><span><i class="fa-solid fa-tags"></i></span><div><b>Categorías</b><small>Organiza este contacto para filtros y seguimiento.</small></div><button type="button" class="icon-mini" data-open="categoryModal" title="Crear categoría"><i class="fa-solid fa-plus"></i></button></div>
    <form id="contactCategoriesForm"><input type="hidden" name="action" value="contact_categories_save"><input type="hidden" name="contact_id" value="<?=$selected['contact_id']?>"><div class="tag-checks"><?php foreach($categories as $cat):?><label class="tag-check"><input type="checkbox" name="categories[]" value="<?=$cat['id']?>" <?=in_array((int)$cat['id'],$selectedCategories,true)?'checked':''?>><span><?=htmlspecialchars($cat['name'])?></span></label><?php endforeach?></div><button class="soft wide"><i class="fa-solid fa-tags"></i> Guardar categorías</button></form>
   </section>
   <section class="client360-modal-section">
    <div class="client360-modal-section-head"><span><i class="fa-solid fa-bell"></i></span><div><b>Seguimiento</b><small>Programa un recordatorio para retomar esta conversación.</small></div></div>
    <form id="followupForm" class="followup-form"><input type="hidden" name="action" value="followup_save"><input type="hidden" name="conversation_id" value="<?=$selected['id']?>"><div class="settings-grid"><div class="field"><label>Fecha y hora</label><input type="datetime-local" name="follow_up_at" value="<?=$followup?htmlspecialchars(date('Y-m-d\TH:i',strtotime($followup['follow_up_at']))):''?>" required></div><div class="field"><label>Motivo</label><input name="follow_up_note" value="<?=htmlspecialchars($followup['note']??'')?>" placeholder="Ej. Dar seguimiento a la solicitud"></div></div><button class="soft"><i class="fa-solid fa-bell"></i> Programar seguimiento</button></form>
   </section>
   <section class="client360-modal-section">
    <div class="client360-modal-section-head"><span><i class="fa-solid fa-note-sticky"></i></span><div><b>Notas internas</b><small>Información privada para tu equipo.</small></div></div>
    <form id="noteForm"><input type="hidden" name="action" value="conversation_note_add"><input type="hidden" name="conversation_id" value="<?=$selected['id']?>"><div class="field"><textarea name="note" rows="3" placeholder="Nota privada para el equipo; puedes usar @nombre"></textarea></div><button class="soft"><i class="fa-solid fa-note-sticky"></i> Agregar nota</button></form><?php foreach($notes as $n):?><div class="mini-note"><b><?=htmlspecialchars($n['name'])?></b><span><?=htmlspecialchars($n['body'])?></span></div><?php endforeach?>
   </section>
  </div>
  <div class="modal-actions"><button type="button" class="soft modal-close"><i class="fa-solid fa-xmark"></i> Cerrar</button></div>
 </div>
</div>
<?php endif?>

<div class="modal-shell" id="quickReplyModal" aria-hidden="true">
 <div class="modal-card quick-reply-modal">
  <div class="modal-head">
   <div class="modal-title-with-icon"><span class="modal-title-icon"><i class="fa-solid fa-bolt"></i></span><span class="modal-title-copy"><b>Respuestas rápidas</b><small>Inserta respuestas frecuentes o crea plantillas con formato tipo WhatsApp y adjuntos.</small></span></div>
   <button type="button" class="modal-close"><i class="fa-solid fa-xmark"></i></button>
  </div>
  <div class="quick-reply-modal-body">
   <section class="quick-reply-library">
    <div class="quick-reply-library-head"><div><b>Biblioteca</b><small>Haz clic para usar una respuesta en el chat.</small></div><button type="button" class="primary" id="quickReplyNew"><i class="fa-solid fa-plus"></i> Nueva</button></div>
    <div class="quick-reply-list" id="quickReplyList">
     <?php if(!$quickReplies):?><div class="empty-compact quick-reply-empty"><i class="fa-solid fa-bolt"></i><b>Aún no hay respuestas rápidas</b><span>Crea la primera para reutilizarla en cualquier conversación.</span></div><?php endif?>
     <?php foreach($quickReplies as $qr):$media=json_decode((string)($qr['media_json']??''),true)?:[];?>
      <button type="button" class="quick-reply-item" data-quick-reply-id="<?=$qr['id']?>" data-quick-reply-body="<?=htmlspecialchars($qr['body'],ENT_QUOTES,'UTF-8')?>" data-quick-reply-media="<?=htmlspecialchars(json_encode($media,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),ENT_QUOTES,'UTF-8')?>">
       <span class="quick-reply-item-icon"><i class="fa-solid fa-bolt"></i></span>
       <span class="quick-reply-item-copy"><b><?=htmlspecialchars($qr['title'])?></b><small>/<?=htmlspecialchars($qr['shortcut'])?> · <?=htmlspecialchars(mb_substr(preg_replace('/\s+/u',' ',strip_tags($qr['body'])),0,90))?></small><?php if($media):?><em><i class="fa-solid fa-paperclip"></i> <?=count($media)?> adjunto(s)</em><?php endif?></span>
       <span class="quick-reply-item-actions"><i class="fa-solid fa-arrow-right"></i></span>
      </button>
     <?php endforeach?>
    </div>
   </section>
   <form id="quickReplyForm" class="quick-reply-editor" enctype="multipart/form-data" hidden>
    <input type="hidden" name="action" value="quick_reply_save">
    <input type="hidden" name="quick_reply_id" value="0">
    <div class="quick-reply-editor-head"><div><b>Nueva respuesta rápida</b><small>El formato usa sintaxis compatible con WhatsApp.</small></div><button type="button" class="soft" id="quickReplyBack"><i class="fa-solid fa-arrow-left"></i> Biblioteca</button></div>
    <div class="settings-grid">
     <div class="field"><label>Título</label><input name="title" maxlength="120" required placeholder="Ej. Información de servicios"></div>
     <div class="field"><label>Atajo</label><div class="input-prefix"><span>/</span><input name="shortcut" maxlength="80" required placeholder="servicios"></div></div>
     <div class="field full"><label>Mensaje</label>
      <div class="whatsapp-format-toolbar" aria-label="Formato tipo WhatsApp">
       <button type="button" data-qr-format="bold" title="Negrita"><i class="fa-solid fa-bold"></i></button>
       <button type="button" data-qr-format="italic" title="Cursiva"><i class="fa-solid fa-italic"></i></button>
       <button type="button" data-qr-format="strike" title="Tachado"><i class="fa-solid fa-strikethrough"></i></button>
       <button type="button" data-qr-format="code" title="Código"><i class="fa-solid fa-code"></i></button>
       <span>WhatsApp: <b>*negrita*</b> · <i>_cursiva_</i> · ~tachado~ · ```código```</span>
      </div>
      <textarea name="body" id="quickReplyBody" rows="7" required maxlength="5000" placeholder="Escribe la respuesta que deseas reutilizar…"></textarea>
     </div>
     <div class="field full quick-reply-upload-field">
      <label>Adjunto opcional</label>
      <label class="upload-zone quick-reply-upload-zone" id="quickReplyUploadZone" tabindex="0" for="quickReplyMedia">
       <span class="upload-zone-main"><i class="fa-solid fa-cloud-arrow-up"></i><b>Arrastra, pega o selecciona un archivo</b></span>
       <small>Imagen, video, audio, PDF, Word, Excel o TXT · máximo 10 MB.</small>
       <span class="quick-reply-upload-cta"><i class="fa-solid fa-folder-open"></i> Seleccionar archivo</span>
       <input type="file" name="media" id="quickReplyMedia" accept="image/*,video/mp4,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.txt" hidden>
      </label>
      <div class="quick-reply-upload-preview" id="quickReplyUploadPreview" hidden></div>
     </div>
    </div>
    <div class="modal-actions quick-reply-actions"><button type="button" class="soft" id="quickReplyCancel"><i class="fa-solid fa-xmark"></i> Cancelar</button><button class="primary"><i class="fa-solid fa-floppy-disk"></i> Guardar respuesta</button></div>
   </form>
  </div>
  <div class="modal-actions quick-reply-modal-footer"><button type="button" class="soft modal-close"><i class="fa-solid fa-xmark"></i> Cerrar</button></div>
 </div>
</div>
<div class="modal-shell" id="categoryModal"><div class="modal-card small-modal"><div class="modal-head"><div><b>Nueva categoría</b><small>Disponible para todos los agentes de esta empresa.</small></div><button class="modal-close"><i class="fa-solid fa-xmark"></i></button></div><form id="categoryForm"><input type="hidden" name="action" value="category_add"><div class="field"><label>Nombre</label><input name="category_name" required placeholder="Ej. Cliente potencial"></div><div class="modal-actions"><button type="button" class="soft modal-close"><i class="fa-solid fa-xmark"></i> Cancelar</button><button class="primary"><i class="fa-solid fa-plus"></i> Crear categoría</button></div></form></div></div>
<?php require __DIR__.'/partials/bottom.php'; ?>
