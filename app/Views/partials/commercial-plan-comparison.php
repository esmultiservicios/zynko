<?php
$notIncluded = $ct('No incluido', 'Not included');
$included = $ct('Incluido', 'Included');
$metaPricing = 'https://developers.facebook.com/documentation/business-messaging/whatsapp/pricing/#rate-cards-and-volume-tiers';
$features = [];
$channelsByPlan = [];
$allCodes = [];
foreach ($cards as $plan) {
    $id = (int)$plan['id'];
    $features[$id] = zynkoDecodeMap($plan['module_access_json'] ?? null);
    $channelsByPlan[$id] = zynkoPlanDisplayChannels($plan, $publicChannelCatalog);
    foreach ($channelsByPlan[$id] as $code) $allCodes[$code] = true;
}

$module = fn(string $code) => fn($p) => !empty($features[(int)$p['id']][$code]) ? $included : $notIncluded;
$newChats = function ($p) use ($ct, $notIncluded) {
    if (!empty($p['max_daily_chats'])) return number_format((int)$p['max_daily_chats']).' '.$ct('/ día', '/ day');
    if (!empty($p['max_monthly_chats'])) return number_format((int)$p['max_monthly_chats']).' '.$ct('/ mes', '/ month');
    return ($p['code'] ?? '') === 'api_only' ? $notIncluded : $ct('Sin límite del plan', 'No plan limit');
};
$groups = [
    $ct('Capacidad y herramientas', 'Capacity and tools') => [
        $ct('Usuarios', 'Users') => fn($p) => $limit($p['max_users'] ?? null),
        $ct('Conexiones externas', 'External connections') => fn($p) => zynkoPlanExternalConnectionLimit($p) ?? $unlimited,
        $ct('Sitios de NIVO Web Chat', 'NIVO Web Chat sites') => fn($p) => in_array('webchat', $channelsByPlan[(int)$p['id']], true) ? $limit($p['max_webchat_sites'] ?? null) : $notIncluded,
        $ct('Chats nuevos en Bandeja', 'New inbox chats') => $newChats,
        $ct('Bandeja compartida', 'Shared inbox') => $module('inbox'),
        $ct('API REST', 'REST API') => $module('api'),
        $ct('NIVO IA', 'NIVO AI') => $module('chatbot'),
        $ct('Automatizaciones', 'Automations') => $module('automations'),
        $ct('Tokens IA externa / mes', 'External AI tokens / month') => fn($p) => empty($p['external_ai_included'])
            ? $notIncluded : (empty($p['external_ai_monthly_tokens']) ? $ct('Sin límite del plan', 'No plan limit') : number_format((int)$p['external_ai_monthly_tokens'])),
    ],
    $ct('Activación y soporte', 'Activation and support') => [
        $ct('Orientación inicial', 'Initial guidance') => fn() => $included,
        $ct('Activación asistida básica', 'Basic assisted activation') => fn($p) => (float)$p['monthly_price'] > 0 && in_array('whatsapp', $channelsByPlan[(int)$p['id']], true) ? $included : $notIncluded,
        $ct('Soporte prioritario', 'Priority support') => fn($p) => in_array('Soporte prioritario', zynkoDecodeList($p['features_json'] ?? null), true) ? $included : $notIncluded,
        $ct('Servicios personalizados', 'Custom services') => fn() => $ct('Por cotización', 'By quote'),
    ],
];
$renderRow = static function (string $label, callable $value, bool $rich = false) use ($cards, $ce, $notIncluded): void { ?>
    <div class="plan-matrix-row">
        <div class="plan-matrix-label"><?=$ce($label)?></div>
        <?php foreach ($cards as $plan): $text = (string)$value($plan); ?>
            <div class="plan-matrix-cell <?=!empty($plan['is_featured'])?'is-featured':''?> <?=$text===$notIncluded?'is-excluded':''?>">
                <span class="plan-matrix-mobile-name"><?=$ce($plan['name'])?></span>
                <span><?=$rich && $text !== $notIncluded ? $text : $ce($text)?></span>
            </div>
        <?php endforeach; ?>
    </div>
<?php };
?>
<div class="public-plan-compare zc-compare">
    <details class="plan-matrix-card">
        <summary class="plan-matrix-summary">
            <span class="zc-icon"><i class="fa-solid fa-table-columns" aria-hidden="true"></i></span>
            <span><strong><?=$ct('Compara los planes de ZYNKO','Compare ZYNKO plans')?></strong>
            <small><?=$ct('Precios, capacidades y requisitos en un solo lugar.','Prices, capabilities and requirements in one place.')?></small></span>
            <i class="fa-solid fa-chevron-down compare-chevron" aria-hidden="true"></i>
        </summary>
        <div class="plan-matrix-body">
            <div class="plan-matrix-requirements">
                <div><strong><?=$ct('Conecta tu propio número de WhatsApp','Connect your own WhatsApp number')?></strong>
                    <p><?=$ct('En los planes compatibles, prepara tu portafolio empresarial de Meta, registra o autoriza tu número y configura la facturación en Meta. Luego conecta y prueba el canal en ZYNKO.','For eligible plans, prepare your Meta business portfolio, register or authorize your number, and set up Meta billing. Then connect and test the channel in ZYNKO.')?></p>
                </div>
                <a class="zc-button zc-outline" href="<?=$ce($metaPricing)?>" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> <?=$ct('Tarifas oficiales de Meta','Official Meta rates')?></a>
            </div>
            <div class="plan-matrix" style="--plan-count:<?=count($cards)?>">
                <div class="plan-matrix-row plan-matrix-head">
                    <div class="plan-matrix-label"><?=$ct('Mensualidad de ZYNKO','ZYNKO subscription')?></div>
                    <?php foreach ($cards as $plan): ?>
                        <div class="plan-matrix-cell <?=!empty($plan['is_featured'])?'is-featured':''?>">
                            <strong><?=$ce($plan['name'])?></strong>
                            <span><?=$ce($priceText($plan))?><?=!$isFreePlan($plan)&&!$pricePending($plan)?' '.$ct('/mes','/month'):''?></span>
                            <button type="button" class="zc-button zc-outline" data-zc-open="zc-plan-<?=(int)$plan['id']?>"><?=$ct('Detalles','Details')?></button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php foreach ($groups as $heading => $rows): ?>
                    <div class="plan-matrix-group"><h3><?=$ce($heading)?></h3>
                        <?php foreach ($rows as $label => $value) $renderRow($label, $value); ?>
                    </div>
                <?php endforeach; ?>
                <div class="plan-matrix-group"><h3><?=$ct('Canales y disponibilidad','Channels and availability')?></h3>
                    <?php foreach (array_keys($allCodes) as $code):
                        $item = zynkoPlanChannelDisplay($publicChannelCatalog, $code);
                        $renderRow($item['name'], fn($p) => in_array($code, $channelsByPlan[(int)$p['id']], true) ? zynkoPlanChannelStatus($publicChannelCatalog,$code,$publicLang) : $notIncluded, true);
                    endforeach; ?>
                </div>
            </div>
            <div class="zc-compare-footer"><button type="button" class="zc-button zc-dark" data-zc-hide-comparison><i class="fa-solid fa-chevron-up" aria-hidden="true"></i> <?=$ct('Ocultar comparativa','Hide comparison')?></button></div>
        </div>
    </details>
</div>
