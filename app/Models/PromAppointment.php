<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromAppointment extends Model
{
    protected $primaryKey = 'appointment_id';
    
    protected $fillable = [
        'appointment_datetime',
        'city_id',
        'is_interview',
        'person_phone',
        'person_telegram',
        'person_name',
        'district_id',
        'leaflets_to_prepare',
        'appointment_status',
        'appointment_comment',
    ];
    
    /**
     * Поля, которые нельзя массово заполнять (устанавливаются явно в коде)
     * created_by - автор записи
     */
    
    protected $casts = [
        'appointment_datetime' => 'datetime',
        'is_interview' => 'boolean',
        'leaflets_to_prepare' => 'integer',
    ];
    
    // Константы статусов
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_ATTENDED = 'attended';
    const STATUS_REFUSAL = 'refusal';
    const STATUS_IGNORED = 'ignored';
    
    public static function getStatusLabels(): array
    {
        return [
            self::STATUS_SCHEDULED => 'Запись',
            self::STATUS_ATTENDED => 'Встреча',
            self::STATUS_REFUSAL => 'Отказ',
            self::STATUS_IGNORED => 'Игнор',
        ];
    }
    
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }
    
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id');
    }
    
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
