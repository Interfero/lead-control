<?php

namespace App\Services;

use App\Models\City;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Оргструктура LC: роли × города (pivot user_cities).
 * Для чатов и справочников — не для прав на заказы.
 */
class OrgTreeService
{
    /** @var array<string, string> */
    private const COMPANY_ROLE_BUCKETS = [
        'developer' => 'developers',
        'general_director' => 'general_directors',
        'call_center' => 'call_center',
        'senior_dispatcher' => 'senior_dispatchers',
        'investor' => 'investors',
    ];

    /** @var array<string, string> */
    private const CITY_ROLE_BUCKETS = [
        'regional_director' => 'regional_directors',
        'branch_head' => 'branch_heads',
        'tech_director' => 'tech_directors',
        'senior_manager' => 'senior_managers',
        'ad_manager' => 'ad_managers',
        'order_manager' => 'order_managers',
        'master' => 'masters',
    ];

    /**
     * @return array<string, mixed>
     */
    public function tree(?User $viewer = null): array
    {
        $viewer?->loadMissing(['roles', 'cities']);

        $scopeIds = $viewer?->cityIdsForOrdersFilter();
        $citiesQuery = City::query()
            ->where('is_active', true)
            ->orderBy('city_name');

        if (is_array($scopeIds)) {
            if ($scopeIds === []) {
                return $this->payload($viewer, $scopeIds, collect(), $this->emptyCompany());
            }
            $citiesQuery->whereIn('city_id', $scopeIds);
        }

        $cities = $citiesQuery->get([
            'city_id',
            'city_name',
            'city_timezone',
            'parent_city_id',
            'city_type',
        ]);

        $users = User::query()
            ->where('is_active', true)
            ->where('is_blacklisted', false)
            ->whereNull('user_fired_at')
            ->with(['roles', 'cities'])
            ->orderBy('user_name')
            ->get();

        $company = $this->emptyCompany();
        $byCity = [];
        foreach ($cities as $city) {
            $byCity[(int) $city->city_id] = $this->emptyCityRoles();
        }

        foreach ($users as $user) {
            $person = $this->serializePerson($user);
            $roleCodes = $user->roles->pluck('role_code')->all();

            foreach ($roleCodes as $code) {
                if (isset(self::COMPANY_ROLE_BUCKETS[$code])) {
                    $this->pushUnique($company[self::COMPANY_ROLE_BUCKETS[$code]], $person);
                }
            }

            $coveredCityIds = $this->coveredCityIds($user);
            foreach ($roleCodes as $code) {
                $bucket = self::CITY_ROLE_BUCKETS[$code] ?? null;
                if ($bucket === null) {
                    continue;
                }
                foreach ($coveredCityIds as $cityId) {
                    if (! isset($byCity[$cityId])) {
                        continue;
                    }
                    $this->pushUnique($byCity[$cityId][$bucket], $person);
                }
            }
        }

        $cityNodes = $cities->map(function (City $city) use ($byCity) {
            $id = (int) $city->city_id;
            $parentId = $city->parent_city_id ? (int) $city->parent_city_id : null;

            return [
                'id' => $id,
                'name' => $city->city_name,
                'timezone' => $city->city_timezone,
                'type' => $city->city_type,
                'parent_id' => $parentId,
                'is_satellite' => $parentId !== null,
            ] + ($byCity[$id] ?? $this->emptyCityRoles());
        })->values()->all();

        return $this->payload($viewer, $scopeIds, collect($cityNodes), $company);
    }

    /**
     * @param  array<int, int>|null  $scopeIds
     * @param  Collection<int, mixed>  $cities
     * @param  array<string, list<array<string, mixed>>>  $company
     * @return array<string, mixed>
     */
    private function payload(?User $viewer, ?array $scopeIds, Collection $cities, array $company): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'scope' => [
                'all_cities' => $scopeIds === null,
                'city_ids' => $scopeIds,
                'viewer_id' => $viewer?->user_id ? (int) $viewer->user_id : null,
            ],
            'company' => $company,
            'cities' => $cities->values()->all(),
        ];
    }

    /**
     * Города, в которых человек числится: pivot + кластер спутников (НН).
     *
     * @return list<int>
     */
    private function coveredCityIds(User $user): array
    {
        $ids = [];
        foreach ($user->cities as $city) {
            foreach (City::operationGroupIds((int) $city->city_id) as $id) {
                $ids[(int) $id] = true;
            }
        }

        return array_map('intval', array_keys($ids));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePerson(User $user): array
    {
        $scopeCityIds = $user->cityIdsForOrdersFilter();

        return [
            'id' => (int) $user->user_id,
            'name' => $user->user_name,
            'email' => $user->email,
            'phone' => $user->user_phone,
            'roles' => $user->roles->pluck('role_code')->values()->all(),
            'city_ids' => $user->cities->pluck('city_id')->map(fn ($id) => (int) $id)->values()->all(),
            'access_all_cities' => $scopeCityIds === null,
            'is_active' => (bool) $user->is_active,
        ];
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function emptyCompany(): array
    {
        return [
            'general_directors' => [],
            'developers' => [],
            'call_center' => [],
            'senior_dispatchers' => [],
            'investors' => [],
        ];
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function emptyCityRoles(): array
    {
        return [
            'regional_directors' => [],
            'branch_heads' => [],
            'tech_directors' => [],
            'senior_managers' => [],
            'ad_managers' => [],
            'order_managers' => [],
            'masters' => [],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $list
     * @param  array<string, mixed>  $person
     */
    private function pushUnique(array &$list, array $person): void
    {
        foreach ($list as $existing) {
            if ((int) $existing['id'] === (int) $person['id']) {
                return;
            }
        }

        $list[] = $person;
    }
}
