<?php
require __DIR__ . '/../../app/bootstrap.php';
$u = current_user();
if (!$u || $u['role'] !== 'customer') json_out(['ok' => false, 'error' => 'Please log in.'], 401);
if (!is_post()) json_out(['ok' => false, 'error' => 'POST required.'], 405);
csrf_check(true);
if (setting('accepting_orders', '1') !== '1') json_out(['ok' => false, 'error' => setting('closed_message')], 403);
if (!rate_limit('order:' . $u['id'], 15, 3600)) json_out(['ok' => false, 'error' => 'You are placing orders too quickly. Please try again later.'], 429);

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$s = fetch_one('SELECT * FROM services WHERE id = ? AND is_active = 1', [(int)($in['service_id'] ?? 0)]);
if (!$s) json_out(['ok' => false, 'error' => 'That service is no longer available.'], 404);
$calc = calc_price($s, $in);                                   // price is ALWAYS recomputed on the server
if (!$calc['ok']) json_out(['ok' => false, 'error' => implode(' ', $calc['errors'])], 422);
$notes = mb_substr(trim((string)($in['notes'] ?? '')), 0, 1000);
$method = (string)($in['payment_method'] ?? '');
if ($method !== '' && !isset(payment_methods(true)[$method])) $method = '';
$tokens = is_array($in['files'] ?? null) ? array_values($in['files']) : [];

[$ref, $err] = create_order($u, $s, $calc, $tokens, $notes, $method);
if ($err) json_out(['ok' => false, 'error' => $err], 422);
json_out(['ok' => true, 'reference' => $ref, 'total' => $calc['total'], 'needs_review' => $calc['needs_review']]);
