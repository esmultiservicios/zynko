<?php
$subscriptionStatusOptions = [
    'active' => 'Activo',
    'grace' => 'Gracia',
    'suspended' => 'Suspendido',
    'cancelled' => 'Cancelado',
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
</fieldset>
