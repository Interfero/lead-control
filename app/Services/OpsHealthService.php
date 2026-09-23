<?php

namespace App\Services;

use App\Models\ReportCityDaily;
use App\Models\Source;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Операционный health для мониторинга (ТЗ §6.5) на shared / VPS.
 */
class OpsHealthService
{
    /**
     * @return array{ok: bool, status: string, checks: array<string, mixed>, checked_at: string}
     */
    public function snapshot(): array
    {
        $checks = [];
        $ok = true;

        try {
            DB::select('select 1');
            $checks['database'] = ['ok' => true];
        } catch (\Throwable $e) {
            $ok = false;
            $checks['database'] = ['ok' => false, 'error' => $e->getMessage()];
        }

        $storagePath = storage_path();
        $free = @disk_free_space($storagePath);
        $total = @disk_total_space($storagePath);
        $freeMb = $free !== false ? (int) round($free / 1024 / 1024) : null;
        $diskOk = $freeMb === null || $freeMb >= 200;
        if (! $diskOk) {
            $ok = false;
        }
        $checks['disk'] = [
            'ok' => $diskOk,
            'free_mb' => $freeMb,
            'total_mb' => $total !== false ? (int) round($total / 1024 / 1024) : null,
            'path' => $storagePath,
        ];

        $jobsPending = Schema::hasTable('jobs') ? (int) DB::table('jobs')->count() : null;
        $failedJobs = Schema::hasTable('failed_jobs') ? (int) DB::table('failed_jobs')->count() : null;
        $queueOk = ($jobsPending === null || $jobsPending < 200) && ($failedJobs === null || $failedJobs < 50);
        if (! $queueOk) {
            $ok = false;
        }
        $checks['queue'] = [
            'ok' => $queueOk,
            'pending' => $jobsPending,
            'failed' => $failedJobs,
            'driver' => config('queue.default'),
        ];

        $staleSlices = null;
        $slicesOk = true;
        if (Schema::hasTable('report_city_daily')) {
            $staleSlices = (int) ReportCityDaily::query()->where('stale', true)->count();
            $slicesOk = $staleSlices < 500;
            if (! $slicesOk) {
                $ok = false;
            }
        }
        $checks['report_slices'] = [
            'ok' => $slicesOk,
            'stale' => $staleSlices,
            'table' => Schema::hasTable('report_city_daily'),
        ];

        $mangoSecret = (string) config('services.mango.webhook_secret', '');
        $mangoConfigured = $mangoSecret !== '';
        $mangoRequired = (bool) config('security.ops_health_require_mango', false);
        $mangoOk = ! app()->isProduction() || $mangoConfigured || ! $mangoRequired;
        if (! $mangoOk) {
            $ok = false;
        }
        $checks['mango_webhook_secret'] = [
            'ok' => $mangoOk,
            'configured' => $mangoConfigured,
            'required' => $mangoRequired,
            'deferred' => app()->isProduction() && ! $mangoConfigured && ! $mangoRequired,
        ];

        $checks['superpart_outbox'] = $this->outboxCheck();
        if (! ($checks['superpart_outbox']['ok'] ?? true)) {
            $ok = false;
        }

        $checks['superpart_ingest'] = $this->superpartIngestCheck();
        if (! ($checks['superpart_ingest']['ok'] ?? true)) {
            $ok = false;
        }

        return [
            'ok' => $ok,
            'status' => $ok ? 'ok' : 'degraded',
            'checks' => $checks,
            'checked_at' => now()->toIso8601String(),
            'app' => config('app.name'),
            'env' => config('app.env'),
        ];
    }

    /**
     * Мониторинг dead/stale outbox (ТЗ §13).
     *
     * @return array<string, mixed>
     */
    private function outboxCheck(): array
    {
        if (! Schema::hasTable('superpart_outbox')) {
            return ['ok' => true, 'skipped' => true];
        }

        $pending = (int) DB::table('superpart_outbox')->where('status', 'pending')->count();
        $dead = (int) DB::table('superpart_outbox')->where('status', 'dead')->count();
        $oldestPendingAt = DB::table('superpart_outbox')
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->value('created_at');
        $oldestAgeSec = $oldestPendingAt ? now()->diffInSeconds(\Carbon\Carbon::parse($oldestPendingAt)) : 0;

        // Очередь старше 5 минут или dead-letter не пуст → degraded.
        $ok = $dead === 0 && ($pending === 0 || $oldestAgeSec < 300);

        return [
            'ok' => $ok,
            'pending' => $pending,
            'dead' => $dead,
            'oldest_pending_age_sec' => $oldestAgeSec,
            'threshold_sec' => 300,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function superpartIngestCheck(): array
    {
        $authorId = (int) config('services.superpart.order_author_user_id');
        $authorOk = $authorId > 0 && User::query()->where('user_id', $authorId)->exists();
        $sourceId = (int) config('services.superpart.default_source_id');
        $sourceOk = $sourceId > 0 && Source::query()
            ->where('source_id', $sourceId)
            ->where('is_active', true)
            ->where('available_for_superpart', true)
            ->exists();

        return [
            'ok' => $authorOk && $sourceOk,
            'author_ok' => $authorOk,
            'source_ok' => $sourceOk,
            'author_id' => $authorId,
            'source_id' => $sourceId,
        ];
    }
}
