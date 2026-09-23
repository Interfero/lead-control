<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Services\CityReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Замер отчёта «по городам за месяц» (ТЗ §6.1: ≤80 городов, p95 ≤ 3 с на полный ответ).
 */
class ReportsBenchCityMonthCommand extends Command
{
    protected $signature = 'reports:bench-city-month
        {--limit=80 : Число городов}
        {--json : JSON-вывод}';

    protected $description = 'Benchmark buildCityStats × N городов за текущий месяц';

    public function handle(CityReportService $cityReport): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $from = Carbon::now()->startOfMonth();
        $to = Carbon::now()->endOfMonth();

        $cityIds = City::query()
            ->where('is_active', true)
            ->where('city_type', 'city')
            ->orderBy('city_id')
            ->limit($limit)
            ->pluck('city_id')
            ->all();

        if ($cityIds === []) {
            $this->error('No cities');

            return self::FAILURE;
        }

        $perCityMs = [];
        $t0 = hrtime(true);
        foreach ($cityIds as $cityId) {
            $tCity = hrtime(true);
            $cityReport->buildCityStats((int) $cityId, $from, $to);
            $perCityMs[] = (hrtime(true) - $tCity) / 1e6;
        }
        $totalMs = (hrtime(true) - $t0) / 1e6;

        sort($perCityMs);
        $n = count($perCityMs);
        $p95Index = min($n - 1, (int) ceil($n * 0.95) - 1);
        $p95CityMs = $perCityMs[$p95Index];

        $normMs = 3000;
        $passTotal = $totalMs <= $normMs;

        $result = [
            'cities' => $n,
            'period' => $from->format('Y-m-d').'..'.$to->format('Y-m-d'),
            'total_ms' => round($totalMs, 2),
            'p95_per_city_ms' => round($p95CityMs, 2),
            'max_per_city_ms' => round($perCityMs[$n - 1], 2),
            'norm_total_ms' => $normMs,
            'pass_tz_6_1_total' => $passTotal,
            'checked_at' => now()->toIso8601String(),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $this->info('cities='.$n.' total_ms='.round($totalMs, 2).' pass='.($passTotal ? 'YES' : 'NO').' (norm ≤ '.$normMs.' ms)');
            $this->line('p95_per_city_ms='.round($p95CityMs, 2).' max_per_city_ms='.round($perCityMs[$n - 1], 2));
        }

        return $passTotal ? self::SUCCESS : self::FAILURE;
    }
}
