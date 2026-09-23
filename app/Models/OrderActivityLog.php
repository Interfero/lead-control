<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'user_id',
        'action',
        'field_name',
        'old_value',
        'new_value',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'created' => 'Создание заказа',
            'updated' => 'Изменение',
            'shift' => 'Перенос встречи',
            'completed' => 'Проведение',
            'reopened' => 'Переоткрытие',
            default => $this->action,
        };
    }

    public function getDetailsAttribute(): string
    {
        if ($this->action === 'created' || $this->action === 'completed') {
            return (string) ($this->new_value ?? '—');
        }

        if ($this->action === 'reopened') {
            return 'Заказ снова открыт для редактирования';
        }

        if ($this->old_value && $this->new_value) {
            return $this->old_value.' → '.$this->new_value;
        }

        return (string) ($this->new_value ?? $this->old_value ?? '—');
    }
}
