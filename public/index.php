<?php
declare(strict_types=1);

// Single entry point + front controller. Routes /api?*, /s/*, /dl/*, /login,
// /logout, and the dashboard. Pretty URLs via .htaccess; falls back to ?route=.

// PHP built-in server: when a router script is given, it handles every
// request — so let static files (assets) be served directly by returning false.
// Apache (cPanel) reaches index.php only via .htaccess rewrite, which already
// skips real files via RewriteCond %{REQUEST_FILENAME} -f.
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$realPath = __DIR__ . $uri;
if ($uri !== '/' && is_file($realPath)) {
    return false; // let the built-in server output the static file
}

require __DIR__ . '/../app/bootstrap.php';

// Determine route:
// - Apache with .htaccess rewrite (cPanel prod): PATH_INFO carries the route.
// - PHP built-in server / plain hosts: use REQUEST_URI directly.
$path = $_SERVER['PATH_INFO'] ?? '';
if ($path === '') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    // Strip subfolder base only when SCRIPT_NAME is our index.php (real app
    // root). PHP's built-in server sets SCRIPT_NAME to the request path for
    // missing files — stripping based on that would corrupt the route.
    $sn = $_SERVER['SCRIPT_NAME'] ?? '/';
    if (str_ends_with($sn, 'index.php')) {
        $base = rtrim(dirname($sn), '/');
        if ($base !== '' && $base !== '\\' && str_starts_with($path, $base . '/')) {
            $path = substr($path, strlen($base));
        } elseif ($base !== '' && $base !== '\\' && $path === $base) {
            $path = '/';
        }
    }
}
$path = '/' . trim($path, '/');

// Public share + download (no login).
if (preg_match('#^/s/(?<token>[0-9a-f]+)$#', $path, $m)) {
    share_view($m['token']);
    exit;
}
if (preg_match('#^/dl/(?<id>\d+)/(?<token>[0-9a-f.]+)$#', $path, $m)) {
    file_download_signed((int)$m['id'], $m['token']);
    exit;
}
if (preg_match('#^/raw/(?<id>\d+)$#', $path, $m)) {
    require_login();
    file_raw((int)$m['id']);
    exit;
}
if (preg_match('#^/thumb/(?<id>\d+)$#', $path, $m)) {
    require_login();
    file_thumb((int)$m['id']);
    exit;
}
// QR code for an arbitrary URL (used by share UI). Public-ish: it only encodes
// a URL, no secrets. Registered as public so share page (logged-out) can use it.
if (preg_match('#^/qr/(?<data>.+)$#', $path, $m)) {
    qr_png(urldecode($m['data']));
    exit;
}

// Auth.
if ($path === '/login') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        login_post();
    } else {
        login_view();
    }
    exit;
}
if ($path === '/logout') {
    logout();
    exit;
}

// Everything else requires login.
require_login();

// API endpoints (JSON).
if (str_starts_with($path, '/api/')) {
    api($path);
    exit;
}

// Dashboard / default.
dashboard($path);
