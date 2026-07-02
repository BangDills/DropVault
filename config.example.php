<?php
// Copy this file to config.php and fill in real values. config.php is gitignored.

return [
    'password'        => 'change-me-please',
    'secret'          => 'change-this-to-a-long-random-string',
    'storage_path'    => dirname(__DIR__) . '/storage/uploads',
    'db_path'         => __DIR__ . '/data.sqlite',
    'max_upload'      => 512 * 1024 * 1024, // 512 MiB
    'share_purge_after' => 0,
    'app_name'        => 'Celiuz Vault',
];
