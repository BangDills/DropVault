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

    $pdo = db($cfg['db_path']);
    $stmt = $pdo->prepare(
        'INSERT INTO files (storage_id, name, mime, size, folder_id) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$sid, $name, $mime, $file['size'], $folderId]);
    $id = (int)$pdo->lastInsertId();
    return get_file($id) ?? throw new RuntimeException('Insert failed');
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
