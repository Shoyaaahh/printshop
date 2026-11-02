<?php
require __DIR__ . '/../../app/bootstrap.php';
$admin = require_login('admin'); $aid = (int)$admin['id'];
$id = (int)get('id');
$o = load_order('o.id = ?', [$id]);
if (!$o) { flash('error', 'Order not found.'); redirect('admin/orders.php'); }
$self = 'admin/order_view.php?id=' . $id;

if (is_post()) {
    csrf_check(); $act = post('action'); $err = null; $ok = 'Saved.';
    switch ($act) {
        case 'approve':
            if ($o['status'] !== 'awaiting_review') { $err = 'Only orders awaiting review can be approved.'; break; }
            $new = round((float)post('final_total', (string)order_total($o)), 2);
            if (post('completion') !== '') q('UPDATE orders SET estimated_completion = ? WHERE id = ?', [post('completion'), $id]);
            if (abs($new - order_total($o)) > 0.004) { $err = adjust_price($id, $new, post('note'), $aid); $ok = 'Price adjusted. The customer must confirm it.'; }
            else { $err = change_status($id, 'pending_payment', post('note'), $aid); $ok = 'Order approved.'; }
            break;
        case 'reject':
            $err = change_status($id, 'cancelled', post('reason'), $aid); $ok = 'Order rejected and cancelled.'; break;
        case 'adjust':
            $err = adjust_price($id, (float)post('final_total'), post('note'), $aid); $ok = 'Price adjusted. Customer notified.'; break;
        case 'status':
            $err = change_status($id, post('status'), post('note'), $aid); $ok = 'Status updated.'; break;
        case 'details':
            $d = post('completion');
            if ($d !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) { $err = 'Invalid date.'; break; }
            q('UPDATE orders SET estimated_completion = ?, internal_notes = ?, updated_at = ? WHERE id = ?', [$d ?: null, post('internal_notes') ?: null, now(), $id]);
            add_history($id, $o['status'], 'Order details / notes updated', $aid); audit('order_update', 'order', $id); break;
        case 'cash':
            $err = record_cash_payment($id, $aid, (float)post('amount')); $ok = 'Cash payment recorded.'; break;
        case 'verify':
            $err = mark_payment_paid((int)post('payment_id'), $aid); $ok = 'Payment verified.'; break;
        case 'reject_payment':
            $err = reject_payment((int)post('payment_id'), $aid, post('reason')); $ok = 'Payment rejected.'; break;
        case 'pickup':
            $err = confirm_pickup($id, $aid, post('picked_up_by'), post('notes'), post('cash') === '1'); $ok = 'Pickup confirmed. Order completed.'; break;
        default: $err = 'Unknown action.';
    }
    flash($err ? 'error' : 'success', $err ?: $ok);
    redirect($self);
}

$it = $o['item']; $total = order_total($o); $st = $o['status'];
$next = array_values(array_filter(STATUS_FLOW[$st] ?? [], fn($s) => $s !== 'cancelled' && $s !== 'awaiting_customer_confirmation' && !($st === 'awaiting_review' && $s === 'pending_payment')));
$detected = array_sum(array_map(fn($f) => (int)$f['page_count'], $o['files']));
dash_header('Order ' . $o['reference'], 'admin', 'orders'); ?>
<p><a href="<?= e(url('admin/orders.php')) ?>">← All orders</a></p>
<div class="card"><div class="card-head"><div><h2><?= e($o['reference']) ?></h2><span class="muted small">Submitted <?= fmt_dt($o['created_at']) ?></span></div><div><?= status_badge($st) ?> <?= status_badge($o['payment_status']) ?></div></div><?= order_progress($st) ?></div>

