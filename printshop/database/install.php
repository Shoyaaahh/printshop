<?php
declare(strict_types=1);
/**
 * Creates the database, loads the schema and seeds starter data.
 *
 *   php database/install.php
 *   php database/install.php --admin-email=you@shop.com --admin-password="Str0ngPass!"
 *   php database/install.php --reset      (DELETES the existing database first)
 */
if (PHP_SAPI !== 'cli') exit('Run this script from the command line.');
require __DIR__ . '/../app/bootstrap.php';

$opts = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z\-]+)(?:=(.*))?$/', $a, $m)) $opts[$m[1]] = $m[2] ?? '1';
}
$path = (string)env('DB_PATH', STORAGE . '/printshop.sqlite');
@mkdir(dirname($path), 0775, true);
if (is_file($path)) {
    if (!isset($opts['reset'])) { fwrite(STDERR, "Database already exists at $path\nUse --reset to wipe it and start over.\n"); exit(1); }
    foreach (['', '-wal', '-shm'] as $s) @unlink($path . $s);
}
foreach (['files', 'receipts', 'tmp'] as $d) ensure_dir(UPLOAD_DIR . "/$d");
ensure_dir(STORAGE . '/backups');
ensure_dir(ROOT . '/public/uploads/services');
ensure_dir(ROOT . '/public/uploads/branding');

$pdo = db();
$pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));
$t = now();

// ---- admin account ----
$email = $opts['admin-email'] ?? 'admin@printshop.local';
$pass = $opts['admin-password'] ?? 'Admin@12345';
if ($err = validate_password($pass)) { fwrite(STDERR, "Admin password: $err\n"); exit(1); }
$uid = insert('users', ['email' => strtolower($email), 'password_hash' => password_hash($pass, PASSWORD_DEFAULT), 'role' => 'admin', 'created_at' => $t]);
insert('admin_users', ['user_id' => $uid, 'full_name' => 'Shop Administrator', 'admin_role' => 'owner']);

// ---- pricing rules (all editable in Admin → Pricing) ----
$rules = [
    ['print.bw', 'Black & white printing (per page)', 'Printing rates', 2.00],
    ['print.color', 'Colored printing (per page)', 'Printing rates', 8.00],
    ['copy.bw', 'Black & white photocopy (per page)', 'Photocopy rates', 1.50],
    ['copy.color', 'Colored photocopy (per page)', 'Photocopy rates', 6.00],
    ['size.A4', 'A4 size multiplier', 'Paper size (multiplier)', 1.0],
    ['size.Letter', 'Letter size multiplier', 'Paper size (multiplier)', 1.0],
    ['size.Legal', 'Legal size multiplier', 'Paper size (multiplier)', 1.15],
    ['size.A3', 'A3 size multiplier', 'Paper size (multiplier)', 2.0],
    ['size.Custom', 'Custom size multiplier', 'Paper size (multiplier)', 1.5],
    ['paper.Regular', 'Regular paper surcharge (per sheet)', 'Paper type (surcharge per sheet)', 0],
    ['paper.Glossy', 'Glossy paper surcharge (per sheet)', 'Paper type (surcharge per sheet)', 3],
    ['paper.Matte', 'Matte paper surcharge (per sheet)', 'Paper type (surcharge per sheet)', 2.5],
    ['paper.Photo Paper', 'Photo paper surcharge (per sheet)', 'Paper type (surcharge per sheet)', 8],
    ['paper.Cardstock', 'Cardstock surcharge (per sheet)', 'Paper type (surcharge per sheet)', 4],
    ['side.double', 'Double-sided multiplier (0.9 = 10% off)', 'Printing side', 0.9],
    ['binding.spiral', 'Spiral binding (per document)', 'Binding charges', 45],
    ['binding.soft', 'Soft binding (per document)', 'Binding charges', 60],
    ['binding.hard', 'Hard binding (per document)', 'Binding charges', 150],
];
foreach ($rules as [$k, $l, $g, $a]) insert('pricing_rules', ['rule_key' => $k, 'label' => $l, 'group_name' => $g, 'amount' => $a, 'updated_at' => $t]);

// ---- categories & services ----
$cats = [
    'Document Printing' => 'Everyday black & white and colored document printing.',
    'Photo Printing' => 'ID pictures and photo prints on photo paper.',
    'Document Services' => 'Photocopying, scanning, formatting and conversion.',
    'Binding Services' => 'Keep your documents neat and professional.',
    'Marketing Materials' => 'Flyers, posters, brochures, cards and tarpaulins.',
    'Custom Printing' => 'Unusual sizes, papers and special requests.',
];
$catId = [];
$i = 0;
foreach ($cats as $n => $d) $catId[$n] = insert('service_categories', ['name' => $n, 'slug' => slugify($n), 'description' => $d, 'sort_order' => ++$i]);

