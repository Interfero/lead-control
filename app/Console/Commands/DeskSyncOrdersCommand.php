<?php

namespace App\Console\Commands;

use App\Desk\Services\DeskSyncService;
use App\Models\CrmConnection;
use Illuminate\Console\Command;
use Throwable;

class DeskSyncOrdersCommand extends Command
{
    protected $signature = 'desk:sync {--crm= : ID подключения} {--full : Полная пересинхронизация}';

    protected $description = 'Синхронизация заказов в Единое окно (orders_cache)';

    public function handle(DeskSyncService $sync): int
    {
        $query = CrmConnection::query()->whereIn('status', ['active', 'error']);

        if ($this->option('crm')) {
            $query->whereKey((int) $this->option('crm'));
        }

        $connections = $query->get();
        if ($connections->isEmpty()) {
            $this->warn('Нет активных подключений CRM');

            return self::SUCCESS;
        }

        $full = (bool) $this->option('full');
        $failed = 0;

        foreach ($connections as $connection) {
            if ($connection->type === 'crm2_http') {
                $this->line("Skip {$connection->name} (CRM2 stub)");
                continue;
            }

            $this->info("Sync {$connection->name}…");
            try {
                $result = $sync->syncConnection($connection, $full);
                $this->line("  upserted={$result['upserted']} removed={$result['removed']}");
            } catch (Throwable $e) {
                $failed++;
                $this->error('  '.$e->getMessage());
            }
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
