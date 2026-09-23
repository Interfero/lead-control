<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Source;

$rows = Source::query()
    ->whereNull('superpart_local_source_id')
    ->orderBy('source_id')
    ->get();

if ($rows->isEmpty()) {
    echo "No legacy CRM sources to deactivate.\n";
    exit(0);
}

foreach ($rows as $source) {
    $source->is_active = false;
    $source->available_for_superpart = false;
    $source->save();

    echo "deactivated crm source_id={$source->source_id} name={$source->source_name}\n";
}

echo "Done: {$rows->count()} legacy sources deactivated.\n";
