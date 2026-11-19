<?php
// Place inside the registration/plan request form. Only versions read from the
// database are rendered. The server checks every acceptance again at submission.
$commercialE='zynkoCommercialEscape';
$commercialLang=$commercialLang??zynkoCommercialLanguage();
$commercialT=fn(string $es,string $en)=>$commercialLang==='en'?$en:$es;
$commercialPrefix=$commercialPrefix??'commercial';
$commercialDocs=$commercialDocs??[];
$commercialChecks=[
 'terms_conditions'=>['terms',$commercialT('He leído y acepto los Términos y Condiciones.','I have read and accept the Terms and Conditions.'),$commercialT('Términos y Condiciones','Terms and Conditions')],
 'privacy_policy'=>['privacy',$commercialT('He leído y acepto la Política de Privacidad.','I have read and accept the Privacy Policy.'),$commercialT('Política de Privacidad','Privacy Policy')],
 'commercial_conditions'=>['commercial',$commercialT('Comprendo qué cubre mi suscripción y qué cargos externos debo pagar.','I understand what my subscription covers and which external charges I must pay.'),$commercialT('Condiciones comerciales','Commercial conditions')]
];
?>
<div class="zc-consent">
 <div class="zc-section-head"><span class="zc-icon"><i class="fa-solid fa-scale-balanced"></i></span><div><b><?=$commercialT('Condiciones de contratación','Subscription conditions')?></b><small><?=$commercialT('Revisa los documentos vigentes antes de continuar.','Review the current documents before continuing.')?></small></div></div>
 <?php foreach($commercialChecks as $key=>$item):$doc=$commercialDocs[$key]??null;?>
 <div class="zc-consent-row"><label><input type="checkbox" name="<?=$commercialE($item[0])?>" value="1" required <?=$doc?'':'disabled'?>> <span><?=$commercialE($item[1])?></span></label>
  <input type="hidden" name="<?=$commercialE($item[0])?>_version" value="<?=(int)($doc['version']??0)?>">
  <?php if($doc):?><button type="button" class="zc-text-button" data-zc-open="<?=$commercialE($commercialPrefix.'-'.$item[0])?>"><i class="fa-solid fa-book-open"></i> <?=$commercialT('Consultar documento','Read document')?> · v<?=(int)$doc['version']?></button><?php else:?><small role="alert"><?=$commercialT('Documento no disponible. Actualiza la página.','Document unavailable. Refresh the page.')?></small><?php endif?>
 </div>
 <?php endforeach?>
 <div class="zc-consent-row"><label><input type="checkbox" name="activation_terms" value="1" required> <span><?=$commercialT('Comprendo que los canales externos requieren autorización y validación del proveedor; tener WhatsApp instalado no permite conectarlo automáticamente.','I understand external channels require provider authorization and validation; installing WhatsApp alone does not connect it automatically.')?></span></label>
 <?php if(isset($commercialDocs['commercial_conditions'])):?><button type="button" class="zc-text-button" data-zc-open="<?=$commercialE($commercialPrefix.'-commercial')?>"><i class="fa-solid fa-circle-info"></i> <?=$commercialT('Ver requisitos de activación','View activation requirements')?></button><?php endif?></div>
 <small class="zc-muted"><i class="fa-solid fa-lock"></i> <?=$commercialT('Guardamos la versión aceptada y la fecha. No se generan cargos de Meta al crear una cuenta Gratis.','We record the accepted version and date. Creating a Free account does not incur Meta charges.')?></small>
</div>
