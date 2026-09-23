<?php

namespace App\Models\Hub;

use Illuminate\Database\Eloquent\Model;

/**
 * Заказ в кэше Единого хаба (таблица orders_cache приложения lead-desk).
 *
 * Только чтение: наполняет её синхронизация Единого окна, Lead Control лишь агрегирует.
 *
 * @property int $id
 * @property int $crm_id
 * @property string $external_id
 * @property int|null $city_id
 * @property string $status
 * @property string|null $raw_status
 * @property string|null $master_name
 * @property string|null $master_external_id
 */
class HubOrderCache extends Model
{
    protected $table = 'orders_cache';

    public $timestamps = false;

    protected $casts = [
        'documents' => 'array',
        'client_history' => 'array',
        'created_at_local' => 'datetime',
        'call_at_local' => 'datetime',
        'updated_at_local' => 'datetime',
        'last_synced_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'total_amount' => 'integer',
        'paid_amount' => 'integer',
        'parts_amount' => 'integer',
        'prepayment' => 'integer',
        'priority' => 'integer',
        'is_noncore' => 'boolean',
        'is_long_trip' => 'boolean',
        'is_satellite' => 'boolean',
        'is_partner_order' => 'boolean',
    ];

    public function getConnectionName(): ?string
    {
        return (string) config('services.unified_hub.connection', 'hub');
    }
}
