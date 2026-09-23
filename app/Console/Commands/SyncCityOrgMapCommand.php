<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Синхронизация карты городов: спутники (parent_city_id), имена «Спутник (Мать)»,
 * приписка (МСК), привязка городов региональным директорам.
 */
class SyncCityOrgMapCommand extends Command
{
    protected $signature = 'cities:sync-org-map
                            {--dry-run : Только показать, без записи}
                            {--skip-regs : Не трогать user_cities регионалов}';

    protected $description = 'Создать/связать города-спутники и обновить города регионалов';

    /**
     * Спутники: [отображаемое имя, имя материнского города].
     *
     * @var list<array{0: string, 1: string}>
     */
    private array $satellites = [
        ['Дзержинск (Нижний Новгород)', 'Нижний Новгород'],
        ['Бердск (Новосибирск)', 'Новосибирск'],
        ['Прокопьевск (Новокузнецк)', 'Новокузнецк'],
        ['Адлер (Сочи)', 'Сочи'],
        ['Горно-Алтайск (Бийск)', 'Бийск'],
        ['Новомосковск (Тула)', 'Тула'],
        ['Обнинск (Калуга)', 'Калуга'],
        ['Коломна (Рязань)', 'Рязань'],
        ['Геленджик (Новороссийск)', 'Новороссийск'],
        ['Крымск (Новороссийск)', 'Новороссийск'],
        ['Железногорск (Курск)', 'Курск'],
        ['Волжский (Волгоград)', 'Волгоград'],
        ['Славянск-на-Кубани (Краснодар)', 'Краснодар'],
        ['Невинномысск (Ставрополь)', 'Ставрополь'],
        ['Новоалтайск (Барнаул)', 'Барнаул'],
        ['Коммунарка (Подольск) (МСК)', 'Подольск (МСК)'],
        ['Салават (Стерлитамак)', 'Стерлитамак'],
        ['Евпатория (Симферополь)', 'Симферополь'],
        ['Ачинск (Красноярск)', 'Красноярск'],
        ['Кисловодск (Пятигорск)', 'Пятигорск'],
    ];

    /**
     * Переименования материнских / обычных городов (старое → новое).
     *
     * @var array<string, string>
     */
    private array $renames = [
        'СПБ З' => 'СПБ 3',
    ];

    /**
     * Города с припиской МСК (должны называться так; не спутники).
     *
     * @var list<string>
     */
    private array $mskLabels = [
        'Орехово-Зуево (МСК)',
        'Видное (МСК)',
        'Долгопрудный (МСК)',
        'Красногорск (МСК)',
        'Подольск (МСК)',
    ];

    /**
     * Регионал (фрагмент ФИО) → список городов (имена как в справочнике после sync).
     * Нижний + Дзержинск оставляем у Малышева дополнительно к его списку.
     *
     * @var array<string, list<string>>
     */
    private array $regCities = [
        'Егоров Даниил' => [
            'Сочи', 'Петропавловск-Камчатский', 'Новосибирск', 'Новокузнецк',
            'Орехово-Зуево (МСК)', 'Йошкар-Ола',
            'Бердск (Новосибирск)', 'Прокопьевск (Новокузнецк)', 'Адлер (Сочи)',
        ],
        'Иванов Евгений' => [
            'Бийск', 'Оренбург',
            'Горно-Алтайск (Бийск)',
        ],
        'Логинов Сергей' => [
            'СПБ 3', 'Тула', 'Сыктывкар',
            'Новомосковск (Тула)',
        ],
        'Логоцкий Ян' => [
            'Псков', 'Рязань', 'Анапа', 'Калуга', 'Новороссийск',
            'Обнинск (Калуга)', 'Коломна (Рязань)',
            'Геленджик (Новороссийск)', 'Крымск (Новороссийск)',
        ],
        'Малышев Андрей' => [
            'Курск', 'Нижнекамск',
            'Железногорск (Курск)',
            // кластер НН (не в списке ТЗ, но уже был у регионала)
            'Нижний Новгород', 'Дзержинск (Нижний Новгород)',
        ],
        'Мофа Илья' => [
            'Волгоград', 'Тверь', 'Краснодар', 'Смоленск', 'Белгород',
            'Ставрополь', 'Армавир', 'Набережные Челны',
            'Волжский (Волгоград)', 'Славянск-на-Кубани (Краснодар)',
            'Невинномысск (Ставрополь)',
        ],
        'Сабитов Александр' => [
            'Барнаул', 'Красногорск (МСК)', 'Подольск (МСК)', 'Стерлитамак',
            'Новоалтайск (Барнаул)', 'Коммунарка (Подольск) (МСК)',
            'Салават (Стерлитамак)',
        ],
        'Стрельникова Арина' => [
            'Тюмень', 'Красноярск', 'Майкоп', 'Севастополь', 'Симферополь',
            'Евпатория (Симферополь)', 'Ачинск (Красноярск)',
        ],
        'Шустваль Андрей' => [
            'Москва 1', 'Долгопрудный (МСК)', 'Пятигорск', 'Череповец', 'Вологда',
            'Кисловодск (Пятигорск)',
        ],
        'Долженков Иван' => [
            'Видное (МСК)',
        ],
        'Болдырев Станислав' => [
            'Видное (МСК)',
        ],
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $this->info($dry ? 'DRY-RUN cities:sync-org-map' : 'cities:sync-org-map');

        DB::transaction(function () use ($dry) {
            $this->applyRenames($dry);
            $this->ensureMskLabels($dry);
            $this->syncSatellites($dry);
            if (! $this->option('skip-regs')) {
                $this->syncRegionals($dry);
            }
        });

        $this->info('Готово.');

        return self::SUCCESS;
    }

