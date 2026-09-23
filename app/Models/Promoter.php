<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promoter extends Model
{
    protected $primaryKey = 'promoter_id';
    
    protected $fillable = [
        'city_id',
        'promoter_name',
        'promoter_phone',
        'promoter_telegram',
        'promoter_age',
        'promoter_address',
        'promoter_requisites',
        'bank_id',
        'promoter_status',
        'hired_at',
        'fired_at',
        'promoter_comment',
        'source_id',
    ];
    
    /**
     * Поля, которые нельзя массово заполнять (устанавливаются явно в коде)
     * created_by - кто создал промоутера
     */
    
    protected $casts = [
        'hired_at' => 'date',
        'fired_at' => 'date',
    ];
    
    // Константы статусов
    const STATUS_ACTIVE = 'active';
    const STATUS_FIRED = 'fired';
    
    public static function getStatusLabels(): array
    {
        return [
            self::STATUS_ACTIVE => 'Активен',
            self::STATUS_FIRED => 'Уволен',
        ];
    }
    
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }
    
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }
    
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'source_id');
    }
    
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    public function meetings(): HasMany
    {
        return $this->hasMany(PromMeeting::class, 'promoter_id');
    }
    
    public function routeActions(): HasMany
    {
        return $this->hasMany(RouteAction::class, 'promoter_id');
    }
    
    public function payments(): HasMany
    {
        return $this->hasMany(PromPayment::class, 'promoter_id');
    }
    
    /**
     * Проверка, активен ли промоутер
     */
    public function isActive(): bool
    {
        return $this->promoter_status === self::STATUS_ACTIVE;
    }
    
    /**
     * Уволить промоутера
     */
    public function fire(): void
    {
        $this->update([
            'promoter_status' => self::STATUS_FIRED,
            'fired_at' => now(),
        ]);
    }
}
