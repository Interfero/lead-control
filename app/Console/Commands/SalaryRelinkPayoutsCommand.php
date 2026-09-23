<?php

namespace App\Console\Commands;

use App\Services\DirectorSalaryService;
use Illuminate\Console\Command;

class SalaryRelinkPayoutsCommand extends Command
{
    protected $signature = 'salary:relink-payouts {--dry-run : Только показать, что будет перенесено}';

    protected $description = 'Перепривязать выплаты ЗП: проводка в месяце M → расчёт за M−1';

    public function handle(DirectorSalaryService $salary): int
    {
        if ($this->option('dry-run')) {
            $this->warn('dry-run: изменений не будет, ниже текущие совпадения месяца кассы и расчёта');
            $cat = $salary->paymentCategory();
            $ops = \App\Models\CfmOperation::query()
                ->with('salaryCalculation')
                ->where('cfm_cat_id', $cat->cfm_cat_id)
                ->whereNotNull('cfm_closed_at')
                ->whereNotNull('salary_calculation_id')
                ->orderBy('cfm_id')
                ->get();
            $n = 0;
            foreach ($ops as $op) {
                $calc = $op->salaryCalculation;
                if (! $calc) {
                    continue;
                }
                $closed = $op->cfm_closed_at->timezone((string) config('app.timezone'))->format('Y-m');
                $period = $calc->period_month->format('Y-m');
                if ($closed !== $period) {
                    continue;
                }
                $n++;
                $this->line('#'.$op->cfm_id.' city='.$op->city_id.' '.$closed.' calc='.$calc->salary_calculation_id.' → prev');
            }
            $this->info('to_move='.$n);

            return self::SUCCESS;
        }

        $moved = $salary->relinkSameMonthPayoutsToPreviousPeriod();
        foreach ($moved as $row) {
            $this->line('#'.$row['cfm_id'].' city='.$row['city_id'].' '.$row['from'].' → '.$row['to']);
        }
        $this->info('moved='.count($moved));

        return self::SUCCESS;
    }
}
