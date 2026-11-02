<?php
require __DIR__ . '/../../app/bootstrap.php';
$u = require_login('customer');
$services = [];
foreach (active_services() as $s) {
    $services[] = ['id' => (int)$s['id'], 'slug' => $s['slug'], 'name' => $s['name'], 'category' => $s['category_name'], 'description' => $s['description'],
        'mode' => $s['pricing_mode'], 'unit' => $s['price_unit'], 'price_label' => service_price_label($s), 'needs_upload' => (bool)$s['needs_upload'],
        'needs_review' => (bool)$s['needs_review'], 'days' => (int)$s['processing_days'], 'options' => service_options($s)];
}
$methods = [];
foreach (payment_methods(true) as $k => $m) $methods[$k] = ['label' => $m['label'], 'account' => $m['account'], 'instructions' => $m['instructions']];
$data = [
    'csrf' => csrf_token(), 'api' => url('api'), 'services' => $services, 'preselect' => get('service'),
    'maxMB' => (int)(MAX_UPLOAD / 1048576), 'accepting' => setting('accepting_orders', '1') === '1', 'closedMsg' => setting('closed_message'),
    'user' => ['name' => $u['full_name'], 'email' => $u['email'], 'phone' => $u['phone']],
    'store' => ['address' => setting('address'), 'policy' => setting('pickup_policy'), 'phone' => setting('phone')],
    'methods' => $methods, 'paymentPolicy' => setting('payment_policy', 'before_processing'),
    'labels' => ['colors' => COLORS, 'sides' => SIDES, 'bindings' => BINDINGS, 'fields' => FIELD_LABELS],
    'links' => ['orders' => url('customer/orders.php'), 'track' => url('track.php'), 'view' => url('customer/order_view.php?ref=')],
];
dash_header('New Printing Order', 'customer', 'order_new');
?>
<div id="wizard" class="wizard-container" style="max-width:1240px">
  <noscript><div class="alert alert-error">JavaScript is required to use the order form.</div></noscript>
</div>
<script>window.ORDER = <?= json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;</script>
<?php dash_footer('<script src="' . e(url('assets/js/order.js')) . '"></script>');
