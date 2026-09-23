<?php

namespace App\Console\Commands;

use App\Services\DirectorSalaryService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SalarySyncMonthCommand extends Command
{
    protected $signature = 'salary:sync-month {month? : YYYY-MM} {--force : Пересчитать зафиксированные}';

    protected $description = 'Создать/обновить расчёты ЗП директоров за месяц';

    public function handle(DirectorSalaryService $salary): int
    {
        $arg = $this->argument('month');
        $month = $arg
            ? $salary->parsePeriodMonth($arg)
            : $salary->accrualPeriodForDisplay($salary->defaultPeriodMonth());
        $salary->seedInitialPolicies(Carbon::create(2026, 8, 1, 0, 0, 0, config('app.timezone')));
        $rows = $salary->ensureMonth(null, $month, (bool) $this->option('force'));
        $this->info('Synced '.$rows->count().' cities for '.$month->format('Y-m'));

        return self::SUCCESS;
    }
}
