<?php
require __DIR__ . '/../../app/bootstrap.php';
$u = current_user();
if (!$u || $u['role'] !== 'customer') json_out(['ok' => false, 'error' => 'Please log in.'], 401);
if (!is_post()) json_out(['ok' => false, 'error' => 'POST required.'], 405);
csrf_check(true);
if (!rate_limit('price:' . $u['id'], 240, 600)) json_out(['ok' => false, 'error' => 'Too many requests.'], 429);
$in = json_decode(file_get_contents('php://input'), true) ?: [];
$s = fetch_one('SELECT * FROM services WHERE id = ? AND is_active = 1', [(int)($in['service_id'] ?? 0)]);
if (!$s) json_out(['ok' => false, 'error' => 'Service not found.'], 404);
$c = calc_price($s, $in);
json_out(['ok' => $c['ok'], 'errors' => $c['errors'], 'lines' => $c['lines'], 'total' => $c['total'], 'needs_review' => $c['needs_review'], 'unit_price' => $c['unit_price']]);
