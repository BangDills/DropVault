<?php
declare(strict_types=1);

// Auth + misc helpers.

function config(): array
{
    static $cfg = null;
    $cfg ??= require APP_ROOT . '/config.php';
    return $cfg;
}

function is_logged_in(): bool
{
    return !empty($_SESSION['authed']);
}

// CSRF: per-session random token. Verified on every state-changing request.
// API key auth (Bearer) bypasses CSRF — it carries its own credential.
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_verify(): bool
{
    $tok = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($tok === '' && function_exists('getallheaders')) {
        $all = getallheaders();
        $tok = $all['X-CSRF-Token'] ?? $all['X-Csrf-Token'] ?? '';
    }
    if ($tok === '' && is_array($_POST) && isset($_POST['csrf'])) {
        $tok = (string)$_POST['csrf'];
    }
    return $tok !== '' && hash_equals($_SESSION['csrf'] ?? '', $tok);
}

// API auth: a configured api_key grants access via Authorization: Bearer.
// Used as an alternative to session login for script/curl access.
function is_api_authed(): bool
{
    $key = config()['api_key'] ?? '';
    if ($key === '') return false;
    $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($hdr === '' && function_exists('getallheaders')) {
        $all = getallheaders();
        $hdr = $all['Authorization'] ?? $all['authorization'] ?? '';
    }
    if (!preg_match('/^Bearer\s+(.+)$/i', $hdr, $m)) return false;
    return hash_equals($key, trim($m[1]));
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ' . url('/login'));
        exit;
    }
}

