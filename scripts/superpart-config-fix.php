<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Source;
use App\Models\User;

$authorId = (int) (User::query()->orderBy('user_id')->value('user_id') ?? 0);
$sourceId = (int) (Source::query()
    ->where('is_active', true)
    ->where('available_for_superpart', true)
    ->orderBy('source_id')
    ->value('source_id') ?? 0);

if ($authorId < 1 || $sourceId < 1) {
    echo "Cannot resolve author or default source.\n";
    exit(1);
}

$envPath = dirname(__DIR__).'/.env';
$lines = file($envPath, FILE_IGNORE_NEW_LINES);
if ($lines === false) {
    echo ".env not readable\n";
    exit(1);
}

$keys = [
    'SUPERPART_ORDER_AUTHOR_USER_ID' => (string) $authorId,
    'SUPERPART_DEFAULT_SOURCE_ID' => (string) $sourceId,
];

$updated = [];
foreach ($keys as $key => $value) {
    $found = false;
    foreach ($lines as $i => $line) {
        if (str_starts_with($line, $key.'=')) {
            $lines[$i] = $key.'='.$value;
            $found = true;
            $updated[] = $key;
            break;
        }
    }
    if (! $found) {
        $lines[] = $key.'='.$value;
        $updated[] = $key.' (added)';
    }
}

file_put_contents($envPath, implode(PHP_EOL, $lines).PHP_EOL);

echo "Updated: ".implode(', ', $updated)."\n";
echo "SUPERPART_ORDER_AUTHOR_USER_ID={$authorId}\n";
echo "SUPERPART_DEFAULT_SOURCE_ID={$sourceId}\n";
