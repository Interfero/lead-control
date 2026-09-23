<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportCsvExporter
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<mixed>>  $rows
     */
    public function download(string $filename, array $headers, iterable $rows, string $delimiter = ';'): StreamedResponse
    {
        $this->auditExport($filename);

        return response()->streamDownload(function () use ($headers, $rows, $delimiter) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, $headers, $delimiter);
            foreach ($rows as $row) {
                fputcsv($out, array_values($row), $delimiter);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Сразу скачивает CSV (очередь отключена).
     *
     * @param  list<string>  $headers
     * @param  iterable<int, list<mixed>>  $rows
     */
    public function queueDownload(string $filename, array $headers, iterable $rows, string $delimiter = ';'): StreamedResponse
    {
        return $this->download($filename, $headers, $rows, $delimiter);
    }

    /**
     * @param  list<array{title?: string, headers: list<string>, rows: iterable<int, list<mixed>>}>  $sections
     */
    public function downloadSections(string $filename, array $sections, string $delimiter = ';'): StreamedResponse
    {
        $this->auditExport($filename);

        return response()->streamDownload(function () use ($sections, $delimiter) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            $first = true;
            foreach ($sections as $section) {
                if (! $first) {
                    fputcsv($out, [], $delimiter);
                }
                $first = false;
                if (! empty($section['title'])) {
                    fputcsv($out, [$section['title']], $delimiter);
                }
                fputcsv($out, $section['headers'], $delimiter);
                foreach ($section['rows'] as $row) {
                    fputcsv($out, array_values($row), $delimiter);
                }
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Сразу скачивает CSV с секциями (очередь отключена).
     *
     * @param  list<array{title?: string, headers: list<string>, rows: iterable<int, list<mixed>>}>  $sections
     */
    public function queueDownloadSections(string $filename, array $sections, string $delimiter = ';'): StreamedResponse
    {
        return $this->downloadSections($filename, $sections, $delimiter);
    }

    private function auditExport(string $filename): void
    {
        try {
            app(SecurityAuditService::class)->log(
                'export_csv',
                auth()->user(),
                'report',
                $filename,
                ['filename' => $filename],
            );
        } catch (\Throwable) {
        }
    }
}
