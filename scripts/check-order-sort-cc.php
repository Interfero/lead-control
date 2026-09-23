<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$visible = [
    'callback', 'not_processed', 'pending',
    'on_way', 'in_progress', 'in_progress_sd', 'rejected',
    'completed', 'cancelled_cc', 'cancelled_city',
];
$closed = ['completed', 'cancelled_cc', 'cancelled_city'];

$orders = App\Models\Order::query()
    ->whereIn('order_status', $visible)
    ->whereNotIn('order_status', $closed)
    ->orderByListDefault()
    ->limit(20)
    ->get(['order_id', 'order_status', 'datetime_order']);

echo "=== call_center view (no closed) ===\n";
foreach ($orders as $o) {
    echo $o->order_id.' '.$o->order_status.' '.($o->datetime_order?->format('Y-m-d H:i') ?? 'null').PHP_EOL;
}

$pending = App\Models\Order::query()->where('order_status', 'pending')->whereNull('order_closed_at')->count();
echo "pending count: $pending\n";
