<?php
require __DIR__ . '/../../app/bootstrap.php';
$admin = require_login('admin'); $aid = (int)$admin['id'];
if (is_post()) {
    csrf_check();
    $err = post('action') === 'pickup' ? confirm_pickup((int)post('order_id'), $aid, post('picked_up_by'), '', post('cash') === '1') : 'Unknown action.';
    flash($err ? 'error' : 'success', $err ?: 'Pickup confirmed. Order completed.'); redirect('admin/pickups.php');
}
$base = 'SELECT o.*, c.full_name, c.phone, i.service_name FROM orders o LEFT JOIN customer_profiles c ON c.user_id = o.customer_id LEFT JOIN order_items i ON i.order_id = o.id';
$cols = [
    'Awaiting Processing' => fetch_all("$base WHERE o.status IN ('order_received','awaiting_review','awaiting_customer_confirmation','pending_payment','payment_verified') GROUP BY o.id ORDER BY o.id"),
    'In Production' => fetch_all("$base WHERE o.status IN ('processing','printing','quality_checking') GROUP BY o.id ORDER BY o.id"),
    'Ready for Pickup' => fetch_all("$base WHERE o.status = 'ready_for_pickup' GROUP BY o.id ORDER BY o.id"),
];
$done = fetch_all("SELECT o.*, c.full_name, c.phone, i.service_name, pr.picked_up_at, pr.picked_up_by FROM orders o JOIN pickup_records pr ON pr.order_id = o.id
  LEFT JOIN customer_profiles c ON c.user_id = o.customer_id LEFT JOIN order_items i ON i.order_id = o.id GROUP BY o.id ORDER BY pr.id DESC LIMIT 25");
dash_header('Pickups', 'admin', 'pickups'); ?>
<div class="kanban">
<?php foreach ($cols as $title => $list): ?><div class="col"><h3><?= e($title) ?> <span class="badge badge-muted"><?= count($list) ?></span></h3>
 <?php foreach ($list as $o): ?><div class="kcard"><a href="<?= e(url('admin/order_view.php?id=' . $o['id'])) ?>"><strong><?= e($o['reference']) ?></strong></a> <?= status_badge($o['status']) ?>
   <div><?= e($o['full_name']) ?> · <?= e($o['phone']) ?></div><div class="muted small"><?= e($o['service_name']) ?> · <?= peso(order_total($o)) ?> · <?= status_badge($o['payment_status']) ?></div>
   <?php if ($title === 'Ready for Pickup'): ?><form method="post" style="margin-top:.5rem"><?= csrf_field() ?><input type="hidden" name="action" value="pickup"><input type="hidden" name="order_id" value="<?= $o['id'] ?>"><input type="hidden" name="picked_up_by" value="<?= e($o['full_name']) ?>">
     <?php if ($o['payment_status'] !== 'paid'): ?><label class="check small"><input type="checkbox" name="cash" value="1"> Cash <?= peso(order_total($o)) ?> received</label><?php endif; ?>
     <button class="btn btn-sm btn-ok" style="margin-top:.4rem" data-confirm="Confirm that <?= e($o['full_name']) ?> collected <?= e($o['reference']) ?>?">Confirm Pickup</button></form><?php endif; ?></div>
 <?php endforeach; if (!$list) echo '<p class="muted small">Nothing here.</p>'; ?></div><?php endforeach; ?>
</div>
<h2 style="margin-top:1.5rem">Picked up</h2>
<div class="table-wrap"><table><thead><tr><th>Order</th><th>Customer</th><th>Contact</th><th>Collected by</th><th>Pickup date &amp; time</th><th>Payment</th></tr></thead><tbody>
<?php foreach ($done as $o): ?><tr><td><a href="<?= e(url('admin/order_view.php?id=' . $o['id'])) ?>"><?= e($o['reference']) ?></a></td><td><?= e($o['full_name']) ?></td><td><?= e($o['phone']) ?></td><td><?= e($o['picked_up_by']) ?></td><td><?= fmt_dt($o['picked_up_at']) ?></td><td><?= status_badge($o['payment_status']) ?></td></tr>
<?php endforeach; if (!$done) echo '<tr><td colspan="6" class="empty">No pickups recorded yet.</td></tr>'; ?></tbody></table></div>
<?php dash_footer();
