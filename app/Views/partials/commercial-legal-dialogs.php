<?php foreach($commercialChecks as $key=>$item):$doc=$commercialDocs[$key]??null;if(!$doc)continue;$dialogId=$commercialPrefix.'-'.$item[0];$localized=zynkoLegalLocalizedDocument($key,$doc,$commercialLang);$localizedCatalog=zynkoLegalLocalizedCatalog($commercialLang);?>
<dialog class="zc-dialog zc-legal-dialog" id="<?=$commercialE($dialogId)?>" aria-labelledby="<?=$commercialE($dialogId.'-title')?>">
 <div class="zc-dialog-frame">
  <header class="zc-dialog-head"><span class="zc-icon"><i class="fa-solid fa-file-signature"></i></span><div><h3 id="<?=$commercialE($dialogId.'-title')?>"><?=$commercialE(!empty($localized['translated'])?($localizedCatalog[$key]['title']??$doc['title']):$doc['title'])?></h3><p><?=$commercialT('Documento vigente','Current document')?> · v<?=(int)$doc['version']?> · <?=$commercialE(date('d/m/Y',strtotime((string)$doc['published_at'])))?></p></div><button type="button" class="zc-close" data-zc-close aria-label="<?=$commercialT('Cerrar','Close')?>"><i class="fa-solid fa-xmark"></i></button></header>
  <div class="zc-dialog-body zc-legal-content"><?php if(!empty($localized['fallback'])):?><p lang="en">The current published version is available in Spanish. An English translation has not been verified for this revision.</p><?php endif?><?=zynkoLegalContentToHtml($localized['content'])?></div>
  <footer class="zc-dialog-foot"><button type="button" class="zc-button zc-dark" data-zc-close><i class="fa-solid fa-check"></i> <?=$commercialT('Volver al formulario','Return to form')?></button></footer>
 </div>
</dialog>
<?php endforeach?>
