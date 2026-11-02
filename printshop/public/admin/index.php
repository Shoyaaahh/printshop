<?php
require __DIR__ . '/../../app/bootstrap.php';
$u = require_login('admin');
$cnt = fn(string $in) => (int)fetch_val("SELECT COUNT(*) FROM orders WHERE status IN ($in)");
$revenue = (float)fetch_val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'paid'");
$pendingPay = fetch_one("SELECT COUNT(*) AS n, COALESCE(SUM(amount),0) AS amt FROM payments WHERE status = 'pending_verification'");
$stats = [
    ['Total Orders', (int)fetch_val('SELECT COUNT(*) FROM orders'), 'clipboard-list', 'orders.php'],
    ['Pending Orders', $cnt("'order_received','awaiting_review','awaiting_customer_confirmation','pending_payment','payment_verified'"), 'hourglass', 'orders.php?group=pending'],
    ['Processing Orders', $cnt("'processing','printing','quality_checking'"), 'printer', 'orders.php?group=processing'],
    ['Ready for Pickup', $cnt("'ready_for_pickup'"), 'package-check', 'pickups.php'],
    ['Completed Orders', $cnt("'completed'"), 'circle-check', 'orders.php?status=completed'],
    ['Total Revenue', peso($revenue), 'banknote', 'reports.php'],
    ['Pending Payments', (int)$pendingPay['n'] . ' (' . peso($pendingPay['amt']) . ')', 'wallet', 'payments.php?status=pending_verification'],
    ['Registered Customers', (int)fetch_val("SELECT COUNT(*) FROM users WHERE role = 'customer'"), 'users', 'customers.php'],
];
function series(string $fmt, array $keys, string $from): array
{
    $rows = fetch_all("SELECT strftime('$fmt', verified_at) AS k, SUM(amount) AS t FROM payments WHERE status = 'paid' AND date(verified_at) >= ? GROUP BY k", [$from]);
    $m = array_column($rows, 't', 'k');
    return array_map(fn($k) => round((float)($m[$k] ?? 0), 2), $keys);
}
$days = $months = [];
for ($i = 13; $i >= 0; $i--) $days[date('Y-m-d', strtotime("-$i day"))] = date('M j', strtotime("-$i day"));
for ($i = 11; $i >= 0; $i--) $months[date('Y-m', strtotime("first day of -$i month"))] = date('M Y', strtotime("first day of -$i month"));
$wkVals = []; $wkLabels = [];
for ($i = 7; $i >= 0; $i--) {   // calendar weeks (Monday to Sunday)
    $mon = date('Y-m-d', strtotime('monday this week -' . $i . ' week')); $sun = date('Y-m-d', strtotime($mon . ' +6 day'));
    $wkLabels[] = 'Wk of ' . date('M j', strtotime($mon));
    $wkVals[] = round((float)fetch_val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid' AND date(verified_at) BETWEEN ? AND ?", [$mon, $sun]), 2);
}
$chart = [
    'daily' => ['labels' => array_values($days), 'data' => series('%Y-%m-%d', array_keys($days), array_key_first($days))],
    'weekly' => ['labels' => $wkLabels, 'data' => $wkVals],
    'monthly' => ['labels' => array_values($months), 'data' => series('%Y-%m', array_keys($months), array_key_first($months) . '-01')],
];
$pop = fetch_all("SELECT i.service_name AS n, COUNT(*) AS c FROM order_items i JOIN orders o ON o.id = i.order_id WHERE o.status <> 'cancelled' GROUP BY i.service_name ORDER BY c DESC LIMIT 8");
$dist = fetch_all('SELECT status, COUNT(*) AS c FROM orders GROUP BY status ORDER BY c DESC');
$chart['popular'] = ['labels' => array_column($pop, 'n'), 'data' => array_map('intval', array_column($pop, 'c'))];
$chart['status'] = ['labels' => array_map(fn($r) => ORDER_STATUSES[$r['status']], $dist), 'data' => array_map('intval', array_column($dist, 'c'))];
$recent = fetch_all('SELECT o.*, c.full_name, i.service_name FROM orders o LEFT JOIN customer_profiles c ON c.user_id = o.customer_id LEFT JOIN order_items i ON i.order_id = o.id GROUP BY o.id ORDER BY o.id DESC LIMIT 6');
$attention = (int)fetch_val("SELECT COUNT(*) FROM orders WHERE status = 'awaiting_review'");
dash_header('Dashboard', 'admin', 'index'); ?>

<?php if ($attention): ?>
  <div class="alert alert-warn" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.75rem;margin-bottom:1.5rem">
    <div>
      <?= icon('alert-triangle') ?>
      <strong><?= $attention ?></strong> order(s) waiting for technician review.
    </div>
    <a class="btn btn-sm btn-primary" href="<?= e(url('admin/orders.php?status=awaiting_review')) ?>">Review now <?= icon('arrow-right') ?></a>
  </div>
<?php endif; ?>

<!-- Executive KPI Metrics -->
<div class="grid g4" style="margin-bottom:1.5rem">
  <?php foreach ($stats as [$l, $n, $ic, $href]): ?>
    <a class="card stat" href="<?= e(url('admin/' . $href)) ?>" style="color:inherit;text-decoration:none">
      <div class="ico"><?= icon($ic) ?></div>
      <div>
        <div class="num" style="font-size:<?= is_string($n) ? '1.15rem' : '1.5rem' ?>"><?= e($n) ?></div>
        <div class="lbl"><?= e($l) ?></div>
      </div>
    </a>
  <?php endforeach; ?>
</div>

<!-- Interactive Analytics Charts -->
<div class="grid g2" style="margin-bottom:1.5rem">
  <div class="card">
    <div class="card-head">
      <div>
        <h3>Sales (verified payments)</h3>
        <span class="muted small">Actual collected revenue</span>
      </div>
      <select id="period" aria-label="Sales period" style="width:auto;min-height:36px;padding:0.35rem 0.75rem">
        <option value="daily">Daily (14 days)</option>
        <option value="weekly">Weekly (8 weeks)</option>
        <option value="monthly">Monthly (12 months)</option>
      </select>
    </div>
    <div class="chart-box"><canvas id="salesChart"></canvas></div>
  </div>

  <div class="card">
    <div class="card-head">
      <div>
        <h3>Revenue trend (monthly)</h3>
        <span class="muted small">Rolling 12-month trajectory</span>
      </div>
    </div>
    <div class="chart-box"><canvas id="trendChart"></canvas></div>
  </div>

  <div class="card">
    <div class="card-head">
      <div>
        <h3>Popular printing services</h3>
        <span class="muted small">Volume distribution by service</span>
      </div>
    </div>
    <div class="chart-box"><canvas id="popChart"></canvas></div>
  </div>

  <div class="card">
    <div class="card-head">
      <div>
        <h3>Order status distribution</h3>
        <span class="muted small">Live production pipeline breakdown</span>
      </div>
    </div>
    <div class="chart-box"><canvas id="statusChart"></canvas></div>
  </div>
</div>

<!-- Latest Orders Table -->
<div class="card">
  <div class="card-head">
    <div>
      <h3>Latest incoming orders</h3>
      <span class="muted small">Real-time submissions awaiting action or in production</span>
    </div>
    <a class="btn btn-sm btn-outline" href="<?= e(url('admin/orders.php')) ?>"><?= icon('list') ?> All orders</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Reference</th>
          <th>Customer</th>
          <th>Service</th>
          <th>Total</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recent as $o): ?>
          <tr>
            <td><strong style="font-family:var(--font-mono)"><a href="<?= e(url('admin/order_view.php?id=' . $o['id'])) ?>"><?= e($o['reference']) ?></a></strong></td>
            <td><?= e($o['full_name']) ?></td>
            <td><?= e($o['service_name']) ?></td>
            <td><strong><?= peso(order_total($o)) ?></strong></td>
            <td><?= status_badge($o['status']) ?></td>
            <td class="right"><a class="btn btn-sm btn-outline" href="<?= e(url('admin/order_view.php?id=' . $o['id'])) ?>">Open</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$recent): ?>
          <tr><td colspan="6" class="empty">No orders recorded in system yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>window.CHART = <?= json_encode($chart, JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<?php dash_footer('<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script><script src="' . e(url('assets/js/admin-charts.js')) . '"></script>');