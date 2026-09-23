<?php

namespace App\Console\Commands;

use App\Helpers\PhoneHelper;
use App\Models\Source;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SourcesNormalizePhonesCommand extends Command
{
    protected $signature = 'sources:normalize-phones {--dry-run : Только показать изменения}';

    protected $description = 'Нормализовать sources.source_phone через PhoneHelper (единый формат)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $sources = Source::query()
            ->whereNotNull('source_phone')
            ->where('source_phone', '!=', '')
            ->orderBy('source_id')
            ->get(['source_id', 'source_name', 'source_phone']);

        $changed = 0;
        $conflicts = 0;

        foreach ($sources as $source) {
            $normalized = PhoneHelper::normalize($source->source_phone);
            if ($normalized === '' || $normalized === $source->source_phone) {
                continue;
            }

            $conflict = Source::query()
                ->where('source_phone', $normalized)
                ->where('source_id', '!=', $source->source_id)
                ->exists();

            if ($conflict) {
                $conflicts++;
                $this->warn("#{$source->source_id} «{$source->source_name}»: {$source->source_phone} → {$normalized} CONFLICT");
                continue;
            }

            $this->line("#{$source->source_id} «{$source->source_name}»: {$source->source_phone} → {$normalized}");
            if (! $dryRun) {
                DB::table('sources')
                    ->where('source_id', $source->source_id)
                    ->update(['source_phone' => $normalized]);
            }
            $changed++;
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."Изменено: {$changed}, конфликтов: {$conflicts}");

        return $conflicts > 0 ? self::FAILURE : self::SUCCESS;
    }
}