$docOpts = fn(array $colors, array $sides = ['single', 'double']) => ['fields' => ['size', 'paper', 'color', 'sides', 'orientation', 'pages', 'copies', 'binding'],
    'sizes' => ['A4', 'Letter', 'Legal', 'A3'], 'papers' => ['Regular', 'Matte', 'Cardstock'], 'colors' => $colors, 'sides' => $sides, 'bindings' => ['none', 'spiral', 'soft', 'hard']];
$photo = fn(array $papers = ['Photo Paper', 'Glossy', 'Matte']) => ['fields' => ['paper', 'copies'], 'papers' => $papers];
$mk = fn(array $sizes, array $papers) => ['fields' => ['size', 'paper', 'orientation', 'copies'], 'sizes' => $sizes, 'papers' => $papers];

// [category, name, description, mode, rate_group, base_price, unit, days, options, upload, review, popular]
$services = [
    ['Document Printing', 'Black & White Printing', 'Standard text and graphics documents printed in black and white. Choose single or double-sided and add binding.', 'per_page', 'print', 0, 'page', 1, $docOpts(['bw']), 1, 0, 1],
    ['Document Printing', 'Colored Printing', 'Full-color printing for reports, presentations and school projects.', 'per_page', 'print', 0, 'page', 1, $docOpts(['color']), 1, 0, 1],
    ['Document Printing', 'Single-Sided Printing', 'Print on one side of the paper, in black & white or color.', 'per_page', 'print', 0, 'page', 1, $docOpts(['bw', 'color'], ['single']), 1, 0, 0],
    ['Document Printing', 'Double-Sided Printing', 'Print on both sides to save paper and money.', 'per_page', 'print', 0, 'page', 1, $docOpts(['bw', 'color'], ['double']), 1, 0, 0],
    ['Photo Printing', 'Passport-Size Pictures', 'Passport-size photos (set of pictures). Upload a clear, front-facing photo with a plain background.', 'per_unit', null, 60, 'set', 1, $photo(['Photo Paper', 'Glossy']), 1, 1, 1],
    ['Photo Printing', '2x2 Pictures', 'Standard 2x2 inch ID pictures (set of pictures).', 'per_unit', null, 55, 'set', 1, $photo(['Photo Paper', 'Glossy']), 1, 1, 0],
    ['Photo Printing', '4R Photos', '4 x 6 inch photo prints on glossy or matte photo paper.', 'per_unit', null, 15, 'photo', 1, $photo(), 1, 0, 1],
    ['Photo Printing', 'Custom Photo Sizes', 'Need another size? Describe it in the instructions and we will confirm the price.', 'per_unit', null, 35, 'photo', 2, $photo(), 1, 1, 0],
    ['Document Services', 'Photocopying', 'Bring your original to the store. Place the order online and we will have copies ready.', 'per_page', 'copy', 0, 'page', 1, $docOpts(['bw', 'color']), 0, 0, 1],
    ['Document Services', 'Document Scanning', 'Scan paper documents to PDF. Bring the originals to the store.', 'flat_page', null, 5, 'page', 1, ['fields' => ['pages', 'copies']], 0, 0, 0],
    ['Document Services', 'Document Formatting', 'Layout cleanup, margins, fonts and page numbering for theses, resumes and reports.', 'flat_page', null, 10, 'page', 2, ['fields' => ['pages']], 1, 1, 0],
    ['Document Services', 'Document Conversion', 'Convert between Word, PDF, image and slide formats.', 'flat_page', null, 8, 'page', 1, ['fields' => ['pages']], 1, 1, 0],
    ['Binding Services', 'Spiral Binding', 'Plastic coil binding that lays flat. Price per document.', 'per_unit', null, 45, 'document', 1, ['fields' => ['copies']], 0, 0, 0],
    ['Binding Services', 'Soft Binding', 'Soft cover binding with a clean spine, ideal for reports.', 'per_unit', null, 60, 'document', 1, ['fields' => ['copies']], 0, 0, 0],
    ['Binding Services', 'Hard Binding', 'Hardcover binding for theses and keepsakes.', 'per_unit', null, 150, 'document', 3, ['fields' => ['copies']], 0, 0, 0],
    ['Marketing Materials', 'Flyers', 'Full-color flyers on glossy or matte paper.', 'per_unit', null, 8, 'piece', 2, $mk(['A4', 'A3'], ['Glossy', 'Matte']), 1, 0, 1],
    ['Marketing Materials', 'Posters', 'Large format posters for events and promotions.', 'per_unit', null, 60, 'piece', 2, $mk(['A4', 'A3', 'Custom'], ['Glossy', 'Matte']), 1, 0, 0],
    ['Marketing Materials', 'Brochures', 'Folded brochures printed in color. Upload your finished layout.', 'per_unit', null, 15, 'piece', 3, $mk(['A4', 'Letter'], ['Glossy', 'Matte']), 1, 1, 0],
    ['Marketing Materials', 'Business Cards', 'Business cards on cardstock. Price per card.', 'per_unit', null, 3, 'card', 3, ['fields' => ['paper', 'copies'], 'papers' => ['Cardstock', 'Matte']], 1, 1, 1],
    ['Marketing Materials', 'Tarpaulin Printing', 'Weather-resistant tarpaulin banners. Enter the size in feet.', 'per_sqft', null, 18, 'sq ft', 3, ['fields' => ['dimensions', 'copies']], 1, 1, 0],
    ['Custom Printing', 'Custom Sizes', 'Any paper size not listed. We confirm the final price after reviewing your file.', 'quote', null, 50, 'piece', 3, ['fields' => ['size', 'copies', 'paper'], 'sizes' => ['Custom'], 'papers' => ['Regular', 'Glossy', 'Matte', 'Cardstock']], 1, 1, 0],
    ['Custom Printing', 'Custom Paper Types', 'Special stock, textured paper or stickers. Tell us what you need.', 'quote', null, 50, 'piece', 3, ['fields' => ['copies', 'paper'], 'papers' => ['Regular', 'Glossy', 'Matte', 'Photo Paper', 'Cardstock']], 1, 1, 0],
    ['Custom Printing', 'Special Printing Requests', 'Something unusual? Describe it and we will send you a quote.', 'quote', null, 100, 'job', 3, ['fields' => ['copies']], 0, 1, 0],
];
$i = 0;
foreach ($services as [$cat, $name, $desc, $mode, $rg, $price, $unit, $days, $opt, $up, $rev, $pop]) {
    insert('services', ['category_id' => $catId[$cat], 'name' => $name, 'slug' => slugify($name), 'description' => $desc, 'pricing_mode' => $mode, 'rate_group' => $rg,
        'base_price' => $price, 'price_unit' => $unit, 'processing_days' => $days, 'options' => json_encode($opt), 'needs_upload' => $up, 'needs_review' => $rev,
        'is_popular' => $pop, 'is_active' => 1, 'sort_order' => ++$i, 'created_at' => $t]);
}

