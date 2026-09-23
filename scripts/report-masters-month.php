<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\City;
use App\Services\CityReportService;

$from = now()->startOfMonth();
$to = now()->endOfDay();
$service = app(CityReportService::class);

$cityIds = City::query()
    ->where('is_active', true)
    ->where('city_type', 'city')
    ->pluck('city_id')
    ->all();

$masters = $service->buildMasterRows($cityIds, $from, $to, false)
    ->filter(fn ($row) => ($row['completed'] ?? 0) > 0)
    ->values();

echo json_encode([
    'period_label' => $from->format('d.m.Y') . ' — ' . $to->format('d.m.Y'),
    'masters' => $masters,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
