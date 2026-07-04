<?php
declare(strict_types=1);

// Route handlers. Included by public/index.php via bootstrap -> require here.
// ponytail: a tiny hand-router; swap for a real lib if the route table grows.

require APP_ROOT . '/app/files.php';
require APP_ROOT . '/app/shares.php';
require APP_ROOT . '/app/notes.php';

function json_out($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
}

function json_in(): array
{
    $raw = file_get_contents('php://input');
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
}

// --- Auth -------------------------------------------------------------

function login_view(): void
{
    view('login', ['error' => null]);
}

function login_post(): void
{
    $input = (string)($_POST['password'] ?? '');
    if (check_password($input)) {
        session_regenerate_id(true);
        $_SESSION['authed'] = true;
        header('Location: ' . url('/'));
        exit;
    }
    sleep(1); // throttle brute force
    view('login', ['error' => 'Password salah']);
}

function logout(): void
{
    $_SESSION = [];
    session_destroy();
    header('Location: ' . url('/login'));
    exit;
}

// --- Dashboard --------------------------------------------------------

function dashboard(string $path): void
{
    $view = $_GET['view'] ?? 'dashboard';
    $folderId = isset($_GET['folder']) && $_GET['folder'] !== '' ? (int)$_GET['folder'] : null;

    // Base data shared across all views.
    $data = [
        'view'     => $view,
        'folderId' => $folderId,
        'stats'    => ['size' => total_size(), 'count' => file_count(), 'trash' => trash_count()],
        'favIds'   => favorite_ids(),
    ];

    switch ($view) {
        case 'files':
            $data['files']   = list_files($folderId);
            $data['folders'] = list_folders($folderId);
            $data['notes']   = array_map('note_view_model', list_notes($folderId));
            $data['chain']   = folder_chain($folderId);
            break;
        case 'recent':
            $data['files']   = list_recent_files(50);
            $data['folders'] = [];
            $data['notes']   = [];
            $data['chain']   = [];
            break;
        case 'shared':
            $data['files']   = [];
            $data['folders'] = [];
            $data['notes']   = [];
            $data['chain']   = [];
            $data['shares']  = list_shares();
            break;
        case 'favorites':
            $data['files']   = list_favorites();
            $data['folders'] = [];
            $data['notes']   = [];
            $data['chain']   = [];
            break;
        case 'trash':
            $data['files']   = list_trashed_files();
            $data['folders'] = [];
            $data['notes']   = [];
            $data['chain']   = [];
            break;
        default: // 'dashboard'
            $data['files']   = list_files($folderId);
            $data['folders'] = list_folders($folderId);
            $data['notes']   = array_map('note_view_model', list_notes($folderId));
            $data['chain']   = folder_chain($folderId);
            break;
    }

    view('dashboard', $data);
}

// --- File raw (inline preview, authed) -------------------------------

function file_raw(int $id): void
{
    $file = get_file($id);
    if (!$file) {
        http_response_code(404);
        echo 'Not found';
        return;
    }
    stream_file(file_path($file), $file['name'], $file['mime'], (int)$file['size']);
}

// Thumbnail (authed). Serves the generated jpg for video files.
function file_thumb(int $id): void
{
    $file = get_file($id);
    if (!$file || $file['thumb'] === null) {
        http_response_code(404);
        return;
    }
    $path = storage_dir() . '/' . $file['thumb'];
    if (!is_file($path)) {
        http_response_code(404);
        return;
    }
    header('Content-Type: image/jpeg');
    header('Cache-Control: private, max-age=86400');
    readfile($path);
}

