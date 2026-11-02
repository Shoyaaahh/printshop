<?php
require __DIR__ . '/../../app/bootstrap.php';
require_login('admin');
$types = ['sales_day' => 'Daily sales', 'sales_week' => 'Weekly sales', 'sales_month' => 'Monthly sales', 'sales_year' => 'Annual revenue',
    'services' => 'Service popularity', 'activity' => 'Order activity', 'payments' => 'Payment report'];
$type = isset($types[get('type')]) ? get('type') : 'sales_day';
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', get('from')) ? get('from') : date('Y-m-01');
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', get('to')) ? get('to') : date('Y-m-d');
$p = [$from, $to];
if (str_starts_with($type, 'sales_')) {
    $fmt = ['sales_day' => '%Y-%m-%d', 'sales_week' => '%Y-W%W', 'sales_month' => '%Y-%m', 'sales_year' => '%Y'][$type];
    $head = ['Period', 'Verified payments', 'Revenue (₱)'];
    $rows = fetch_all("SELECT strftime('$fmt', verified_at) AS period, COUNT(*) AS n, ROUND(SUM(amount),2) AS total FROM payments WHERE status = 'paid' AND date(verified_at) BETWEEN ? AND ? GROUP BY period ORDER BY period", $p);
    $rows = array_map(fn($r) => [$r['period'], $r['n'], number_format((float)$r['total'], 2, '.', '')], $rows);
    $sum = array_sum(array_map(fn($r) => (float)$r[2], $rows)); $foot = ['Total', array_sum(array_column($rows, 1)), number_format($sum, 2, '.', '')];
} elseif ($type === 'services') {
    $head = ['Service', 'Orders', 'Units / copies', 'Order value (₱)'];
    $rows = array_map(fn($r) => [$r['service_name'], $r['n'], $r['units'], number_format((float)$r['val'], 2, '.', '')],
        fetch_all("SELECT i.service_name, COUNT(*) AS n, SUM(i.copies) AS units, SUM(COALESCE(o.final_total, o.estimated_total)) AS val FROM order_items i JOIN orders o ON o.id = i.order_id WHERE o.status <> 'cancelled' AND date(o.created_at) BETWEEN ? AND ? GROUP BY i.service_name ORDER BY n DESC", $p));
} elseif ($type === 'activity') {
    $head = ['Date', 'Orders received', 'Completed', 'Cancelled', 'Still open'];
    $rows = array_map(fn($r) => array_values($r), fetch_all("SELECT date(created_at) AS d, COUNT(*), SUM(status='completed'), SUM(status='cancelled'), SUM(status NOT IN ('completed','cancelled')) FROM orders WHERE date(created_at) BETWEEN ? AND ? GROUP BY d ORDER BY d", $p));
} else {
    $head = ['Date', 'Order', 'Customer', 'Method', 'Reference no.', 'Amount (₱)', 'Status'];
    $rows = array_map(fn($r) => [$r['submitted_at'], $r['reference'], $r['full_name'], $r['method'], $r['reference_no'], number_format((float)$r['amount'], 2, '.', ''), $r['status']],
        fetch_all("SELECT p.*, o.reference, c.full_name FROM payments p JOIN orders o ON o.id = p.order_id LEFT JOIN customer_profiles c ON c.user_id = o.customer_id WHERE date(p.submitted_at) BETWEEN ? AND ? ORDER BY p.id", $p));
}
if (get('export') === 'csv') {
    audit('report_export', 'report', $type, "$from..$to");
    header('Content-Type: text/csv; charset=utf-8'); header("Content-Disposition: attachment; filename=\"$type-$from-to-$to.csv\"");
    $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
    $safe = fn($v) => is_string($v) && $v !== '' && strpbrk($v[0], '=+-@') !== false ? "'" . $v : $v;   // CSV-injection guard
    fputcsv($out, $head);
    foreach ($rows as $r) fputcsv($out, array_map($safe, $r));
    if (isset($foot)) fputcsv($out, $foot);
    exit;
}
dash_header('Reports', 'admin', 'reports'); ?>
<form class="filters" method="get">
  <div class="field"><label for="t">Report</label><select id="t" name="type"><?php foreach ($types as $k => $l) echo '<option value="' . $k . '"' . ($type === $k ? ' selected' : '') . '>' . e($l) . '</option>'; ?></select></div>
  <div class="field"><label for="f">From</label><input id="f" type="date" name="from" value="<?= e($from) ?>"></div><div class="field"><label for="to">To</label><input id="to" type="date" name="to" value="<?= e($to) ?>"></div>
  <button class="btn btn-primary">Generate</button><button class="btn btn-outline" name="export" value="csv"><?= icon('download') ?> Export CSV</button></form>
<p class="muted small">Revenue only counts payments an administrator verified (or cash you recorded). Dates use the payment verification date.</p>
<div class="table-wrap"><table><thead><tr><?php foreach ($head as $h) echo '<th>' . e($h) . '</th>'; ?></tr></thead><tbody>
<?php foreach ($rows as $r) echo '<tr>' . implode('', array_map(fn($c) => '<td>' . e($c ?? '') . '</td>', $r)) . '</tr>'; if (!$rows) echo '<tr><td colspan="' . count($head) . '" class="empty">No data for this period.</td></tr>'; ?>
</tbody><?php if (isset($foot) && $rows): ?><tfoot><tr><?php foreach ($foot as $c) echo '<th>' . e($c) . '</th>'; ?></tr></tfoot><?php endif; ?></table></div>
<?php dash_footer();
