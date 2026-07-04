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

    // Versioning: if a file with the same name exists in this folder,
    // archive the old revision and replace the current row in place.
    $existing = find_file_in_folder($name, $folderId);
    if ($existing !== null) {
        archive_version($existing['id'], $existing['storage_id'], $existing['name'], $existing['mime'], (int)$existing['size']);
        // Remove old thumbnail; the new upload gets a fresh one.
        if ($existing['thumb'] !== null) {
            @unlink(storage_dir() . '/' . $existing['thumb']);
        }
        $stmt = $pdo->prepare(
            'UPDATE files SET storage_id = ?, mime = ?, size = ?, thumb = ? WHERE id = ?'
        );
        $stmt->execute([$sid, $mime, $file['size'], $thumb, $existing['id']]);
        return get_file((int)$existing['id']) ?? throw new RuntimeException('Update failed');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO files (storage_id, name, mime, size, folder_id, thumb) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$sid, $name, $mime, $file['size'], $folderId, $thumb]);
    $id = (int)$pdo->lastInsertId();
    return get_file($id) ?? throw new RuntimeException('Insert failed');
}

// Find an existing file row by name within a folder (for versioning).
function find_file_in_folder(string $name, ?int $folderId): ?array
{
    $pdo = db(config()['db_path']);
    if ($folderId === null) {
        $stmt = $pdo->prepare('SELECT * FROM files WHERE name = ? AND folder_id IS NULL LIMIT 1');
        $stmt->bindValue(1, $name);
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare('SELECT * FROM files WHERE name = ? AND folder_id = ? LIMIT 1');
        $stmt->execute([$name, $folderId]);
    }
    $row = $stmt->fetch();
    return $row ?: null;
}

// Archive the current revision of a file into file_versions.
function archive_version(int $fileId, string $storageId, string $name, string $mime, int $size): void
{
    db(config()['db_path'])
        ->prepare('INSERT INTO file_versions (file_id, storage_id, name, mime, size) VALUES (?, ?, ?, ?, ?)')
        ->execute([$fileId, $storageId, $name, $mime, $size]);
}

function list_versions(int $fileId): array
{
    $stmt = db(config()['db_path'])->prepare('SELECT * FROM file_versions WHERE file_id = ? ORDER BY created_at DESC');
    $stmt->execute([$fileId]);
    return $stmt->fetchAll();
}

function version_count(int $fileId): int
{
    $stmt = db(config()['db_path'])->prepare('SELECT COUNT(*) FROM file_versions WHERE file_id = ?');
    $stmt->execute([$fileId]);
    return (int)$stmt->fetchColumn();
}

// Restore an archived revision: archive the current, then promote the old
// revision's storage_id/mime/size into the current row. Returns updated file.
function restore_version(int $fileId, int $versionId): ?array
{
    $pdo = db(config()['db_path']);
    $file = get_file($fileId);
    if (!$file) return null;
    $stmt = $pdo->prepare('SELECT * FROM file_versions WHERE id = ? AND file_id = ?');
    $stmt->execute([$versionId, $fileId]);
    $v = $stmt->fetch();
    if (!$v) return null;

    // Archive current, delete the restored version row, swap in the old blob.
    archive_version($fileId, $file['storage_id'], $file['name'], $file['mime'], (int)$file['size']);
    $pdo->prepare('DELETE FROM file_versions WHERE id = ?')->execute([$versionId]);
    $pdo->prepare('UPDATE files SET storage_id = ?, mime = ?, size = ? WHERE id = ?')
        ->execute([$v['storage_id'], $v['mime'], $v['size'], $fileId]);
    return get_file($fileId);
}

