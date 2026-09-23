<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Модель звонков для интеграции с Mango Office
 */
class Call extends Model
{
    protected $table = 'calls';
    protected $primaryKey = 'call_id';
    
    public $timestamps = false;
    
    const CREATED_AT = 'call_created_at';
    const UPDATED_AT = 'call_updated_at';
    
    protected $fillable = [
        'person_id',
        'order_id',
        'source_id',
        'phone',
        'direction',
        'status',
        'record_url',
        'mango_call_id',
        'internal_number',
        'duration',
        'call_created_at',
        'call_updated_at',
    ];
    
    /**
     * Поля, которые нельзя массово заполнять (устанавливаются явно в коде)
     * operator_id - оператор, совершивший звонок
     */
    
    protected $casts = [
        'duration' => 'integer',
        'call_created_at' => 'datetime',
        'call_updated_at' => 'datetime',
    ];
    
    /**
     * Связь с персоной (клиентом)
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }
    
    /**
     * Связь с заказом
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
    
    /**
     * Связь с оператором
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
    
    /**
     * Связь с источником (линия, на которую позвонили → источник заказа)
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'source_id');
    }
    
    /**
     * Входящий звонок?
     */
    public function isIncoming(): bool
    {
        return $this->direction === 'in';
    }
    
    /**
     * Исходящий звонок?
     */
    public function isOutgoing(): bool
    {
        return $this->direction === 'out';
    }
    
    /**
     * Получить человекочитаемое направление
     */
    public function getDirectionLabelAttribute(): string
    {
        return match ($this->direction) {
            'in' => 'Входящий',
            'out' => 'Исходящий',
            default => $this->direction ?? 'Неизвестно',
        };
    }
    
    /**
     * Получить человекочитаемый статус
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'initiated' => 'Инициирован',
            'connected' => 'Соединение',
            'talking' => 'Разговор',
            'result' => 'Завершён',
            'missed' => 'Пропущен',
            'busy' => 'Занято',
            'no_answer' => 'Нет ответа',
            'failed' => 'Ошибка',
            default => $this->status ?? 'Неизвестно',
        };
    }
    
    /**
     * Форматированная длительность звонка
     */
    public function getFormattedDurationAttribute(): string
    {
        if (!$this->duration) {
            return '—';
        }
        
        $minutes = floor($this->duration / 60);
        $seconds = $this->duration % 60;
        
        if ($minutes > 0) {
            return sprintf('%d мин %02d сек', $minutes, $seconds);
        }
        
        return sprintf('%d сек', $seconds);
    }
    
    /**
     * Форматированный номер телефона
     */
    public function getFormattedPhoneAttribute(): string
    {
        return \App\Helpers\PhoneHelper::format($this->phone);
    }
}
