<?php
require __DIR__ . '/../app/bootstrap.php';
public_header('About Us', 'about'); ?>
<div class="container" style="max-width:860px">
  <div class="page-title"><h1>About <?= e(setting('business_name')) ?></h1></div>
  <div class="card"><?php foreach (preg_split('/\n\s*\n/', setting('about')) as $p) echo '<p>' . nl2br(e($p)) . '</p>'; ?></div>
  <div class="grid g2" style="margin-top:1rem">
    <div class="card"><h3>Visit the store</h3><p><?= nl2br(e(setting('address'))) ?></p><?= hours_table() ?></div>
    <div class="card"><h3>Pickup policy</h3><p class="muted"><?= nl2br(e(setting('pickup_policy'))) ?></p></div>
  </div>
</div>
<?php public_footer();
