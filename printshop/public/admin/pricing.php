<?php
require __DIR__ . '/../../app/bootstrap.php';
require_login('admin');
if (is_post()) {
    csrf_check(); $bad = false;
    transaction(function () use (&$bad) {
        foreach ((array)($_POST['rule'] ?? []) as $id => $v) {
            if (!is_numeric($v) || (float)$v < 0 || (float)$v > 100000) { $bad = true; continue; }
            q('UPDATE pricing_rules SET amount = ?, updated_at = ? WHERE id = ?', [round((float)$v, 2), now(), (int)$id]);
        }
        foreach ((array)($_POST['svc'] ?? []) as $id => $v) {
            if (!is_numeric($v) || (float)$v < 0 || (float)$v > 1000000) { $bad = true; continue; }
            q('UPDATE services SET base_price = ? WHERE id = ?', [round((float)$v, 2), (int)$id]);
        }
    });
    audit('pricing_update', 'pricing');
    flash($bad ? 'error' : 'success', $bad ? 'Some values were invalid and were skipped. Valid changes were saved.' : 'Pricing saved. The customer price calculator uses these values immediately.');
    redirect('admin/pricing.php');
}
$groups = [];
foreach (fetch_all('SELECT * FROM pricing_rules ORDER BY id') as $r) $groups[$r['group_name']][] = $r;
$svc = fetch_all("SELECT * FROM services WHERE pricing_mode <> 'per_page' ORDER BY name");
dash_header('Pricing', 'admin', 'pricing'); ?>
<form method="post"><?= csrf_field() ?>
<p class="muted">Printing price = pages × copies × rate × size multiplier, + paper surcharge per sheet, + binding per document. Multipliers: 1 = no change.</p>
<div class="grid g2"><?php foreach ($groups as $g => $list): ?><div class="card"><h3><?= e($g) ?></h3><?php foreach ($list as $r): ?><div class="field" style="display:flex;align-items:center;gap:.8rem;margin-bottom:.6rem"><label for="r<?= $r['id'] ?>" style="flex:1;margin:0;font-weight:500"><?= e($r['label']) ?></label><input id="r<?= $r['id'] ?>" type="number" step="0.01" min="0" name="rule[<?= $r['id'] ?>]" value="<?= e($r['amount']) ?>" style="width:110px"></div><?php endforeach; ?></div><?php endforeach; ?></div>
<div class="card" style="margin-top:1rem"><h3>Service prices (flat, per piece, per sq ft, quotes)</h3><div class="table-wrap"><table><thead><tr><th>Service</th><th>Mode</th><th>Unit</th><th>Price (₱)</th></tr></thead><tbody>
<?php foreach ($svc as $s): ?><tr><td><?= e($s['name']) ?></td><td><?= e(PRICING_MODES[$s['pricing_mode']]) ?></td><td><?= e($s['price_unit']) ?></td><td><input type="number" step="0.01" min="0" name="svc[<?= $s['id'] ?>]" value="<?= e($s['base_price']) ?>" style="width:110px" aria-label="Price for <?= e($s['name']) ?>"></td></tr><?php endforeach; ?></tbody></table></div></div>
<p style="margin-top:1rem"><button class="btn btn-primary" data-confirm="Save all price changes? New orders will use the new prices immediately.">Save pricing</button></p></form>
<?php dash_footer();
