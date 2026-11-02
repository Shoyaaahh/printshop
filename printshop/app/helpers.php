<?php
declare(strict_types=1);

const ORDER_STATUSES = [
    'order_received' => 'Order Received',
    'awaiting_review' => 'Awaiting Shop Review',
    'awaiting_customer_confirmation' => 'Awaiting Customer Confirmation',
    'pending_payment' => 'Pending Payment',
    'payment_verified' => 'Payment Verified',
    'processing' => 'Processing',
    'printing' => 'Printing',
    'quality_checking' => 'Quality Checking',
    'ready_for_pickup' => 'Ready for Pickup',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
];
const PAYMENT_STATUSES = [
    'unpaid' => 'Unpaid', 'pending_verification' => 'Pending Verification',
    'paid' => 'Verified', 'rejected' => 'Rejected', 'refunded' => 'Refunded',
];
const PAYMENT_METHOD_KEYS = ['gcash', 'maya', 'bank', 'cash'];

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function now(): string { return date('Y-m-d H:i:s'); }
function url(string $path = ''): string { return BASE_URL . '/' . ltrim($path, '/'); }
function peso($n): string { return chr(226).chr(130).chr(177) . number_format((float)$n, 2); }
function fmt_dt(?string $d, bool $time = true): string
{
    if (!$d) return '-';
    $t = strtotime($d);
    return $t ? date($time ? 'M j, Y g:i A' : 'M j, Y', $t) : e($d);
}
function client_ip(): string { return $_SERVER['REMOTE_ADDR'] ?? 'cli'; }

function redirect(string $path): never
{
    if (!preg_match('#^https?://#', $path)) $path = url($path);
    header('Location: ' . $path);
    exit;
}
/** Only allow redirects to local paths (prevents open-redirect). */
function safe_next(?string $next, string $fallback): string
{
    if ($next && str_starts_with($next, '/') && !str_starts_with($next, '//') && !str_contains($next, '\\')) return $next;
    return $fallback;
}

// ---------- flash messages ----------
function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }
function flash_render(): string
{
    $out = '';
    foreach ($_SESSION['flash'] ?? [] as [$t, $m]) {
        $out .= '<div class="alert alert-' . e($t) . '" role="alert">' . e($m) . '</div>';
    }
    unset($_SESSION['flash']);
    return $out;
}

// ---------- CSRF ----------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_valid(): bool
{
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($sent) && $sent !== '' && hash_equals(csrf_token(), $sent);
}
/** Call at the top of every POST handler. */
function csrf_check(bool $json = false): void
{
    if (csrf_valid()) return;
    http_response_code(419);
    if ($json) json_out(['ok' => false, 'error' => 'Session expired. Please refresh the page.'], 419);
    exit('Invalid or expired form token. Go back, refresh the page and try again.');
}
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'; }
function post(string $k, string $d = ''): string { $v = $_POST[$k] ?? $d; return is_string($v) ? trim($v) : $d; }
function get(string $k, string $d = ''): string { $v = $_GET[$k] ?? $d; return is_string($v) ? trim($v) : $d; }