    private function applyRenames(bool $dry): void
    {
        foreach ($this->renames as $from => $to) {
            $city = City::query()->where('city_name', $from)->first();
            if (! $city) {
                continue;
            }
            if (City::query()->where('city_name', $to)->where('city_id', '!=', $city->city_id)->exists()) {
                $this->warn("Пропуск rename «{$from}»→«{$to}»: цель уже есть");

                continue;
            }
            $this->line("Rename: {$from} → {$to}");
            if (! $dry) {
                $city->update(['city_name' => $to]);
            }
        }
    }

    private function ensureMskLabels(bool $dry): void
    {
        foreach ($this->mskLabels as $target) {
            $base = City::baseNameFromDisplay($target);
            $city = City::query()->where('city_name', $target)->first()
                ?? City::query()->where('city_name', $base)->first()
                ?? City::query()->where('city_name', 'like', $base.' (%')->first();

            if (! $city) {
                $this->warn("МСК-город не найден: {$target}");

                continue;
            }
            if ($city->city_name === $target) {
                continue;
            }
            $this->line("МСК label: {$city->city_name} → {$target}");
            if (! $dry) {
                $city->update(['city_name' => $target, 'parent_city_id' => null]);
            }
        }
    }

    private function syncSatellites(bool $dry): void
    {
        foreach ($this->satellites as [$displayName, $parentName]) {
            $parent = $this->findCityByName($parentName);
            if (! $parent) {
                $this->error("Мать не найдена для «{$displayName}»: {$parentName}");

                continue;
            }

            $base = City::baseNameFromDisplay($displayName);
            $city = City::query()->where('city_name', $displayName)->first()
                ?? City::query()->where('city_name', $base)->first()
                ?? City::query()->where('city_name', 'like', $base.' (%')->first();

            $tz = $parent->city_timezone ?: 'Europe/Moscow';

            if (! $city) {
                $this->line("Create satellite: {$displayName} → {$parent->city_name}");
                if (! $dry) {
                    City::query()->create([
                        'city_name' => $displayName,
                        'city_type' => 'city',
                        'city_timezone' => $tz,
                        'parent_city_id' => $parent->city_id,
                        'is_active' => true,
                    ]);
                }

                continue;
            }

            $needs = $city->city_name !== $displayName
                || (int) $city->parent_city_id !== (int) $parent->city_id;

            if (! $needs) {
                $this->line("OK satellite: {$displayName}");

                continue;
            }

            $this->line("Update satellite: {$city->city_name} → {$displayName} (parent {$parent->city_name})");
            if (! $dry) {
                $city->update([
                    'city_name' => $displayName,
                    'parent_city_id' => $parent->city_id,
                    'is_active' => true,
                ]);
            }
        }
    }

    private function syncRegionals(bool $dry): void
    {
        $role = Role::query()->where('role_code', 'regional_director')->first();
        if (! $role) {
            $this->error('Роль regional_director не найдена');

            return;
        }

        foreach ($this->regCities as $nameNeedle => $cityNames) {
            $user = User::query()
                ->whereHas('roles', fn ($q) => $q->where('role_code', 'regional_director'))
                ->where('user_name', 'like', '%'.$nameNeedle.'%')
                ->first();

            // Долженков может ещё не быть регионалом / не существовать
            if (! $user && $nameNeedle === 'Долженков Иван') {
                $user = User::query()->where('user_name', 'like', '%Долженков%')->first();
                if ($user && $role && ! $dry) {
                    $user->roles()->syncWithoutDetaching([$role->role_id]);
                }
            }

            if (! $user) {
                $this->warn("Регионал не найден: {$nameNeedle}");

                continue;
            }

            $ids = [];
            foreach ($cityNames as $cityName) {
                $city = $this->findCityByName($cityName);
                if (! $city) {
                    $this->warn("  город «{$cityName}» не найден для {$nameNeedle}");

                    continue;
                }
                $ids[] = (int) $city->city_id;
            }
            $ids = array_values(array_unique($ids));

            $current = $user->cities()->pluck('cities.city_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $next = collect($ids)->sort()->values()->all();
            if ($current === $next) {
                $this->line("OK reg cities: {$user->user_name}");

                continue;
            }

            $this->line("Sync reg {$user->user_name}: ".count($ids).' городов');
            if (! $dry) {
                $user->cities()->sync($ids);
                if ($user->access_all_cities) {
                    $user->update(['access_all_cities' => false]);
                }
            }
        }
    }

    private function findCityByName(string $name): ?City
    {
        return City::query()->where('city_name', $name)->first()
            ?? City::query()->where('city_name', City::baseNameFromDisplay($name))->first();
    }
}
