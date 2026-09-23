<?php

namespace App\Services;

use App\Helpers\PhoneHelper;
use App\Models\Source;
use App\Models\SourcePhoneAlias;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Канон атрибуции линии Mango / DID → Source.
 * Приоритет: sources.source_phone → алиасы → .env fallback (с предупреждением в лог).
 */
class SourceAttributionService
{
    /**
     * @return array{source_id: int|null, via: string|null, matched: string|null}
     */
    public function resolveForLine(string $lineNumber): array
    {
        $candidates = $this->lineCandidates($lineNumber);
        if ($candidates === []) {
            return ['source_id' => null, 'via' => null, 'matched' => null];
        }

        $byPhone = $this->findActiveSourceByPhones($candidates);
        if ($byPhone !== null) {
            return [
                'source_id' => (int) $byPhone->source_id,
                'via' => 'source_phone',
                'matched' => $byPhone->source_phone,
            ];
        }

        $alias = $this->findAliasByPhones($candidates);
        if ($alias !== null) {
            return [
                'source_id' => (int) $alias->source_id,
                'via' => 'alias',
                'matched' => $alias->phone,
            ];
        }

        $envId = $this->resolveFromEnvFallback($candidates, $lineNumber);
        if ($envId !== null) {
            Log::channel('single')->warning('Mango line resolved via .env fallback (migrate to sources.source_phone)', [
                'line' => $lineNumber,
                'candidates' => $candidates,
                'source_id' => $envId,
            ]);

            return [
                'source_id' => $envId,
                'via' => 'env_fallback',
                'matched' => $lineNumber,
            ];
        }

        Log::channel('single')->warning('Mango line has no Source mapping', [
            'line' => $lineNumber,
            'candidates' => $candidates,
        ]);

        return ['source_id' => null, 'via' => null, 'matched' => null];
    }

    public function getSourceIdForLine(string $lineNumber): ?int
    {
        return $this->resolveForLine($lineNumber)['source_id'];
    }

    /**
     * @return list<string>
     */
    public function lineCandidates(string $lineNumber): array
    {
        $raw = trim($lineNumber);
        if ($raw === '') {
            return [];
        }

        $digits = PhoneHelper::clean($raw);
        $normalized = PhoneHelper::normalize($raw);

        $out = [];
        foreach ([$raw, $digits, $normalized] as $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            $out[$value] = $value;
        }

        if ($digits !== '' && strlen($digits) > 10) {
            $last10 = substr($digits, -10);
            $out[$last10] = $last10;
        }

        // DID с ведущей 7 без нормализации (на случай хранения как 11 цифр)
        if ($normalized !== '' && strlen($normalized) === 10) {
            $with7 = '7'.$normalized;
            $out[$with7] = $with7;
        }

        return array_values($out);
    }

    /**
     * @param  list<string>  $phones
     */
    private function findActiveSourceByPhones(array $phones): ?Source
    {
        $active = Source::query()
            ->whereIn('source_phone', $phones)
            ->where('is_active', true)
            ->orderBy('source_id')
            ->first();

        if ($active) {
            return $active;
        }

        return Source::query()
            ->whereIn('source_phone', $phones)
            ->orderByDesc('is_active')
            ->orderBy('source_id')
            ->first();
    }

    /**
     * @param  list<string>  $phones
     */
    private function findAliasByPhones(array $phones): ?SourcePhoneAlias
    {
        if (! Schema::hasTable('source_phone_aliases')) {
            return null;
        }

        return SourcePhoneAlias::query()
            ->whereIn('phone', $phones)
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  list<string>  $candidates
     */
    private function resolveFromEnvFallback(array $candidates, string $lineNumber): ?int
    {
        $lineSources = config('services.mango.line_sources', []);
        if (! is_array($lineSources) || $lineSources === []) {
            return null;
        }

        $keys = array_unique(array_merge([$lineNumber, trim($lineNumber)], $candidates));
        foreach ($keys as $key) {
            $sourceId = (int) ($lineSources[$key] ?? 0);
            if ($sourceId > 0) {
                return $sourceId;
            }
        }

        return null;
    }

    /**
     * @return array{
     *     without_phone: int,
     *     without_phone_superpart_party: int,
     *     without_phone_all: int,
     *     inactive_with_phone: int,
     *     duplicate_phones: int
     * }
     */
    public function gapStats(): array
    {
        $activeWithoutPhone = Source::query()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('source_phone')->orWhere('source_phone', '');
            });

        $withoutPhoneAll = (clone $activeWithoutPhone)->count();
        $withoutPhoneBlocking = (clone $activeWithoutPhone)->requiresDidPhone()->count();
        $withoutPhoneSuperpart = (clone $activeWithoutPhone)->exemptFromDidPhone()->count();

        $inactiveWithPhone = Source::query()
            ->where('is_active', false)
            ->whereNotNull('source_phone')
            ->where('source_phone', '!=', '')
            ->count();

        $duplicatePhones = Source::query()
            ->select('source_phone')
            ->whereNotNull('source_phone')
            ->where('source_phone', '!=', '')
            ->groupBy('source_phone')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        return [
            // Блокер §10.2 ш.4 / приёмки DID: без телефона и не SuperPart-парт
            'without_phone' => $withoutPhoneBlocking,
            'without_phone_superpart_party' => $withoutPhoneSuperpart,
            'without_phone_all' => $withoutPhoneAll,
            'inactive_with_phone' => $inactiveWithPhone,
            'duplicate_phones' => $duplicatePhones,
        ];
    }
}
