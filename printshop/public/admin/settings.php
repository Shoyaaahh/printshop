<?php
require __DIR__ . '/../../app/bootstrap.php';
$admin = require_login('admin'); $aid = (int)$admin['id'];
if (is_post()) {
    csrf_check(); $act = post('action'); $err = null;
    if ($act === 'business') {
        if (post('business_name') === '') $err = 'Business name is required.';
        elseif (post('email') !== '' && !filter_var(post('email'), FILTER_VALIDATE_EMAIL)) $err = 'Enter a valid business email.';
        else {
            foreach (['business_name', 'tagline', 'address', 'phone', 'email', 'about'] as $k) set_setting($k, post($k));
            if (!empty($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
                [$ext, , $ue] = validate_upload($_FILES['logo'], ['jpg', 'jpeg', 'png']);
                if ($ue) $err = 'Logo: ' . $ue;
                else { $n = 'logo-' . bin2hex(random_bytes(6)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext); ensure_dir(ROOT . '/public/uploads/branding'); move_uploaded_file($_FILES['logo']['tmp_name'], ROOT . '/public/uploads/branding/' . $n); set_setting('logo', $n); }
            }
        }
    } elseif ($act === 'hours') {
        $h = [];
        foreach (DAYS as $k => $_) {
            $o = post("open_$k", '08:00'); $c = post("close_$k", '18:00');
            if (!preg_match('/^\d{2}:\d{2}$/', $o) || !preg_match('/^\d{2}:\d{2}$/', $c)) { $err = 'Invalid time.'; break; }
            $h[$k] = ['open' => $o, 'close' => $c, 'closed' => post("closed_$k") === '1'];
        }
        if (!$err) set_setting('hours', json_encode($h));
    } elseif ($act === 'payments') {
        $m = [];
        foreach (PAYMENT_METHOD_KEYS as $k) $m[$k] = ['label' => post("label_$k") ?: ucfirst($k), 'enabled' => post("enabled_$k") === '1' ? 1 : 0, 'account' => post("account_$k"), 'instructions' => post("instr_$k")];
        set_setting('payment_methods', json_encode($m));
        set_setting('payment_policy', post('payment_policy') === 'at_store' ? 'at_store' : 'before_processing');
    } elseif ($act === 'pickup') {
        set_setting('pickup_policy', post('pickup_policy'));
        set_setting('accepting_orders', post('accepting_orders') === '1' ? '1' : '0');
        set_setting('closed_message', post('closed_message'));
        set_setting('email_enabled', post('email_enabled') === '1' ? '1' : '0');
    } elseif ($act === 'password') {
        $hash = fetch_val('SELECT password_hash FROM users WHERE id = ?', [$aid]);
        if (!password_verify($_POST['current'] ?? '', $hash)) $err = 'Current password is incorrect.';
        elseif ($e = validate_password($_POST['new'] ?? '')) $err = $e;
        elseif (($_POST['new'] ?? '') !== ($_POST['confirm'] ?? '')) $err = 'New passwords do not match.';
        else { q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($_POST['new'], PASSWORD_DEFAULT), $aid]); session_regenerate_id(true); }
    }
    if (!$err) audit('settings_' . $act, 'settings');
    flash($err ? 'error' : 'success', $err ?: 'Settings saved.'); redirect('admin/settings.php');
}
$hours = store_hours(); $methods = payment_methods();
dash_header('Settings', 'admin', 'settings'); ?>
<div class="grid">
<form class="card" method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="business"><h2>Business information</h2>
 <div class="form-grid"><div class="field"><label for="bn">Business name</label><input id="bn" name="business_name" value="<?= e(setting('business_name')) ?>" required></div><div class="field"><label for="tg">Tagline</label><input id="tg" name="tagline" value="<?= e(setting('tagline')) ?>"></div>
 <div class="field"><label for="ph">Contact number</label><input id="ph" name="phone" value="<?= e(setting('phone')) ?>"></div><div class="field"><label for="em">Email address</label><input id="em" type="email" name="email" value="<?= e(setting('email')) ?>"></div></div>
 <div class="field"><label for="ad">Store address</label><textarea id="ad" name="address"><?= e(setting('address')) ?></textarea></div><div class="field"><label for="ab">About us text</label><textarea id="ab" name="about" style="min-height:140px"><?= e(setting('about')) ?></textarea></div>
 <div class="field"><label for="lg">Business logo (JPG or PNG)</label><input id="lg" type="file" name="logo" accept=".jpg,.jpeg,.png"><?php if (setting('logo')) echo '<div class="hint">Current logo is set.</div>'; ?></div><button class="btn btn-primary">Save business information</button></form>
