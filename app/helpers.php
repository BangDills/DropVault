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

// Stream a file from protected storage with range support. Never expose path.
function stream_file(string $path, string $name, string $mime, int $size): void
{
    if (!is_readable($path)) {
        http_response_code(404);
        echo 'Not found';
        return;
    }
    $start = 0;
    $end = $size - 1;
    if (isset($_SERVER['HTTP_RANGE'])) {
        if (preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
            $start = (int)$m[1];
            if ($m[2] !== '') $end = (int)$m[2];
            http_response_code(206);
            header("Content-Range: bytes $start-$end/$size");
        }
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . ($end - $start + 1));
    header('Content-Disposition: inline; filename="' . addslashes($name) . '"');
    header('Accept-Ranges: bytes');
    header('Cache-Control: private, max-age=0');
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

// Constant-time password check (config still plaintext — hash for prod).
function check_password(string $input): bool
{
    $expected = config()['password'];
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
