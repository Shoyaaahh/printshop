<?php
require __DIR__ . '/../app/bootstrap.php';
$u = current_user();
if (!$u) redirect('login.php');
$p = fetch_one("SELECT p.*, o.reference, o.customer_id, o.final_total, o.estimated_total, c.full_name, c.phone, i.service_name
    FROM payments p JOIN orders o ON o.id = p.order_id LEFT JOIN customer_profiles c ON c.user_id = o.customer_id
    LEFT JOIN order_items i ON i.order_id = o.id WHERE p.id = ? AND p.status = 'paid' GROUP BY p.id", [(int)get('id')]);
if (!$p || ($u['role'] !== 'admin' && (int)$p['customer_id'] !== (int)$u['id'])) { http_response_code(404); exit('Receipt not found.'); }
$methods = payment_methods();
page_head('Receipt ' . $p['receipt_no'], 'receipt-page'); ?>
<div class="receipt">
  <h2 style="margin:0"><?= e(setting('business_name')) ?></h2>
  <div style="color:#555;font-size:.9rem"><?= nl2br(e(setting('address'))) ?><br><?= e(setting('phone')) ?></div>
  <hr><h3>OFFICIAL PAYMENT RECEIPT</h3>
  <table style="width:100%">
    <tr><td>Receipt no.</td><td><strong><?= e($p['receipt_no']) ?></strong></td></tr>
    <tr><td>Order reference</td><td><?= e($p['reference']) ?></td></tr>
    <tr><td>Customer</td><td><?= e($p['full_name']) ?></td></tr>
    <tr><td>Service</td><td><?= e($p['service_name']) ?></td></tr>
    <tr><td>Payment method</td><td><?= e($methods[$p['method']]['label'] ?? $p['method']) ?><?= $p['reference_no'] ? ' · Ref ' . e($p['reference_no']) : '' ?></td></tr>
    <tr><td>Date verified</td><td><?= fmt_dt($p['verified_at']) ?></td></tr>
    <tr><td><strong>Amount paid</strong></td><td><strong style="font-size:1.2rem"><?= peso($p['amount']) ?></strong></td></tr>
  </table>
  <hr><p style="font-size:.85rem;color:#555">Thank you! Present your order reference when picking up your order.</p>
  <button class="btn btn-primary no-print" onclick="window.print()">Print receipt</button>
</div>
<?php page_scripts();
