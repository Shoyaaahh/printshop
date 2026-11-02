<?php
require __DIR__ . '/../app/bootstrap.php';
if (current_user()) redirect(current_user()['role'] === 'admin' ? 'admin/index.php' : 'customer/dashboard.php');
$error = null; $email = '';
if (is_post()) {
    csrf_check();
    $email = strtolower(post('email'));
    [$uid, $error] = attempt_login($email, $_POST['password'] ?? '', 'customer');
    if ($uid) { login_user($uid); redirect(safe_next(get('next'), url('customer/dashboard.php'))); }
}
public_header('Login'); ?>
<div class="auth-wrap"><form class="card" method="post"><?= csrf_field() ?>
  <h1>Log in</h1><p class="muted">Access your orders, payments and notifications.</p>
  <?php if ($error) echo '<div class="alert alert-error">' . e($error) . '</div>'; ?>
  <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="username"></div>
  <div class="field"><label for="password">Password</label><input id="password" type="password" name="password" required autocomplete="current-password"></div>
  <button class="btn btn-primary" style="width:100%;justify-content:center">Log in</button>
  <p class="small muted" style="margin:1rem 0 0">New here? <a href="<?= e(url('register.php')) ?>">Create an account</a></p>
</form></div>
<?php public_footer();
