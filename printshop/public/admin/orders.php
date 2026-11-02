<?php
require __DIR__ . '/../../app/bootstrap.php';
require_login('admin');
$q = get('q'); $st = get('status'); $cust = get('customer'); $svc = (int)get('service'); $from = get('from'); $to = get('to'); $grp = get('group');
$where = '1=1'; $p = [];
if ($q !== '') { $where .= ' AND o.reference LIKE ?'; $p[] = '%' . strtoupper($q) . '%'; }
if ($cust !== '') { $where .= ' AND (c.full_name LIKE ? OR u.email LIKE ? OR c.phone LIKE ?)'; array_push($p, "%$cust%", "%$cust%", "%$cust%"); }
if ($svc) { $where .= ' AND i.service_id = ?'; $p[] = $svc; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $where .= ' AND date(o.created_at) >= ?'; $p[] = $from; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) { $where .= ' AND date(o.created_at) <= ?'; $p[] = $to; }
if (isset(ORDER_STATUSES[$st])) { $where .= ' AND o.status = ?'; $p[] = $st; }
elseif ($grp === 'pending') $where .= " AND o.status IN ('order_received','awaiting_review','awaiting_customer_confirmation','pending_payment','payment_verified')";
elseif ($grp === 'processing') $where .= " AND o.status IN ('processing','printing','quality_checking')";
$pg = paginate("SELECT o.*, c.full_name, u.email, i.service_name FROM orders o JOIN users u ON u.id = o.customer_id
    LEFT JOIN customer_profiles c ON c.user_id = o.customer_id LEFT JOIN order_items i ON i.order_id = o.id WHERE $where GROUP BY o.id ORDER BY o.id DESC", $p, 15);
$services = fetch_all('SELECT id, name FROM services ORDER BY name');
dash_header('Orders', 'admin', 'orders'); ?>

<div class="tabs" style="margin-bottom:1.25rem">
  <a href="orders.php" class="<?= !$st && !$grp ? 'active' : '' ?>">All Orders</a>
  <a href="orders.php?status=awaiting_review" class="<?= $st === 'awaiting_review' ? 'active' : '' ?>">Awaiting Review</a>
  <a href="orders.php?group=pending" class="<?= $grp === 'pending' ? 'active' : '' ?>">Pending Payment</a>
  <a href="orders.php?group=processing" class="<?= $grp === 'processing' ? 'active' : '' ?>">In Production</a>
  <a href="orders.php?status=ready_for_pickup" class="<?= $st === 'ready_for_pickup' ? 'active' : '' ?>">Ready for Pickup</a>
  <a href="orders.php?status=completed" class="<?= $st === 'completed' ? 'active' : '' ?>">Completed</a>
</div>

<form class="filters" method="get">
  <div class="field"><label for="q">Order reference</label><input id="q" name="q" value="<?= e($q) ?>" placeholder="PS-…"></div>
  <div class="field"><label for="c">Customer</label><input id="c" name="customer" value="<?= e($cust) ?>" placeholder="Name, email, phone"></div>
  <div class="field"><label for="s">Service</label><select id="s" name="service"><option value="">All services</option><?php foreach ($services as $s) echo '<option value="' . $s['id'] . '"' . ($svc === (int)$s['id'] ? ' selected' : '') . '>' . e($s['name']) . '</option>'; ?></select></div>
  <div class="field"><label for="st">Status</label><select id="st" name="status"><option value="">All statuses</option><?php foreach (ORDER_STATUSES as $k => $l) echo '<option value="' . $k . '"' . ($st === $k ? ' selected' : '') . '>' . e($l) . '</option>'; ?></select></div>
  <div class="field"><label for="f">From</label><input id="f" type="date" name="from" value="<?= e($from) ?>"></div>
  <div class="field"><label for="t">To</label><input id="t" type="date" name="to" value="<?= e($to) ?>"></div>
  <button class="btn btn-primary"><?= icon('filter') ?> Filter</button><a class="btn btn-outline" href="orders.php">Reset</a>
</form>

<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>Reference</th>
        <th>Date</th>
        <th>Customer</th>
        <th>Service</th>
        <th>Total</th>
        <th>Payment</th>
        <th>Status</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($pg['rows'] as $o): ?>
        <tr>
          <td><strong style="font-family:var(--font-mono)"><a href="order_view.php?id=<?= $o['id'] ?>"><?= e($o['reference']) ?></a></strong></td>
          <td><?= fmt_dt($o['created_at']) ?></td>
          <td><?= e($o['full_name']) ?><div class="small muted"><?= e($o['email']) ?></div></td>
          <td><?= e($o['service_name']) ?></td>
          <td><strong><?= peso(order_total($o)) ?></strong></td>
          <td><?= status_badge($o['payment_status']) ?></td>
          <td><?= status_badge($o['status']) ?></td>
          <td class="right"><a class="btn btn-sm btn-outline" href="order_view.php?id=<?= $o['id'] ?>">Open</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$pg['rows']) echo '<tr><td colspan="8" class="empty">No orders match these filters.</td></tr>'; ?>
    </tbody>
  </table>
</div>
<?= pager($pg) ?>
<?php dash_footer();