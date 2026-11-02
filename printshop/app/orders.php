<?php
declare(strict_types=1);
/** Order workflow, payments, pickups, notifications and secure file handling. */

const STATUS_FLOW = [
    'order_received' => ['awaiting_review', 'cancelled'],
    'awaiting_review' => ['awaiting_customer_confirmation', 'pending_payment', 'cancelled'],
    'awaiting_customer_confirmation' => ['pending_payment', 'awaiting_review', 'cancelled'],
    'pending_payment' => ['payment_verified', 'processing', 'awaiting_customer_confirmation', 'cancelled'],
    'payment_verified' => ['processing', 'cancelled'],
    'processing' => ['printing', 'cancelled'],
    'printing' => ['quality_checking', 'processing'],
    'quality_checking' => ['ready_for_pickup', 'printing'],
    'ready_for_pickup' => ['quality_checking'],   // 'completed' ONLY through confirm_pickup()
    'completed' => [],
    'cancelled' => [],
];
const CUSTOMER_CANCELLABLE = ['order_received', 'awaiting_review', 'awaiting_customer_confirmation', 'pending_payment'];

function order_total(array $o): float { return (float)($o['final_total'] ?? $o['estimated_total']); }

function gen_reference(): string
{
    $alpha = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    do {
        $r = '';
        for ($i = 0; $i < 6; $i++) $r .= $alpha[random_int(0, strlen($alpha) - 1)];
        $ref = 'PS-' . date('ymd') . '-' . $r;
    } while (fetch_val('SELECT 1 FROM orders WHERE reference = ?', [$ref]));
    return $ref;
}

