<?php
require __DIR__ . '/../../app/bootstrap.php';
$u = require_login('admin'); $uid = (int)$u['id'];
if (get('go') !== '') {                       // open a notification: mark read then go to its link
    $n = fetch_one('SELECT * FROM notifications WHERE id = ? AND user_id = ?', [(int)get('go'), $uid]);
    if ($n) { q('UPDATE notifications SET is_read = 1 WHERE id = ?', [$n['id']]); redirect($n['link'] ?: 'admin/notifications.php'); }
}
if (is_post()) {
    csrf_check();
    if (post('action') === 'read_all') q('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [$uid]);
    elseif (post('action') === 'read') q('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?', [(int)post('id'), $uid]);
    redirect('admin/notifications.php');
}
$pg = paginate('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC', [$uid], 20);
dash_header('Notifications', 'admin', 'notifications'); ?>
<div class="card" style="padding:0"><div class="card-head" style="padding:1rem 1.2rem;margin:0"><h2>Notification history</h2>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="read_all"><button class="btn btn-sm btn-outline">Mark all as read</button></form></div>
<?php foreach ($pg['rows'] as $n): ?><div class="notif <?= $n['is_read'] ? '' : 'unread' ?>"><span class="ico"><?= icon('bell') ?></span><div style="flex:1">
  <strong><?= e($n['title']) ?></strong><div><?= e($n['body']) ?></div><div class="small muted"><?= fmt_dt($n['created_at']) ?><?= $n['link'] ? ' · <a href="?go=' . $n['id'] . '">Open</a>' : '' ?></div></div>
  <?php if (!$n['is_read']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="read"><input type="hidden" name="id" value="<?= $n['id'] ?>"><button class="btn btn-sm btn-ghost">Mark as read</button></form><?php endif; ?></div>
<?php endforeach; if (!$pg['rows']) echo '<p class="empty muted" style="padding:2rem;text-align:center">No notifications yet.</p>'; ?></div>
<?= pager($pg) ?>
<?php dash_footer();
