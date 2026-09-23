<?php

namespace App\Http\Controllers;

use App\Jobs\ExportReportCsvJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function download(Request $request, string $exportId): StreamedResponse|\Illuminate\Http\Response
    {
        $meta = Cache::get(ExportReportCsvJob::cacheKeyFor($exportId));
        if (! is_array($meta) || (int) ($meta['user_id'] ?? 0) !== (int) $request->user()->user_id) {
            abort(404);
        }

        if (($meta['status'] ?? '') === 'failed') {
            return response('Экспорт не удался: '.($meta['error'] ?? 'ошибка'), 500);
        }

        if (($meta['status'] ?? '') !== 'ready' || empty($meta['path'])) {
            return response(
                'CSV ещё формируется. Обновите страницу через несколько секунд (очередь крутится каждую минуту).',
                202,
                ['Content-Type' => 'text/plain; charset=UTF-8', 'Retry-After' => '15']
            );
        }

        if (! Storage::disk('local')->exists($meta['path'])) {
            abort(404);
        }

        return Storage::disk('local')->download($meta['path'], $meta['filename'] ?? 'export.csv', [
            'Content-Type' => str_ends_with((string) ($meta['filename'] ?? ''), '.xlsx')
                ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                : 'text/csv; charset=UTF-8',
        ]);
    }
}
