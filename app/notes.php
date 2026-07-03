<?php
declare(strict_types=1);

// Notes CRUD. Stored as title + body text; URLs are auto-linked on display.

function create_note(string $title, string $body, ?int $folderId): array
{
    $pdo = db(config()['db_path']);
    $stmt = $pdo->prepare(
        'INSERT INTO notes (title, body, folder_id) VALUES (?, ?, ?)'
    );
    $stmt->execute([substr(trim($title), 0, 200), $body, $folderId]);
    $id = (int)$pdo->lastInsertId();
    return get_note($id);
}

function get_note(int $id): ?array
{
    $stmt = db(config()['db_path'])->prepare('SELECT * FROM notes WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function list_notes(?int $folderId): array
{
    $stmt = db(config()['db_path'])->prepare(
        'SELECT * FROM notes WHERE folder_id IS ? ORDER BY updated_at DESC'
    );
    if ($folderId === null) {
        $stmt->bindValue(1, null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(1, $folderId, PDO::PARAM_INT);
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

function update_note(int $id, string $title, string $body, ?int $folderId): ?array
{
    $pdo = db(config()['db_path']);
    $stmt = $pdo->prepare(
        'UPDATE notes SET title = ?, body = ?, folder_id = ?, updated_at = datetime(\'now\') WHERE id = ?'
    );
    $stmt->execute([substr(trim($title), 0, 200), $body, $folderId, $id]);
    return get_note($id);
}

function delete_note(int $id): void
{
    db(config()['db_path'])->prepare('DELETE FROM notes WHERE id = ?')->execute([$id]);
}
