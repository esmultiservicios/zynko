<?php
$title='Encuestas y satisfacción'; require __DIR__.'/partials/top.php';
$pdo=appDb();$tid=(int)$_SESSION['user']['tenant_id'];$rows=[];$stats=['total'=>0,'answered'=>0,'avg'=>0,'resolved'=>0,'helpful'=>0];$ratingFilter=(int)($_GET['rating']??0);$statusFilter=$_GET['status']??'all';
try{
 $q=$pdo->prepare("SELECT COUNT(*) total,SUM(responded_at IS NOT NULL) answered,AVG(CASE WHEN responded_at IS NOT NULL THEN rating END) avg_rating,SUM(resolved=1) resolved_yes,SUM(nivo_helpful=1) helpful_yes FROM conversation_surveys WHERE tenant_id=?");$q->execute([$tid]);$r=$q->fetch()?:[];$stats=['total'=>(int)($r['total']??0),'answered'=>(int)($r['answered']??0),'avg'=>(float)($r['avg_rating']??0),'resolved'=>(int)($r['resolved_yes']??0),'helpful'=>(int)($r['helpful_yes']??0)];
 $sql="SELECT s.*,c.uuid,ct.name contact_name,ch.type channel_type,ch.name channel_name FROM conversation_surveys s JOIN conversations c ON c.id=s.conversation_id LEFT JOIN contacts ct ON ct.id=c.contact_id LEFT JOIN channels ch ON ch.id=c.channel_id WHERE s.tenant_id=?";$params=[$tid];
 if($ratingFilter>=1&&$ratingFilter<=5){$sql.=" AND s.rating=?";$params[]=$ratingFilter;}
 if($statusFilter==='answered')$sql.=" AND s.responded_at IS NOT NULL";elseif($statusFilter==='pending')$sql.=" AND s.responded_at IS NULL";
 $sql.=" ORDER BY COALESCE(s.responded_at,s.requested_at) DESC LIMIT 300";$q=$pdo->prepare($sql);$q->execute($params);$rows=$q->fetchAll();
}catch(Throwable $e){}
$resolvedPct=$stats['answered']?round($stats['resolved']*100/$stats['answered']):0;$helpfulPct=$stats['answered']?round($stats['helpful']*100/$stats['answered']):0;
?>
<section class="welcome"><div><small>NIVO WEB CHAT · EXPERIENCIA</small><h1>Encuestas y satisfacción</h1><p>Resultados reales del cierre de conversaciones, resolución y utilidad de NIVO.</p></div><a class="secondary-action" href="?page=webchat"><i class="fa-solid fa-message"></i> NIVO Web Chat</a></section>
<section class="premium-kpis survey-kpis">
 <article><span><i class="fa-solid fa-star"></i></span><div><b><?=number_format($stats['avg'],1)?></b><small>Calificación promedio</small></div></article>
 <article><span><i class="fa-solid fa-clipboard-check"></i></span><div><b><?=$stats['answered']?></b><small>Encuestas respondidas</small></div></article>
 <article><span><i class="fa-solid fa-circle-check"></i></span><div><b><?=$resolvedPct?>%</b><small>Consulta resuelta</small></div></article>
 <article><span><i class="fa-solid fa-robot"></i></span><div><b><?=$helpfulPct?>%</b><small>NIVO fue útil</small></div></article>
</section>
<section class="panel spaced survey-panel">
 <div class="panel-head premium-card-head"><span class="premium-card-head-icon"><i class="fa-solid fa-chart-simple"></i></span><div><b>Resultados de encuestas</b><small>Filtra, revisa comentarios y abre la conversación original cuando necesites contexto.</small></div></div>
 <form class="survey-filters" method="get"><input type="hidden" name="page" value="surveys"><div class="field"><label>Estado</label><select name="status" class="select2"><option value="all" <?=$statusFilter==='all'?'selected':''?>>Todas</option><option value="answered" <?=$statusFilter==='answered'?'selected':''?>>Respondidas</option><option value="pending" <?=$statusFilter==='pending'?'selected':''?>>Pendientes</option></select></div><div class="field"><label>Calificación</label><select name="rating" class="select2"><option value="0">Todas</option><?php for($i=5;$i>=1;$i--):?><option value="<?=$i?>" <?=$ratingFilter===$i?'selected':''?>><?=$i?> estrella<?=$i===1?'':'s'?></option><?php endfor?></select></div><button class="primary" type="submit"><i class="fa-solid fa-filter"></i> Aplicar</button><a class="soft" href="?page=surveys"><i class="fa-solid fa-rotate-left"></i> Restablecer</a></form>
 <div class="survey-list"><?php if(!$rows):?><div class="empty-compact"><i class="fa-regular fa-star"></i><b>No hay encuestas para estos filtros</b><span>Las respuestas aparecerán aquí al finalizar conversaciones.</span></div><?php else:foreach($rows as $r):?><article class="survey-row">
  <div class="survey-row-head"><span class="survey-avatar"><?=htmlspecialchars(mb_strtoupper(mb_substr((string)($r['contact_name']?:'V'),0,1)))?></span><div><b><?=htmlspecialchars((string)($r['contact_name']?:'Visitante'))?></b><small><?=htmlspecialchars((string)($r['channel_name']?:$r['channel_type']?:'Canal'))?> · <?=htmlspecialchars((string)($r['responded_at']?:$r['requested_at']))?></small></div><a class="soft" href="?page=inbox&conversation=<?=(int)$r['conversation_id']?>"><i class="fa-solid fa-arrow-up-right-from-square"></i> Conversación</a></div>
  <div class="survey-score"><?=str_repeat('★',max(0,(int)$r['rating']))?><span><?=($r['rating']!==null?(int)$r['rating'].'/5':'Sin calificar')?></span></div>
  <div class="survey-flags"><span class="<?=((int)($r['resolved']??-1)===1)?'yes':'no'?>"><i class="fa-solid fa-circle-check"></i> Resuelta: <?=($r['resolved']===null?'Sin respuesta':((int)$r['resolved']===1?'Sí':'No'))?></span><span class="<?=((int)($r['nivo_helpful']??-1)===1)?'yes':'no'?>"><i class="fa-solid fa-robot"></i> NIVO útil: <?=($r['nivo_helpful']===null?'Sin respuesta':((int)$r['nivo_helpful']===1?'Sí':'No'))?></span></div>
  <?php if(trim((string)($r['comment']??''))!==''):?><p><?=nl2br(htmlspecialchars((string)$r['comment']))?></p><?php endif?>
 </article><?php endforeach;endif?></div>
</section>
<?php require __DIR__.'/partials/bottom.php'; ?>