function json_out(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------- rate limiting ----------
/** Returns true if the action is allowed, false if the limit has been hit. */
function rate_limit(string $bucket, int $max, int $seconds): bool
{
    $bucket = substr(hash('sha256', $bucket), 0, 40);
    $t = time();
    $row = fetch_one('SELECT hits, window_start FROM rate_limits WHERE bucket = ?', [$bucket]);
    if (!$row || $t - (int)$row['window_start'] >= $seconds) {
        q('INSERT OR REPLACE INTO rate_limits (bucket, hits, window_start) VALUES (?,?,?)', [$bucket, 1, $t]);
        return true;
    }
    if ((int)$row['hits'] >= $max) return false;
    q('UPDATE rate_limits SET hits = hits + 1 WHERE bucket = ?', [$bucket]);
    return true;
}

// ---------- settings ----------
function settings_all(): array
{
    static $s = null;
    if ($s === null) {
        $s = [];
        foreach (fetch_all('SELECT setting_key, setting_value FROM business_settings') as $r) $s[$r['setting_key']] = $r['setting_value'];
    }
    return $s;
}
function setting(string $k, string $d = ''): string { return settings_all()[$k] ?? $d; }
function setting_json(string $k, array $d = []): array
{
    $v = json_decode(setting($k, ''), true);
    return is_array($v) ? $v : $d;
}
function set_setting(string $k, string $v): void
{
    q('INSERT INTO business_settings (setting_key, setting_value) VALUES (?,?) ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value', [$k, $v]);
}

// ---------- audit ----------
function audit(string $action, ?string $entity = null, $entityId = null, ?string $detail = null): void
{
    $uid = $_SESSION['uid'] ?? null;
    q('INSERT INTO audit_logs (user_id, action, entity, entity_id, detail, ip_address, created_at) VALUES (?,?,?,?,?,?,?)',
        [$uid, $action, $entity, $entityId === null ? null : (string)$entityId, $detail, client_ip(), now()]);
}

// ---------- badges ----------
function status_badge(string $s): string
{
    $cls = match ($s) {
        'completed', 'paid', 'payment_verified' => 'ok',
        'cancelled', 'rejected' => 'bad',
        'ready_for_pickup' => 'accent',
        'awaiting_review', 'awaiting_customer_confirmation', 'pending_payment', 'pending_verification', 'unpaid', 'order_received' => 'warn',
        'refunded' => 'muted',
        default => 'info',
    };
    $label = ORDER_STATUSES[$s] ?? PAYMENT_STATUSES[$s] ?? ucfirst(str_replace('_', ' ', $s));
    return '<span class="badge badge-' . $cls . '">' . e($label) . '</span>';
}

// ---------- pagination ----------
function paginate(string $sql, array $params, int $perPage = 15): array
{
    $total = (int)fetch_val("SELECT COUNT(*) FROM ($sql)", $params);
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min(max(1, (int)get('page', '1')), $pages);
    $rows = fetch_all($sql . ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage), $params);
    return ['rows' => $rows, 'total' => $total, 'pages' => $pages, 'page' => $page];
}
function pager(array $p): string
{
    if ($p['pages'] <= 1) return '<div class="pager"><span class="muted">' . $p['total'] . ' result(s)</span></div>';
    $q = $_GET; $out = '<div class="pager"><span class="muted">' . $p['total'] . ' result(s) | page ' . $p['page'] . ' of ' . $p['pages'] . '</span><div>';
    $link = function (int $n, string $label, bool $disabled = false) use ($q) {
        if ($disabled) return '<span class="btn btn-sm btn-outline disabled">' . $label . '</span>';
        $q['page'] = $n;
        return '<a class="btn btn-sm btn-outline" href="?' . e(http_build_query($q)) . '">' . $label . '</a>';
    };
    $out .= $link($p['page'] - 1, 'Previous', $p['page'] <= 1) . ' ' . $link($p['page'] + 1, 'Next', $p['page'] >= $p['pages']);
    return $out . '</div></div>';
}

// ---------- store info helpers ----------
const DAYS = ['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'];
function store_hours(): array
{
    $def = [];
    foreach (DAYS as $k => $_) $def[$k] = ['open' => '08:00', 'close' => '18:00', 'closed' => $k === 'sun'];
    return array_replace($def, setting_json('hours', $def));
}
function fmt_time(string $hhmm): string { $t = strtotime($hhmm); return $t ? date('g:i A', $t) : $hhmm; }
function store_open_now(): bool
{
    $h = store_hours()[strtolower(substr(date('D'), 0, 3))] ?? null;
    if (!$h || !empty($h['closed'])) return false;
    $n = date('H:i');
    return $n >= $h['open'] && $n < $h['close'];
}
function payment_methods(bool $onlyEnabled = false): array
{
    $def = [
        'gcash' => ['label' => 'GCash', 'enabled' => 1, 'account' => '', 'instructions' => ''],
        'maya' => ['label' => 'Maya', 'enabled' => 1, 'account' => '', 'instructions' => ''],
        'bank' => ['label' => 'Bank Transfer', 'enabled' => 1, 'account' => '', 'instructions' => ''],
        'cash' => ['label' => 'Cash at Store', 'enabled' => 1, 'account' => '', 'instructions' => 'Pay in cash when you pick up your order.'],
    ];
    $m = array_replace_recursive($def, setting_json('payment_methods', $def));
    return $onlyEnabled ? array_filter($m, fn($x) => !empty($x['enabled'])) : $m;
}
function slugify(string $s): string
{
    $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-'));
    return $s !== '' ? $s : 'item';
}
