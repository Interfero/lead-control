<?php

namespace App\Console\Commands;

use App\Models\Source;
use App\Services\SourceAttributionService;
use Illuminate\Console\Command;

class SourcesAttributionGapsCommand extends Command
{
    protected $signature = 'sources:attribution-gaps
                            {--csv= : Путь CSV для выгрузки (по умолчанию — блокеры DID; --all = все без телефона)}
                            {--all : Включая парт-каналы SuperPart (не блокер §10.2 ш.4)}';

    protected $description = 'Отчёт дыр атрибуции: активные РК без телефона (DID), дубли source_phone';

    public function handle(SourceAttributionService $attribution): int
    {
        $stats = $attribution->gapStats();
        $this->info('Атрибуция РК — дыры:');
        $this->line("  блокеры DID (без телефона, не SuperPart-парт): {$stats['without_phone']}");
        $this->line("  SuperPart-парты без телефона (не блокер): {$stats['without_phone_superpart_party']}");
        $this->line("  всего активных без телефона: {$stats['without_phone_all']}");
        $this->line("  неактивных с телефоном: {$stats['inactive_with_phone']}");
        $this->line("  дублей source_phone: {$stats['duplicate_phones']}");

        $includeAll = (bool) $this->option('all');

        $blocking = Source::query()
            ->with('city')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('source_phone')->orWhere('source_phone', '');
            })
            ->requiresDidPhone()
            ->orderBy('source_id')
            ->get(['source_id', 'source_name', 'source_kind', 'city_id', 'source_phone']);

        $exempt = Source::query()
            ->with('city')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('source_phone')->orWhere('source_phone', '');
            })
            ->exemptFromDidPhone()
            ->orderBy('source_id')
            ->get(['source_id', 'source_name', 'source_kind', 'city_id', 'source_phone']);

        if ($blocking->isNotEmpty()) {
            $this->newLine();
            $this->warn('Блокеры DID — активные без телефона (нужен номер линии):');
            $this->table(
                ['ID', 'Название', 'Вид', 'Город'],
                $blocking->map(fn (Source $s) => [
                    $s->source_id,
                    $s->source_name,
                    $s->kindLabel() ?? $s->source_kind,
                    $s->city?->city_name ?? '—',
                ])->all()
            );
        } else {
            $this->newLine();
            $this->info('Блокеров DID нет — §10.2 шаг 4 по телефонам боевых линий закрыт.');
        }

        if ($exempt->isNotEmpty()) {
            $this->newLine();
            $this->comment('SuperPart-парты без телефона (заказ с source_id, DID не требуется):');
            $this->table(
                ['ID', 'Название', 'Вид', 'Город'],
                $exempt->map(fn (Source $s) => [
                    $s->source_id,
                    $s->source_name,
                    $s->kindLabel() ?? $s->source_kind,
                    $s->city?->city_name ?? '—',
                ])->all()
            );
        }

        $csvPath = $this->option('csv');
        if ($csvPath) {
            $export = $includeAll ? $blocking->concat($exempt)->sortBy('source_id')->values() : $blocking;
            $fh = fopen($csvPath, 'wb');
            if ($fh === false) {
                $this->error("Не удалось открыть {$csvPath}");

                return self::FAILURE;
            }
            fwrite($fh, "\xEF\xBB\xBF");
            fputcsv($fh, ['source_id', 'source_name', 'source_kind', 'city', 'source_phone', 'did_blocker'], ';');
            foreach ($export as $s) {
                fputcsv($fh, [
                    $s->source_id,
                    $s->source_name,
                    $s->source_kind,
                    $s->city?->city_name ?? '',
                    $s->source_phone ?? '',
                    $s->isExemptFromDidPhoneRequirement() ? '0' : '1',
                ], ';');
            }
            fclose($fh);
            $this->info('CSV: '.$csvPath.($includeAll ? ' (все без телефона)' : ' (только блокеры DID)'));
        }

        return self::SUCCESS;
    }
}
