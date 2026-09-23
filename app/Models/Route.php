<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Route extends Model
{
    protected $primaryKey = 'route_id';
    
    protected $fillable = [
        'route_name',
        'route_type',
        'is_training',
        'route_boxes_count',
        'route_entrances_count',
        'route_apartments_count',
        'is_active',
        'route_note',
        'city_id',
        'district_id',
    ];
    
    protected $casts = [
        'is_training' => 'boolean',
        'route_boxes_count' => 'integer',
        'route_entrances_count' => 'integer',
        'route_apartments_count' => 'integer',
        'is_active' => 'boolean',
    ];
    
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id');
    }
    
    public function actions(): HasMany
    {
        return $this->hasMany(RouteAction::class, 'route_id');
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(PromMeeting::class, 'route_id');
    }

    /**
     * Получить количество прохождений маршрута
     */
    public function getPassesCount(): int
    {
        return $this->actions()->count();
    }

    /**
     * Получить дату последнего прохождения
     */
    public function getLastPassDate(): ?Carbon
    {
        $lastAction = $this->actions()->latest('route_action_date')->first();
        return $lastAction ? Carbon::parse($lastAction->route_action_date) : null;
    }

    /**
     * Получить коэффициент сложности
     * (Квартиры / Подъезды * 100)
     */
    public function getComplexityCoefficient(): int
    {
        if ($this->route_entrances_count === 0) {
            return 0;
        }
        return (int) (($this->route_apartments_count / $this->route_entrances_count) * 100);
    }

    /**
     * Получить коэффициент разноски
     * (Всего разнесено листовок / Квартиры * 100)
     */
    public function getDeliveryCoefficient(): int
    {
        if ($this->route_apartments_count === 0) {
            return 0;
        }
        $totalLeaflets = $this->actions()->sum('leaflets_count');
        return (int) (($totalLeaflets / $this->route_apartments_count) * 100);
    }
}
