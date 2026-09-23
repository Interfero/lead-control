<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromMeeting extends Model
{
    protected $primaryKey = 'meeting_id';
    
    protected $fillable = [
        'meeting_datetime',
        'city_id',
        'person_phone',
        'person_telegram',
        'person_age',
        'person_name',
        'person_address',
        'is_interview',
        'route_id',
        'maket_id',
        'leaflets_issued',
        'person_source',
        'meeting_status',
        'meeting_comment',
        'promoter_id',
    ];
    
    /**
     * Поля, которые нельзя массово заполнять (устанавливаются явно в коде)
     * created_by - автор записи
     */
    
    // Источники промоутера
    const SOURCE_HEAD_HUNTER = 'head_hunter';
    const SOURCE_OLX = 'olx';
    const SOURCE_RECOMMENDATION = 'recommendation';
    
    public static function getSourceLabels(): array
    {
        return [
            self::SOURCE_HEAD_HUNTER => 'Head Hunter',
            self::SOURCE_OLX => 'OLX',
            self::SOURCE_RECOMMENDATION => 'Рекомендация',
        ];
    }
    
    protected $casts = [
        'meeting_datetime' => 'datetime',
        'is_interview' => 'boolean',
        'leaflets_issued' => 'integer',
    ];
    
    // Константы статусов
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_REFUSAL = 'refusal';
    const STATUS_RETURN = 'return';
    const STATUS_NOT_RETURNED = 'not_returned';
    const STATUS_IGNORED = 'ignored';
    
    // Статусы, при которых промоутер считается уволенным
    const FIRED_STATUSES = [
        self::STATUS_REFUSAL,
        self::STATUS_RETURN,
        self::STATUS_NOT_RETURNED,
        self::STATUS_IGNORED,
    ];
    
    public static function getStatusLabels(): array
    {
        return [
            self::STATUS_IN_PROGRESS => 'В работе',
            self::STATUS_COMPLETED => 'Завершено',
            self::STATUS_REFUSAL => 'Отказ',
            self::STATUS_RETURN => 'Возврат',
            self::STATUS_NOT_RETURNED => 'Не сдал',
            self::STATUS_IGNORED => 'Игнор',
        ];
    }
    
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }
    
    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class, 'route_id');
    }
    
    public function maket(): BelongsTo
    {
        return $this->belongsTo(RouteMaket::class, 'maket_id');
    }
    
    public function promoter(): BelongsTo
    {
        return $this->belongsTo(Promoter::class, 'promoter_id');
    }
    
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    /**
     * Проверка, является ли статус "уволен"
     */
    public function isFiredStatus(): bool
    {
        return in_array($this->meeting_status, self::FIRED_STATUSES);
    }
}
