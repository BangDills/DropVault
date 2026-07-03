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
