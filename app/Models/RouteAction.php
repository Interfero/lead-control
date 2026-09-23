<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RouteAction extends Model
{
    protected $table = 'route_actions';
    protected $primaryKey = 'route_action_id';
    
    /**
     * Получить ключ маршрута для route model binding
     */
    public function getRouteKeyName(): string
    {
        return 'route_action_id';
    }
    
    protected $fillable = [
        'route_id',
        'route_action_date',
        'maket_id',
        'assignee_user_id',
        'route_action_status',
        'route_action_note',
        'city_id',
        'promoter_id',
        'leaflets_count',
    ];
    
    /**
     * Поля, которые нельзя массово заполнять (устанавливаются явно в коде)
     * created_by - автор действия
     */
    
    protected $casts = [
        'route_action_date' => 'date',
        'leaflets_count' => 'integer',
    ];
    
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function promoter(): BelongsTo
    {
        return $this->belongsTo(Promoter::class, 'promoter_id');
    }
    
    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class, 'route_id');
    }
    
    public function maket(): BelongsTo
    {
        return $this->belongsTo(RouteMaket::class, 'maket_id');
    }
    
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }
    
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function paymentDetails(): HasMany
    {
        return $this->hasMany(PromPaymentDetail::class, 'route_action_id');
    }
}