<div class="split" style="margin-top:1rem"><div class="grid">
 <?php if ($st === 'awaiting_review'): ?>
 <form class="card" method="post" style="border-color:var(--blue)"><?= csrf_field() ?><h3>Review this order</h3>
   <p class="muted small">Check the files and specifications. Change the price if needed. The customer must confirm any change before work continues.</p>
   <div class="form-grid"><div class="field"><label for="ft">Final price (₱)</label><input id="ft" type="number" step="0.01" min="0" name="final_total" value="<?= e(number_format($total, 2, '.', '')) ?>"><div class="hint">Estimate was <?= peso($o['estimated_total']) ?></div></div>
   <div class="field"><label for="cp">Estimated completion</label><input id="cp" type="date" name="completion" value="<?= e($o['estimated_completion']) ?>"></div></div>
   <div class="field"><label for="nt">Note to customer / reason</label><input id="nt" name="note" maxlength="300"></div>
   <button class="btn btn-ok" name="action" value="approve" data-confirm="Approve this order? The customer will be notified.">Approve order</button>
   <button class="btn btn-danger" name="action" value="reject" data-confirm="Reject and cancel this order? The customer will be notified." onclick="this.form.reason.value=this.form.note.value;if(!this.form.note.value){alert('Enter the reason in the note field first.');return false}">Reject order</button>
   <input type="hidden" name="reason" value=""></form>
 <?php endif; ?>
 <div class="card"><h3>Customer</h3><dl class="kv"><dt>Name</dt><dd><?= e($o['customer_name']) ?></dd><dt>Email</dt><dd><?= e($o['customer_email']) ?></dd><dt>Contact</dt><dd><?= e($o['customer_phone']) ?></dd></dl></div>
 <div class="card"><h3>Printing specifications</h3><dl class="kv">
   <dt>Service</dt><dd><?= e($it['service_name'] ?? '') ?></dd>
   <?php foreach (['paper_size' => 'Paper size', 'paper_type' => 'Paper type', 'orientation' => 'Orientation', 'pages' => 'Pages', 'copies' => 'Copies'] as $k => $l) if (!empty($it[$k])) echo '<dt>' . $l . '</dt><dd>' . e(ucfirst((string)$it[$k])) . '</dd>'; ?>
   <?php if (!empty($it['color'])) echo '<dt>Color</dt><dd>' . e(COLORS[$it['color']] ?? $it['color']) . '</dd>'; ?>
   <?php if (!empty($it['sides'])) echo '<dt>Sides</dt><dd>' . e(SIDES[$it['sides']] ?? $it['sides']) . '</dd>'; ?>
   <?php if (!empty($it['binding']) && $it['binding'] !== 'none') echo '<dt>Binding</dt><dd>' . e(BINDINGS[$it['binding']]) . '</dd>'; ?>
   <?php if (!empty($it['width_ft'])) echo '<dt>Size</dt><dd>' . e($it['width_ft']) . ' × ' . e($it['height_ft']) . ' ft</dd>'; ?>
   <dt>Customer instructions</dt><dd><?= $o['special_instructions'] ? nl2br(e($o['special_instructions'])) : '—' ?></dd></dl>
   <?php if ($detected && !empty($it['pages']) && $detected !== (int)$it['pages']): ?><div class="alert alert-warn" style="margin:1rem 0 0">Pages entered: <?= (int)$it['pages'] ?> · pages detected in files: <?= $detected ?>. Check before approving.</div><?php endif; ?></div>
 <div class="card"><h3>Customer files</h3>
   <?php foreach ($o['files'] as $f): ?><div class="file-row"><span class="svc-ph" style="width:40px;height:40px"><?= icon('file-text') ?></span><div class="meta"><div class="name"><?= e($f['original_name']) ?></div><div class="small muted"><?= round($f['size_bytes'] / 1024) ?> KB<?= $f['page_count'] ? ' · ' . (int)$f['page_count'] . ' page(s)' : '' ?></div></div>
   <?php if (in_array($f['mime_type'], ['image/jpeg', 'image/png', 'application/pdf'], true)): ?><a class="btn btn-sm btn-ghost" target="_blank" rel="noopener" href="<?= e(url('download.php?id=' . $f['id'] . '&inline=1')) ?>">Preview</a><?php endif; ?>
   <a class="btn btn-sm btn-outline" href="<?= e(url('download.php?id=' . $f['id'])) ?>"><?= icon('download') ?> Download</a></div><?php endforeach; ?>
   <?php if (!$o['files']) echo '<p class="muted">No files uploaded. The customer will bring originals.</p>'; ?></div>
 <div class="card"><h3>Payments</h3>
   <?php foreach ($o['payments'] as $p): ?><div class="file-row" style="flex-wrap:wrap"><div class="meta"><strong><?= e(payment_methods()[$p['method']]['label'] ?? $p['method']) ?></strong> · <?= peso($p['amount']) ?> <?= status_badge($p['status']) ?><div class="small muted"><?= $p['reference_no'] ? 'Ref ' . e($p['reference_no']) . ' · ' : '' ?><?= fmt_dt($p['submitted_at']) ?><?= $p['receipt_no'] ? ' · ' . e($p['receipt_no']) : '' ?></div></div>
     <?php if ($p['receipt_id']): ?><a class="btn btn-sm btn-outline" target="_blank" rel="noopener" href="<?= e(url('download.php?type=receipt&inline=1&id=' . $p['receipt_id'])) ?>">View proof / receipt</a><?php endif; ?>
     <?php if ($p['status'] === 'pending_verification'): ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="payment_id" value="<?= $p['id'] ?>"><button class="btn btn-sm btn-ok" name="action" value="verify" data-confirm="Verify this payment of <?= peso($p['amount']) ?>?">Verify</button></form>
       <form method="post" class="inline" style="display:flex;gap:.3rem"><?= csrf_field() ?><input type="hidden" name="payment_id" value="<?= $p['id'] ?>"><input name="reason" placeholder="Reason" required style="width:140px"><button class="btn btn-sm btn-danger" name="action" value="reject_payment" data-confirm="Reject this payment?">Reject</button></form><?php endif; ?></div><?php endforeach; ?>
   <?php if (!$o['payments']) echo '<p class="muted">No payments submitted.</p>'; ?>
   <?php if ($o['payment_status'] !== 'paid' && !in_array($st, ['cancelled', 'completed'], true)): ?><form method="post" style="display:flex;gap:.5rem;align-items:end;margin-top:1rem"><?= csrf_field() ?><div class="field" style="margin:0"><label for="ca">Record cash payment (₱)</label><input id="ca" type="number" step="0.01" min="0" name="amount" value="<?= e(number_format($total, 2, '.', '')) ?>"></div><button class="btn btn-primary" name="action" value="cash" data-confirm="Record a cash payment received at the store?">Record cash</button></form><?php endif; ?></div>