// Delete a single archived version (and its blob on disk).
function delete_version(int $versionId): void
{
    $pdo = db(config()['db_path']);
    $stmt = $pdo->prepare('SELECT * FROM file_versions WHERE id = ?');
    $stmt->execute([$versionId]);
    $v = $stmt->fetch();
    if (!$v) return;
    $path = storage_dir() . '/' . $v['storage_id'];
    if (is_file($path)) @unlink($path);
    $pdo->prepare('DELETE FROM file_versions WHERE id = ?')->execute([$versionId]);
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
        'SELECT * FROM files WHERE folder_id IS ? AND deleted_at IS NULL ORDER BY created_at DESC'
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

// Soft-delete: moves file to trash instead of permanent deletion.
function delete_file(int $id): void
{
    soft_delete_file($id);
}

function soft_delete_file(int $id): void
{
    db(config()['db_path'])
        ->prepare("UPDATE files SET deleted_at = datetime('now') WHERE id = ?")
        ->execute([$id]);
}

function restore_file(int $id): void
{
    db(config()['db_path'])
        ->prepare('UPDATE files SET deleted_at = NULL WHERE id = ?')
        ->execute([$id]);
}

// Permanent delete: removes file blob, versions, and DB row.
function permanent_delete_file(int $id): void
{
    $file = get_file($id);
    if (!$file) return;
    $path = file_path($file);
    if (is_file($path)) @unlink($path);
    if ($file['thumb'] !== null) {
        $tp = storage_dir() . '/' . $file['thumb'];
        if (is_file($tp)) @unlink($tp);
    }
    // Remove archived version blobs too (CASCADE deletes the rows).
    foreach (list_versions($id) as $v) {
        $vp = storage_dir() . '/' . $v['storage_id'];
        if (is_file($vp)) @unlink($vp);
    }
    db(config()['db_path'])->prepare('DELETE FROM files WHERE id = ?')->execute([$id]);
}

function empty_trash(): void
{
    $pdo = db(config()['db_path']);
    $trashed = $pdo->query('SELECT id FROM files WHERE deleted_at IS NOT NULL')->fetchAll();
    foreach ($trashed as $row) {
        permanent_delete_file((int)$row['id']);
    }
}

function move_file(int $id, ?int $folderId): void
{
    db(config()['db_path'])
        ->prepare('UPDATE files SET folder_id = ? WHERE id = ?')
        ->execute([$folderId, $id]);
}

function total_size(): int
{
    return (int)db(config()['db_path'])->query('SELECT COALESCE(SUM(size),0) FROM files WHERE deleted_at IS NULL')->fetchColumn();
}

function file_count(): int
{
    return (int)db(config()['db_path'])->query('SELECT COUNT(*) FROM files WHERE deleted_at IS NULL')->fetchColumn();
}

// --- Recent files (across all folders, non-deleted) ---

function list_recent_files(int $limit = 50): array
{
    $stmt = db(config()['db_path'])->prepare(
        'SELECT * FROM files WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT ?'
    );
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

// --- Trash ---

function list_trashed_files(): array
{
    return db(config()['db_path'])
        ->query('SELECT * FROM files WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC')
        ->fetchAll();
}

function trash_count(): int
{
    return (int)db(config()['db_path'])->query('SELECT COUNT(*) FROM files WHERE deleted_at IS NOT NULL')->fetchColumn();
}

// --- Favorites ---

function toggle_favorite(int $fileId): bool
{
    $pdo = db(config()['db_path']);
    $stmt = $pdo->prepare('SELECT id FROM favorites WHERE file_id = ?');
    $stmt->execute([$fileId]);
    if ($stmt->fetch()) {
        $pdo->prepare('DELETE FROM favorites WHERE file_id = ?')->execute([$fileId]);
        return false; // unfavorited
    }
    $pdo->prepare('INSERT INTO favorites (file_id) VALUES (?)')->execute([$fileId]);
    return true; // favorited
}

function list_favorites(): array
{
    return db(config()['db_path'])
        ->query('SELECT f.* FROM files f JOIN favorites fv ON fv.file_id = f.id WHERE f.deleted_at IS NULL ORDER BY fv.created_at DESC')
        ->fetchAll();
}

function is_favorited(int $fileId): bool
{
    $stmt = db(config()['db_path'])->prepare('SELECT 1 FROM favorites WHERE file_id = ?');
    $stmt->execute([$fileId]);
    return (bool)$stmt->fetch();
}

function favorite_ids(): array
{
    return db(config()['db_path'])
        ->query('SELECT file_id FROM favorites')
        ->fetchAll(PDO::FETCH_COLUMN);
}
