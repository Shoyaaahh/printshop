<?php
require __DIR__ . '/../../app/bootstrap.php';
require_login('admin');
$pg = paginate('SELECT a.*, COALESCE(ad.full_name, c.full_name, u.email) AS who FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id LEFT JOIN admin_users ad ON ad.user_id = a.user_id LEFT JOIN customer_profiles c ON c.user_id = a.user_id ORDER BY a.id DESC', [], 25);
dash_header('Audit Log', 'admin', 'audit'); ?>
<div class="table-wrap"><table><thead><tr><th>Time</th><th>User</th><th>Action</th><th>Entity</th><th>Detail</th><th>IP</th></tr></thead><tbody>
<?php foreach ($pg['rows'] as $a): ?><tr><td class="nowrap"><?= fmt_dt($a['created_at']) ?></td><td><?= e($a['who'] ?? 'guest') ?></td><td><?= e($a['action']) ?></td><td><?= e(trim($a['entity'] . ' ' . $a['entity_id'])) ?></td><td><?= e($a['detail']) ?></td><td><?= e($a['ip_address']) ?></td></tr>
<?php endforeach; ?></tbody></table></div><?= pager($pg) ?>
<?php dash_footer();