// ---------------------------------------------------------------- notifications
function notify(int $userId, string $title, string $body, ?string $link = null): void
{
    q('INSERT INTO notifications (user_id, title, body, link, is_read, created_at) VALUES (?,?,?,?,0,?)', [$userId, $title, $body, $link, now()]);
    if (setting('email_enabled', '0') === '1') {
        $email = fetch_val('SELECT email FROM users WHERE id = ?', [$userId]);
        if ($email) {
            q('INSERT INTO email_queue (to_email, subject, body, created_at) VALUES (?,?,?,?)',
                [$email, '[' . setting('business_name', 'Print Shop') . '] ' . $title, $body, now()]);
        }
    }
}
function notify_admins(string $title, string $body, ?string $link = null): void
{
    foreach (fetch_all("SELECT id FROM users WHERE role = 'admin' AND status = 'active'") as $a) {
        q('INSERT INTO notifications (user_id, title, body, link, is_read, created_at) VALUES (?,?,?,?,0,?)', [$a['id'], $title, $body, $link, now()]);
    }
}
function unread_count(int $userId): int { return (int)fetch_val('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$userId]); }

function status_notice(string $status, string $ref): ?array
{
    $policy = setting('payment_policy', 'before_processing');
    return match ($status) {
        'pending_payment' => $policy === 'before_processing'
            ? ['Payment required', "Order $ref has been approved. Please submit your payment so we can start printing."]
            : ['Order approved', "Order $ref has been approved. You can pay at the store when you pick it up."],
        'payment_verified' => ['Payment verified', "Your payment for order $ref has been verified."],
        'processing' => ['Order is being processed', "We are preparing order $ref."],
        'printing' => ['Printing has started', "Order $ref is now being printed."],
        'quality_checking' => ['Quality checking', "Order $ref is being quality-checked."],
        'ready_for_pickup' => ['Ready for pickup', "Quality check completed. Order $ref is ready for pickup at the store. Bring your reference number."],
        'completed' => ['Order completed', "Order $ref was picked up. Thank you for choosing us!"],
        'cancelled' => ['Order cancelled', "Order $ref has been cancelled."],
        default => null,
    };
}

function add_history(int $orderId, string $status, ?string $note, ?int $by): void
{
    q('INSERT INTO order_status_history (order_id, status, note, changed_by, created_at) VALUES (?,?,?,?,?)', [$orderId, $status, $note, $by, now()]);
}

/** Validated status change. Returns an error string, or null on success. */
function change_status(int $orderId, string $new, string $note, ?int $by): ?string
{
    $o = fetch_one('SELECT * FROM orders WHERE id = ?', [$orderId]);
    if (!$o) return 'Order not found.';
    if ($o['status'] === $new) return null;
    if ($new === 'completed') return 'An order can only be completed by confirming customer pickup.';
    if (!in_array($new, STATUS_FLOW[$o['status']] ?? [], true)) {
        return 'Cannot move an order from "' . ORDER_STATUSES[$o['status']] . '" to "' . (ORDER_STATUSES[$new] ?? $new) . '".';
    }
    if ($new === 'processing' && setting('payment_policy', 'before_processing') === 'before_processing' && $o['payment_status'] !== 'paid') {
        return 'Payment must be verified before processing (shop policy). Verify the payment or change the policy in Settings.';
    }
    if ($new === 'cancelled' && $note === '') return 'Please provide a reason for cancelling.';
    transaction(function () use ($o, $orderId, $new, $note, $by) {
        q('UPDATE orders SET status = ?, updated_at = ?, cancelled_reason = CASE WHEN ? = \'cancelled\' THEN ? ELSE cancelled_reason END WHERE id = ?',
            [$new, now(), $new, $note, $orderId]);
        add_history($orderId, $new, $note ?: null, $by);
        $n = status_notice($new, $o['reference']);
        if ($n) notify((int)$o['customer_id'], $n[0], $n[1] . ($new === 'cancelled' && $note ? " Reason: $note" : ''), 'customer/order_view.php?ref=' . $o['reference']);
    });
    audit('order_status', 'order', $orderId, $o['status'] . ' → ' . $new);
    return null;
}

/** Change the price after review. The customer must confirm before work continues. */
function adjust_price(int $orderId, float $newTotal, string $note, int $by): ?string
{
    $o = fetch_one('SELECT * FROM orders WHERE id = ?', [$orderId]);
    if (!$o) return 'Order not found.';
    if (!in_array($o['status'], ['awaiting_review', 'awaiting_customer_confirmation', 'pending_payment'], true)) return 'Prices can only be adjusted before payment is verified and work begins.';
    if ($o['payment_status'] === 'paid') return 'This order is already paid.';
    if ($newTotal < 0) return 'Price cannot be negative.';
    $old = order_total($o);
    transaction(function () use ($o, $orderId, $newTotal, $note, $by, $old) {
        q('UPDATE orders SET final_total = ?, price_adjusted = 1, customer_confirmed = 0, updated_at = ? WHERE id = ?', [round($newTotal, 2), now(), $orderId]);
        $msg = 'Price adjusted from ' . peso($old) . ' to ' . peso($newTotal) . ($note ? ": $note" : '');
        add_history($orderId, $o['status'], $msg, $by);
        notify((int)$o['customer_id'], 'Estimated price changed',
            "After reviewing your files, the total for order {$o['reference']} is now " . peso($newTotal) . ' (was ' . peso($old) . '). Please review and confirm.' . ($note ? " Note: $note" : ''),
            'customer/order_view.php?ref=' . $o['reference']);
    });
    $err = change_status($orderId, 'awaiting_customer_confirmation', 'Waiting for customer to confirm the new price', $by);
    audit('price_adjust', 'order', $orderId, "$old → $newTotal");
    return $err;
}

function customer_answer_price(array $o, int $uid, bool $accept): ?string
{
    if ((int)$o['customer_id'] !== $uid) return 'Not allowed.';
    if ($o['status'] !== 'awaiting_customer_confirmation') return 'This order is not waiting for your confirmation.';
    if ($accept) {
        q('UPDATE orders SET customer_confirmed = 1, updated_at = ? WHERE id = ?', [now(), $o['id']]);
        $err = change_status((int)$o['id'], 'pending_payment', 'Customer accepted the adjusted price', $uid);
        notify_admins('Customer accepted new price', "Order {$o['reference']}: the customer accepted " . peso(order_total($o)), 'admin/order_view.php?id=' . $o['id']);
        return $err;
    }
    $err = change_status((int)$o['id'], 'cancelled', 'Customer declined the adjusted price', $uid);
    notify_admins('Customer declined new price', "Order {$o['reference']} was cancelled because the customer declined the adjusted price.", 'admin/order_view.php?id=' . $o['id']);
    return $err;
}

function customer_cancel(array $o, int $uid): ?string
{
    if ((int)$o['customer_id'] !== $uid) return 'Not allowed.';
    if (!in_array($o['status'], CUSTOMER_CANCELLABLE, true) || $o['payment_status'] === 'paid') {
        return 'This order can no longer be cancelled online. Please contact the shop.';
    }
    return change_status((int)$o['id'], 'cancelled', 'Cancelled by customer', $uid);
}

// ---------------------------------------------------------------- payments
function submit_payment(array $order, int $uid, string $method, float $amount, string $ref, ?array $receipt): ?string
{
    $methods = payment_methods(true);
    if (!isset($methods[$method])) return 'That payment method is not available.';
    if (in_array($order['status'], ['cancelled', 'completed'], true)) return 'This order is closed.';
    if ($order['payment_status'] === 'paid') return 'This order is already paid.';
    if (fetch_val("SELECT 1 FROM payments WHERE order_id = ? AND status = 'pending_verification'", [$order['id']])) return 'You already have a payment waiting for verification.';
    if ($method !== 'cash') {
        if ($ref === '' || strlen($ref) > 60) return 'Enter the payment reference number (up to 60 characters).';
        if ($amount <= 0) return 'Enter the amount you paid.';
    }
    $requiresScreenshot = in_array($method, ['gcash', 'maya'], true);
    $hasReceipt = $receipt && ($receipt['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if ($requiresScreenshot && !$hasReceipt) return 'Upload a GCash or Maya payment screenshot before submitting.';
    $stored = null;
    $meta = null;
    if ($hasReceipt) {
        $allowedExt = $requiresScreenshot ? ['jpg', 'jpeg', 'png', 'webp'] : ['pdf', 'jpg', 'jpeg', 'png'];
        [$ext, $mime, $uerr] = validate_upload($receipt, $allowedExt);
        if ($uerr) return 'Receipt: ' . $uerr;
        if ($requiresScreenshot) {
            $image = @getimagesize($receipt['tmp_name']);
            if (!$image || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || $image['mime'] !== $mime) {
                return 'Payment proof must be a valid JPG, PNG, or WebP image.';
            }
        }
        $stored = bin2hex(random_bytes(20));
        $dest = UPLOAD_DIR . '/receipts/' . $stored;
        ensure_dir(dirname($dest));
        if (!move_uploaded_file($receipt['tmp_name'], $dest)) return 'Could not save the receipt.';
        $meta = [basename($receipt['name']), $mime, (int)$receipt['size']];
    }
    try {
        transaction(function () use ($order, $uid, $method, $amount, $ref, $stored, $meta) {
            $pid = insert('payments', ['order_id' => $order['id'], 'method' => $method, 'amount' => round($amount, 2),
                'reference_no' => $ref ?: null, 'status' => 'pending_verification', 'submitted_by' => $uid, 'submitted_at' => now()]);
            if ($stored) insert('payment_receipts', ['payment_id' => $pid, 'original_name' => $meta[0], 'stored_name' => $stored,
                'mime_type' => $meta[1], 'size_bytes' => $meta[2], 'uploaded_at' => now()]);
            q('UPDATE orders SET payment_status = ?, payment_method = ?, updated_at = ? WHERE id = ?', ['pending_verification', $method, now(), $order['id']]);
            add_history((int)$order['id'], $order['status'], 'Payment submitted (' . strtoupper($method) . ($ref ? ", ref $ref" : '') . ')', $uid);
            notify_admins('Payment to verify', "Order {$order['reference']}: " . payment_methods()[$method]['label'] . ' payment submitted.', 'admin/payments.php?status=pending_verification');
        });
    } catch (Throwable $e) {
        if ($stored) @unlink(UPLOAD_DIR . '/receipts/' . $stored);
        return 'Could not record the payment. Please try again.';
    }
    return null;
}

function mark_payment_paid(int $paymentId, int $adminId, ?float $amount = null): ?string
{
    $p = fetch_one('SELECT * FROM payments WHERE id = ?', [$paymentId]);
    if (!$p) return 'Payment not found.';
    if ($p['status'] === 'paid') return 'Already verified.';
    $o = fetch_one('SELECT * FROM orders WHERE id = ?', [$p['order_id']]);
    transaction(function () use ($p, $o, $adminId, $amount) {
        q('UPDATE payments SET status = \'paid\', amount = ?, receipt_no = ?, verified_by = ?, verified_at = ?, rejection_reason = NULL WHERE id = ?',
            [round($amount ?? (float)$p['amount'], 2), 'OR-' . str_pad((string)$p['id'], 6, '0', STR_PAD_LEFT), $adminId, now(), $p['id']]);
        q('UPDATE orders SET payment_status = \'paid\', payment_method = ?, updated_at = ? WHERE id = ?', [$p['method'], now(), $o['id']]);
        add_history((int)$o['id'], $o['status'], 'Payment verified (receipt OR-' . str_pad((string)$p['id'], 6, '0', STR_PAD_LEFT) . ')', $adminId);
    });
    audit('payment_verified', 'payment', $paymentId);
    if ($o['status'] === 'pending_payment') {
        change_status((int)$o['id'], 'payment_verified', 'Payment verified', $adminId);
    } else {
        notify((int)$o['customer_id'], 'Payment verified', "Your payment for order {$o['reference']} has been verified.", 'customer/order_view.php?ref=' . $o['reference']);
    }
    return null;
}

function reject_payment(int $paymentId, int $adminId, string $reason): ?string
{
    if ($reason === '') return 'Please enter a reason so the customer knows what to fix.';
    $p = fetch_one('SELECT * FROM payments WHERE id = ?', [$paymentId]);
    if (!$p || $p['status'] !== 'pending_verification') return 'Only payments waiting for verification can be rejected.';
    $o = fetch_one('SELECT * FROM orders WHERE id = ?', [$p['order_id']]);
    transaction(function () use ($p, $o, $adminId, $reason) {
        q('UPDATE payments SET status = \'rejected\', rejection_reason = ?, verified_by = ?, verified_at = ? WHERE id = ?', [$reason, $adminId, now(), $p['id']]);
        q('UPDATE orders SET payment_status = \'rejected\', updated_at = ? WHERE id = ?', [now(), $o['id']]);
        add_history((int)$o['id'], $o['status'], 'Payment rejected: ' . $reason, $adminId);
        notify((int)$o['customer_id'], 'Payment rejected', "Your payment for order {$o['reference']} was rejected: $reason. Please submit it again.", 'customer/order_view.php?ref=' . $o['reference']);
    });
    audit('payment_rejected', 'payment', $paymentId, $reason);
    return null;
}

function refund_payment(int $paymentId, int $adminId, string $reason): ?string
{
    $p = fetch_one('SELECT * FROM payments WHERE id = ?', [$paymentId]);
    if (!$p || $p['status'] !== 'paid') return 'Only verified payments can be refunded.';
    $o = fetch_one('SELECT * FROM orders WHERE id = ?', [$p['order_id']]);
    transaction(function () use ($p, $o, $adminId, $reason) {
        q('UPDATE payments SET status = \'refunded\', rejection_reason = ? WHERE id = ?', [$reason ?: null, $p['id']]);
        q('UPDATE orders SET payment_status = \'refunded\', updated_at = ? WHERE id = ?', [now(), $o['id']]);
        add_history((int)$o['id'], $o['status'], 'Payment refunded' . ($reason ? ": $reason" : ''), $adminId);
        notify((int)$o['customer_id'], 'Payment refunded', "Your payment for order {$o['reference']} was marked as refunded.", 'customer/order_view.php?ref=' . $o['reference']);
    });
    audit('payment_refunded', 'payment', $paymentId);
    return null;
}

function record_cash_payment(int $orderId, int $adminId, float $amount): ?string
{
    $o = fetch_one('SELECT * FROM orders WHERE id = ?', [$orderId]);
    if (!$o) return 'Order not found.';
    if ($o['payment_status'] === 'paid') return 'This order is already paid.';
    if ($amount <= 0) return 'Enter the amount received.';
    $pid = insert('payments', ['order_id' => $orderId, 'method' => 'cash', 'amount' => round($amount, 2), 'status' => 'pending_verification',
        'submitted_by' => $adminId, 'submitted_at' => now()]);
    return mark_payment_paid($pid, $adminId, $amount);
}

// ---------------------------------------------------------------- pickup
function confirm_pickup(int $orderId, int $adminId, string $pickedUpBy, string $notes, bool $recordCash): ?string
{
    $o = fetch_one('SELECT * FROM orders WHERE id = ?', [$orderId]);
    if (!$o) return 'Order not found.';
    if ($o['status'] !== 'ready_for_pickup') return 'Only orders marked "Ready for Pickup" can be confirmed as collected.';
    if ($pickedUpBy === '') return 'Enter the name of the person who collected the order.';
    if ($o['payment_status'] !== 'paid') {
        if (!$recordCash) return 'This order is not paid. Record the cash payment received (tick the box) or verify an online payment first.';
        $err = record_cash_payment($orderId, $adminId, order_total($o));
        if ($err) return $err;
    }
    transaction(function () use ($o, $adminId, $pickedUpBy, $notes) {
        insert('pickup_records', ['order_id' => $o['id'], 'picked_up_by' => $pickedUpBy, 'confirmed_by' => $adminId, 'picked_up_at' => now(), 'notes' => $notes ?: null]);
        q('UPDATE orders SET status = \'completed\', completed_at = ?, updated_at = ? WHERE id = ?', [now(), now(), $o['id']]);
        add_history((int)$o['id'], 'completed', "Collected by $pickedUpBy" . ($notes ? " — $notes" : ''), $adminId);
        $n = status_notice('completed', $o['reference']);
        notify((int)$o['customer_id'], $n[0], $n[1], 'customer/order_view.php?ref=' . $o['reference']);
    });
    audit('pickup_confirmed', 'order', $orderId, $pickedUpBy);
    return null;
}

// ---------------------------------------------------------------- files
const UPLOAD_TYPES = [
    'pdf' => ['application/pdf'],
    'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
    'webp' => ['image/webp'],
    'doc' => ['application/msword', 'application/x-ole-storage', 'application/CDFV2', 'application/octet-stream', 'application/vnd.ms-office', 'application/x-cfb'],
    'ppt' => ['application/vnd.ms-powerpoint', 'application/x-ole-storage', 'application/CDFV2', 'application/octet-stream', 'application/vnd.ms-office', 'application/x-cfb'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
];
const ORDER_FILE_EXT = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'ppt', 'pptx'];

function ensure_dir(string $d): void { if (!is_dir($d)) mkdir($d, 0775, true); }

function upload_error_text(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File is larger than the server allows (' . (MAX_UPLOAD / 1048576) . ' MB max; also check php.ini upload_max_filesize).',
        UPLOAD_ERR_PARTIAL => 'Upload was interrupted. Please try again.',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
        default => 'The upload failed.',
    };
}

/** Validates an uploaded file ($_FILES entry). Returns [ext, mime, error|null]. */
function validate_upload(array $f, array $allowedExt): array
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return ['', '', upload_error_text((int)($f['error'] ?? 4))];
    if (!is_uploaded_file($f['tmp_name'])) return ['', '', 'Invalid upload.'];
    if ($f['size'] <= 0) return ['', '', 'The file is empty.'];
    if ($f['size'] > MAX_UPLOAD) return ['', '', 'File is too large (max ' . (MAX_UPLOAD / 1048576) . ' MB).'];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) return ['', '', 'Unsupported file type. Allowed: ' . strtoupper(implode(', ', $allowedExt)) . '.'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: 'application/octet-stream';
    if (!in_array($mime, UPLOAD_TYPES[$ext], true)) return ['', '', 'The file content does not match its extension.'];
    return [$ext, $mime, null];
}

/** Page count via the Python helper (tools/analyze_file.py), with a PHP fallback for PDFs. */
function analyze_file(string $path, string $ext): ?int
{
    $py = (string)env('PYTHON_BIN', PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3');
    $script = ROOT . '/tools/analyze_file.py';
    if (function_exists('proc_open') && is_file($script)) {
        $p = @proc_open([$py, $script, $path, $ext], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (is_resource($p)) {
            $out = stream_get_contents($pipes[1]);
            fclose($pipes[1]); fclose($pipes[2]); proc_close($p);
            $j = json_decode((string)$out, true);
            if (is_array($j) && isset($j['pages'])) return ((int)$j['pages']) ?: null;
        }
    }
    if (in_array($ext, ['jpg', 'jpeg', 'png'], true)) return 1;
    if ($ext === 'pdf') {
        $c = @file_get_contents($path);
        if ($c !== false && preg_match_all('#/Type\s*/Page[^s]#', $c, $m) && count($m[0]) > 0) return count($m[0]);
    }
    return null;
}

function tmp_dir(int $uid): string { return UPLOAD_DIR . '/tmp/' . $uid; }
function valid_token(string $t): bool { return (bool)preg_match('/^[a-f0-9]{32}$/', $t); }
function file_disk_path(array $row, string $kind = 'files'): string { return UPLOAD_DIR . "/$kind/" . $row['stored_name']; }

// ---------------------------------------------------------------- create order
/**
 * Creates an order, its item and its files atomically.
 * $tokens = temp upload tokens previously returned by api/upload.php.
 * Returns [reference|null, error|null].
 */
function create_order(array $user, array $service, array $calc, array $tokens, string $notes, string $method): array
{
    $moved = [];
    $uid = (int)$user['id'];
    $files = [];
    foreach ($tokens as $t) {
        if (!is_string($t) || !valid_token($t)) return [null, 'Invalid file reference.'];
        $meta = @json_decode((string)@file_get_contents(tmp_dir($uid) . "/$t.json"), true);
        if (!$meta || !is_file(tmp_dir($uid) . "/$t.bin")) return [null, 'One of your uploaded files has expired. Please upload it again.'];
        $files[] = $meta + ['token' => $t];
    }
    if (count($files) > 20) return [null, 'You can attach up to 20 files.'];
    if (!$files && !empty($service['needs_upload'])) return [null, 'Please upload at least one file for this service.'];

    $n = $calc['normalized'];
    $days = (int)$service['processing_days'];
    $eta = date('Y-m-d', strtotime("+$days day"));
    $ref = gen_reference();
    try {
        $orderId = transaction(function () use ($user, $service, $calc, $files, $notes, $method, $n, $eta, $ref, $uid, &$moved) {
            $oid = insert('orders', [
                'reference' => $ref, 'customer_id' => $uid, 'status' => 'order_received', 'payment_status' => 'unpaid',
                'payment_method' => $method ?: null, 'estimated_total' => $calc['total'], 'estimated_completion' => $eta,
                'special_instructions' => $notes ?: null, 'created_at' => now(), 'updated_at' => now(),
            ]);
            insert('order_items', [
                'order_id' => $oid, 'service_id' => $service['id'], 'service_name' => $service['name'],
                'paper_size' => $n['size'], 'paper_type' => $n['paper'], 'color' => $n['color'], 'sides' => $n['sides'],
                'orientation' => $n['orientation'], 'binding' => $n['binding'], 'pages' => $n['pages'], 'copies' => $n['copies'],
                'width_ft' => $n['width'], 'height_ft' => $n['height'], 'unit_price' => $calc['unit_price'],
                'line_total' => $calc['total'], 'breakdown' => json_encode($calc['lines']), 'notes' => $notes ?: null,
            ]);
            foreach ($files as $f) {
                $stored = bin2hex(random_bytes(20));
                $dest = UPLOAD_DIR . '/files/' . $stored;
                ensure_dir(dirname($dest));
                $src = tmp_dir($uid) . '/' . $f['token'] . '.bin';
                if (!rename($src, $dest)) throw new RuntimeException('Could not store file');
                $moved[] = [$dest, $src];
                insert('uploaded_files', ['order_id' => $oid, 'user_id' => $uid, 'original_name' => $f['name'], 'stored_name' => $stored,
                    'mime_type' => $f['mime'], 'size_bytes' => $f['size'], 'page_count' => $f['pages'] ?? null, 'uploaded_at' => now()]);
            }
            add_history($oid, 'order_received', 'Order submitted online', $uid);
            q('UPDATE orders SET status = \'awaiting_review\' WHERE id = ?', [$oid]);
            add_history($oid, 'awaiting_review', $calc['needs_review'] ? 'Price needs shop confirmation' : 'Waiting for shop review', null);
            notify($uid, 'Order received', "We received order $ref. We'll review it shortly. Estimated total: " . peso($calc['total']) . '.', 'customer/order_view.php?ref=' . $ref);
            notify_admins('New order', "$ref — {$service['name']} (" . peso($calc['total']) . ')', 'admin/order_view.php?id=' . $oid);
            return $oid;
        });
    } catch (Throwable $e) {
        foreach ($moved as [$dest, $src]) @rename($dest, $src);   // put files back so the customer can retry
        error_log('create_order failed: ' . $e->getMessage());
        return [null, 'Could not save your order. Please try again.'];
    }
    foreach ($files as $f) @unlink(tmp_dir($uid) . '/' . $f['token'] . '.json');
    audit('order_created', 'order', $orderId, $ref);
    return [$ref, null];
}

/** Load an order (with its first item) enforcing ownership when $customerId is given. */
function load_order(string $where, array $params, ?int $customerId = null): ?array
{
    $sql = "SELECT o.*, u.email AS customer_email, c.full_name AS customer_name, c.phone AS customer_phone
            FROM orders o JOIN users u ON u.id = o.customer_id LEFT JOIN customer_profiles c ON c.user_id = o.customer_id
            WHERE $where";
    if ($customerId !== null) { $sql .= ' AND o.customer_id = ?'; $params[] = $customerId; }
    $o = fetch_one($sql, $params);
    if (!$o) return null;
    $o['item'] = fetch_one('SELECT * FROM order_items WHERE order_id = ? ORDER BY id LIMIT 1', [$o['id']]) ?: [];
    $o['files'] = fetch_all('SELECT * FROM uploaded_files WHERE order_id = ? ORDER BY id', [$o['id']]);
    $o['history'] = fetch_all('SELECT h.*, COALESCE(a.full_name, cp.full_name) AS by_name FROM order_status_history h
        LEFT JOIN admin_users a ON a.user_id = h.changed_by LEFT JOIN customer_profiles cp ON cp.user_id = h.changed_by
        WHERE h.order_id = ? ORDER BY h.id', [$o['id']]);
    $o['payments'] = fetch_all('SELECT p.*, r.id AS receipt_id, r.original_name AS receipt_name FROM payments p
        LEFT JOIN payment_receipts r ON r.payment_id = p.id WHERE p.order_id = ? ORDER BY p.id DESC', [$o['id']]);
    return $o;
}
