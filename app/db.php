<?php
declare(strict_types=1);

// SQLite access. File-based DB — enough for single user, zero config.
// ponytail: switch to MySQL/PDO other DSN if shared host needs it.

function db(string $dbPath): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA foreign_keys = ON');
    return $pdo;
}

function db_init(string $dbPath): void
{
    $pdo = db($dbPath);
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS files (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            storage_id  TEXT    NOT NULL UNIQUE,
            name        TEXT    NOT NULL,
            mime        TEXT    NOT NULL,
            size        INTEGER NOT NULL,
            folder_id   INTEGER NULL,
            created_at  TEXT    NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE SET NULL
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS folders (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            name        TEXT    NOT NULL,
            parent_id   INTEGER NULL,
            created_at  TEXT    NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (parent_id) REFERENCES folders(id) ON DELETE CASCADE
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS shares (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            token       TEXT    NOT NULL UNIQUE,
            file_id     INTEGER NOT NULL,
            password    TEXT    NULL,
            expires_at  TEXT    NULL,
            hits        INTEGER NOT NULL DEFAULT 0,
            created_at  TEXT    NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_files_folder ON files(folder_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_folders_parent ON folders(parent_id)");

    // Migrations: add columns if missing (idempotent, works on existing DBs).
    $cols = $pdo->query('PRAGMA table_info(files)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('thumb', $cols, true)) {
        $pdo->exec('ALTER TABLE files ADD COLUMN thumb TEXT NULL');
    }
}
