<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class User extends Authenticatable
{
    use Notifiable;
    
    protected $primaryKey = 'user_id';
    
    protected $fillable = [
        'kp_employee_id',
        'user_name',
        'email',
        'password',
        'must_change_password',
        'password_set_at',
        'user_phone',
        'user_passport',
        'user_inn',
        'user_birth_date',
        'user_hired_at',
        'user_fired_at',
        'user_note',
        'is_active',
        'is_blacklisted',
        'blacklist_reason',
        'blacklisted_at',
        'blacklisted_by',
        'theme',
        'access_all_cities',
    ];
    
    protected $hidden = ['password', 'remember_token', 'two_factor_secret'];
    
    protected $casts = [
        'email_verified_at' => 'datetime',
        'user_birth_date' => 'date',
        'user_hired_at' => 'date',
        'user_fired_at' => 'date',
        'is_active' => 'boolean',
        'is_blacklisted' => 'boolean',
        'blacklisted_at' => 'datetime',
        'password' => 'hashed',
        'must_change_password' => 'boolean',
        'password_set_at' => 'datetime',
        'access_all_cities' => 'boolean',
        'two_factor_confirmed_at' => 'datetime',
    ];
    
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id');
    }
    
    public function cities(): BelongsToMany
    {
        return $this->belongsToMany(City::class, 'user_cities', 'user_id', 'city_id');
    }
    
    public function ordersAsMaster(): HasMany
    {
        return $this->hasMany(Order::class, 'master_id');
    }
    
    public function ordersCreated(): HasMany
    {
        return $this->hasMany(Order::class, 'order_created_by');
    }
    
    public function masterSchedules(): HasMany
    {
        return $this->hasMany(MasterSchedule::class, 'user_id');
    }
    
    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class, 'user_id');
    }
    
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
    
    /**
     * Комментарии к сотруднику
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
    
    /**
     * Звонки, обработанные пользователем (как оператором)
     */
    public function handledCalls(): HasMany
    {
        return $this->hasMany(Call::class, 'operator_id');
    }
    
    /**
     * Кто добавил в чёрный список
     */
    public function blacklistedByUser()
    {
        return $this->belongsTo(User::class, 'blacklisted_by', 'user_id');
    }
    
    /**
     * Проверка паспорта в чёрном списке
     */
    public static function isPassportBlacklisted(string $passport): ?User
    {
        return static::where('user_passport', $passport)
            ->where('is_blacklisted', true)
            ->first();
    }
    
    /**
     * Проверка наличия роли
     */
    public function hasRole(string $roleCode): bool
    {
        return $this->roles()->where('role_code', $roleCode)->exists();
    }

    /** Роли с обязательной 2FA (ТЗ §9.5). Вкл. через TWO_FACTOR_ENFORCE=true. */
    public function requiresTwoFactor(): bool
    {
        if (! config('security.two_factor_enforce', false)) {
            return false;
        }

        return $this->hasAnyRole(['developer', 'general_director']);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return filled($this->two_factor_secret) && $this->two_factor_confirmed_at !== null;
    }

    /**
     * Проверка наличия любой из ролей
     */
    public function hasAnyRole(array $roleCodes): bool
    {
        return $this->roles()->whereIn('role_code', $roleCodes)->exists();
    }
    
    /**
     * Роль разработчика — полный доступ ко всем разделам и городам.
     */
    public function isDeveloper(): bool
    {
        return $this->hasRole('developer');
    }

    /**
     * Проверка доступа к городу
     */
    public function hasAccessToCity(int $cityId): bool
    {
        if ($this->hasAnyRole(['developer', 'general_director'])) {
            return true;
        }

        // Диспетчеры — все города
        if ($this->hasAnyRole(['call_center', 'senior_dispatcher'])) {
            return true;
        }

        if ($this->access_all_cities) {
            return true;
        }

        $userCityIds = $this->cities()->pluck('cities.city_id')->all();
        foreach ($userCityIds as $userCityId) {
            if (in_array($cityId, City::operationGroupIds((int) $userCityId), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Для отображения в HR: все города (роль КЦ или флаг).
     */
    public function hasAllCitiesAccess(): bool
    {
        if ($this->hasAnyRole(['call_center', 'senior_dispatcher'])) {
            return true;
        }

        return (bool) $this->access_all_cities;
    }

    /**
     * ID городов для фильтрации данных. null — все города (разработчик / диспетчеры).
     *
     * @return array<int>|null
     */
    public function cityIdsForScope(): ?array
    {
        if ($this->hasAnyRole(['developer', 'general_director']) || $this->hasAllCitiesAccess()) {
            return null;
        }

        return $this->cities->pluck('city_id')->all();
    }

    /**
     * Города для списка/счётчика заказов: null = все; иначе pivot + кластеры спутников (НН).
     * Учитывает access_all_cities (раньше список смотрел только pivot → пустой список при флаге).
     *
     * @return array<int, int>|null
     */
    public function cityIdsForOrdersFilter(): ?array
    {
        $scope = $this->cityIdsForScope();
        if ($scope === null) {
            return null;
        }

        $expanded = [];
        foreach ($scope as $cityId) {
            foreach (City::operationGroupIds((int) $cityId) as $id) {
                $expanded[] = (int) $id;
            }
        }

        return array_values(array_unique($expanded));
    }

    /** Города для селектов и фильтров. */
    public function accessibleCities()
    {
        if ($this->hasAnyRole(['developer', 'general_director']) || $this->hasAllCitiesAccess()) {
            return City::where('is_active', true)->orderBy('city_name');
        }

        return $this->cities()->where('is_active', true)->orderBy('city_name');
    }

    public function isSeniorDispatcher(): bool
    {
        return $this->hasRole('senior_dispatcher');
    }

    public function isDispatcher(): bool
    {
        return $this->hasAnyRole(['call_center', 'senior_dispatcher']);
    }
    
    /**
     * Проверка доступности мастера для нового заказа
     * Мастер не может быть назначен, если:
     * - Условие А: 3+ заказов со статусами in_progress_sd, waiting_parts, waiting_payment
     * - Условие Б: 2+ заказов со статусом in_progress
     */
    public function isAvailableForNewOrder(): bool
    {
        // Условие А: 3+ заказов со статусами in_progress_sd, waiting_parts, waiting_payment
        $countA = Order::where('master_id', $this->user_id)
            ->whereIn('order_status', ['in_progress_sd', 'waiting_parts', 'waiting_payment'])
            ->whereNull('order_closed_at')
            ->count();
        
        if ($countA >= 3) return false;
        
        // Условие Б: 2+ заказов со статусом in_progress
        $countB = Order::where('master_id', $this->user_id)
            ->where('order_status', 'in_progress')
            ->whereNull('order_closed_at')
            ->count();
        
        if ($countB >= 2) return false;
        
        return true;
    }
}
