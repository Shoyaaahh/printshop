<?php
/** Authorised file delivery. Uploaded files live outside the web root; this is the only way to read them. */
require __DIR__ . '/../app/bootstrap.php';
$u = current_user();
if (!$u) { http_response_code(401); exit('Please log in.'); }
$id = (int)get('id');
$kind = get('type', 'file');
if ($kind === 'receipt') {
    $row = fetch_one('SELECT r.*, o.customer_id FROM payment_receipts r JOIN payments p ON p.id = r.payment_id JOIN orders o ON o.id = p.order_id WHERE r.id = ?', [$id]);
    $path = $row ? file_disk_path($row, 'receipts') : '';
} else {
    $row = fetch_one('SELECT f.*, o.customer_id FROM uploaded_files f JOIN orders o ON o.id = f.order_id WHERE f.id = ?', [$id]);
    $path = $row ? file_disk_path($row, 'files') : '';
}
// Customers may only read their own files; admins may read all.
if (!$row || !is_file($path) || ($u['role'] !== 'admin' && (int)$row['customer_id'] !== (int)$u['id'])) { http_response_code(404); exit('File not found.'); }
if ($u['role'] === 'admin') audit('file_download', $kind, $id, $row['original_name']);

$inline = get('inline') === '1' && in_array($row['mime_type'], ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true);
header('Content-Type: ' . ($inline ? $row['mime_type'] : 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . rawurlencode($row['original_name']) . '"');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
header('Cache-Control: private, no-store');
readfile($path);
