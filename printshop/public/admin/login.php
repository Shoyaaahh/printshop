<?php
require __DIR__ . '/../../app/bootstrap.php';
if (current_user()) redirect(current_user()['role'] === 'admin' ? 'admin/index.php' : 'customer/dashboard.php');
$error = null; $email = '';
if (is_post()) {
    csrf_check();
    $email = strtolower(post('email'));
    [$uid, $error] = attempt_login($email, $_POST['password'] ?? '', 'admin');
    if ($uid) { login_user($uid); audit('admin_login', 'user', $uid); redirect(safe_next(get('next'), url('admin/index.php'))); }
}
page_head('Admin Login', 'public'); ?>
<main><div class="auth-wrap"><form class="card" method="post"><?= csrf_field() ?>
  <div style="margin-bottom:1rem"><?= brand_logo() ?></div>
  <h1>Administrator login</h1><p class="muted">Staff only.</p>
  <?php if ($error) echo '<div class="alert alert-error">' . e($error) . '</div>'; ?>
  <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="username"></div>
  <div class="field"><label for="password">Password</label><input id="password" type="password" name="password" required autocomplete="current-password"></div>
  <button class="btn btn-primary" style="width:100%;justify-content:center">Log in</button>
</form></div></main>
<?php page_scripts();
