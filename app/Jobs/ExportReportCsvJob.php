<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExportReportCsvJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /**
     * @param  list<string>|null  $headers
     * @param  list<list<mixed>>|null  $rows
     * @param  list<array{title?: string, headers: list<string>, rows: list<list<mixed>>}>|null  $sections
     */
    public function __construct(
        public string $exportId,
        public int $userId,
        public string $filename,
        public ?array $headers = null,
        public ?array $rows = null,
        public ?array $sections = null,
        public string $delimiter = ';',
    ) {}

    public function handle(): void
    {
        $relative = 'report-exports/'.$this->userId.'/'.$this->exportId.'_'.$this->safeFilename($this->filename);
        $full = Storage::disk('local')->path($relative);
        $dir = dirname($full);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $out = fopen($full, 'w');
        if ($out === false) {
            throw new \RuntimeException('Cannot open export file: '.$full);
        }

        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

        if ($this->sections !== null) {
            $first = true;
            foreach ($this->sections as $section) {
                if (! $first) {
                    fputcsv($out, [], $this->delimiter);
                }
                $first = false;
                if (! empty($section['title'])) {
                    fputcsv($out, [$section['title']], $this->delimiter);
                }
                fputcsv($out, $section['headers'], $this->delimiter);
                foreach ($section['rows'] as $row) {
                    fputcsv($out, array_values($row), $this->delimiter);
                }
            }
        } else {
            fputcsv($out, $this->headers ?? [], $this->delimiter);
            foreach ($this->rows ?? [] as $row) {
                fputcsv($out, array_values($row), $this->delimiter);
            }
        }

        fclose($out);

        Cache::put($this->cacheKey(), [
            'status' => 'ready',
            'user_id' => $this->userId,
            'filename' => $this->filename,
            'path' => $relative,
            'ready_at' => now()->toIso8601String(),
        ], now()->addDay());

        Log::channel('single')->info('ExportReportCsvJob ready', [
            'export_id' => $this->exportId,
            'user_id' => $this->userId,
            'filename' => $this->filename,
        ]);
    }

    public function failed(?\Throwable $e): void
    {
        Cache::put($this->cacheKey(), [
            'status' => 'failed',
            'user_id' => $this->userId,
            'filename' => $this->filename,
            'error' => $e?->getMessage(),
        ], now()->addHours(6));
    }

    public static function cacheKeyFor(string $exportId): string
    {
        return 'report_export:'.$exportId;
    }

    private function cacheKey(): string
    {
        return self::cacheKeyFor($this->exportId);
    }

    private function safeFilename(string $filename): string
    {
        $name = preg_replace('/[^A-Za-z0-9._\-]+/', '_', $filename) ?: 'export.csv';

        return str_ends_with(strtolower($name), '.csv') ? $name : $name.'.csv';
    }
}