// QR code PNG generated locally (no third-party API). Encodes arbitrary URL.
function qr_png(string $data): void
{
    if ($data === '' || !filter_var($data, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        return;
    }
    // phpqrcode is an old lib that emits PHP 8 deprecation notices; silence
    // them so they don't corrupt the PNG output stream.
    $prevEr = error_reporting(E_ERROR | E_PARSE);
    $prevDd = ini_set('display_errors', '0');
    require_once APP_ROOT . '/app/lib/phpqrcode.php';
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=86400');
    QRcode::png($data, false, QR_ECLEVEL_L, 4, 2, false);
    error_reporting($prevEr);
    ini_set('display_errors', $prevDd);
}

// Signed download (time-limited token, used by share page).
function file_download_signed(int $id, string $token): void
{
    $file = get_file($id);
    if (!$file) {
        http_response_code(404);
        echo 'Not found';
        return;
    }
    if (!verify_token((string)$file['id'], $token)) {
        http_response_code(403);
        echo 'Link expired';
        return;
    }
    header('Content-Disposition: attachment; filename="' . addslashes($file['name']) . '"');
    stream_file(file_path($file), $file['name'], $file['mime'], (int)$file['size']);
}

// --- Share page (public) ---------------------------------------------

function share_view(string $token): void
{
    $share = get_share_by_token($token);
    if (!$share) {
        http_response_code(404);
        view('error', ['message' => 'Share tidak ditemukan']);
        return;
    }
    if (share_expired($share)) {
        http_response_code(410);
        view('error', ['message' => 'Share sudah kedaluwarsa']);
        return;
    }

    $file = get_file((int)$share['file_id']);
    if (!$file) {
        http_response_code(404);
        view('error', ['message' => 'File tidak ditemukan']);
        return;
    }

    $error = null;
    $unlocked = !share_needs_password($share);

    if (share_needs_password($share) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $pw = (string)($_POST['password'] ?? '');
        if (share_check_password($share, $pw)) {
            $unlocked = true;
        } else {
            $error = 'Password salah';
            sleep(1);
        }
    }

    if ($unlocked) {
        share_increment_hits($share);
    }

    $dlToken = $unlocked ? sign_token((string)$file['id'], 300) : null;
    view('share', [
        'share'   => $share,
        'file'    => $file,
        'unlocked'=> $unlocked,
        'error'   => $error,
        'dlToken' => $dlToken,
        'dlUrl'   => $dlToken ? url('/dl/' . $file['id'] . '/' . $dlToken) : null,
        'previewUrl' => $unlocked ? url('/dl/' . $file['id'] . '/' . $dlToken) : null,
    ]);
}

// --- API (authed, JSON) ----------------------------------------------

function api(string $path): void
{
    $method = $_SERVER['REQUEST_METHOD'];
    try {
        // Versioning routes (regex-matched) handled before the static match.
        if (preg_match('#^/api/file/(\d+)/versions$#', $path, $m)) {
            $GLOBALS['__vfile'] = (int)$m[1];
            if ($method === 'GET') { api_list_versions(); return; }
            if ($method === 'POST') { api_restore_version(); return; }
        }
        if (preg_match('#^/api/version/(\d+)$#', $path, $m)) {
            $GLOBALS['__vver'] = (int)$m[1];
            if ($method === 'DELETE') { api_delete_version(); return; }
        }
        // Favorite toggle.
        if (preg_match('#^/api/favorite/(\d+)$#', $path, $m)) {
            if ($method === 'POST') { api_toggle_favorite((int)$m[1]); return; }
        }
        // Trash routes.
        if (preg_match('#^/api/trash/(\d+)/restore$#', $path, $m)) {
            if ($method === 'POST') { api_restore_from_trash((int)$m[1]); return; }
        }
        if (preg_match('#^/api/trash/(\d+)$#', $path, $m)) {
            if ($method === 'DELETE') { api_permanent_delete((int)$m[1]); return; }
        }
        match (true) {
            $path === '/api/upload' && $method === 'POST' => api_upload(),
            $path === '/api/folder' && $method === 'POST' => api_folder(),
            $path === '/api/share' && $method === 'POST' => api_share(),
            $path === '/api/note' && $method === 'POST' => api_note_create(),
            $path === '/api/trash' && $method === 'DELETE' => api_empty_trash(),
            $path === '/api/password' && $method === 'POST' => api_change_password(),
            str_starts_with($path, '/api/note/') && $method === 'PUT' => api_note_update(),
            str_starts_with($path, '/api/note/') && $method === 'DELETE' => api_note_delete(),
            str_starts_with($path, '/api/file/') && $method === 'DELETE' => api_delete_file(),
            str_starts_with($path, '/api/file/') && $method === 'PATCH' => api_move_file(),
            str_starts_with($path, '/api/share/') && $method === 'DELETE' => api_delete_share(),
            default => json_out(['error' => 'Not found'], 404),
        };
    } catch (Throwable $e) {
        json_out(['error' => $e->getMessage()], 400);
    }
}

function api_upload(): void
{
    if (upload_rate_limited()) {
        json_out(['error' => 'Terlalu banyak upload, coba lagi nanti'], 429);
        return;
    }
    $folderId = isset($_GET['folder']) && $_GET['folder'] !== '' ? (int)$_GET['folder'] : null;
    if (empty($_FILES['file'])) {
        throw new RuntimeException('No file');
    }
    // Normalize $_FILES['file'] (single or name="file[]") into a list of entries.
    $entries = normalize_files('file');
    $out = [];
    foreach ($entries as $entry) {
        $row = save_upload($entry, $folderId);
        $out[] = file_view_model($row);
    }
    json_out(['files' => $out]);
}

// Turn PHP's nested $_FILES structure into a flat list of per-file arrays.
function normalize_files(string $key): array
{
    if (!isset($_FILES[$key])) return [];
    $group = $_FILES[$key];
    // Single file: scalar values.
    if (!is_array($group['name'])) {
        return [$group];
    }
    // Multi: name is an array.
    $entries = [];
    foreach ($group['name'] as $i => $_) {
        $entries[] = [
            'name'     => $group['name'][$i],
            'type'     => $group['type'][$i],
            'tmp_name' => $group['tmp_name'][$i],
            'error'    => $group['error'][$i],
            'size'     => $group['size'][$i],
        ];
    }
    return $entries;
}

function api_folder(): void
{
    $body = json_in();
    $name = (string)($body['name'] ?? '');
    $parent = isset($body['parent']) && $body['parent'] !== '' ? (int)$body['parent'] : null;
    $id = create_folder($name, $parent);
    json_out(['id' => $id, 'name' => $name, 'parent' => $parent]);
}

function api_share(): void
{
    $body = json_in();
    $fileId = (int)($body['file_id'] ?? 0);
    $password = isset($body['password']) && $body['password'] !== '' ? (string)$body['password'] : null;
    $ttl = isset($body['ttl_hours']) && $body['ttl_hours'] > 0 ? (int)$body['ttl_hours'] : null;
    $share = create_share($fileId, $password, $ttl);
    json_out([
        'id'    => $share['id'],
        'token' => $share['token'],
        'url'   => url('/s/' . $share['token']),
        'has_password' => $share['password'] !== null,
        'expires_at' => $share['expires_at'],
    ]);
}

function api_delete_file(): void
{
    $id = (int)substr($_SERVER['PATH_INFO'] ?? '', strlen('/api/file/'));
    delete_file($id);
    json_out(['ok' => true]);
}

function api_move_file(): void
{
    $id = (int)substr($_SERVER['PATH_INFO'] ?? '', strlen('/api/file/'));
    $body = json_in();
    $folderId = isset($body['folder']) && $body['folder'] !== '' ? (int)$body['folder'] : null;
    move_file($id, $folderId);
    json_out(['ok' => true]);
}

function api_delete_share(): void
{
    $id = (int)substr($_SERVER['PATH_INFO'] ?? '', strlen('/api/share/'));
    delete_share($id);
    json_out(['ok' => true]);
}

// --- Notes ------------------------------------------------------------

function api_note_create(): void
{
    $body = json_in();
    $title = (string)($body['title'] ?? '');
    $text = (string)($body['body'] ?? '');
    $folder = isset($body['folder']) && $body['folder'] !== '' ? (int)$body['folder'] : null;
    $note = create_note($title, $text, $folder);
    json_out(note_view_model($note));
}

function api_note_update(): void
{
    $id = (int)substr($_SERVER['PATH_INFO'] ?? '', strlen('/api/note/'));
    $body = json_in();
    $title = (string)($body['title'] ?? '');
    $text = (string)($body['body'] ?? '');
    $folder = isset($body['folder']) && $body['folder'] !== '' ? (int)$body['folder'] : null;
    $note = update_note($id, $title, $text, $folder);
    if (!$note) {
        json_out(['error' => 'Not found'], 404);
        return;
    }
    json_out(note_view_model($note));
}

function api_note_delete(): void
{
    $id = (int)substr($_SERVER['PATH_INFO'] ?? '', strlen('/api/note/'));
    delete_note($id);
    json_out(['ok' => true]);
}

// --- File versioning --------------------------------------------------

function api_list_versions(): void
{
    $fileId = $GLOBALS['__vfile'] ?? 0;
    $versions = list_versions($fileId);
    json_out([
        'current' => file_view_model(get_file($fileId) ?? ['id' => $fileId, 'name' => '', 'mime' => '', 'size' => 0, 'storage_id' => '', 'created_at' => '', 'thumb' => null]),
        'versions' => array_map(function ($v) {
            return [
                'id'      => (int)$v['id'],
                'size'    => (int)$v['size'],
                'size_h'  => human_size((int)$v['size']),
                'mime'    => $v['mime'],
                'created' => $v['created_at'],
            ];
        }, $versions),
    ]);
}

function api_restore_version(): void
{
    $fileId = $GLOBALS['__vfile'] ?? 0;
    $body = json_in();
    $versionId = (int)($body['version_id'] ?? 0);
    $file = restore_version($fileId, $versionId);
    if (!$file) { json_out(['error' => 'Not found'], 404); return; }
    json_out(file_view_model($file));
}

function api_delete_version(): void
{
    $versionId = $GLOBALS['__vver'] ?? 0;
    delete_version($versionId);
    json_out(['ok' => true]);
}

function note_view_model(array $note): array
{
    $title = trim($note['title']);
    return [
        'id'      => (int)$note['id'],
        'kind'    => 'note',
        'title'   => $title !== '' ? $title : 'Catatan tanpa judul',
        'body'    => $note['body'],
        'html'    => auto_link((string)$note['body']),
        'icon'    => '📝',
        'updated' => $note['updated_at'],
        'preview' => null,
        'size_h'  => null,
    ];
}

// --- Favorites API ---------------------------------------------------

function api_toggle_favorite(int $fileId): void
{
    $isFav = toggle_favorite($fileId);
    json_out(['ok' => true, 'favorited' => $isFav]);
}

// --- Trash API -------------------------------------------------------

function api_restore_from_trash(int $id): void
{
    restore_file($id);
    json_out(['ok' => true]);
}

function api_permanent_delete(int $id): void
{
    permanent_delete_file($id);
    json_out(['ok' => true]);
}

function api_empty_trash(): void
{
    empty_trash();
    json_out(['ok' => true]);
}

function api_change_password(): void
{
    $body = json_in();
    $current = (string)($body['current_password'] ?? '');
    $new = (string)($body['new_password'] ?? '');

    if ($current === '' || $new === '') {
        json_out(['error' => 'Semua field harus diisi'], 400);
        return;
    }

    if (!check_password($current)) {
        json_out(['error' => 'Password saat ini salah'], 400);
        return;
    }

    $newHashed = password_hash($new, PASSWORD_DEFAULT);
    $configPath = APP_ROOT . '/config.php';

    if (!is_writable($configPath)) {
        json_out(['error' => 'File config.php tidak dapat ditulis oleh server'], 500);
        return;
    }

    $content = file_get_contents($configPath);
    $newContent = preg_replace_callback(
        "/('password'\s*=>\s*)(['\"]).*?\\2/s",
        function ($matches) use ($newHashed) {
            return $matches[1] . "'" . addcslashes($newHashed, "'\\") . "'";
        },
        $content
    );

    if ($newContent === null || file_put_contents($configPath, $newContent) === false) {
        json_out(['error' => 'Gagal menyimpan password baru ke config.php'], 500);
        return;
    }

    json_out(['ok' => true]);
}

// --- View model (what the UI needs) ----------------------------------

function file_view_model(array $file, array $favIds = []): array
{
    $kind = file_kind($file['mime'], $file['name']);
    return [
        'id'        => (int)$file['id'],
        'name'      => $file['name'],
        'mime'      => $file['mime'],
        'size'      => (int)$file['size'],
        'size_h'    => human_size((int)$file['size']),
        'kind'      => $kind,
        'created'   => $file['created_at'],
        'deleted'   => $file['deleted_at'] ?? null,
        'preview'   => url('/raw/' . $file['id']),
        'thumb'     => $file['thumb'] !== null ? url('/thumb/' . $file['id']) : null,
        'icon'      => icon_for($kind),
        'versions'  => version_count((int)$file['id']),
        'favorited' => in_array((int)$file['id'], $favIds, true),
    ];
}

function icon_for(string $kind): string
{
    // Returns a Lucide icon name (design-only); rendered as SVG on the client.
    return [
        'image'   => 'image',
        'video'   => 'video',
        'audio'   => 'music',
        'pdf'     => 'file-text',
        'text'    => 'file-text',
        'archive' => 'archive',
        'note'    => 'note',
    ][$kind] ?? 'file';
}

// --- View renderer ----------------------------------------------------

function view(string $name, array $data = []): void
{
    extract($data, EXTR_SKIP);
    $cfg = config();
    $appName = $cfg['app_name'];
    require APP_ROOT . "/views/{$name}.php";
}
