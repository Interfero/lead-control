<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\City;
use App\Services\CityReportService;

$from = now()->startOfMonth();
$to = now()->endOfDay();
$service = app(CityReportService::class);

$cities = City::query()
    ->where('is_active', true)
    ->where('city_type', 'city')
    ->orderBy('city_name')
    ->get();

$rows = [];
foreach ($cities as $city) {
    $row = $service->buildClosedOrdersRow($city->city_id, $from, $to);
    if (($row['closed_total'] ?? 0) > 0 || ($row['accepted_count'] ?? 0) > 0) {
        $rows[] = $row;
    }
}

$totals = $service->sumClosedOrdersRows($rows);

echo json_encode([
    'period_from' => $from->format('Y-m-d'),
    'period_to' => $to->format('Y-m-d'),
    'period_label' => $from->format('d.m.Y') . ' — ' . $to->format('d.m.Y'),
    'rows' => $rows,
    'totals' => $totals,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
