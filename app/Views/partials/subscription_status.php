<?php
$subscriptionStatusOptions = [
    'active' => 'Activo',
    'grace' => 'Gracia',
    'suspended' => 'Suspendido',
    'cancelled' => 'Cancelado',
];
$subscriptionStatusDescriptions = [
    'active' => 'Habilita las funciones y límites del plan asignado.',
    'grace' => 'Mantiene el acceso durante un período de gracia. El administrador debe revisar la situación.',
    'suspended' => 'Pausa el acceso a las funciones del plan hasta que se reactive.',
    'cancelled' => 'Finaliza el acceso al plan. Puede asignarse un plan vigente más adelante.',
];
$selectedSubscriptionStatus = isset($subscriptionStatusOptions[$subscriptionStatus]) ? $subscriptionStatus : 'active';
?>
<fieldset class="billing-control billing-status-control full">
  <legend><?=htmlspecialchars($subscriptionStatusLabel, ENT_QUOTES, 'UTF-8')?></legend>
  <div class="billing-status-options">
    <?php foreach ($subscriptionStatusOptions as $value => $label): ?>
      <label><input type="radio" name="status" value="<?=$value?>" <?=$selectedSubscriptionStatus === $value ? 'checked' : ''?>> <span><?=$label?></span></label>
    <?php endforeach; ?>
  </div>
  <button type="button" class="subscription-status-help" aria-haspopup="dialog"><i class="fa-solid fa-circle-question" aria-hidden="true"></i> Qué significa cada estado</button><template class="subscription-status-guide"><dl class="record-detail-grid">
      <?php foreach ($subscriptionStatusOptions as $value => $label): ?>
        <div class="record-detail-section"><dt><b><?=$label?></b></dt><dd><?=$subscriptionStatusDescriptions[$value]?></dd></div>
      <?php endforeach; ?>
    </dl>
  </template>
</fieldset>
