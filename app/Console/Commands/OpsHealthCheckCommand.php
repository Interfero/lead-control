<?php

namespace App\Console\Commands;

use App\Services\OpsAlertService;
use App\Services\OpsHealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class OpsHealthCheckCommand extends Command
{
    protected $signature = 'ops:health-check {--json : Вывод JSON}';

    protected $description = 'Проверка health (БД, диск, очередь, stale-срезы) для cron/мониторинга';

    public function handle(OpsHealthService $health, OpsAlertService $alerts): int
    {
        $snapshot = $health->snapshot();

        if ($this->option('json')) {
            $this->line(json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $this->info('status='.$snapshot['status']);
            foreach ($snapshot['checks'] as $name => $check) {
                $mark = ($check['ok'] ?? false) ? 'OK' : 'FAIL';
                $this->line("  [{$mark}] {$name}: ".json_encode($check, JSON_UNESCAPED_UNICODE));
            }
        }

        if (! $snapshot['ok']) {
            Log::channel('single')->warning('ops:health-check degraded', $snapshot);
            $this->error('Health degraded — см. storage/logs/laravel.log');
            $alerts->notify(
                '[Lead Control] health degraded',
                'status='.$snapshot['status']."\n".json_encode($snapshot['checks'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            );

            return self::FAILURE;
        }

        Log::channel('single')->info('ops:health-check ok', [
            'queue_pending' => $snapshot['checks']['queue']['pending'] ?? null,
            'stale' => $snapshot['checks']['report_slices']['stale'] ?? null,
        ]);

        return self::SUCCESS;
    }
}
