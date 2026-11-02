<?php
require __DIR__ . '/../app/bootstrap.php';
public_header('Pricing', 'pricing');
$rules = [];
foreach (fetch_all('SELECT * FROM pricing_rules ORDER BY id') as $r) $rules[$r['group_name']][] = $r;
$svc = active_services();
$estimateServices = [];
foreach ($svc as $s) {
  $options = service_options($s);
  if (in_array('pages', $options['fields'], true) && in_array('copies', $options['fields'], true) && in_array('color', $options['fields'], true)) {
    $estimateServices[] = ['id' => (int)$s['id'], 'name' => $s['name'], 'fields' => $options['fields'], 'sizes' => $options['sizes'], 'papers' => $options['papers'], 'colors' => $options['colors'], 'sides' => $options['sides'], 'bindings' => $options['bindings']];
  }
}
?>
<div class="container">
  <div class="page-title" data-reveal>
    <span class="eyebrow">Transparent Rate Cards</span>
    <h1>Pricing &amp; rate schedules</h1>
    <p>We pride ourselves on transparent, predictable rates. All estimates in our order system are calculated directly from this published rate schedule. Custom requirements are verified upon file inspection.</p>
  </div>

  <?php if (current_user() && current_user()['role'] === 'customer' && $estimateServices): ?>
    <section class="pricing-calculator card" data-reveal>
      <div>
        <span class="eyebrow">Real-Time Estimator</span>
        <h2>Calculate a live print estimate</h2>
        <p class="muted">Uses current shop rates via our secure server pricing API.</p>
      </div>
      <form id="priceEstimator" data-api="<?= e(url('api/price.php')) ?>" data-csrf="<?= e(csrf_token()) ?>">
        <div class="form-grid">
          <div class="field"><label for="estimateService">Service</label><select id="estimateService" required></select></div>
          <div class="field"><label for="estimateColor">Print color</label><select id="estimateColor"><option value="bw">Black &amp; white</option><option value="color">Color</option></select></div>
          <div class="field"><label for="estimatePages">Pages</label><input id="estimatePages" type="number" min="1" max="5000" value="1" required></div>
          <div class="field"><label for="estimateCopies">Copies</label><input id="estimateCopies" type="number" min="1" max="10000" value="1" required></div>
        </div>
        <div class="estimate-result" aria-live="polite" id="estimateResult"><span class="muted">Enter your print details to see an estimate.</span></div>
      </form>
    </section>
    <script>window.PRICE_ESTIMATOR = <?= json_encode(['services' => $estimateServices, 'colors' => COLORS, 'sides' => SIDES, 'api' => url('api/price.php'), 'csrf' => csrf_token()], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;</script>
  <?php else: ?>
    <section class="pricing-calculator card" data-reveal>
      <div>
        <span class="eyebrow">Real-Time Estimator</span>
        <h2>Calculate a live print estimate</h2>
        <p class="muted">Sign in to your customer account to calculate live estimates using the shop’s active rates.</p>
      </div>
      <a class="btn btn-primary" href="<?= e(url('login.php')) ?>"><?= icon('log-in') ?> Log in to estimate</a>
    </section>
  <?php endif; ?>

  <div class="grid g2" style="margin-top:1.5rem">
    <?php foreach ($rules as $group => $list): ?>
      <div class="card" data-reveal>
        <div class="card-head">
          <h3><?= e($group) ?></h3>
          <span class="badge badge-muted"><?= count($list) ?> items</span>
        </div>
        <table><tbody>
          <?php foreach ($list as $r): $mult = str_contains($group, 'multiplier') || $r['rule_key'] === 'side.double'; ?>
            <tr>
              <td><?= e($r['label']) ?></td>
              <td class="right nowrap"><strong><?= $mult ? '× ' . rtrim(rtrim(number_format((float)$r['amount'], 2), '0'), '.') : peso($r['amount']) ?></strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody></table>
      </div>
    <?php endforeach; ?>
  </div>

  <details class="rate-table" data-reveal style="margin-top:2rem">
    <summary>
      <span>
        <strong>Complete service catalog &amp; standard lead times</strong>
        <small>Browse all active services, pricing structures, and preparation turnaround</small>
      </span>
      <?= icon('chevron-down') ?>
    </summary>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Service</th>
            <th>Category</th>
            <th>Base Pricing</th>
            <th>Turnaround</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($svc as $s): ?>
            <tr>
              <td><strong><?= e($s['name']) ?></strong></td>
              <td><span class="chip"><?= e($s['category_name']) ?></span></td>
              <td><strong><?= e(service_price_label($s)) ?></strong></td>
              <td><?= (int)$s['processing_days'] ?> business day(s)</td>
              <td class="right"><a class="btn btn-sm btn-outline" href="<?= e(url('customer/order_new.php?service=' . $s['slug'])) ?>">Configure</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </details>

  <div class="card" style="margin-top:1.5rem;background:var(--surface-2)" data-reveal>
    <div class="card-head">
      <h3>Formula &amp; Surcharge Transparency</h3>
    </div>
    <p class="muted small" style="margin:0">
      Standard Document Price = <code>(Pages × Copies × Rate × Size Multiplier) + (Paper Surcharge × Copies) + Binding Fee</code>. Double-sided printing applies a 10% volume discount multiplier (0.9×) to page costs. Custom sizes and special request items are reviewed by print technicians prior to final billing.
    </p>
  </div>
</div>
<?php public_footer();