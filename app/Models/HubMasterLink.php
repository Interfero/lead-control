<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Соответствие мастера внешней CRM (KP-Lead) пользователю Lead Control.
 *
 * @property int $id
 * @property string $source
 * @property string|null $external_master_id
 * @property string|null $external_master_name
 * @property string|null $name_key
 * @property int|null $city_id
 * @property int $user_id
 * @property string $link_type
 * @property bool $is_active
 */
class HubMasterLink extends Model
{
    public const SOURCE_KP = 'kp_lead';

    public const TYPE_AUTO = 'auto';

    public const TYPE_MANUAL = 'manual';

    protected $table = 'hub_master_links';

    protected $fillable = [
        'source',
        'external_master_id',
        'external_master_name',
        'name_key',
        'city_id',
        'user_id',
        'link_type',
        'is_active',
        'note',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'city_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Нормализация ФИО для ключа поиска: нижний регистр, ё→е, «Фамилия Имя».
     * KP отдаёт «Цыганков Кирилл», в CRM он «Цыганков Кирилл Сергеевич» — ключ один.
     */
    public static function normalizeName(?string $name): ?string
    {
        $value = trim((string) $name);
        if ($value === '') {
            return null;
        }

        $value = mb_strtolower($value);
        $value = str_replace(['ё', 'Ё'], 'е', $value);
        $value = preg_replace('/[^\p{L}\p{N}\s\-]+/u', ' ', $value) ?? $value;
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        if ($value === '') {
            return null;
        }

        $parts = array_values(array_filter(explode(' ', $value)));
        $parts = array_slice($parts, 0, 2);

        return implode(' ', $parts);
    }
}
