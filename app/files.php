<?php
declare(strict_types=1);

// File + folder operations.

function storage_dir(): string
{
    $path = config()['storage_path'];
    if (!is_dir($path)) {
        mkdir($path, 0700, true);
    }
    return $path;
}

// Save an uploaded file (from $_FILES entry). Returns the new file row.
function save_upload(array $file, ?int $folderId): array
{
    $cfg = config();
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload error: ' . $file['error']);
    }
    if ($file['size'] > $cfg['max_upload']) {
        throw new RuntimeException('File too large');
    }

    $name = trim(basename($file['name']));
    if ($name === '' || str_contains($name, '/') || str_contains($name, "\\")) {
        throw new RuntimeException('Invalid filename');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = (string)finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if ($mime === '') $mime = 'application/octet-stream';

    $ext = pathinfo($name, PATHINFO_EXTENSION);
    $sid = storage_id($ext);
    $dest = storage_dir() . '/' . $sid;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Failed to move uploaded file');
    }

    // Best-effort video thumbnail. Skipped silently if ffmpeg absent
    // (common on shared/cPanel hosting) — UI falls back to an icon.
    $thumb = generate_thumb($dest, $mime);

    $pdo = db($cfg['db_path']);
    $stmt = $pdo->prepare(
        'INSERT INTO files (storage_id, name, mime, size, folder_id, thumb) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$sid, $name, $mime, $file['size'], $folderId, $thumb]);
    $id = (int)$pdo->lastInsertId();
    return get_file($id) ?? throw new RuntimeException('Insert failed');
}

// Path to a file's thumbnail (jpg next to it in storage).
function thumb_path(array $file): string
{
    return storage_dir() . '/' . $file['storage_id'] . '.thumb.jpg';
}

// Generate a thumbnail for video files via ffmpeg. Returns the thumb storage_id
// name (relative to storage dir) on success, null otherwise.
function generate_thumb(string $filePath, string $mime): ?string
{
    if (!str_starts_with($mime, 'video/')) {
        return null;
    }
    $ffmpeg = trim((string)shell_exec('command -v ffmpeg 2>/dev/null'));
    if ($ffmpeg === '' || !is_executable($ffmpeg)) {
        return null;
    }
    $base = basename($filePath);
    $out = substr($base, 0, (int)strrpos($base, '.')) . '.thumb.jpg';
    $outPath = dirname($filePath) . '/' . $out;

    // Grab frame at ~1s, scale to 480px wide, overwrite. Errors suppressed.
    $cmd = sprintf(
        '%s -y -ss 1 -i %s -vframes 1 -vf "scale=480:-1" %s 2>&1',
        escapeshellarg($ffmpeg),
        escapeshellarg($filePath),
        escapeshellarg($outPath)
    );
    @shell_exec($cmd);
    return is_file($outPath) ? $out : null;
}

function get_file(int $id): ?array
{
    $stmt = db(config()['db_path'])->prepare('SELECT * FROM files WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function file_path(array $file): string
{
    return storage_dir() . '/' . $file['storage_id'];
}

function list_files(?int $folderId): array
{
    $stmt = db(config()['db_path'])->prepare(
        'SELECT * FROM files WHERE folder_id IS ? ORDER BY created_at DESC'
    );
    // SQLite needs explicit NULL handling
    if ($folderId === null) {
        $stmt->bindValue(1, null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(1, $folderId, PDO::PARAM_INT);
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

function list_folders(?int $parent): array
{
    $stmt = db(config()['db_path'])->prepare(
        'SELECT * FROM folders WHERE parent_id IS ? ORDER BY name ASC'
    );
    if ($parent === null) {
        $stmt->bindValue(1, null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(1, $parent, PDO::PARAM_INT);
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

function create_folder(string $name, ?int $parent): int
{
    $name = trim($name);
    if ($name === '' || str_contains($name, '/')) {
        throw new RuntimeException('Invalid folder name');
    }
    $stmt = db(config()['db_path'])->prepare(
        'INSERT INTO folders (name, parent_id) VALUES (?, ?)'
    );
    $stmt->execute([$name, $parent]);
    return (int)db(config()['db_path'])->lastInsertId();
}

// Breadcrumb chain from a folder id up to root.
function folder_chain(?int $folderId): array
{
    $chain = [];
    $pdo = db(config()['db_path']);
    $seen = [];
    while ($folderId !== null) {
        if (isset($seen[$folderId])) break; // cycle guard
        $seen[$folderId] = true;
        $stmt = $pdo->prepare('SELECT id, name, parent_id FROM folders WHERE id = ?');
        $stmt->execute([$folderId]);
        $f = $stmt->fetch();
        if (!$f) break;
        $chain[] = $f;
        $folderId = $f['parent_id'] !== null ? (int)$f['parent_id'] : null;
    }
    return array_reverse($chain);
}

function delete_file(int $id): void
{
    $file = get_file($id);
    if (!$file) return;
    $path = file_path($file);
    if (is_file($path)) @unlink($path);
    if ($file['thumb'] !== null) {
        $tp = storage_dir() . '/' . $file['thumb'];
        if (is_file($tp)) @unlink($tp);
    }
    db(config()['db_path'])->prepare('DELETE FROM files WHERE id = ?')->execute([$id]);
}

function move_file(int $id, ?int $folderId): void
{
    db(config()['db_path'])
        ->prepare('UPDATE files SET folder_id = ? WHERE id = ?')
        ->execute([$folderId, $id]);
}

function total_size(): int
{
    return (int)db(config()['db_path'])->query('SELECT COALESCE(SUM(size),0) FROM files')->fetchColumn();
}

function file_count(): int
{
    return (int)db(config()['db_path'])->query('SELECT COUNT(*) FROM files')->fetchColumn();
}
