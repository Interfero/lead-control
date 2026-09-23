<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\PartnerApiService;
use Illuminate\Console\Command;

/**
 * Догон CRM→SP: заявки с SP-источником за последние N часов.
 * Страховка, если какой-то UI/API путь забыл webhook при создании.
 */
class CatchUpSuperpartOrdersCommand extends Command
{
    protected $signature = 'superpart:catch-up-orders
                            {--hours=72 : Окно по order_created_at (часы)}
                            {--limit=150 : Максимум заказов за прогон}
                            {--dry-run : Только показать}';

    protected $description = 'Догон partner-order-created (+ charge если уже completed) для недавних SP-заказов';

    public function handle(PartnerApiService $partnerApi): int
    {
        if (! $partnerApi->isConfigured()) {
            $this->error('SuperPart не настроен');

            return self::FAILURE;
        }

        $hours = max(1, (int) $this->option('hours'));
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $since = now()->subHours($hours);

        $orders = Order::query()
            ->with(['source', 'address.city', 'persons.phones'])
            ->whereNotNull('source_id')
            ->where('order_created_at', '>=', $since)
            ->whereHas('source', function ($q) {
                $q->where('available_for_superpart', true)
                    ->whereNotNull('superpart_partner_id')
                    ->where('superpart_partner_id', '>', 0);
            })
            ->orderByDesc('order_id')
            ->limit($limit)
            ->get();

        $this->info("Кандидаты: {$orders->count()} (с {$since->toDateTimeString()}, limit {$limit})");

        $ok = 0;
        $fail = 0;
        foreach ($orders as $order) {
            $line = "#{$order->order_id} status={$order->order_status} source={$order->source_id}";
            if ($dryRun) {
                $this->line("[dry-run] {$line}");
                continue;
            }

            $charge = (bool) $order->order_closed_at
                && in_array((string) $order->order_status, ['completed', 'waiting_payment'], true);
            $result = $partnerApi->syncOrderToSuperpart($order, $charge);
            if ($result['created'] || ($result['errors'] === [] && $result['partner_user_id'])) {
                // created=false может быть HTTP duplicate — syncOrderToSuperpart marks created from notify bool
                if ($result['errors'] === [] || $result['created'] || $result['charged']) {
                    $ok++;
                    $this->line('✓ '.$line.' created='.($result['created'] ? '1' : '0').' charged='.($result['charged'] ? '1' : '0'));
                    continue;
                }
            }
            $fail++;
            $this->warn('✗ '.$line.': '.implode('; ', $result['errors'] ?: ['unknown']));
        }

        if (! $dryRun) {
            $this->info("Итого ok≈{$ok} fail≈{$fail}");
        }

        return self::SUCCESS;
    }
}
