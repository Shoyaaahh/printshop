<?php
require __DIR__ . '/../../app/bootstrap.php';
$u = require_login('customer'); $uid = (int)$u['id'];
if (is_post()) {
    csrf_check(); $act = post('action'); $errs = [];
    if ($act === 'profile') {
        $name = post('full_name'); $phone = post('phone'); $email = strtolower(post('email'));
        if (strlen($name) < 2 || strlen($name) > 100) $errs[] = 'Enter your full name.';
        if (!valid_phone($phone)) $errs[] = 'Enter a valid contact number.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errs[] = 'Enter a valid email.';
        elseif (fetch_val('SELECT 1 FROM users WHERE email = ? AND id <> ?', [$email, $uid])) $errs[] = 'That email is used by another account.';
        if (!$errs) {
            transaction(function () use ($uid, $name, $phone, $email) {
                q('UPDATE customer_profiles SET full_name = ?, phone = ?, updated_at = ? WHERE user_id = ?', [$name, $phone, now(), $uid]);
                q('UPDATE users SET email = ? WHERE id = ?', [$email, $uid]);
            });
            audit('profile_update', 'user', $uid); flash('success', 'Profile updated.'); redirect('customer/profile.php');
        }
    } elseif ($act === 'password') {
        $cur = $_POST['current'] ?? ''; $new = $_POST['new'] ?? ''; $conf = $_POST['confirm'] ?? '';
        $hash = fetch_val('SELECT password_hash FROM users WHERE id = ?', [$uid]);
        if (!password_verify($cur, $hash)) $errs[] = 'Current password is incorrect.';
        if ($e = validate_password($new)) $errs[] = $e;
        if ($new !== $conf) $errs[] = 'New passwords do not match.';
        if (!$errs) {
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $uid]);
            session_regenerate_id(true); audit('password_change', 'user', $uid); flash('success', 'Password changed.'); redirect('customer/profile.php');
        }
    }
    foreach ($errs as $er) flash('error', $er);
    redirect('customer/profile.php');
}
dash_header('My Profile', 'customer', 'profile'); ?>
<div class="grid g2">
<form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="profile"><h2>Contact information</h2>
  <div class="field"><label for="fn">Full name</label><input id="fn" name="full_name" value="<?= e($u['full_name']) ?>" required></div>
  <div class="field"><label for="em">Email address</label><input id="em" type="email" name="email" value="<?= e($u['email']) ?>" required></div>
  <div class="field"><label for="ph">Contact number</label><input id="ph" type="tel" name="phone" value="<?= e($u['phone']) ?>" required></div>
  <button class="btn btn-primary">Save changes</button></form>
<form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="password"><h2>Change password</h2>
  <div class="field"><label for="c">Current password</label><input id="c" type="password" name="current" required autocomplete="current-password"></div>
  <div class="field"><label for="n">New password</label><input id="n" type="password" name="new" required minlength="8" autocomplete="new-password"><div class="hint">At least 8 characters with letters and numbers.</div></div>
  <div class="field"><label for="cf">Confirm new password</label><input id="cf" type="password" name="confirm" required autocomplete="new-password"></div>
  <button class="btn btn-primary">Change password</button></form>
</div>
<p class="muted small" style="margin-top:1rem">Account created <?= fmt_dt($u['created_at'], false) ?>. You can only see your own orders and information.</p>
<?php dash_footer();
