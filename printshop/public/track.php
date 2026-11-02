<?php
require __DIR__ . '/../app/bootstrap.php';
$ref = strtoupper(preg_replace('/\s+/', '', get('ref')));
$order = null; $error = null;
if ($ref !== '') {
    if (!rate_limit('track:' . client_ip(), 30, 600)) $error = 'Too many lookups. Please wait a few minutes.';
    else {
        $order = load_order('o.reference = ?', [$ref]);
        if (!$order) $error = 'No order found with that reference number. Check it and try again.';
    }
}
public_header('Track Order', 'track'); ?>
<div class="container" style="max-width:960px">
  <div class="page-title" data-reveal>
    <span class="eyebrow">Real-Time Order Monitoring</span>
    <h1>Track order progress</h1>
    <p>Enter the reference code you received when placing your order (e.g. <code>PS-250101-AB12CD</code>) to check live production status.</p>
  </div>

  <form class="card" method="get" style="display:flex;gap:0.85rem;align-items:flex-end;flex-wrap:wrap;margin-bottom:1.5rem" data-reveal>
    <div class="field" style="flex:1;margin:0;min-width:260px">
      <label for="ref"><?= icon('barcode') ?> Order Reference Number</label>
      <input id="ref" name="ref" value="<?= e($ref) ?>" placeholder="PS-YYMMDD-XXXXXX" required style="font-family:var(--font-mono);font-size:1.05rem;font-weight:600;letter-spacing:0.04em">
    </div>
    <button class="btn btn-primary btn-lg"><?= icon('search') ?> Track Order</button>
  </form>

  <?php if ($error) echo '<div class="alert alert-error" style="margin:1rem 0">' . icon('alert-circle') . ' ' . e($error) . '</div>'; ?>

  <?php if ($order): $it = $order['item']; ?>
    <div class="card" style="margin-top:1.5rem;padding:2rem" data-reveal>
      <div class="card-head" style="flex-wrap:wrap;gap:1rem;border-bottom:1px solid var(--border);padding-bottom:1.25rem;margin-bottom:1.5rem">
        <div>
          <span class="eyebrow" style="margin-bottom:0.25rem">Active Printing Order</span>
          <h2 style="font-family:var(--font-mono);font-size:1.85rem;margin:0;color:var(--blue);letter-spacing:0.04em"><?= e($order['reference']) ?></h2>
        </div>
        <div style="display:flex;gap:0.5rem;align-items:center">
          <?= status_badge($order['status']) ?>
          <?= status_badge($order['payment_status']) ?>
        </div>
      </div>

      <!-- Animated Progress Tracker -->
      <div style="margin-bottom:2rem">
        <?= order_progress($order['status']) ?>
      </div>

      <!-- Pickup Pass Highlight (if ready for pickup) -->
      <?php if ($order['status'] === 'ready_for_pickup'): ?>
        <div class="pickup-pass-box card" style="background:linear-gradient(135deg,var(--ok-bg),var(--surface));border:2px dashed var(--ok);margin-bottom:1.75rem">
          <div style="display:flex;gap:1rem;align-items:center">
            <span class="svc-ph" style="width:52px;height:52px;background:var(--ok);color:#fff;border-radius:50%"><?= icon('package-check') ?></span>
            <div>
              <h3 style="color:var(--ok);margin:0 0 0.25rem">Ready for store pickup!</h3>
              <p style="margin:0;font-size:0.95rem">Your order is packaged and waiting at the counter. Show reference <strong><?= e($order['reference']) ?></strong> upon arrival.</p>
              <p class="muted small" style="margin:0.35rem 0 0"><?= icon('map-pin') ?> Store Address: <?= e(str_replace("\n", ', ', setting('address'))) ?></p>
            </div>
          </div>
        </div>
      <?php elseif ($order['status'] === 'completed'): ?>
        <div class="alert alert-success" style="margin-bottom:1.5rem">
          <?= icon('check-circle') ?> <strong>Completed:</strong> This order has been verified and picked up from our store.
        </div>
      <?php elseif ($order['status'] !== 'cancelled'): ?>
        <div class="alert alert-info" style="margin-bottom:1.5rem">
          <?= icon('clock') ?> <strong>In Production:</strong> We are processing your job. You will receive an in-app notification when it is ready for counter pickup.
        </div>
      <?php endif; ?>

      <!-- Specifications Grid -->
      <div class="grid g2" style="margin-bottom:1.75rem">
        <div>
          <h3>Job Details</h3>
          <dl class="kv">
            <dt>Service</dt><dd><strong><?= e($it['service_name'] ?? '—') ?></strong></dd>
            <dt>Submitted Date</dt><dd><?= fmt_dt($order['created_at']) ?></dd>
            <dt>Target Completion</dt><dd><strong><?= fmt_dt($order['estimated_completion'], false) ?></strong></dd>
          </dl>
        </div>
        <div>
          <h3>Status &amp; Collection</h3>
          <dl class="kv">
            <dt>Current Stage</dt><dd><?= e(ORDER_STATUSES[$order['status']]) ?></dd>
            <dt>Payment</dt><dd><?= status_badge($order['payment_status']) ?></dd>
            <dt>Pickup Counter</dt><dd><?= e(str_replace("\n", ', ', setting('address'))) ?></dd>
          </dl>
        </div>
      </div>

      <h3 style="margin-top:1.5rem;padding-top:1.25rem;border-top:1px solid var(--border)">Production Milestone History</h3>
      <ul class="timeline" style="margin-top:1rem">
        <?php foreach (array_reverse($order['history']) as $h): ?>
          <li class="<?= $h['status'] === $order['status'] ? 'timeline-current' : '' ?>">
            <strong><?= e(ORDER_STATUSES[$h['status']] ?? $h['status']) ?><?= $h['status'] === $order['status'] ? ' · Current Status' : '' ?></strong>
            <div class="muted small"><?= fmt_dt($h['created_at']) ?></div>
            <?php if (!empty($h['note'])): ?><div class="small" style="margin-top:0.25rem"><?= e($h['note']) ?></div><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>

      <p class="small muted" style="margin-top:1.5rem;padding-top:1rem;border-top:1px dashed var(--border)">
        Looking to upload payment proof, view detailed price receipts, or download your files? <a href="<?= e(url('login.php')) ?>">Log in to your Customer Portal</a>.
      </p>
    </div>
  <?php endif; ?>
</div>
<?php public_footer();