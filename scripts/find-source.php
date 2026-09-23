<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$needle = $argv[1] ?? 'удал';
$rows = App\Models\Source::query()
    ->whereRaw('LOWER(source_name) LIKE ?', ['%'.mb_strtolower($needle).'%'])
    ->orderByDesc('source_id')
    ->get(['source_id', 'source_name', 'superpart_local_source_id', 'is_active', 'available_for_superpart', 'source_kind']);

echo json_encode($rows->toArray(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;
