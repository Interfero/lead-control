<?php

namespace App\Models\Hub;

use Illuminate\Database\Eloquent\Model;

/**
 * Подключение внешней CRM в Едином окне (таблица crm_connections приложения lead-desk).
 * Читаем только служебные поля состояния — логины/пароли источников Lead Control не трогает.
 *
 * @property int $id
 * @property string $name
 * @property string $type
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $last_sync_at
 * @property string|null $last_error
 */
class HubSourceConnection extends Model
{
    protected $table = 'crm_connections';

    public $timestamps = false;

    protected $casts = [
        'last_sync_at' => 'datetime',
    ];

    public function getConnectionName(): ?string
    {
        return (string) config('services.unified_hub.connection', 'hub');
    }
}
