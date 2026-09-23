<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Source;
use App\Services\PartnerApiService;
use Illuminate\Console\Command;

class SyncSuperpartCommand extends Command
{
    protected $signature = 'superpart:sync
                            {--sources : Только источники}
                            {--orders : Только заказы}
                            {--order-id=* : Конкретные order_id CRM}
                            {--closed-only : Только проведённые заказы (completed)}
                            {--dry-run : Показать план без отправки}';

    protected $description = 'Синхронизация источников и заказов CRM → SuperPart (webhook)';

    public function handle(PartnerApiService $partnerApi): int
    {
        if (! $partnerApi->isConfigured()) {
            $this->error('SuperPart не настроен: проверьте SUPERPART_BASE_URL, SUPERPART_API_KEY, SUPERPART_API_SECRET в .env');

            return self::FAILURE;
        }

        $onlySources = (bool) $this->option('sources') && ! $this->option('orders');
        $onlyOrders = (bool) $this->option('orders') && ! $this->option('sources');
        $syncSources = ! $onlyOrders;
        $syncOrders = ! $onlySources;
        $dryRun = (bool) $this->option('dry-run');

        if ($syncSources) {
            $this->syncSources($partnerApi, $dryRun);
        }

        if ($syncOrders) {
            $this->syncOrders($partnerApi, $dryRun);
        }

        return self::SUCCESS;
    }

    private function syncSources(PartnerApiService $partnerApi, bool $dryRun): void
    {
        $sources = Source::query()
            ->with('city')
            ->where('available_for_superpart', true)
            ->where('is_active', true)
            ->orderBy('source_id')
            ->get();

        $this->info("Источники SuperPart: {$sources->count()}");

        foreach ($sources as $source) {
            $line = "  #{$source->source_id} {$source->source_name} → partner {$source->superpart_partner_id}";
            if ($dryRun) {
                $this->line("[dry-run] {$line}");
                continue;
            }

            $ok = $partnerApi->notifyReferenceSourceUpsert($source);
            $this->line(($ok ? '✓' : '✗').$line);
        }
    }

    private function syncOrders(PartnerApiService $partnerApi, bool $dryRun): void
    {
        $orderIds = array_filter(array_map('intval', (array) $this->option('order-id')));

        $query = Order::query()
            ->with(['source', 'address.city', 'persons.phones'])
            ->whereNotNull('source_id')
            ->whereHas('source', function ($q) {
                $q->where('available_for_superpart', true)
                    ->whereNotNull('superpart_partner_id')
                    ->where('superpart_partner_id', '>', 0);
            })
            ->orderBy('order_id');

        if ($orderIds !== []) {
            $query->whereIn('order_id', $orderIds);
        }

        if ($this->option('closed-only')) {
            $query->where('order_status', 'completed')->whereNotNull('order_closed_at');
        }

        $orders = $query->get();
        $this->info("Заказы для SuperPart: {$orders->count()}");

        $stats = ['ok' => 0, 'partial' => 0, 'skip' => 0, 'fail' => 0];

        foreach ($orders as $order) {
            $partnerId = $order->source?->superpart_partner_id;
            $line = "#{$order->order_id} source={$order->source_id} partner={$partnerId} status={$order->order_status}";

            if ($dryRun) {
                $this->line("[dry-run] {$line}");
                continue;
            }

            $result = $partnerApi->syncOrderToSuperpart($order);

            if ($result['errors'] === []) {
                $stats['ok']++;
                $this->line("✓ {$line} → created=".($result['created'] ? '1' : '0').' charged='.($result['charged'] ? '1' : '0'));
            } elseif ($result['created'] || $result['charged']) {
                $stats['partial']++;
                $this->warn("~ {$line}: ".implode('; ', $result['errors']));
            } elseif ($result['partner_user_id'] === null) {
                $stats['skip']++;
                $this->line("- skip {$line}");
            } else {
                $stats['fail']++;
                $this->error("✗ {$line}: ".implode('; ', $result['errors']));
            }

            usleep(1_100_000);
        }

        if (! $dryRun) {
            $this->info("Итого: ok={$stats['ok']}, partial={$stats['partial']}, skip={$stats['skip']}, fail={$stats['fail']}");
        }
    }
}
