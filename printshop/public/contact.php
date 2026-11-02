<?php
require __DIR__ . '/../app/bootstrap.php';
$errors = []; $f = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];
if (is_post()) {
    csrf_check();
    foreach ($f as $k => $_) $f[$k] = post($k);
    if (post('website') !== '') { redirect('contact.php'); }                // honeypot field for bots
    if ($f['name'] === '' || strlen($f['name']) > 100) $errors[] = 'Enter your name.';
    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if ($f['subject'] === '' || strlen($f['subject']) > 150) $errors[] = 'Enter a subject (up to 150 characters).';
    if (strlen($f['message']) < 5 || strlen($f['message']) > 3000) $errors[] = 'Write a message between 5 and 3000 characters.';
    if (!$errors && !rate_limit('contact:' . client_ip(), 5, 3600)) $errors[] = 'Too many messages. Please try again later.';
    if (!$errors) {
        insert('contact_messages', $f + ['created_at' => now()]);
        notify_admins('New contact message', $f['name'] . ': ' . $f['subject'], 'admin/messages.php');
        flash('success', 'Message sent. We will reply by email.');
        redirect('contact.php');
    }
}
public_header('Contact Us', 'contact'); ?>
<div class="container">
  <div class="page-title"><h1>Contact us</h1><p>Questions about a file, a price or your order? Send a message or visit the store.</p></div>
  <div class="split">
    <form class="card" method="post"><?= csrf_field() ?>
      <?php foreach ($errors as $er) echo '<div class="alert alert-error">' . e($er) . '</div>'; ?>
      <div class="form-grid"><div class="field"><label for="name">Your name</label><input id="name" name="name" value="<?= e($f['name']) ?>" required maxlength="100"></div>
      <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="<?= e($f['email']) ?>" required></div></div>
      <div class="field"><label for="subject">Subject</label><input id="subject" name="subject" value="<?= e($f['subject']) ?>" required maxlength="150"></div>
      <div class="field"><label for="message">Message</label><textarea id="message" name="message" required maxlength="3000"><?= e($f['message']) ?></textarea></div>
      <div style="position:absolute;left:-9999px" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
      <button class="btn btn-primary"><?= icon('send') ?> Send message</button>
    </form>
    <div class="card"><h3>Store</h3><p><?= nl2br(e(setting('address'))) ?></p><p><?= icon('phone') ?> <?= e(setting('phone')) ?><br><?= icon('mail') ?> <?= e(setting('email')) ?></p><h3>Hours</h3><?= hours_table() ?></div>
  </div>
</div>
<?php public_footer();