<form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="hours"><h2>Operating hours</h2>
 <?php foreach (DAYS as $k => $d): $h = $hours[$k]; ?><div style="display:flex;gap:.8rem;align-items:center;margin-bottom:.5rem;flex-wrap:wrap"><strong style="width:100px"><?= $d ?></strong><input type="time" name="open_<?= $k ?>" value="<?= e($h['open']) ?>" style="width:130px" aria-label="<?= $d ?> opens"><span>to</span><input type="time" name="close_<?= $k ?>" value="<?= e($h['close']) ?>" style="width:130px" aria-label="<?= $d ?> closes"><label class="check"><input type="checkbox" name="closed_<?= $k ?>" value="1"<?= !empty($h['closed']) ? ' checked' : '' ?>> Closed</label></div><?php endforeach; ?>
 <button class="btn btn-primary">Save hours</button></form>
<form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="payments"><h2>Payment methods</h2>
 <div class="field"><label for="pp">Payment policy</label><select id="pp" name="payment_policy"><option value="before_processing"<?= setting('payment_policy') !== 'at_store' ? ' selected' : '' ?>>Customers must pay (verified) before processing</option><option value="at_store"<?= setting('payment_policy') === 'at_store' ? ' selected' : '' ?>>Customers may pay at the store when picking up</option></select></div>
 <?php foreach ($methods as $k => $m): ?><fieldset style="border:1px solid var(--border);border-radius:6px;margin-bottom:1rem"><legend><label class="check"><input type="checkbox" name="enabled_<?= $k ?>" value="1"<?= $m['enabled'] ? ' checked' : '' ?>> <strong><?= e(ucfirst($k)) ?></strong></label></legend>
 <div class="form-grid"><div class="field"><label>Display name</label><input name="label_<?= $k ?>" value="<?= e($m['label']) ?>"></div><div class="field"><label>Account / number</label><input name="account_<?= $k ?>" value="<?= e($m['account']) ?>"></div></div><div class="field"><label>Payment instructions</label><textarea name="instr_<?= $k ?>"><?= e($m['instructions']) ?></textarea></div></fieldset><?php endforeach; ?>
 <button class="btn btn-primary">Save payment settings</button></form>
<form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="pickup"><h2>Pickup &amp; ordering</h2>
 <div class="field"><label for="pk">Pickup policy (shown to customers)</label><textarea id="pk" name="pickup_policy"><?= e(setting('pickup_policy')) ?></textarea></div>
 <label class="check"><input type="checkbox" name="accepting_orders" value="1"<?= setting('accepting_orders', '1') === '1' ? ' checked' : '' ?>> Accept new online orders</label>
 <div class="field" style="margin-top:.6rem"><label for="cm">Message when orders are paused</label><input id="cm" name="closed_message" value="<?= e(setting('closed_message')) ?>"></div>
 <label class="check"><input type="checkbox" name="email_enabled" value="1"<?= setting('email_enabled') === '1' ? ' checked' : '' ?>> Send email notifications (needs SMTP set up in .env and <code>tools/send_emails.py</code> scheduled)</label>
 <p style="margin-top:1rem"><button class="btn btn-primary">Save</button></p></form>
<form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="password"><h2>Account: change admin password</h2>
 <div class="form-grid"><div class="field"><label for="c">Current password</label><input id="c" type="password" name="current" required autocomplete="current-password"></div><div class="field"><label for="n">New password</label><input id="n" type="password" name="new" required minlength="8" autocomplete="new-password"></div><div class="field"><label for="cf">Confirm</label><input id="cf" type="password" name="confirm" required autocomplete="new-password"></div></div><button class="btn btn-primary">Change password</button></form>
</div>
<?php dash_footer();
