<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Services\ReportCityDailyService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RebuildReportCityDailyCommand extends Command
{
    protected $signature = 'reports:rebuild-city-daily
                            {--from= : Дата начала Y-m-d (по умолчанию начало прошлого месяца)}
                            {--to= : Дата конца Y-m-d (по умолчанию сегодня)}
                            {--city=* : city_id (по умолчанию все активные города)}
                            {--stale : Только помеченные stale}
                            {--limit= : Лимит stale-строк}';

    protected $description = 'Пересчёт материализованных срезов report_city_daily';

    public function handle(ReportCityDailyService $service): int
    {
        if (! $service->tableReady()) {
            $this->error('Таблица report_city_daily не найдена. Сначала migrate.');

            return self::FAILURE;
        }

        if ($this->option('stale')) {
            $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
            $n = $service->rebuildStale($limit);
            $this->info("Пересчитано stale: {$n}");

            return self::SUCCESS;
        }

        $to = $this->option('to')
            ? Carbon::parse($this->option('to'))->startOfDay()
            : Carbon::today();
        $from = $this->option('from')
            ? Carbon::parse($this->option('from'))->startOfDay()
            : $to->copy()->subMonth()->startOfMonth();

        $cityOpt = array_filter(array_map('intval', (array) $this->option('city')));
        $cityIds = $cityOpt !== []
            ? $cityOpt
            : City::query()
                ->where('is_active', true)
                ->where('city_type', 'city')
                ->pluck('city_id')
                ->map(fn ($id) => (int) $id)
                ->all();

        $this->info("Городов: ".count($cityIds).", период {$from->toDateString()} … {$to->toDateString()}");

        $bar = $this->output->createProgressBar(count($cityIds) * (max(1, $from->diffInDays($to) + 1)));
        $bar->start();

        $done = $service->rebuildRange($cityIds, $from, $to, function () use ($bar) {
            $bar->advance();
        });

        $bar->finish();
        $this->newLine();
        $this->info("Готово: {$done} срезов.");

        return self::SUCCESS;
    }
}
