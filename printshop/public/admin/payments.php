<?php
require __DIR__ . '/../../app/bootstrap.php';
$admin = require_login('admin'); $aid = (int)$admin['id'];
if (is_post()) {
    csrf_check(); $act = post('action'); $pid = (int)post('payment_id'); $err = null; $ok = 'Done.';
    if ($act === 'verify') { $err = mark_payment_paid($pid, $aid); $ok = 'Payment verified.'; }
    elseif ($act === 'reject') { $err = reject_payment($pid, $aid, post('reason')); $ok = 'Payment rejected.'; }
    elseif ($act === 'refund') { $err = refund_payment($pid, $aid, post('reason')); $ok = 'Payment marked as refunded.'; }
    elseif ($act === 'cash') {
        $ord = fetch_one('SELECT id FROM orders WHERE reference = ?', [strtoupper(post('reference'))]);
        $err = $ord ? record_cash_payment((int)$ord['id'], $aid, (float)post('amount')) : 'Order reference not found.'; $ok = 'Cash payment recorded.';
    }
    flash($err ? 'error' : 'success', $err ?: $ok); redirect('admin/payments.php');
}
$st = get('status'); $mt = get('method'); $from = get('from'); $to = get('to'); $q = get('q');
$where = '1=1'; $p = [];
if (isset(PAYMENT_STATUSES[$st])) { $where .= ' AND p.status = ?'; $p[] = $st; }
if (in_array($mt, PAYMENT_METHOD_KEYS, true)) { $where .= ' AND p.method = ?'; $p[] = $mt; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $where .= ' AND date(p.submitted_at) >= ?'; $p[] = $from; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) { $where .= ' AND date(p.submitted_at) <= ?'; $p[] = $to; }
if ($q !== '') { $where .= ' AND (o.reference LIKE ? OR p.reference_no LIKE ?)'; array_push($p, '%' . strtoupper($q) . '%', "%$q%"); }
$pg = paginate("SELECT p.*, o.reference, c.full_name, r.id AS receipt_id FROM payments p JOIN orders o ON o.id = p.order_id
  LEFT JOIN customer_profiles c ON c.user_id = o.customer_id LEFT JOIN payment_receipts r ON r.payment_id = p.id WHERE $where ORDER BY p.id DESC", $p, 15);
$methods = payment_methods();
dash_header('Payments', 'admin', 'payments'); ?>
<form class="filters" method="get">
  <div class="field"><label for="q">Order / payment ref.</label><input id="q" name="q" value="<?= e($q) ?>"></div>
  <div class="field"><label for="s">Status</label><select id="s" name="status"><option value="">All</option><?php foreach (PAYMENT_STATUSES as $k => $l) if ($k !== 'unpaid') echo '<option value="' . $k . '"' . ($st === $k ? ' selected' : '') . '>' . e($l) . '</option>'; ?></select></div>
  <div class="field"><label for="m">Method</label><select id="m" name="method"><option value="">All</option><?php foreach ($methods as $k => $m) echo '<option value="' . $k . '"' . ($mt === $k ? ' selected' : '') . '>' . e($m['label']) . '</option>'; ?></select></div>
  <div class="field"><label for="f">From</label><input id="f" type="date" name="from" value="<?= e($from) ?>"></div><div class="field"><label for="t">To</label><input id="t" type="date" name="to" value="<?= e($to) ?>"></div>
  <button class="btn btn-primary">Filter</button><a class="btn btn-outline" href="payments.php">Reset</a></form>
<div class="split"><div>
<div class="table-wrap"><table><thead><tr><th>Date</th><th>Order</th><th>Customer</th><th>Method</th><th>Amount</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($pg['rows'] as $r): ?><tr><td><?= fmt_dt($r['submitted_at']) ?></td><td><a href="<?= e(url('admin/order_view.php?id=' . $r['order_id'])) ?>"><?= e($r['reference']) ?></a></td><td><?= e($r['full_name']) ?></td>
<td><?= e($methods[$r['method']]['label'] ?? $r['method']) ?><div class="small muted"><?= e($r['reference_no'] ?: '') ?></div></td><td><?= peso($r['amount']) ?></td>
<td><?= status_badge($r['status']) ?><?= $r['rejection_reason'] ? '<div class="small muted">' . e($r['rejection_reason']) . '</div>' : '' ?></td>
<td style="min-width:180px"><?php if ($r['receipt_id']): ?><a class="btn btn-sm btn-outline" target="_blank" rel="noopener" href="<?= e(url('download.php?type=receipt&inline=1&id=' . $r['receipt_id'])) ?>">View proof / receipt</a> <?php endif; ?>
<?php if ($r['status'] === 'pending_verification'): ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="payment_id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-ok" name="action" value="verify" data-confirm="Verify this <?= e(peso($r['amount'])) ?> payment for <?= e($r['reference']) ?>?">Verify</button></form>
<form method="post" style="display:flex;gap:.3rem;margin-top:.4rem"><?= csrf_field() ?><input type="hidden" name="payment_id" value="<?= $r['id'] ?>"><input name="reason" placeholder="Reject reason" required style="width:130px"><button class="btn btn-sm btn-danger" name="action" value="reject" data-confirm="Reject this payment?">Reject</button></form>
<?php elseif ($r['status'] === 'paid'): ?><a class="btn btn-sm btn-outline" href="<?= e(url('receipt.php?id=' . $r['id'])) ?>">Official receipt</a>
<form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="payment_id" value="<?= $r['id'] ?>"><input type="hidden" name="reason" value="Refunded by admin"><button class="btn btn-sm btn-ghost" name="action" value="refund" data-confirm="Mark this payment as refunded? Do this only after you returned the money.">Refund</button></form><?php endif; ?></td></tr>
<?php endforeach; if (!$pg['rows']) echo '<tr><td colspan="7" class="empty">No payment records.</td></tr>'; ?></tbody></table></div><?= pager($pg) ?></div>
<form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="cash"><h3>Record cash payment</h3>
  <div class="field"><label for="rf">Order reference</label><input id="rf" name="reference" placeholder="PS-…" required></div>
  <div class="field"><label for="am">Amount received (₱)</label><input id="am" type="number" step="0.01" min="0.01" name="amount" required></div>
  <button class="btn btn-primary" data-confirm="Record this cash payment? It is marked as paid immediately.">Record payment</button></form></div>
<?php dash_footer();