// ---- business settings ----
$hours = [];
foreach (DAYS as $k => $_) $hours[$k] = ['open' => '08:00', 'close' => $k === 'sat' ? '17:00' : '18:00', 'closed' => $k === 'sun'];
$methods = [
    'gcash' => ['label' => 'GCash', 'enabled' => 1, 'account' => '0917 000 0000 (Account name)', 'instructions' => "1. Open GCash and choose Send Money.\n2. Send the exact order total to the number above.\n3. Enter the GCash reference number here and upload a screenshot of the receipt."],
    'maya' => ['label' => 'Maya', 'enabled' => 1, 'account' => '0918 000 0000 (Account name)', 'instructions' => "1. Open Maya and choose Send Money.\n2. Send the exact order total to the number above.\n3. Enter the reference number here and upload the receipt screenshot."],
    'bank' => ['label' => 'Bank Transfer', 'enabled' => 1, 'account' => 'Bank name · Account no. 0000-0000-00 · Account name', 'instructions' => "Transfer the exact order total, then enter the transaction reference number and upload the deposit slip or screenshot."],
    'cash' => ['label' => 'Cash at Store', 'enabled' => 1, 'account' => '', 'instructions' => 'Pay in cash when you pick up your order at the store.'],
];
$defaults = [
    'business_name' => 'Your Print Shop', 'tagline' => 'Order online. Pick up in store.',
    'address' => "123 Main Street\nYour Barangay, Your City", 'phone' => '0900 000 0000', 'email' => 'hello@yourprintshop.example',
    'logo' => '', 'hours' => json_encode($hours), 'payment_methods' => json_encode($methods),
    'payment_policy' => 'before_processing', 'accepting_orders' => '1',
    'closed_message' => 'We are not accepting online orders right now. Please visit the store.',
    'pickup_policy' => 'Present your order reference number at the counter. Orders not collected within 14 days of being marked ready may be discarded. Pickup is during store hours only.',
    'email_enabled' => '0',
    'about' => "We are a neighbourhood printing shop that has served students, offices and small businesses for years. This website lets you send your files, see your price and pick up your prints without waiting in line.\n\nEvery order is checked by a real person before printing, so you get what you expect.",
];
foreach ($defaults as $k => $v) set_setting($k, $v);

echo "Database created: $path\n";
echo "Admin login:  $email\n";
echo "Password:     $pass" . (isset($opts['admin-password']) ? '' : "   <-- default! change it after first login (Admin → Settings → Account)") . "\n";
echo "Next: php -S localhost:8000 -t public   then open http://localhost:8000\n";
