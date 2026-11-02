<?php
require __DIR__ . '/../../app/bootstrap.php';
$u = require_login('customer');
$q = get('q'); $st = get('status'); $sort = get('sort', 'newest');
$where = 'o.customer_id = ?'; $p = [$u['id']];
if ($q !== '') { $where .= ' AND (o.reference LIKE ? OR i.service_name LIKE ?)'; $p[] = "%$q%"; $p[] = "%$q%"; }
if (isset(ORDER_STATUSES[$st])) { $where .= ' AND o.status = ?'; $p[] = $st; }
$order = ['newest' => 'o.id DESC', 'oldest' => 'o.id ASC', 'total_high' => 'COALESCE(o.final_total,o.estimated_total) DESC', 'total_low' => 'COALESCE(o.final_total,o.estimated_total) ASC'][$sort] ?? 'o.id DESC';
$pg = paginate("SELECT o.*, i.service_name FROM orders o LEFT JOIN order_items i ON i.order_id = o.id WHERE $where GROUP BY o.id ORDER BY $order", $p, 10);
dash_header('My Orders', 'customer', 'orders'); ?>

<div class="card" style="margin-bottom:1.5rem">
  <div class="card-head">
    <div>
      <span class="eyebrow" style="margin-bottom:0.25rem">Order Management</span>
      <h2 style="margin:0">All orders</h2>
    </div>
    <a class="btn btn-primary btn-sm" href="<?= e(url('customer/order_new.php')) ?>"><?= icon('file-plus-2') ?> New order</a>
  </div>

  <form class="filters" method="get" style="margin-top:1rem;margin-bottom:0">
    <div class="field"><label for="q">Search</label><input id="q" name="q" value="<?= e($q) ?>" placeholder="Reference or service name…"></div>
    <div class="field"><label for="st">Status</label><select id="st" name="status"><option value="">All statuses</option><?php foreach (ORDER_STATUSES as $k => $l) echo '<option value="' . $k . '"' . ($st === $k ? ' selected' : '') . '>' . e($l) . '</option>'; ?></select></div>
    <div class="field"><label for="sort">Sort order</label><select id="sort" name="sort"><?php foreach (['newest' => 'Newest first', 'oldest' => 'Oldest first', 'total_high' => 'Total: high to low', 'total_low' => 'Total: low to high'] as $k => $l) echo '<option value="' . $k . '"' . ($sort === $k ? ' selected' : '') . '>' . $l . '</option>'; ?></select></div>
    <button class="btn btn-primary"><?= icon('filter') ?> Apply</button>
    <a class="btn btn-outline" href="orders.php">Reset</a>
  </form>
</div>

<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>Reference</th>
        <th>Order Date</th>
        <th>Service</th>
        <th>Total Amount</th>
        <th>Payment</th>
        <th>Status</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($pg['rows'] as $o): ?>
        <tr>
          <td><strong style="font-family:var(--font-mono)"><a href="<?= e(url('customer/order_view.php?ref=' . $o['reference'])) ?>"><?= e($o['reference']) ?></a></strong></td>
          <td><?= fmt_dt($o['created_at']) ?></td>
          <td><?= e($o['service_name']) ?></td>
          <td><strong><?= peso(order_total($o)) ?></strong><?= $o['price_adjusted'] ? ' <span class="badge badge-info">adjusted</span>' : '' ?></td>
          <td><?= status_badge($o['payment_status']) ?></td>
          <td><?= status_badge($o['status']) ?></td>
          <td class="right"><a class="btn btn-sm btn-outline" href="<?= e(url('customer/order_view.php?ref=' . $o['reference'])) ?>">View Details</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$pg['rows']): ?>
        <tr>
          <td colspan="7" class="empty">No orders found matching the filter criteria.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?= pager($pg) ?>
<?php dash_footer();