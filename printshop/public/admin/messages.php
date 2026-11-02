<?php
require __DIR__ . '/../../app/bootstrap.php';
require_login('admin');
if (is_post()) { csrf_check(); q('UPDATE contact_messages SET is_read = 1 WHERE id = ?', [(int)post('id')]); redirect('admin/messages.php'); }
$pg = paginate('SELECT * FROM contact_messages ORDER BY id DESC', [], 15);
dash_header('Messages', 'admin', 'messages'); ?>
<?php foreach ($pg['rows'] as $m): ?><div class="card" style="margin-bottom:.8rem;<?= $m['is_read'] ? '' : 'border-color:var(--blue)' ?>"><div class="card-head"><div><strong><?= e($m['subject']) ?></strong><div class="small muted"><?= e($m['name']) ?> · <a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a> · <?= fmt_dt($m['created_at']) ?></div></div>
<?php if (!$m['is_read']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $m['id'] ?>"><button class="btn btn-sm btn-outline">Mark as read</button></form><?php endif; ?></div><p style="margin:0"><?= nl2br(e($m['message'])) ?></p></div>
<?php endforeach; if (!$pg['rows']) echo '<p class="muted">No messages yet.</p>'; ?><?= pager($pg) ?>
<?php dash_footer();
