<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

class FixZeroPaidCompletedOrdersCommand extends Command
{
    protected $signature = 'orders:fix-zero-paid-rejected {--dry-run : Только показать количество}';

    protected $description = 'Статус «Готов» при оплате 0 → «Отказ»';

    public function handle(): int
    {
        $query = Order::query()
            ->where('order_status', 'completed')
            ->where(function ($q) {
                $q->whereNull('amount_paid')->orWhere('amount_paid', '<=', 0);
            })
            ->whereNotNull('order_closed_at');

        $count = (clone $query)->count();
        $this->info("Заказов «Готов» с оплатой 0: {$count}");

        if ($this->option('dry-run') || $count === 0) {
            return self::SUCCESS;
        }

        $updated = $query->update(['order_status' => 'rejected']);
        $this->info("Обновлено статусов на «Отказ»: {$updated}");

        return self::SUCCESS;
    }
}
