<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$orders = App\Models\Order::query()
    ->whereNull('order_closed_at')
    ->whereNotIn('order_status', ['completed', 'cancelled_cc', 'cancelled_city'])
    ->orderByListDefault()
    ->limit(20)
    ->get(['order_id', 'order_status', 'datetime_order']);

foreach ($orders as $o) {
    echo $o->order_id.' '.$o->order_status.' '.($o->datetime_order?->format('Y-m-d H:i') ?? 'null').PHP_EOL;
}
