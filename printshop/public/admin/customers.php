<?php
require __DIR__ . '/../../app/bootstrap.php';
require_login('admin');
if (is_post()) {
    csrf_check(); $id = (int)post('id');
    $u = fetch_one("SELECT * FROM users WHERE id = ? AND role = 'customer'", [$id]);
    if ($u) { $new = $u['status'] === 'active' ? 'suspended' : 'active'; q('UPDATE users SET status = ? WHERE id = ?', [$new, $id]); audit('customer_' . $new, 'user', $id); flash('success', 'Account ' . $new . '.'); }
    redirect('admin/customers.php?id=' . $id);
}
if (get('id')) {
    $c = fetch_one("SELECT u.*, p.full_name, p.phone FROM users u JOIN customer_profiles p ON p.user_id = u.id WHERE u.id = ? AND u.role = 'customer'", [(int)get('id')]);
    if (!$c) redirect('admin/customers.php');
    $orders = fetch_all('SELECT o.*, i.service_name FROM orders o LEFT JOIN order_items i ON i.order_id = o.id WHERE o.customer_id = ? GROUP BY o.id ORDER BY o.id DESC', [$c['id']]);
    dash_header('Customer', 'admin', 'customers'); ?>
<p><a href="<?= e(url('admin/customers.php')) ?>">← All customers</a></p>
<div class="split"><div class="card"><div class="card-head"><h2>Orders</h2></div><div class="table-wrap"><table><thead><tr><th>Reference</th><th>Date</th><th>Service</th><th>Total</th><th>Status</th></tr></thead><tbody>
<?php foreach ($orders as $o): ?><tr><td><a href="<?= e(url('admin/order_view.php?id=' . $o['id'])) ?>"><?= e($o['reference']) ?></a></td><td><?= fmt_dt($o['created_at'], false) ?></td><td><?= e($o['service_name']) ?></td><td><?= peso(order_total($o)) ?></td><td><?= status_badge($o['status']) ?></td></tr><?php endforeach; if (!$orders) echo '<tr><td colspan="5" class="empty">No orders.</td></tr>'; ?></tbody></table></div></div>
<div class="card"><h2><?= e($c['full_name']) ?></h2><dl class="kv"><dt>Email</dt><dd><?= e($c['email']) ?></dd><dt>Contact</dt><dd><?= e($c['phone']) ?></dd><dt>Joined</dt><dd><?= fmt_dt($c['created_at'], false) ?></dd><dt>Last login</dt><dd><?= fmt_dt($c['last_login_at']) ?></dd><dt>Account</dt><dd><?= $c['status'] === 'active' ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-bad">Suspended</span>' ?></dd></dl>
<div style="margin-top:1rem"><?= confirm_form(url('admin/customers.php'), ['id' => $c['id']], $c['status'] === 'active' ? 'Suspend account' : 'Reactivate account', ($c['status'] === 'active' ? 'Suspend' : 'Reactivate') . ' this customer account?', 'btn btn-outline') ?></div></div></div>
<?php dash_footer(); exit; }
$q = get('q'); $w = "u.role = 'customer'"; $p = [];
if ($q !== '') { $w .= ' AND (c.full_name LIKE ? OR u.email LIKE ? OR c.phone LIKE ?)'; $p = ["%$q%", "%$q%", "%$q%"]; }
$pg = paginate("SELECT u.*, c.full_name, c.phone, (SELECT COUNT(*) FROM orders o WHERE o.customer_id = u.id) AS orders FROM users u JOIN customer_profiles c ON c.user_id = u.id WHERE $w ORDER BY u.id DESC", $p, 15);
dash_header('Customers', 'admin', 'customers'); ?>
<form class="filters" method="get"><div class="field"><label for="q">Search customers</label><input id="q" name="q" value="<?= e($q) ?>" placeholder="Name, email or phone"></div><button class="btn btn-primary">Search</button></form>
<div class="table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Contact</th><th>Orders</th><th>Joined</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($pg['rows'] as $c): ?><tr><td><?= e($c['full_name']) ?></td><td><?= e($c['email']) ?></td><td><?= e($c['phone']) ?></td><td><?= (int)$c['orders'] ?></td><td><?= fmt_dt($c['created_at'], false) ?></td><td><?= $c['status'] === 'active' ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-bad">Suspended</span>' ?></td><td><a class="btn btn-sm btn-outline" href="?id=<?= $c['id'] ?>">View</a></td></tr>
<?php endforeach; if (!$pg['rows']) echo '<tr><td colspan="7" class="empty">No customers found.</td></tr>'; ?></tbody></table></div><?= pager($pg) ?>
<?php dash_footer();