</div><div class="grid">
 <div class="card"><h3>Price</h3><?php foreach (json_decode($it['breakdown'] ?? '[]', true) ?: [] as $l) echo '<div class="line price-box" style="display:flex;justify-content:space-between;padding:.2rem 0;border:0;background:none"><span>' . e($l['label']) . '</span><span>' . peso($l['amount']) . '</span></div>'; ?>
   <div class="price-box"><div class="total" style="border:0;margin:0;padding:0"><span><?= $o['final_total'] !== null ? 'Final' : 'Estimated' ?></span><span><?= peso($total) ?></span></div><?php if ($o['price_adjusted']) echo '<div class="small muted">Estimate was ' . peso($o['estimated_total']) . ($o['customer_confirmed'] ? ' · customer confirmed' : ' · awaiting customer') . '</div>'; ?></div>
   <?php if (in_array($st, ['awaiting_customer_confirmation', 'pending_payment'], true) && $o['payment_status'] !== 'paid'): ?><form method="post" style="margin-top:1rem"><?= csrf_field() ?><div class="field"><label for="af">Adjust price (₱)</label><input id="af" type="number" step="0.01" min="0" name="final_total" value="<?= e(number_format($total, 2, '.', '')) ?>"></div><div class="field"><input name="note" placeholder="Reason for the change" maxlength="300"></div><button class="btn btn-outline btn-sm" name="action" value="adjust" data-confirm="Change the price? The customer must confirm it again.">Adjust price</button></form><?php endif; ?></div>
 <?php if ($st === 'ready_for_pickup'): ?><form class="card" method="post" style="border-color:var(--ok)"><?= csrf_field() ?><h3>Confirm pickup</h3><p class="muted small">Check the customer's reference number, then confirm they collected the order. This completes the order.</p>
   <div class="field"><label for="pb">Collected by</label><input id="pb" name="picked_up_by" value="<?= e($o['customer_name']) ?>" required></div><div class="field"><label for="pn">Notes</label><input id="pn" name="notes" maxlength="200"></div>
   <?php if ($o['payment_status'] !== 'paid'): ?><label class="check" style="margin-bottom:1rem"><input type="checkbox" name="cash" value="1"> Cash of <?= peso($total) ?> received now</label><?php endif; ?>
   <button class="btn btn-ok" name="action" value="pickup" data-confirm="Confirm that the customer collected this order? It will be marked Completed.">Confirm pickup</button></form><?php endif; ?>
 <?php if ($next): ?><form class="card" method="post"><?= csrf_field() ?><h3>Update status</h3><div class="field"><label for="ns">Move to</label><select id="ns" name="status"><?php foreach ($next as $s) echo '<option value="' . $s . '">' . e(ORDER_STATUSES[$s]) . '</option>'; ?></select></div><div class="field"><input name="note" placeholder="Note (optional)" maxlength="300"></div><button class="btn btn-primary" name="action" value="status" data-confirm="Update the order status? The customer may be notified.">Update status</button></form><?php endif; ?>
 <?php if (!in_array($st, ['completed', 'cancelled', 'awaiting_review'], true) && in_array('cancelled', STATUS_FLOW[$st] ?? [], true)): ?><form class="card" method="post"><?= csrf_field() ?><h3>Cancel order</h3><div class="field"><input name="reason" placeholder="Reason (required)" required maxlength="300"></div><input type="hidden" name="status" value="cancelled"><input type="hidden" name="note" value=""><button class="btn btn-danger btn-sm" name="action" value="status" onclick="this.form.note.value=this.form.reason.value" data-confirm="Cancel this order?">Cancel order</button></form><?php endif; ?>
 <form class="card" method="post"><?= csrf_field() ?><h3>Completion &amp; internal notes</h3><div class="field"><label for="ec">Estimated completion</label><input id="ec" type="date" name="completion" value="<?= e($o['estimated_completion']) ?>"></div><div class="field"><label for="in">Internal notes (not visible to customer)</label><textarea id="in" name="internal_notes"><?= e($o['internal_notes']) ?></textarea></div><button class="btn btn-outline btn-sm" name="action" value="details">Save</button></form>
 <div class="card"><h3>History</h3><ul class="timeline"><?php foreach (array_reverse($o['history']) as $h): ?><li><strong><?= e(ORDER_STATUSES[$h['status']] ?? $h['status']) ?></strong><div class="small muted"><?= fmt_dt($h['created_at']) ?><?= $h['by_name'] ? ' · ' . e($h['by_name']) : '' ?></div><?php if ($h['note']) echo '<div class="small">' . e($h['note']) . '</div>'; ?></li><?php endforeach; ?></ul></div>
</div></div>
<?php dash_footer();
