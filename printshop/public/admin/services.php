<?php
require __DIR__ . '/../../app/bootstrap.php';
$admin = require_login('admin');

function save_service_image(?array $f): array   // returns [filename|null, error|null]
{
    if (!$f || ($f['error'] ?? 4) === UPLOAD_ERR_NO_FILE) return [null, null];
    [$ext, , $err] = validate_upload($f, ['jpg', 'jpeg', 'png']);
    if ($err) return [null, 'Image: ' . $err];
    $name = bin2hex(random_bytes(10)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
    ensure_dir(ROOT . '/public/uploads/services');
    return move_uploaded_file($f['tmp_name'], ROOT . '/public/uploads/services/' . $name) ? [$name, null] : [null, 'Could not save the image.'];
}

if (is_post()) {
    csrf_check(); $act = post('action'); $err = null; $ok = 'Saved.'; $redir = 'admin/services.php';
    if ($act === 'save_service') {
        $sid = (int)post('id'); $name = post('name'); $mode = post('pricing_mode');
        if ($name === '' || strlen($name) > 100) $err = 'Enter a service name.';
        elseif (!isset(PRICING_MODES[$mode])) $err = 'Choose a pricing mode.';
        elseif (!fetch_val('SELECT 1 FROM service_categories WHERE id = ?', [(int)post('category_id')])) $err = 'Choose a category.';
        elseif ((float)post('base_price') < 0) $err = 'Price cannot be negative.';
        else {
            [$img, $ierr] = save_service_image($_FILES['image'] ?? null);
            if ($ierr) $err = $ierr;
            else {
                $fields = array_values(array_intersect(array_keys(FIELD_LABELS), (array)($_POST['fields'] ?? [])));
                $opt = ['fields' => $fields,
                    'sizes' => array_values(array_intersect(PAPER_SIZES, (array)($_POST['sizes'] ?? []))) ?: ['A4'],
                    'papers' => array_values(array_intersect(PAPER_TYPES, (array)($_POST['papers'] ?? []))) ?: ['Regular'],
                    'colors' => array_values(array_intersect(array_keys(COLORS), (array)($_POST['colors'] ?? []))) ?: ['bw'],
                    'sides' => array_values(array_intersect(array_keys(SIDES), (array)($_POST['sides'] ?? []))) ?: ['single'],
                    'bindings' => array_values(array_intersect(array_keys(BINDINGS), (array)($_POST['bindings'] ?? []))) ?: ['none']];
                $row = ['category_id' => (int)post('category_id'), 'name' => $name, 'description' => post('description'), 'pricing_mode' => $mode,
                    'rate_group' => $mode === 'per_page' ? (post('rate_group') === 'copy' ? 'copy' : 'print') : null, 'base_price' => round((float)post('base_price'), 2),
                    'price_unit' => post('price_unit') ?: 'unit', 'processing_days' => max(0, (int)post('processing_days')), 'options' => json_encode($opt),
                    'needs_upload' => post('needs_upload') ? 1 : 0, 'needs_review' => post('needs_review') ? 1 : 0, 'is_popular' => post('is_popular') ? 1 : 0, 'is_active' => post('is_active') ? 1 : 0];
                if ($img) $row['image'] = $img;
                if ($sid) { update_row('services', $sid, $row); audit('service_update', 'service', $sid, $name); }
                else {
                    $slug = slugify($name); $n = 2; while (fetch_val('SELECT 1 FROM services WHERE slug = ?', [$slug])) $slug = slugify($name) . '-' . $n++;
                    $sid = insert('services', $row + ['slug' => $slug, 'sort_order' => 100, 'created_at' => now()]); audit('service_create', 'service', $sid, $name);
                }
            }
        }
        if ($err) $redir = 'admin/services.php?' . ($sid ? 'edit=' . $sid : 'new=1');
    } elseif ($act === 'toggle') {
        q('UPDATE services SET is_active = 1 - is_active WHERE id = ?', [(int)post('id')]); audit('service_toggle', 'service', post('id'));
    } elseif ($act === 'delete') {
        $sid = (int)post('id');
        if (fetch_val('SELECT 1 FROM order_items WHERE service_id = ?', [$sid])) { q('UPDATE services SET is_active = 0 WHERE id = ?', [$sid]); $ok = 'This service has orders, so it was deactivated instead of deleted.'; }
        else { q('DELETE FROM services WHERE id = ?', [$sid]); $ok = 'Service deleted.'; }
        audit('service_delete', 'service', $sid);
    } elseif ($act === 'save_category') {
        $cid = (int)post('id'); $name = post('name');
        if ($name === '') $err = 'Enter a category name.';
        elseif (fetch_val('SELECT 1 FROM service_categories WHERE name = ? AND id <> ?', [$name, $cid])) $err = 'That category already exists.';
        else {
            $d = ['name' => $name, 'description' => post('description'), 'sort_order' => (int)post('sort_order')];
            if ($cid) update_row('service_categories', $cid, $d); else insert('service_categories', $d + ['slug' => slugify($name) . '-' . substr(bin2hex(random_bytes(2)), 0, 3)]);
            audit('category_save', 'category', $cid, $name);
        }
    } elseif ($act === 'delete_category') {
        if (fetch_val('SELECT 1 FROM services WHERE category_id = ?', [(int)post('id')])) $err = 'Move or delete the services in this category first.';
        else { q('DELETE FROM service_categories WHERE id = ?', [(int)post('id')]); $ok = 'Category deleted.'; }
    }
    flash($err ? 'error' : 'success', $err ?: $ok); redirect($redir);
}

$cats = fetch_all('SELECT * FROM service_categories ORDER BY sort_order, id');
$editing = null;
if (get('edit')) $editing = fetch_one('SELECT * FROM services WHERE id = ?', [(int)get('edit')]);
if ($editing || get('new')) {
    $s = $editing ?: ['id' => 0, 'category_id' => $cats[0]['id'] ?? 0, 'name' => '', 'description' => '', 'pricing_mode' => 'per_unit', 'rate_group' => 'print', 'base_price' => 0, 'price_unit' => 'piece',
        'processing_days' => 1, 'options' => json_encode(['fields' => ['copies']]), 'needs_upload' => 1, 'needs_review' => 0, 'is_popular' => 0, 'is_active' => 1, 'image' => null];
    $o = service_options($s);
    $chk = fn(array $list, $v) => in_array($v, $list, true) ? ' checked' : '';
    dash_header($editing ? 'Edit service' : 'Add service', 'admin', 'services'); ?>
<p><a href="<?= e(url('admin/services.php')) ?>">← All services</a></p>
<form class="card" method="post" enctype="multipart/form-data" style="max-width:900px"><?= csrf_field() ?><input type="hidden" name="action" value="save_service"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
<div class="form-grid"><div class="field"><label for="n">Service name</label><input id="n" name="name" value="<?= e($s['name']) ?>" required maxlength="100"></div>
<div class="field"><label for="c">Category</label><select id="c" name="category_id"><?php foreach ($cats as $c) echo '<option value="' . $c['id'] . '"' . ((int)$s['category_id'] === (int)$c['id'] ? ' selected' : '') . '>' . e($c['name']) . '</option>'; ?></select></div></div>
<div class="field"><label for="d">Description</label><textarea id="d" name="description"><?= e($s['description']) ?></textarea></div>
<div class="form-grid"><div class="field"><label for="pm">Pricing mode</label><select id="pm" name="pricing_mode"><?php foreach (PRICING_MODES as $k => $l) echo '<option value="' . $k . '"' . ($s['pricing_mode'] === $k ? ' selected' : '') . '>' . e($l) . '</option>'; ?></select></div>
<div class="field"><label for="rg">Rate group (per-page mode)</label><select id="rg" name="rate_group"><option value="print"<?= $s['rate_group'] !== 'copy' ? ' selected' : '' ?>>Printing rates</option><option value="copy"<?= $s['rate_group'] === 'copy' ? ' selected' : '' ?>>Photocopy rates</option></select></div>
<div class="field"><label for="bp">Base / unit price (₱)</label><input id="bp" type="number" step="0.01" min="0" name="base_price" value="<?= e($s['base_price']) ?>"><div class="hint">Used by flat, per-piece, per-sq-ft and quote modes.</div></div>
<div class="field"><label for="pu">Price unit label</label><input id="pu" name="price_unit" value="<?= e($s['price_unit']) ?>" maxlength="20"></div>
<div class="field"><label for="pd">Processing time (days)</label><input id="pd" type="number" min="0" name="processing_days" value="<?= (int)$s['processing_days'] ?>"></div>
<div class="field"><label for="im">Service image</label><input id="im" type="file" name="image" accept=".jpg,.jpeg,.png"><?= $s['image'] ? '<div class="hint">Current: ' . e($s['image']) . '</div>' : '' ?></div></div>
<h3>Options shown to customers</h3>
<div class="field"><div class="label">Fields</div><?php foreach (FIELD_LABELS as $k => $l) echo '<label class="check"><input type="checkbox" name="fields[]" value="' . $k . '"' . $chk($o['fields'], $k) . '> ' . e($l) . '</label>'; ?></div>
<div class="grid g3"><div class="field"><div class="label">Paper sizes</div><?php foreach (PAPER_SIZES as $v) echo '<label class="check"><input type="checkbox" name="sizes[]" value="' . $v . '"' . $chk($o['sizes'], $v) . '> ' . $v . '</label>'; ?></div>
<div class="field"><div class="label">Paper types</div><?php foreach (PAPER_TYPES as $v) echo '<label class="check"><input type="checkbox" name="papers[]" value="' . $v . '"' . $chk($o['papers'], $v) . '> ' . $v . '</label>'; ?></div>
<div class="field"><div class="label">Colors</div><?php foreach (COLORS as $k => $v) echo '<label class="check"><input type="checkbox" name="colors[]" value="' . $k . '"' . $chk($o['colors'], $k) . '> ' . $v . '</label>'; ?><div class="label" style="margin-top:.8rem">Sides</div><?php foreach (SIDES as $k => $v) echo '<label class="check"><input type="checkbox" name="sides[]" value="' . $k . '"' . $chk($o['sides'], $k) . '> ' . $v . '</label>'; ?></div>
<div class="field"><div class="label">Binding</div><?php foreach (BINDINGS as $k => $v) echo '<label class="check"><input type="checkbox" name="bindings[]" value="' . $k . '"' . $chk($o['bindings'], $k) . '> ' . $v . '</label>'; ?></div></div>
<div class="field"><label class="check"><input type="checkbox" name="needs_upload" value="1"<?= $s['needs_upload'] ? ' checked' : '' ?>> Customer must upload a file</label>
<label class="check"><input type="checkbox" name="needs_review" value="1"<?= $s['needs_review'] ? ' checked' : '' ?>> Price must be confirmed by the shop after inspecting the file</label>
<label class="check"><input type="checkbox" name="is_popular" value="1"<?= $s['is_popular'] ? ' checked' : '' ?>> Show as popular on the homepage</label>
<label class="check"><input type="checkbox" name="is_active" value="1"<?= $s['is_active'] ? ' checked' : '' ?>> Available to customers</label></div>
<button class="btn btn-primary">Save service</button></form>
<?php dash_footer(); exit; }

$list = fetch_all('SELECT s.*, c.name AS cat FROM services s JOIN service_categories c ON c.id = s.category_id ORDER BY c.sort_order, s.sort_order, s.id');
$ecat = get('cat') ? fetch_one('SELECT * FROM service_categories WHERE id = ?', [(int)get('cat')]) : null;
dash_header('Services', 'admin', 'services'); ?>
<div class="card-head"><span class="muted">Deactivate a service to hide it without losing past orders.</span><a class="btn btn-primary" href="?new=1"><?= icon('plus') ?> Add service</a></div>
<div class="table-wrap"><table><thead><tr><th>Service</th><th>Category</th><th>Price</th><th>Days</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($list as $s): ?><tr><td><strong><?= e($s['name']) ?></strong><?= $s['needs_review'] ? ' <span class="badge badge-warn">review</span>' : '' ?></td><td><?= e($s['cat']) ?></td><td><?= e(service_price_label($s)) ?></td><td><?= (int)$s['processing_days'] ?></td><td><?= $s['is_active'] ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-muted">Inactive</span>' ?></td>
<td class="nowrap"><a class="btn btn-sm btn-outline" href="?edit=<?= $s['id'] ?>">Edit</a>
<?= confirm_form(url('admin/services.php'), ['action' => 'toggle', 'id' => $s['id']], $s['is_active'] ? 'Deactivate' : 'Activate', ($s['is_active'] ? 'Deactivate' : 'Activate') . ' "' . $s['name'] . '"?', 'btn btn-sm btn-ghost') ?>
<?= confirm_form(url('admin/services.php'), ['action' => 'delete', 'id' => $s['id']], 'Delete', 'Delete "' . $s['name'] . '"? If it has past orders it will be deactivated instead.', 'btn btn-sm btn-ghost') ?></td></tr><?php endforeach; ?></tbody></table></div>
<h2 style="margin-top:2rem">Categories</h2>
<div class="grid g2"><div class="table-wrap"><table><thead><tr><th>Name</th><th>Order</th><th></th></tr></thead><tbody><?php foreach ($cats as $c): ?><tr><td><?= e($c['name']) ?></td><td><?= (int)$c['sort_order'] ?></td><td class="nowrap"><a class="btn btn-sm btn-outline" href="?cat=<?= $c['id'] ?>">Edit</a> <?= confirm_form(url('admin/services.php'), ['action' => 'delete_category', 'id' => $c['id']], 'Delete', 'Delete category "' . $c['name'] . '"?', 'btn btn-sm btn-ghost') ?></td></tr><?php endforeach; ?></tbody></table></div>
<form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save_category"><input type="hidden" name="id" value="<?= (int)($ecat['id'] ?? 0) ?>"><h3><?= $ecat ? 'Edit category' : 'Add category' ?></h3>
<div class="field"><label for="cn">Name</label><input id="cn" name="name" value="<?= e($ecat['name'] ?? '') ?>" required></div><div class="field"><label for="cd">Description</label><input id="cd" name="description" value="<?= e($ecat['description'] ?? '') ?>"></div>
<div class="field"><label for="co">Sort order</label><input id="co" type="number" name="sort_order" value="<?= (int)($ecat['sort_order'] ?? 50) ?>"></div><button class="btn btn-primary">Save category</button></form></div>
<?php dash_footer();
