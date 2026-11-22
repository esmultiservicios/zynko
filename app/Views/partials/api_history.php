<?php
// Reuse the existing ZYNKO panel, badges, pagination and modal conventions.
$historyQuery=mb_substr(trim((string)($_GET['history_q']??'')),0,120);
$historyPage=max(1,(int)($_GET['history_page']??1));
$historySize=(string)($_GET['history_size']??'10');
if(!in_array($historySize,['10','25','50','100','all'],true))$historySize='10';
$historyRows=[];$historyTotal=0;$historyPages=1;
try{
 $pdo=appDb();ZynkoAccessPolicy::ensureTables($pdo);
 $where=' WHERE h.tenant_id=?';$args=[(int)$_SESSION['user']['tenant_id']];
 if($historyQuery!==''){$where.=' AND (h.destination LIKE ? OR k.name LIKE ? OR ch.name LIKE ? OR m.uuid LIKE ?)';$like='%'.$historyQuery.'%';array_push($args,$like,$like,$like,$like);}
 $joins=' FROM api_message_history h JOIN messages m ON m.id=h.message_id AND m.tenant_id=h.tenant_id JOIN channels ch ON ch.id=h.channel_id AND ch.tenant_id=h.tenant_id LEFT JOIN api_keys k ON k.id=h.api_key_id AND k.tenant_id=h.tenant_id LEFT JOIN channel_outbox o ON o.message_id=m.id AND o.tenant_id=m.tenant_id';
 $count=$pdo->prepare('SELECT COUNT(*)'.$joins.$where);$count->execute($args);$historyTotal=(int)$count->fetchColumn();
 $per=$historySize==='all'?max(1,$historyTotal):(int)$historySize;$historyPages=max(1,(int)ceil($historyTotal/$per));$historyPage=min($historyPage,$historyPages);
 $q=$pdo->prepare('SELECT h.id,h.created_at,h.destination,h.delivery_mode,k.name integration,ch.name channel_name,m.uuid,m.status,o.state queue_state,o.last_error'.$joins.$where.' ORDER BY h.id DESC LIMIT '.(int)$per.' OFFSET '.(int)(($historyPage-1)*$per));$q->execute($args);$historyRows=$q->fetchAll();
}catch(Throwable $e){error_log('ZYNKO API history read: '.get_class($e));}
$historyLink=static fn($pg)=>'?'.http_build_query(['page'=>'integrations','history_q'=>$historyQuery,'history_size'=>$historySize,'history_page'=>$pg]).'#api-history';
$historyEsc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
?>
<section class="panel" id="api-history"><div class="panel-head premium-card-head"><span class="premium-card-head-icon"><i class="fa-solid fa-clock-rotate-left"></i></span><div><b>Historial de envíos API</b><small>Registro por empresa, integrado o independiente de la bandeja. Los estados se actualizan desde la cola real.</small></div><div class="api-history-downloads"><a class="company-export-btn excel" href="?<?=http_build_query(['action'=>'api_history_export_excel','history_q'=>$historyQuery])?>"><span class="company-export-icon"><i class="fa-solid fa-file-excel"></i></span><span class="company-export-copy"><b>Excel</b><small>Reporte de envíos</small></span><i class="fa-solid fa-arrow-down company-export-arrow" aria-hidden="true"></i></a><a class="company-export-btn pdf zynko-report-preview-open" href="?<?=http_build_query(['action'=>'api_history_export_pdf','history_q'=>$historyQuery])?>"><span class="company-export-icon"><i class="fa-solid fa-file-pdf"></i></span><span class="company-export-copy"><b>PDF</b><small>Reporte de envíos</small></span><i class="fa-solid fa-arrow-down company-export-arrow" aria-hidden="true"></i></a></div></div>
<form method="get" action="" class="api-history-toolbar" id="apiHistoryFilters"><input type="hidden" name="page" value="integrations"><div class="api-search-wrap api-history-search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="history_q" id="apiHistorySearch" value="<?=$historyEsc($historyQuery)?>" placeholder="Buscar número, integración, canal o UUID…" autocomplete="off" aria-label="Buscar historial API"><button type="button" id="apiHistoryClear" class="api-history-clear" aria-label="Borrar búsqueda" <?= $historyQuery===''?'hidden':''?>><i class="fa-solid fa-xmark"></i></button></div><label class="api-history-size-label">Mostrar <select name="history_size" id="apiHistorySize" class="select2"><option value="10" <?=$historySize==='10'?'selected':''?>>10 registros</option><option value="25" <?=$historySize==='25'?'selected':''?>>25 registros</option><option value="50" <?=$historySize==='50'?'selected':''?>>50 registros</option><option value="100" <?=$historySize==='100'?'selected':''?>>100 registros</option><option value="all" <?=$historySize==='all'?'selected':''?>>Todos (<?=number_format($historyTotal)?>)</option></select></label></form>
<div class="api-history-table-wrap"><table class="api-history-table"><thead><tr><th>Fecha</th><th>Integración</th><th>Canal</th><th>Destinatario</th><th>Modo</th><th>Estado</th><th>Detalle</th></tr></thead><tbody><?php if(!$historyRows):?><tr><td colspan="7" class="api-history-empty">No se encontraron envíos API para esta empresa.</td></tr><?php else:foreach($historyRows as $r):$status=(string)$r['status'];$isFail=in_array($status,['failed','partial'],true);$isGood=in_array($status,['sent','delivered','read'],true);?><tr><td><?=$historyEsc($r['created_at'])?></td><td><?=$historyEsc($r['integration']?:'Clave revocada')?></td><td><?=$historyEsc($r['channel_name'])?></td><td><?=$historyEsc($r['destination'])?></td><td><span class="ui-badge muted"><?=$r['delivery_mode']==='independent'?'Solo API':'Con bandeja'?></span></td><td><span class="ui-badge <?=$isFail?'danger':($isGood?'success':'muted')?>"><?=$historyEsc($status)?></span></td><td><small><?=$historyEsc($r['queue_state']?:'—')?></small><?php if(!empty($r['last_error'])):?><div class="api-history-error" title="<?=$historyEsc($r['last_error'])?>"><?=$historyEsc($r['last_error'])?></div><?php endif?><small class="api-history-uuid"><?=$historyEsc($r['uuid'])?></small></td></tr><?php endforeach;endif?></tbody></table></div>
<nav class="dashboard-attention-pager api-history-pager" aria-label="Paginación de envíos API"><small><?=number_format($historyTotal)?> registros · Página <?=$historyPage?> de <?=$historyPages?></small><div class="dashboard-attention-pager-actions"><a class="soft <?=$historyPage<=1?'is-disabled':''?>" href="<?=$historyEsc($historyLink(max(1,$historyPage-1)))?>"><i class="fa-solid fa-chevron-left"></i> Anterior</a><?php $historyNumbers=[1,$historyPages];for($i=max(2,$historyPage-2);$i<=min($historyPages-1,$historyPage+2);$i++)$historyNumbers[]=$i;$historyNumbers=array_values(array_unique($historyNumbers));sort($historyNumbers);$prev=0;foreach($historyNumbers as $i):if($prev&&$i-$prev>1):?><span class="company-page-dots">…</span><?php endif?><a class="company-page-btn <?=$historyPage===$i?'active':''?>" href="<?=$historyEsc($historyLink($i))?>" <?=$historyPage===$i?'aria-current="page"':''?>><?=$i?></a><?php $prev=$i;endforeach?><a class="soft <?=$historyPage>=$historyPages?'is-disabled':''?>" href="<?=$historyEsc($historyLink(min($historyPages,$historyPage+1)))?>">Siguiente <i class="fa-solid fa-chevron-right"></i></a></div></nav></section>
<style>
.api-history-toolbar{display:flex;flex-wrap:wrap;gap:14px;align-items:center;margin:18px 0}.api-history-search{flex:1;min-width:min(260px,100%);position:relative}.api-history-search input[type=search]{padding-right:40px;min-width:0;width:100%}.api-history-search input::-webkit-search-cancel-button{-webkit-appearance:none;appearance:none}.api-history-clear{position:absolute;right:10px;top:50%;transform:translateY(-50%);border:0;background:transparent;color:var(--muted,#64748b);cursor:pointer}.api-history-size-label{display:flex;gap:10px;align-items:center;white-space:nowrap}.api-history-size-label select{min-width:220px;width:220px}.api-history-size-label .select2-container{min-width:220px!important;width:220px!important}.api-history-size-label .select2-selection__rendered{white-space:nowrap!important}.api-history-table-wrap{overflow:auto;width:100%}.api-history-table{width:100%;border-collapse:collapse;min-width:780px}.api-history-table th,.api-history-table td{text-align:left;padding:13px 12px;border-bottom:1px solid var(--border,#dce5e7);vertical-align:middle}.api-history-table th{font-size:.8rem}.api-history-error{max-width:240px;white-space:normal;color:var(--danger,#b91c1c);font-size:.78rem}.api-history-uuid{display:block;opacity:.65;font-size:.69rem;overflow-wrap:anywhere}.api-history-empty{text-align:center!important;padding:28px!important}.api-history-pager{margin:16px 0 0}.api-history-downloads{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.api-history-downloads .company-export-btn{min-width:128px}@media(max-width:620px){.api-history-toolbar{flex-direction:column;align-items:stretch}.api-history-size-label{justify-content:space-between}.api-history-size-label select{min-width:220px}}
</style>
<script>
(()=>{
const section=document.getElementById('api-history');if(!section)return;
const form=section.querySelector('#apiHistoryFilters');const input=section.querySelector('#apiHistorySearch');const clear=section.querySelector('#apiHistoryClear');const size=section.querySelector('#apiHistorySize');
let timer=null,controller=null,serial=0;
async function refresh(page=1){
  const seq=++serial;if(controller)controller.abort();controller=new AbortController();
  const params=new URLSearchParams({page:'integrations',api_history_fragment:'1',history_q:input.value,history_size:size.value,history_page:String(page)});
  try{
    const response=await fetch('?'+params,{signal:controller.signal,headers:{'X-Requested-With':'XMLHttpRequest'}});
    if(!response.ok)throw new Error('No fue posible consultar el historial.');
    const documentCopy=new DOMParser().parseFromString(await response.text(),'text/html');
    const newer=documentCopy.querySelector('#api-history');if(!newer)return;
    if(seq!==serial)return;
    for(const selector of ['.api-history-table-wrap','.api-history-pager','.api-history-downloads']){
      const current=section.querySelector(selector),replacement=newer.querySelector(selector);
      if(current&&replacement)current.replaceWith(replacement);
    }
    const allNew=newer.querySelector('#apiHistorySize option[value="all"]');
    const allOld=size.querySelector('option[value="all"]');if(allNew&&allOld)allOld.textContent=allNew.textContent;
    const next=new URL(window.location.href);next.searchParams.set('page','integrations');next.searchParams.set('history_q',input.value);next.searchParams.set('history_size',size.value);next.searchParams.set('history_page',String(page));next.hash='api-history';history.replaceState(null,'',next);
  }catch(err){if(err.name!=='AbortError'){console.warn('Historial API:',err);form.submit();}}
}
const schedule=()=>{clearTimeout(timer);timer=setTimeout(()=>refresh(1),350)};
input?.addEventListener('input',()=>{if(clear)clear.hidden=!input.value;schedule()});
clear?.addEventListener('click',()=>{input.value='';clear.hidden=true;refresh(1);input.focus()});
size?.addEventListener('change',()=>refresh(1));
document.addEventListener('DOMContentLoaded',()=>{if(window.jQuery&&window.jQuery.fn&&window.jQuery.fn.select2)window.jQuery(size).on('change',()=>refresh(1));});
section.addEventListener('click',event=>{const a=event.target.closest('.api-history-pager a');if(!a)return;event.preventDefault();if(a.classList.contains('is-disabled'))return;const href=new URL(a.href,window.location.href);refresh(Math.max(1,Number(href.searchParams.get('history_page')||1)))});
})();
</script>
