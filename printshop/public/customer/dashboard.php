<?php
require __DIR__ . '/../../app/bootstrap.php';
$u = require_login('customer'); $uid = (int)$u['id'];
$cnt = fn(string $in) => (int)fetch_val("SELECT COUNT(*) FROM orders WHERE customer_id = ? AND status IN ($in)", [$uid]);
$stats = [
    ['Total Orders', (int)fetch_val('SELECT COUNT(*) FROM orders WHERE customer_id = ?', [$uid]), 'clipboard-list'],
    ['Pending Review', $cnt("'order_received','awaiting_review','awaiting_customer_confirmation','pending_payment','payment_verified'"), 'hourglass'],
    ['In Production', $cnt("'processing','printing','quality_checking'"), 'printer'],
    ['Ready for Pickup', $cnt("'ready_for_pickup'"), 'package-check'],
    ['Completed', $cnt("'completed'"), 'circle-check'],
];
$todo = fetch_all("SELECT reference, status, payment_status FROM orders WHERE customer_id = ? AND status IN ('awaiting_customer_confirmation','pending_payment','ready_for_pickup') ORDER BY id DESC", [$uid]);
$recent = fetch_all('SELECT o.*, i.service_name FROM orders o LEFT JOIN order_items i ON i.order_id = o.id WHERE o.customer_id = ? GROUP BY o.id ORDER BY o.id DESC LIMIT 6', [$uid]);
dash_header('Dashboard', 'customer', 'dashboard'); ?>

<div class="card" style="margin-bottom:1.5rem;background:linear-gradient(135deg,var(--navy) 0%,var(--navy-2) 100%);color:#fff">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem">
    <div>
      <span class="eyebrow" style="color:#bfdbfe">Customer Portal</span>
      <h2 style="color:#fff;margin:0 0 0.35rem">Welcome back, <?= e($u['full_name']) ?>.</h2>
      <p class="muted" style="color:#cbd5e1;margin:0">Manage active print orders, review price updates, and check in-store pickup readiness.</p>
    </div>
    <a class="btn btn-primary btn-lg" href="<?= e(url('customer/order_new.php')) ?>"><?= icon('file-plus-2') ?> New Printing Order</a>
  </div>
</div>

<div class="grid g4" style="margin-bottom:1.5rem">
  <?php foreach ($stats as [$l, $n, $ic]): ?>
    <div class="card stat">
      <div class="ico"><?= icon($ic) ?></div>
      <div>
        <div class="num" data-count="<?= $n ?>"><?= $n ?></div>
        <div class="lbl"><?= e($l) ?></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php foreach ($todo as $t):
    $msg = match ($t['status']) {
        'awaiting_customer_confirmation' => 'needs your confirmation of the updated total.',
        'pending_payment' => $t['payment_status'] === 'pending_verification' ? 'payment proof is waiting for shop staff verification.' : 'is waiting for payment proof submission.',
        default => 'is finished and ready for pickup at our counter!' }; ?>
  <div class="alert alert-<?= $t['status'] === 'ready_for_pickup' ? 'success' : 'warn' ?>" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.75rem">
    <div>
      <?= icon($t['status'] === 'ready_for_pickup' ? 'package-check' : 'alert-circle') ?>
      <strong>Order <?= e($t['reference']) ?></strong> <?= $msg ?>
    </div>
    <a class="btn btn-sm <?= $t['status'] === 'ready_for_pickup' ? 'btn-ok' : 'btn-primary' ?>" href="<?= e(url('customer/order_view.php?ref=' . $t['reference'])) ?>">Open Order</a>
  </div>
<?php endforeach; ?>

<div class="card" style="margin-top:1.5rem">
  <div class="card-head">
    <div>
      <h2>Recent print orders</h2>
      <span class="muted small">Your latest order activity and production status</span>
    </div>
    <a class="btn btn-outline btn-sm" href="<?= e(url('customer/orders.php')) ?>">View all orders</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Reference</th>
          <th>Order Date</th>
          <th>Service</th>
          <th>Total</th>
          <th>Payment</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recent as $o): ?>
          <tr>
            <td><strong style="font-family:var(--font-mono)"><a href="<?= e(url('customer/order_view.php?ref=' . $o['reference'])) ?>"><?= e($o['reference']) ?></a></strong></td>
            <td><?= fmt_dt($o['created_at'], false) ?></td>
            <td><?= e($o['service_name']) ?></td>
            <td><strong><?= peso(order_total($o)) ?></strong></td>
            <td><?= status_badge($o['payment_status']) ?></td>
            <td><?= status_badge($o['status']) ?></td>
            <td class="right"><a class="btn btn-sm btn-outline" href="<?= e(url('customer/order_view.php?ref=' . $o['reference'])) ?>">Details</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$recent): ?>
          <tr>
            <td colspan="7" class="empty">
              No orders yet. <a class="btn btn-sm btn-primary" style="margin-left:0.5rem" href="<?= e(url('customer/order_new.php')) ?>">Place your first order</a>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php dash_footer();