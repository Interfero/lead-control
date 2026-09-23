<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupReportExportsCommand extends Command
{
    protected $signature = 'reports:cleanup-exports {--days=2 : Удалить файлы старше N дней}';

    protected $description = 'Удалить устаревшие CSV-экспорты отчётов из storage';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days)->getTimestamp();
        $disk = Storage::disk('local');
        $root = 'report-exports';

        if (! $disk->exists($root)) {
            $this->info('Нет каталога report-exports');

            return self::SUCCESS;
        }

        $deleted = 0;
        foreach ($disk->allFiles($root) as $path) {
            $mtime = $disk->lastModified($path);
            if ($mtime !== false && $mtime < $cutoff) {
                $disk->delete($path);
                $deleted++;
            }
        }

        $this->info("Удалено файлов: {$deleted} (старше {$days} дн.)");

        return self::SUCCESS;
    }
}
