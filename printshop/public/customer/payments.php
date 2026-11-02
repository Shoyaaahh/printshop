<?php
require __DIR__ . '/../../app/bootstrap.php';
$u = require_login('customer');
$pg = paginate('SELECT p.*, o.reference, r.id AS receipt_id FROM payments p JOIN orders o ON o.id = p.order_id LEFT JOIN payment_receipts r ON r.payment_id = p.id WHERE o.customer_id = ? ORDER BY p.id DESC', [$u['id']], 15);
$methods = payment_methods();
dash_header('Payments', 'customer', 'payments'); ?>
<div class="table-wrap"><table><thead><tr><th>Date</th><th>Order</th><th>Method</th><th>Reference no.</th><th>Amount</th><th>Status</th><th>Proof</th><th></th></tr></thead><tbody>
<?php foreach ($pg['rows'] as $p): ?><tr><td><?= fmt_dt($p['submitted_at']) ?></td><td><a href="<?= e(url('customer/order_view.php?ref=' . $p['reference'])) ?>"><?= e($p['reference']) ?></a></td><td><?= e($methods[$p['method']]['label'] ?? $p['method']) ?></td><td><?= e($p['reference_no'] ?: '—') ?></td><td><?= peso($p['amount']) ?></td><td><?= status_badge($p['status']) ?><?= $p['rejection_reason'] ? '<div class="small muted">' . e($p['rejection_reason']) . '</div>' : '' ?></td><td><?= $p['receipt_id'] ? '<a target="_blank" rel="noopener" href="' . e(url('download.php?type=receipt&inline=1&id=' . $p['receipt_id'])) . '">View proof</a>' : '<span class="muted">Not attached</span>' ?></td><td><?= $p['status'] === 'paid' ? '<a class="btn btn-sm btn-outline" href="' . e(url('receipt.php?id=' . $p['id'])) . '">Receipt</a>' : '' ?></td></tr>
<?php endforeach; if (!$pg['rows']) echo '<tr><td colspan="8" class="empty">No payments yet. Open an approved order to submit a payment.</td></tr>'; ?>
</tbody></table></div><?= pager($pg) ?>
<?php dash_footer();
