<?php
declare(strict_types=1);

// Share link operations. Links are token-based, optionally password + expiry.

function create_share(int $fileId, ?string $password, ?int $ttlHours): array
{
    $token = bin2hex(random_bytes(8));
    $expires = $ttlHours !== null ? gmdate('Y-m-d H:i:s', time() + $ttlHours * 3600) : null;
    $hash = $password !== null ? password_hash($password, PASSWORD_DEFAULT) : null;

    $pdo = db(config()['db_path']);
    $stmt = $pdo->prepare(
        'INSERT INTO shares (token, file_id, password, expires_at) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$token, $fileId, $hash, $expires]);
    $id = (int)$pdo->lastInsertId();
    return get_share($id);
}

function get_share_by_token(string $token): ?array
{
    $stmt = db(config()['db_path'])->prepare('SELECT * FROM shares WHERE token = ?');
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_share(int $id): ?array
{
    $stmt = db(config()['db_path'])->prepare('SELECT * FROM shares WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function list_shares(): array
{
    return db(config()['db_path'])
        ->query('SELECT s.*, f.name AS file_name FROM shares s JOIN files f ON f.id = s.file_id ORDER BY s.created_at DESC')
        ->fetchAll();
}

function share_expired(array $share): bool
{
    return $share['expires_at'] !== null && strtotime($share['expires_at'] . ' UTC') < time();
}

function share_needs_password(array $share): bool
{
    return $share['password'] !== null;
}

function share_check_password(array $share, string $input): bool
{
    return password_verify($input, $share['password']);
}

function share_increment_hits(array $share): void
{
    db(config()['db_path'])
        ->prepare('UPDATE shares SET hits = hits + 1 WHERE id = ?')
        ->execute([$share['id']]);
}

function delete_share(int $id): void
{
    db(config()['db_path'])->prepare('DELETE FROM shares WHERE id = ?')->execute([$id]);
}

function list_shares_for_file(int $fileId): array
{
    $stmt = db(config()['db_path'])->prepare('SELECT * FROM shares WHERE file_id = ? ORDER BY created_at DESC');
    $stmt->execute([$fileId]);
    return $stmt->fetchAll();
}
