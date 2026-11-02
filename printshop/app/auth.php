<?php
declare(strict_types=1);

function current_user(): ?array
{
    static $cache = false;
    if ($cache !== false) return $cache;
    $id = $_SESSION['uid'] ?? null;
    if (!$id) return $cache = null;
    $u = fetch_one('SELECT u.id, u.email, u.role, u.status, u.created_at,
                    COALESCE(c.full_name, a.full_name) AS full_name, c.phone
                    FROM users u
                    LEFT JOIN customer_profiles c ON c.user_id = u.id
                    LEFT JOIN admin_users a ON a.user_id = u.id
                    WHERE u.id = ?', [$id]);
    if (!$u || $u['status'] !== 'active') {
        unset($_SESSION['uid']);
        return $cache = null;
    }
    return $cache = $u;
}

/** Protects a page. $role = 'customer' | 'admin'. Wrong-role users are redirected, never shown the page. */
function require_login(string $role): array
{
    $u = current_user();
    if (!$u) {
        $loginPage = $role === 'admin' ? 'admin/login.php' : 'login.php';
        redirect($loginPage . '?next=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
    }
    if ($u['role'] !== $role) {
        if ($u['role'] === 'admin') redirect('admin/index.php');
        redirect('customer/dashboard.php');
    }
    return $u;
}

function login_user(int $id): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = $id;
    q('UPDATE users SET last_login_at = ? WHERE id = ?', [now(), $id]);
}
function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Attempts a login for a given role. Returns [userId|null, errorMessage|null]. */
function attempt_login(string $email, string $password, string $role): array
{
    $ip = client_ip();
    if (!rate_limit("login:$ip", 20, 600) || !rate_limit('login-email:' . strtolower($email), 8, 600)) {
        return [null, 'Too many attempts. Please wait a few minutes and try again.'];
    }
    $u = fetch_one('SELECT * FROM users WHERE email = ?', [$email]);
    // Same message for every failure so accounts cannot be enumerated.
    $generic = 'Incorrect email or password.';
    if (!$u || $u['role'] !== $role || !password_verify($password, $u['password_hash'])) {
        audit('login_failed', 'user', null, $email);
        return [null, $generic];
    }
    if ($u['status'] !== 'active') return [null, 'This account has been suspended. Please contact the shop.'];
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
    }
    return [(int)$u['id'], null];
}

function validate_password(string $p): ?string
{
    if (strlen($p) < 8) return 'Password must be at least 8 characters.';
    if (!preg_match('/[A-Za-z]/', $p) || !preg_match('/\d/', $p)) return 'Password must contain letters and numbers.';
    return null;
}
function valid_phone(string $p): bool { return (bool)preg_match('/^[0-9+()\-\s]{7,20}$/', $p); }
