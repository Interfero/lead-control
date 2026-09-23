<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    protected $primaryKey = 'city_id';
    
    protected $fillable = ['city_name', 'city_type', 'city_timezone', 'city_inn', 'parent_city_id', 'is_active'];
    
    protected $casts = ['is_active' => 'boolean'];
    
    /**
     * Это обычный город?
     */
    public function isRegularCity(): bool
    {
        return $this->city_type === 'city';
    }
    
    /**
     * Это Управляющая компания?
     */
    public function isManagementCompany(): bool
    {
        return $this->city_type === 'mc';
    }
    
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_cities', 'city_id', 'user_id');
    }
    
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class, 'city_id');
    }
    
    public function cfmOperations(): HasMany
    {
        return $this->hasMany(CfmOperation::class, 'city_id');
    }

    public function parentCity(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_city_id', 'city_id');
    }

    public function childCities(): HasMany
    {
        return $this->hasMany(self::class, 'parent_city_id', 'city_id');
    }

    /** Город-спутник (привязан к материнскому филиалу). */
    public function isSatellite(): bool
    {
        return $this->parent_city_id !== null;
    }

    /**
     * Короткое имя без суффиксов «(родитель)» / «(МСК)» — для поиска в тексте заказа.
     */
    public static function baseNameFromDisplay(string $cityName): string
    {
        $name = trim($cityName);
        // «Коммунарка (Подольск) (МСК)» / «Кисловодск (Пятигорск)» → до первой скобки
        if (preg_match('/^(.+?)\s*\(/u', $name, $m)) {
            return trim($m[1]);
        }

        return $name;
    }

    /**
     * В city_name уже есть родитель: «Адлер (Сочи)» / «Коммунарка (Подольск) (МСК)».
     */
    public function nameIncludesParent(?string $parentName = null): bool
    {
        $parentName = $parentName ?? $this->parentCity?->city_name;
        if ($parentName === null || $parentName === '') {
            return false;
        }

        $name = (string) $this->city_name;
        $parentBase = self::baseNameFromDisplay($parentName);

        return str_contains($name, '('.$parentName.')')
            || ($parentBase !== '' && str_contains($name, '('.$parentBase.')'));
    }

    /**
     * Подпись для отчётов/списков без дубля скобок.
     * Спутник в БД уже «Адлер (Сочи)» — повторно родителя не дописываем.
     */
    public function displayName(): string
    {
        $this->loadMissing('parentCity');

        if (! $this->parentCity) {
            return (string) $this->city_name;
        }

        if ($this->nameIncludesParent()) {
            return (string) $this->city_name;
        }

        return $this->city_name.' ('.$this->parentCity->city_name.')';
    }

    /**
     * Названия спутников материнского города (для автоопределения в тексте заказа).
     * Возвращает и короткое имя, и полное отображаемое (lower).
     *
     * @return list<string>
     */
    public static function satelliteNamesForParent(int $parentCityId): array
    {
        static $cache = [];

        if (! isset($cache[$parentCityId])) {
            $names = [];
            self::query()
                ->where('parent_city_id', $parentCityId)
                ->where('is_active', true)
                ->orderBy('city_name')
                ->pluck('city_name')
                ->each(function ($name) use (&$names) {
                    $full = mb_strtolower(trim((string) $name));
                    $base = mb_strtolower(self::baseNameFromDisplay((string) $name));
                    if ($full !== '') {
                        $names[] = $full;
                    }
                    if ($base !== '' && $base !== $full) {
                        $names[] = $base;
                    }
                });

            $cache[$parentCityId] = array_values(array_unique(array_filter($names)));
        }

        return $cache[$parentCityId];
    }

    public function sources(): HasMany
    {
        return $this->hasMany(Source::class, 'city_id');
    }

    public function openTimes(): HasMany
    {
        return $this->hasMany(CityOpenTime::class, 'city_id', 'city_id');
    }

    /**
     * @return array<int, int>
     */
    public static function nizhnyNovgorodCityId(): ?int
    {
        static $id = null;
        if ($id === null) {
            $found = self::query()->where('city_name', 'Нижний Новгород')->value('city_id');
            $id = $found ? (int) $found : 0;
        }

        return $id ?: null;
    }

    /**
     * Город + материнский + все спутники кластера (любой parent_city_id).
     *
     * @return array<int, int>
     */
    public static function operationGroupIds(int $cityId): array
    {
        static $cache = [];
        if (isset($cache[$cityId])) {
            return $cache[$cityId];
        }

        $city = self::query()->find($cityId);
        if (! $city) {
            return $cache[$cityId] = [$cityId];
        }

        $rootId = $city->parent_city_id
            ? (int) $city->parent_city_id
            : (int) $city->city_id;

        $hasCluster = $city->parent_city_id !== null
            || self::query()->where('parent_city_id', $rootId)->exists();

        if (! $hasCluster) {
            return $cache[$cityId] = [$cityId];
        }

        $ids = [$rootId];
        $ids = array_merge(
            $ids,
            self::query()->where('parent_city_id', $rootId)->pluck('city_id')->all()
        );

        return $cache[$cityId] = array_values(array_unique(array_map('intval', $ids)));
    }
}