function url(string $path = ''): string
{
    // Base path of the app: dirname of SCRIPT_NAME, but only trust it when
    // the script is actually index.php (PHP's built-in server sets SCRIPT_NAME
    // to the request path for missing files, which would corrupt the base).
    $sn = $_SERVER['SCRIPT_NAME'] ?? '/';
    $base = str_ends_with($sn, 'index.php') ? rtrim(dirname($sn), '/') : '';
    $base = ($base === '/' || $base === '\\') ? '' : $base;
    return $base . $path;
}

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Token-safe storage filename: random + keep extension for MIME hinting.
function storage_id(string $ext = ''): string
{
    return bin2hex(random_bytes(12)) . ($ext !== '' ? '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext) : '');
}

function human_size(int $bytes): string
{
    if ($bytes < 1024) return $bytes . ' B';
    $u = ['KB', 'MB', 'GB', 'TB'];
    $i = -1;
    $n = (float)$bytes;
    do { $n /= 1024; $i++; } while ($n >= 1024 && $i < count($u) - 1);
    return round($n, 1) . ' ' . $u[$i];
}

function file_kind(string $mime, string $name): string
{
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (str_starts_with($mime, 'image/')) return 'image';
    if (str_starts_with($mime, 'video/')) return 'video';
    if (str_starts_with($mime, 'audio/')) return 'audio';
    if ($mime === 'application/pdf' || $ext === 'pdf') return 'pdf';
    if (in_array($ext, ['txt', 'md', 'log', 'json', 'js', 'php', 'py', 'go', 'rs', 'sh', 'yml', 'yaml', 'css', 'html', 'xml', 'ini', 'conf'], true)) return 'text';
    if (in_array($ext, ['zip', 'gz', 'tar', 'rar', '7z', 'bz2'], true)) return 'archive';
    return 'file';
}

// MIME types safe to render inline in a browser. Anything else (HTML, SVG,
// XML, JS, etc.) is forced to download so it can't run a stored-XSS payload
// from /raw — same-origin, with the user's session.
function mime_inline_safe(string $mime, string $name): bool
{
    static $safe = [
        'image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/bmp', 'image/avif',
        'video/mp4', 'video/webm', 'video/ogg',
        'audio/mpeg', 'audio/ogg', 'audio/wav', 'audio/webm', 'audio/aac', 'audio/flac',
        'application/pdf', 'application/octet-stream',
        'text/plain',
    ];
    if (in_array(strtolower($mime), $safe, true)) return true;
    // octet-stream already covered; deny anything else by default.
    return false;
}

// RFC 5987 filename* for non-ASCII names, plus ASCII fallback.
function content_disposition_filename(string $name): string
{
    $ascii = preg_replace('/[^\x20-\x7E]/', '_', $name);
    $ascii = str_replace('"', '', $ascii);
    if ($ascii === $name) {
        return 'filename="' . addslashes($name) . '"';
    }
    return 'filename="' . addslashes($ascii) . '"; filename*=UTF-8\'\'' . rawurlencode($name);
}

// Stream a file from protected storage with range support. Never expose path.
function stream_file(string $path, string $name, string $mime, int $size, bool $inline = true): void
{
    if (!is_readable($path)) {
        http_response_code(404);
        echo 'Not found';
        return;
    }
    // Force download for anything that isn't on the inline-safe list, even when
    // the caller asked for inline preview — blocks stored XSS via /raw.
    if ($inline && !mime_inline_safe($mime, $name)) {
        $inline = false;
    }
    $start = 0;
    $end = max(0, $size - 1);
    if (isset($_SERVER['HTTP_RANGE'])) {
        if (preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
            $start = (int)$m[1];
            if ($m[2] !== '') $end = (int)$m[2];
        }
    }
    // Clamp the end to EOF (a client may ask for bytes past the end).
    $end = min($end, max(0, $size - 1));
    // Range sanity: reject requests starting past EOF with 416. An empty
    // file (size 0) is served as a 200 with no body — not a 416.
    if ($size > 0 && ($start >= $size || $end < $start)) {
        http_response_code(416);
        header('Content-Range: bytes */' . $size);
        return;
    }
    if ($size > 0 && ($start !== 0 || $end !== $size - 1)) {
        http_response_code(206);
        header("Content-Range: bytes $start-$end/$size");
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . ($end - $start + 1));
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; ' . content_disposition_filename($name));
    header('Accept-Ranges: bytes');
    header('Cache-Control: private, max-age=0');
    header('X-Content-Type-Options: nosniff');
    $f = fopen($path, 'rb');
    fseek($f, $start);
    $remaining = $end - $start + 1;
    while ($remaining > 0 && !feof($f)) {
        $chunk = (int)min(8192 * 16, $remaining);
        echo fread($f, $chunk);
        $remaining -= $chunk;
        if (connection_aborted()) break;
    }
    fclose($f);
}

// Sign/verify HMAC for download tokens (time-limited, tamper-proof).
function sign_token(string $payload, int $ttl): string
{
    $secret = config()['secret'];
    $exp = (string)(time() + $ttl);
    $sig = hash_hmac('sha256', $payload . '|' . $exp, $secret);
    return $exp . '.' . $sig;
}

function verify_token(string $payload, string $token): bool
{
    $secret = config()['secret'];
    [$exp, $sig] = array_pad(explode('.', $token, 2), 2, '');
    if ((int)$exp < time()) return false;
    return hash_equals(hash_hmac('sha256', $payload . '|' . $exp, $secret), $sig);
}

// Upload rate limit: max N uploads per window per IP. Returns true if allowed
// (and records the attempt); false if over limit. Best-effort, SQLite-backed.
function upload_rate_limited(): bool
{
    $cfg = config();
    $max = 60;      // max uploads
    $window = 60;   // per 60 seconds
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $pdo = db($cfg['db_path']);
    $now = microtime(true);
    $pdo->exec('BEGIN IMMEDIATE');
    $pdo->prepare('DELETE FROM upload_log WHERE created_at < ?')->execute([$now - $window]);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM upload_log WHERE ip = ?');
    $stmt->execute([$ip]);
    $over = (int)$stmt->fetchColumn() >= $max;
    if (!$over) {
        $pdo->prepare('INSERT INTO upload_log (ip, created_at) VALUES (?, ?)')->execute([$ip, $now]);
    }
    $pdo->exec('COMMIT');
    return $over;
}

// Auth rate limit: caps failed password attempts per IP (login, share unlock,
// password change). Returns true if the caller should reject the attempt now.
function auth_rate_limited(string $bucket): bool
{
    $max = 10;      // attempts
    $window = 600;  // per 10 minutes
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $pdo = db(config()['db_path']);
    $now = microtime(true);
    $pdo->exec('BEGIN IMMEDIATE');
    $pdo->prepare('DELETE FROM auth_log WHERE created_at < ?')->execute([$now - $window]);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM auth_log WHERE ip = ? AND bucket = ?');
    $stmt->execute([$ip, $bucket]);
    $over = (int)$stmt->fetchColumn() >= $max;
    if (!$over) {
        $pdo->prepare('INSERT INTO auth_log (ip, bucket, created_at) VALUES (?, ?, ?)')->execute([$ip, $bucket, $now]);
    }
    $pdo->exec('COMMIT');
    return $over;
}

// Password check: supports both plaintext (constant-time) and password_hash
// strings ($2y$ / $argon2id$). Hashing the config value is recommended.
function check_password(string $input): bool
{
    $expected = config()['password'];
    if (preg_match('/^\$(2y|2a|2b|argon2i|argon2id)\$/', $expected)) {
        return password_verify($input, $expected);
    }
    return hash_equals($expected, $input);
}

// Lucide-style outline icon as inline SVG. Stroke-based, currentColor, 24x24.
// Design-only: returns markup for the named icon or a fallback file icon.
function lucide(string $name, string $cls = 'w-5 h-5'): string
{
    $icons = [
        'cloud'      => '<path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/>',
        'upload'     => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>',
        'folder'     => '<path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/>',
        'trash'      => '<path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>',
        'share'      => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>',
        'download'   => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
        'sun'        => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>',
        'moon'       => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
        'logout'     => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'search'     => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'file'       => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><polyline points="14 2 14 8 20 8"/>',
        'image'      => '<rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>',
        'video'      => '<path d="m22 8-6 4 6 4V8Z"/><rect width="14" height="12" x="2" y="6" rx="2" ry="2"/>',
        'music'      => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
        'file-text'  => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/>',
        'archive'    => '<rect width="20" height="5" x="2" y="3" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"/><path d="M10 12h4"/>',
        'lock'       => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'x'          => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'plus'       => '<path d="M5 12h14"/><path d="M12 5v14"/>',
        'note'       => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><polyline points="14 2 14 8 20 8"/><path d="M8 13h8M8 17h5"/>',
        'home'       => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
        'chevron-left'  => '<path d="m15 18-6-6 6-6"/>',
        'bell'          => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
        'settings'      => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
        'star'          => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
        'clock'         => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'users'         => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'hard-drive'    => '<line x1="22" y1="12" x2="2" y2="12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/><line x1="6" y1="16" x2="6.01" y2="16"/><line x1="10" y1="16" x2="10.01" y2="16"/>',
        'file-plus'     => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/>',
        'link'          => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        'monitor'       => '<rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>',
        'layout-dashboard' => '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="5" x="14" y="12" rx="1"/><rect width="7" height="9" x="3" y="16" rx="1"/>',
        'menu'          => '<line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="18" x2="20" y2="18"/>',
        'inbox'         => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
        'more-horizontal' => '<circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>',
        'activity'      => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
        'wifi'          => '<path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/>',
        'folder-open'   => '<path d="m6 14 1.5-2.9A2 2 0 0 1 9.24 10H20a2 2 0 0 1 1.94 2.5l-1.54 6a2 2 0 0 1-1.95 1.5H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h3.9a2 2 0 0 1 1.69.9l.81 1.2a2 2 0 0 0 1.67.9H18a2 2 0 0 1 2 2v2"/>',
        'square-pen'    => '<path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/>',
    ];
    $body = $icons[$name] ?? $icons['file'];
    return '<svg class="' . e($cls) . '" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $body . '</svg>';
}

// Escape text, then turn URLs into clickable links. For note display.
function auto_link(string $text): string
{
    $esc = e($text);
    $pattern = '~(https?://[^\s<]+)~i';
    return preg_replace_callback($pattern, function ($m) {
        $url = $m[1];
        // Trim trailing punctuation that's likely not part of the URL.
        $url = rtrim($url, ".,;:!?)]}\"'");
        $tail = substr($m[1], strlen($url));
        return '<a href="' . e($url) . '" target="_blank" rel="noopener noreferrer" class="text-cv-accent underline hover:opacity-80">' . e($url) . '</a>' . e($tail);
    }, $esc);
}
