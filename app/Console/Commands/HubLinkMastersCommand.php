<?php

namespace App\Console\Commands;

use App\Models\Hub\HubOrderCache;
use App\Models\HubMasterLink;
use App\Models\User;
use App\Services\Hub\HubOrderRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Наполнение явной карты соответствий «мастер KP-Lead ↔ пользователь Lead Control».
 *
 * ТЗ: нельзя определять мастера только по email/ФИО на лету — соответствие должно
 * лежать в отдельной таблице. Команда только предлагает связи; строки link_type=manual
 * не трогаются, неоднозначные совпадения не связываются, а выводятся для ручного решения.
 */
class HubLinkMastersCommand extends Command
{
    protected $signature = 'hub:link-masters
        {--city= : Ограничить конкретным city_id}
        {--dry-run : Показать план без записи}';

    protected $description = 'Построить карту соответствий мастеров KP-Lead и пользователей Lead Control';

    public function handle(HubOrderRepository $repository): int
    {
        if (! $repository->isEnabled()) {
            $this->warn('Единый хаб отключён (UNIFIED_HUB_ENABLED=false).');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $cityFilter = $this->option('city') !== null ? (int) $this->option('city') : null;
        $kpCrmId = (int) config('services.unified_hub.kp_crm_id', 2);

        try {
            $query = HubOrderCache::query()
                ->selectRaw('city_id, master_name, master_external_id, COUNT(*) as orders_count')
                ->where('crm_id', $kpCrmId)
                ->whereNotNull('master_name')
                ->groupBy('city_id', 'master_name', 'master_external_id');

            if ($cityFilter !== null) {
                $query->where('city_id', $cityFilter);
            }

            $rows = $query->get();
        } catch (\Throwable $exception) {
            $this->error('Не удалось прочитать кэш Единого хаба: '.$exception->getMessage());

            return self::FAILURE;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $ambiguous = [];
        $unmatched = [];

        foreach ($rows as $row) {
            $name = trim((string) $row->master_name);
            if ($name === '') {
                continue;
            }

            $cityId = $row->city_id !== null ? (int) $row->city_id : null;
            $externalId = trim((string) ($row->master_external_id ?? ''));
            $nameKey = HubMasterLink::normalizeName($name);

            $user = $externalId !== ''
                ? User::query()->where('kp_employee_id', $externalId)->first()
                : null;

            if (! $user) {
                $candidates = $this->candidatesByName($nameKey, $cityId);

                if ($candidates->count() > 1) {
                    $ambiguous[] = [
                        'city' => $cityId,
                        'name' => $name,
                        'candidates' => $candidates->pluck('user_id')->implode(', '),
                        'orders' => (int) $row->orders_count,
                    ];
                    $skipped++;

                    continue;
                }

                $user = $candidates->first();
            }

            if (! $user) {
                $unmatched[] = ['city' => $cityId, 'name' => $name, 'orders' => (int) $row->orders_count];
                $skipped++;

                continue;
            }

            // Уникальность — по исходному написанию имени в источнике:
            // «Орлов Иван» и «Орлов Иван (Нов)» — две строки на одного мастера.
            $existing = HubMasterLink::query()
                ->where('source', HubMasterLink::SOURCE_KP)
                ->where('external_master_name', $name)
                ->where('city_id', $cityId)
                ->first();

            if ($existing && $existing->link_type === HubMasterLink::TYPE_MANUAL) {
                $skipped++;

                continue;
            }

            $attributes = [
                // ID сотрудника КП пишем только если его отдал источник:
                // users.kp_employee_id и так учитывается при выборке заявок.
                'external_master_id' => $externalId !== '' ? $externalId : null,
                'external_master_name' => $name,
                'user_id' => (int) $user->user_id,
                'link_type' => HubMasterLink::TYPE_AUTO,
                'is_active' => true,
            ];

            if ($dryRun) {
                $this->line(sprintf(
                    '[dry-run] city=%s «%s» → user_id=%d (%s), заявок: %d',
                    $cityId ?? '—',
                    $name,
                    $user->user_id,
                    $user->user_name,
                    (int) $row->orders_count
                ));
                $existing ? $updated++ : $created++;

                continue;
            }

            if ($existing) {
                $existing->fill($attributes)->save();
                $updated++;
            } else {
                HubMasterLink::query()->create(array_merge($attributes, [
                    'source' => HubMasterLink::SOURCE_KP,
                    'name_key' => $nameKey,
                    'city_id' => $cityId,
                ]));
                $created++;
            }
        }

        $this->info(sprintf('Связей создано: %d, обновлено: %d, пропущено: %d', $created, $updated, $skipped));

        if ($ambiguous !== []) {
            $this->warn('Неоднозначные ФИО (нужна ручная связь link_type=manual):');
            $this->table(['city_id', 'ФИО в КП', 'кандидаты user_id', 'заявок'], array_map(
                fn (array $item) => [$item['city'], $item['name'], $item['candidates'], $item['orders']],
                $ambiguous
            ));
        }

        if ($unmatched !== []) {
            $this->warn('Не найден пользователь CRM:');
            $this->table(['city_id', 'ФИО в КП', 'заявок'], array_map(
                fn (array $item) => [$item['city'], $item['name'], $item['orders']],
                $unmatched
            ));
        }

        Log::info('hub.link-masters', [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'ambiguous' => count($ambiguous),
            'unmatched' => count($unmatched),
            'dry_run' => $dryRun,
        ]);

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function candidatesByName(?string $nameKey, ?int $cityId)
    {
        if ($nameKey === null) {
            return collect();
        }

        $query = User::query()
            ->whereHas('roles', fn ($q) => $q->where('role_code', 'master'));

        if ($cityId !== null) {
            $query->whereHas('cities', fn ($q) => $q->where('cities.city_id', $cityId));
        }

        return $query->get()
            ->filter(fn (User $user) => HubMasterLink::normalizeName($user->user_name) === $nameKey)
            ->values();
    }
}
