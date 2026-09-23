<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeskOrderCache extends Model
{
    protected $table = 'orders_cache';

    protected $fillable = [
        'crm_id',
        'external_id',
        'city_id',
        'status',
        'raw_status',
        'client_name',
        'phone',
        'address',
        'description',
        'master_name',
        'total_amount',
        'paid_amount',
        'parts_amount',
        'created_at_local',
        'call_at_local',
        'timezone',
        'updated_at_local',
        'order_type',
        'gm_status',
        'comments',
        'documents',
        'priority',
        'row_highlight',
        'hash',
        'last_synced_at',
    ];

    protected $casts = [
        'documents' => 'array',
        'created_at_local' => 'datetime',
        'call_at_local' => 'datetime',
        'updated_at_local' => 'datetime',
        'last_synced_at' => 'datetime',
        'total_amount' => 'integer',
        'paid_amount' => 'integer',
        'parts_amount' => 'integer',
        'priority' => 'integer',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(CrmConnection::class, 'crm_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id', 'city_id');
    }

    public static function unifiedStatusLabels(): array
    {
        return [
            'new' => 'Новые',
            'in_progress' => 'В работе',
            'on_way' => 'В пути',
            'ready' => 'К закрытию',
        ];
    }

    public static function orderTypeLabels(): array
    {
        return [
            'first' => 'Впервые',
            'repeat' => 'Повтор',
            'warranty' => 'Гарантия',
        ];
    }
}
