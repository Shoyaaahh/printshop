<?php
declare(strict_types=1);
/**
 * Bootstrap: environment, constants, session, security headers, core libraries.
 * Every page in /public starts with: require __DIR__ . '/../app/bootstrap.php';
 */
define('ROOT', dirname(__DIR__));

function env(string $key, $default = null)
{
    static $file = null;
    if ($file === null) {
        $file = [];
        $path = ROOT . '/.env';
        if (is_file($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
                [$k, $v] = explode('=', $line, 2);
                $file[trim($k)] = trim($v, " \t\"'");
            }
        }
    }
    $real = getenv($key);
    if ($real !== false) return $real;      // real environment variables win
    return $file[$key] ?? $default;
}

date_default_timezone_set((string)env('APP_TIMEZONE', 'Asia/Manila'));
define('APP_DEBUG', env('APP_DEBUG', '0') === '1');
define('STORAGE', rtrim((string)env('STORAGE_DIR', ROOT . '/storage'), '/\\'));
define('UPLOAD_DIR', STORAGE . '/uploads');
define('MAX_UPLOAD', (int)env('MAX_UPLOAD_MB', '20') * 1024 * 1024);

ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Base URL path (so the site works from "/" or from "/printshop/public")
$base = '';
if (PHP_SAPI !== 'cli' && isset($_SERVER['SCRIPT_FILENAME'], $_SERVER['SCRIPT_NAME'])) {
    $pub = realpath(ROOT . '/public');
    $script = realpath($_SERVER['SCRIPT_FILENAME']);
    if ($pub && $script && str_starts_with($script, $pub)) {
        $rel = str_replace('\\', '/', substr($script, strlen($pub)));
        if ($rel !== '' && str_ends_with($_SERVER['SCRIPT_NAME'], $rel)) {
            $base = substr($_SERVER['SCRIPT_NAME'], 0, -strlen($rel));
        }
    }
}
define('BASE_URL', rtrim($base, '/'));

require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/pricing.php';
require __DIR__ . '/orders.php';
require __DIR__ . '/layout.php';

if (PHP_SAPI !== 'cli') {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name('PSSESSID');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https,
        'httponly' => true, 'samesite' => 'Lax']);
    ini_set('session.use_strict_mode', '1');
    session_start();

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if ($https) header('Strict-Transport-Security: max-age=31536000');
}
