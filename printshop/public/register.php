<?php
require __DIR__ . '/../app/bootstrap.php';
if (current_user()) redirect('customer/dashboard.php');
$errors = []; $f = ['full_name' => '', 'email' => '', 'phone' => ''];
if (is_post()) {
    csrf_check();
    foreach ($f as $k => $_) $f[$k] = post($k);
    $f['email'] = strtolower($f['email']);
    $pw = $_POST['password'] ?? ''; $pw2 = $_POST['password_confirm'] ?? '';
    if (strlen($f['full_name']) < 2 || strlen($f['full_name']) > 100) $errors[] = 'Enter your full name.';
    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL) || strlen($f['email']) > 150) $errors[] = 'Enter a valid email address.';
    if (!valid_phone($f['phone'])) $errors[] = 'Enter a valid contact number.';
    if ($e = validate_password($pw)) $errors[] = $e;
    if ($pw !== $pw2) $errors[] = 'Passwords do not match.';
    if (!$errors && !rate_limit('register:' . client_ip(), 10, 3600)) $errors[] = 'Too many sign-ups from this network. Try again later.';
    if (!$errors && fetch_val('SELECT 1 FROM users WHERE email = ?', [$f['email']])) $errors[] = 'An account with this email already exists. Try logging in.';
    if (!$errors) {
        $uid = transaction(function () use ($f, $pw) {
            $id = insert('users', ['email' => $f['email'], 'password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'role' => 'customer', 'created_at' => now()]);
            insert('customer_profiles', ['user_id' => $id, 'full_name' => $f['full_name'], 'phone' => $f['phone'], 'updated_at' => now()]);
            return $id;
        });
        login_user($uid);
        audit('register', 'user', $uid);
        notify($uid, 'Welcome!', 'Your account is ready. You can now place a printing order.', 'customer/order_new.php');
        flash('success', 'Account created. Welcome!');
        redirect(safe_next(get('next'), url('customer/dashboard.php')));
    }
}
public_header('Register'); ?>
<div class="auth-wrap"><form class="card" method="post"><?= csrf_field() ?>
  <h1>Create an account</h1><p class="muted">Register to place orders and track them.</p>
  <?php foreach ($errors as $er) echo '<div class="alert alert-error">' . e($er) . '</div>'; ?>
  <div class="field"><label for="full_name">Full name</label><input id="full_name" name="full_name" value="<?= e($f['full_name']) ?>" required maxlength="100" autocomplete="name"></div>
  <div class="field"><label for="email">Email address</label><input id="email" type="email" name="email" value="<?= e($f['email']) ?>" required autocomplete="email"></div>
  <div class="field"><label for="phone">Contact number</label><input id="phone" type="tel" name="phone" value="<?= e($f['phone']) ?>" required autocomplete="tel"></div>
  <div class="field"><label for="password">Password</label><input id="password" type="password" name="password" required minlength="8" autocomplete="new-password"><div class="hint">At least 8 characters, with letters and numbers.</div></div>
  <div class="field"><label for="pc">Confirm password</label><input id="pc" type="password" name="password_confirm" required autocomplete="new-password"></div>
  <button class="btn btn-primary" style="width:100%;justify-content:center">Create account</button>
  <p class="small muted" style="margin:1rem 0 0">Already registered? <a href="<?= e(url('login.php')) ?>">Log in</a></p>
</form></div>
<?php public_footer();
