<?php foreach($plans as $promoPlan):
    if(!empty($promoPlan['is_free_plan'])||!empty($promoPlan['is_default_free'])||(float)$promoPlan['monthly_price']<=0)continue;
    $promoConfig=json_decode((string)($promoPlan['promotion_json']??''),true)?:[];
    $promoId='plan-promotion-'.(int)$promoPlan['id'];
?>
<dialog class="zc-dialog zc-dialog-fit" id="<?=$promoId?>" aria-labelledby="<?=$promoId?>-title">
    <form class="zc-dialog-frame plan-promotion-form" method="post">
        <header class="zc-dialog-head">
            <span class="zc-icon"><i class="fa-solid fa-tag" aria-hidden="true"></i></span>
            <div><h3 id="<?=$promoId?>-title">Promoción · <?=zynkoCommercialEscape($promoPlan['name'])?></h3><p>Configura el precio del primer mes y su vigencia.</p></div>
            <button type="button" class="zc-close" data-zc-close aria-label="Cerrar"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </header>
        <div class="zc-dialog-body">
            <input type="hidden" name="action" value="plan_promotion_save">
            <input type="hidden" name="csrf" value="<?=zynkoCommercialEscape(authCsrfToken())?>">
            <input type="hidden" name="plan_id" value="<?=(int)$promoPlan['id']?>">
            <label class="plan-payment-confirmation"><input type="checkbox" name="promotion_enabled" value="1" <?=!empty($promoConfig['enabled'])?'checked':''?>> Publicar promoción durante la vigencia indicada</label>
            <div class="zc-fields">
                <label>Precio del primer mes (<?=zynkoCommercialEscape($promoPlan['currency'])?>)
                    <input type="number" name="promotion_price" min="0.01" step="0.01" value="<?=zynkoCommercialEscape($promoConfig['price']??'')?>">
                </label>
                <label>Desde<input type="date" name="promotion_starts" value="<?=zynkoCommercialEscape($promoConfig['starts']??'')?>"></label>
                <label>Hasta, inclusive<input type="date" name="promotion_ends" value="<?=zynkoCommercialEscape($promoConfig['ends']??'')?>"></label>
            </div>
            <div class="zc-notice"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><div><b>Mensualidad regular · <?=zynkoCommercialEscape($promoPlan['currency'].' '.number_format((float)$promoPlan['monthly_price'],2))?></b><p>La oferta aplica al primer mes de nuevas solicitudes. Las fechas siguen la hora de Honduras. El plan debe estar publicado y disponible para contratar.</p></div></div>
        </div>
        <footer class="zc-dialog-foot">
            <button type="button" class="zc-button zc-dark" data-zc-close>Cancelar</button>
            <button type="submit" class="zc-button zc-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Guardar promoción</button>
        </footer>
    </form>
</dialog>
<?php endforeach; ?>
