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
  <details class="subscription-status-help">
    <summary>Qué significa cada estado</summary>
    <dl>
      <?php foreach ($subscriptionStatusOptions as $value => $label): ?>
        <div><dt><?=$label?></dt><dd><?=$subscriptionStatusDescriptions[$value]?></dd></div>
      <?php endforeach; ?>
    </dl>
  </details>
</fieldset>
