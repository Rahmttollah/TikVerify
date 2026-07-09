<?php
/**
 * config.php
 * Central configuration for the TikTok Link Checker.
 * Edit the values below to tune behaviour. No other file needs changes
 * for basic deployment.
 */

// Prevent direct informational leakage in production.
error_reporting(E_ALL);
ini_set('display_errors', '0'); // set to '1' temporarily if you need to debug

// --- Network settings -------------------------------------------------
define('REQUEST_TIMEOUT', 12);        // seconds per HTTP request
define('CONNECT_TIMEOUT', 6);         // seconds to establish connection
define('MAX_REDIRECTS', 5);           // for short-link resolution

// Rotated User-Agents to reduce chance of being blocked. A random one is
// picked per request.
define('USER_AGENTS', [
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15',
    'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1',
    'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36',
]);

// --- App settings -------------------------------------------------------
define('APP_NAME', 'TikVerify');
define('APP_TAGLINE', 'Premium bulk TikTok link verification');
define('MAX_URL_LENGTH', 500);

// Set to true to write per-request method/debug logs via error_log().
// Safe to leave on for shared hosting — it only writes to the PHP error log,
// never to the HTTP response.
define('DEBUG_LOG_METHODS', true);

// Session-based CSRF protection.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- Cookie jar ----------------------------------------------------------
// TikTok issues anti-bot cookies (e.g. tt_csrf/msToken) on the first request
// that later requests are expected to send back. We persist a small cookie
// jar per PHP session so the short-link redirect request and the canonical
// page request (and subsequent checks in the same browser session) share
// the same cookies, mimicking a real browser session.
//
// Hardened for shared hosting (where /tmp is often world-readable and
// shared across every tenant's PHP processes):
//   - stored in an app-private directory, not the shared system temp dir
//   - filename is an HMAC of the session id, not the raw id, so it can't be
//     predicted or guessed from a leaked session cookie
//   - file is created with 0600 permissions (owner read/write only)
//   - refuses to use the path if it turns out to be a symlink
function tiktok_cookie_jar_path(): string
{
    $dir = __DIR__ . '/.cookie_jars';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    // .htaccess belt-and-suspenders in case the host serves this dir directly.
    $htaccess = $dir . '/.htaccess';
    if (!file_exists($htaccess)) {
        @file_put_contents($htaccess, "Require all denied\nDeny from all\n");
    }

    // A random per-installation secret (generated once, stored 0600, never
    // exposed) salts the filename hash. We don't rely on an env var here —
    // shared hosts like InfinityFree don't provide one.
    $secretFile = $dir . '/.salt';
    if (!file_exists($secretFile)) {
        file_put_contents($secretFile, bin2hex(random_bytes(32)));
        chmod($secretFile, 0600);
    }
    $secret = trim((string) file_get_contents($secretFile));

    $sid = session_id() ?: 'nosession';
    $name = hash_hmac('sha256', $sid, $secret);
    $path = $dir . '/' . $name . '.txt';

    if (is_link($path)) {
        // Never follow/write through a symlink; force a fresh, safe file.
        @unlink($path);
    }
    if (!file_exists($path)) {
        touch($path);
    }
    chmod($path, 0600);

    return $path;
}
