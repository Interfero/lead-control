<?php

namespace App\Console\Commands;

use App\Helpers\PhoneHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * §10.2 шаг 3: нормализация person_phones.phone_number (тот же PhoneHelper, что у sources).
 */
class PersonsNormalizePhonesCommand extends Command
{
    protected $signature = 'persons:normalize-phones
                            {--dry-run : Только показать изменения}
                            {--limit=0 : Ограничить число обновлений (0 = все)}';

    protected $description = 'Нормализовать person_phones.phone_number через PhoneHelper';

    public function handle(): int
    {
        if (! Schema::hasTable('person_phones')) {
            $this->error('Таблица person_phones отсутствует');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');
        $col = Schema::hasColumn('person_phones', 'phone_number') ? 'phone_number' : null;
        if ($col === null) {
            $this->error('Колонка phone_number не найдена');

            return self::FAILURE;
        }

        $pk = Schema::hasColumn('person_phones', 'phone_id') ? 'phone_id' : 'id';

        $rows = DB::table('person_phones')
            ->whereNotNull($col)
            ->where($col, '!=', '')
            ->orderBy($pk)
            ->get([$pk, $col]);

        $changed = 0;
        $conflicts = 0;
        $skippedEmpty = 0;

        foreach ($rows as $row) {
            if ($limit > 0 && $changed >= $limit) {
                break;
            }

            $current = (string) $row->{$col};
            $normalized = PhoneHelper::normalize($current);
            if ($normalized === '') {
                $skippedEmpty++;
                continue;
            }
            if ($normalized === $current) {
                continue;
            }

            $conflict = DB::table('person_phones')
                ->where($col, $normalized)
                ->where($pk, '!=', $row->{$pk})
                ->exists();

            if ($conflict) {
                $conflicts++;
                $this->warn("#{$row->{$pk}}: {$current} → {$normalized} CONFLICT");
                continue;
            }

            $this->line("#{$row->{$pk}}: {$current} → {$normalized}");
            if (! $dryRun) {
                DB::table('person_phones')
                    ->where($pk, $row->{$pk})
                    ->update([$col => $normalized]);
            }
            $changed++;
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."Изменено: {$changed}, конфликтов: {$conflicts}, пустых после normalize: {$skippedEmpty}");

        return $conflicts > 0 ? self::FAILURE : self::SUCCESS;
    }
}
