<?php

namespace App\Services;

use App\Models\City;
use App\Models\CityOpenTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CityOpenTimeService
{
    public function listQuery(array $filters = []): Builder
    {
        $year = (int) ($filters['year'] ?? now()->year);
        $month = (int) ($filters['month'] ?? now()->month);

        $dateFrom = ! empty($filters['date_from'])
            ? Carbon::parse($filters['date_from'])->startOfDay()
            : Carbon::create($year, $month, 1)->startOfDay();

        $dateTo = ! empty($filters['date_to'])
            ? Carbon::parse($filters['date_to'])->endOfDay()
            : Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();

        $query = CityOpenTime::query()
            ->with(['city', 'creator', 'editor'])
            ->whereDate('begin_date', '<=', $dateTo)
            ->whereDate('end_date', '>=', $dateFrom)
            ->orderByDesc('begin_date')
            ->orderByDesc('city_open_time_id');

        if (! empty($filters['city_id'])) {
            $query->where('city_id', (int) $filters['city_id']);
        }

        return $query;
    }

    public function activeForCity(int $cityId, ?Carbon $onDate = null): ?CityOpenTime
    {
        $date = ($onDate ?? now())->toDateString();

        return CityOpenTime::query()
            ->with(['city', 'creator', 'editor'])
            ->where('city_id', $cityId)
            ->whereDate('begin_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->orderByDesc('begin_date')
            ->orderByDesc('city_open_time_id')
            ->first();
    }

    /**
     * Актуальный закреп для создания заявки: действующий на дату или ближайший будущий.
     */
    public function relevantForCity(int $cityId, ?Carbon $onDate = null): ?CityOpenTime
    {
        $active = $this->activeForCity($cityId, $onDate);
        if ($active) {
            return $active;
        }

        $date = ($onDate ?? now())->toDateString();

        return CityOpenTime::query()
            ->with(['city', 'creator', 'editor'])
            ->where('city_id', $cityId)
            ->whereDate('begin_date', '>', $date)
            ->whereDate('end_date', '>=', $date)
            ->orderBy('begin_date')
            ->orderByDesc('city_open_time_id')
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function relevantPayloadForCity(int $cityId, ?Carbon $onDate = null): ?array
    {
        $pin = $this->relevantForCity($cityId, $onDate);
        if (! $pin) {
            return null;
        }

        return $this->payloadFromPin($pin);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function activePayloadForCity(int $cityId, ?Carbon $onDate = null): ?array
    {
        $pin = $this->activeForCity($cityId, $onDate);
        if (! $pin) {
            return null;
        }

        return $this->payloadFromPin($pin);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFromPin(CityOpenTime $pin): array
    {
        return [
            'city_open_time_id' => $pin->city_open_time_id,
            'city_id' => $pin->city_id,
            'city_name' => $pin->city?->city_name,
            'begin_date' => $pin->begin_date->format('Y-m-d'),
            'end_date' => $pin->end_date->format('Y-m-d'),
            'time_from' => $pin->time_from,
            'time_from_label' => $pin->timeFromLabel(),
            'comment' => $pin->comment,
            'summary' => $pin->summaryText(),
        ];
    }

    /** @return Collection<int, City> */
    public function citiesForSelect(): Collection
    {
        return City::query()
            ->where('is_active', true)
            ->orderBy('city_name')
            ->get(['city_id', 'city_name']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $user): CityOpenTime
    {
        return CityOpenTime::create($data + [
            'created_by' => $user->user_id,
            'updated_by' => $user->user_id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CityOpenTime $pin, array $data, User $user): CityOpenTime
    {
        $pin->update($data + [
            'updated_by' => $user->user_id,
        ]);

        return $pin->fresh(['city', 'creator', 'editor']);
    }
}
