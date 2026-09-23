<?php

/**
 * One-off: create MySQL user with SELECT-only on all app databases on this host.
 * Run on server: php scripts/create-ai-read-db-user.php
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$prefix = env('DB_USERNAME');
if (! preg_match('/^(u\d+)_/', (string) $prefix, $m)) {
    fwrite(STDERR, "Cannot detect account prefix from DB_USERNAME\n");
    exit(1);
}
$account = $m[1];
$user = $account.'_ai_read';
$password = bin2hex(random_bytes(24));

$databases = collect(DB::select('SHOW DATABASES'))
    ->map(fn ($row) => $row->Database)
    ->filter(fn ($name) => str_starts_with($name, $account.'_'))
    ->values()
    ->all();

if ($databases === []) {
    fwrite(STDERR, "No databases found for prefix {$account}_\n");
    exit(1);
}

$hosts = ['localhost', '127.0.0.1'];
$passwordEsc = str_replace(['\\', "'"], ['\\\\', "''"], $password);

foreach ($hosts as $host) {
    $hostEsc = str_replace("'", "''", $host);
    DB::unprepared("CREATE USER IF NOT EXISTS '{$user}'@'{$hostEsc}' IDENTIFIED BY '{$passwordEsc}'");
    foreach ($databases as $db) {
        DB::unprepared("GRANT SELECT ON `{$db}`.* TO '{$user}'@'{$hostEsc}'");
    }
}

DB::statement('FLUSH PRIVILEGES');

echo json_encode([
    'host' => env('DB_HOST', 'localhost'),
    'port' => (int) env('DB_PORT', 3306),
    'username' => $user,
    'password' => $password,
    'databases' => $databases,
    'privileges' => 'SELECT only',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
