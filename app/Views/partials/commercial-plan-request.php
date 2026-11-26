<?php
$ce='zynkoCommercialEscape';
$commercialLang=zynkoCommercialLanguage();
$ct=fn($a,$b)=>$commercialLang==='en'?$b:$a;
?>
<?php if($canRequest):$commercialPrefix='zc-contract';?>
<dialog class="zc-dialog zc-dialog-fit" id="zcPlanRequestDialog"><form id="zcPlanRequestForm" class="zc-dialog-frame" method="post"><header class="zc-dialog-head"><span class="zc-icon"><i class="fa-solid fa-credit-card"></i></span><div><h3 data-zc-contract-title>Solicitar plan</h3><p data-zc-contract-price></p></div><button type="button" class="zc-close" data-zc-close aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button></header><div class="zc-dialog-body"><input type="hidden" name="action" value="plan_request"><input type="hidden" name="csrf" value="<?=$ce(authCsrfToken())?>"><input type="hidden" name="plan_id"><input type="hidden" name="plan_revision"><label class="zc-field">Comentario opcional<textarea name="note" maxlength="500" data-no-rte="1"></textarea></label><?php require __DIR__.'/commercial-consent.php';?><p role="alert" data-zc-contract-error></p></div><footer class="zc-dialog-foot"><button type="button" class="zc-button zc-dark" data-zc-close><i class="fa-solid fa-xmark"></i> Cancelar</button><button type="submit" class="zc-button zc-primary"><i class="fa-solid fa-paper-plane"></i> Enviar solicitud</button></footer></form></dialog>
<?php require __DIR__.'/commercial-legal-dialogs.php';endif?>
