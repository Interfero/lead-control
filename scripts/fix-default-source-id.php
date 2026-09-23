<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Source;

$fallback = (int) (Source::query()
    ->where('is_active', true)
    ->where('available_for_superpart', true)
    ->whereNotNull('superpart_local_source_id')
    ->orderBy('source_id')
    ->value('source_id') ?? 0);

if ($fallback < 1) {
    echo "No active PP source in CRM for default.\n";
    exit(1);
}

$envPath = dirname(__DIR__).'/.env';
$lines = file($envPath, FILE_IGNORE_NEW_LINES);
$key = 'SUPERPART_DEFAULT_SOURCE_ID';
$found = false;

foreach ($lines as $i => $line) {
    if (str_starts_with($line, $key.'=')) {
        $lines[$i] = $key.'='.$fallback;
        $found = true;
        break;
    }
}

if (! $found) {
    $lines[] = $key.'='.$fallback;
}

file_put_contents($envPath, implode(PHP_EOL, $lines).PHP_EOL);

echo "SUPERPART_DEFAULT_SOURCE_ID={$fallback}\n";
