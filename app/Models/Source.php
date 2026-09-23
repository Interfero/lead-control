<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Source extends Model
{
    public const FORMAT_ONLINE = 'online';

    public const FORMAT_OFFLINE = 'offline';

    public const KIND_PARTY = 'party';

    public const KIND_FLYER = 'flyer';

    /** @return list<string> */
    public static function kindCodes(): array
    {
        return [
            self::KIND_FLYER,
            self::KIND_PARTY,
        ];
    }

    protected $primaryKey = 'source_id';

    protected $fillable = [
        'source_name',
        'source_phone',
        'source_format',
        'source_kind',
        'source_url',
        'use_source_url',
        'city_id',
        'flyer_maket_id',
        'superpart_partner_id',
        'superpart_local_source_id',
        'is_active',
        'available_for_superpart',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'available_for_superpart' => 'boolean',
        'use_source_url' => 'boolean',
        'superpart_partner_id' => 'integer',
        'superpart_local_source_id' => 'integer',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'source_id');
    }

    public function phoneAliases(): HasMany
    {
        return $this->hasMany(SourcePhoneAlias::class, 'source_id', 'source_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id', 'city_id');
    }

    public function flyerMaket(): BelongsTo
    {
        return $this->belongsTo(FlyerMaket::class, 'flyer_maket_id', 'flyer_maket_id');
    }

    public static function formatLabels(): array
    {
        return [
            self::FORMAT_ONLINE => 'Онлайн',
            self::FORMAT_OFFLINE => 'Офлайн',
        ];
    }

    public static function kindLabels(): array
    {
        return [
            self::KIND_FLYER => 'Листовки',
            self::KIND_PARTY => 'Парты',
        ];
    }

    public function kindLabel(): ?string
    {
        $legacy = [
            'rk' => 'Листовки',
            'partner_flyer' => 'Парты',
        ];

        return self::kindLabels()[$this->source_kind]
            ?? $legacy[$this->source_kind]
            ?? $this->source_kind;
    }

    public function isPartyKind(): bool
    {
        return $this->source_kind === self::KIND_PARTY;
    }

    public function isFlyerKind(): bool
    {
        return $this->source_kind === self::KIND_FLYER;
    }

    /**
     * Парт-канал SuperPart: заказ приходит с source_id, DID/Mango для атрибуции не используется.
     * Для §10.2 шаг 4 телефон линии не обязателен (решение 2026-07-20).
     */
    public function isExemptFromDidPhoneRequirement(): bool
    {
        if ($this->source_kind !== self::KIND_PARTY) {
            return false;
        }

        return (bool) $this->available_for_superpart
            || $this->superpart_local_source_id !== null;
    }

    /**
     * Активные РК, которым для DID→Source нужен source_phone (не каталог SuperPart).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Source>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Source>
     */
    public function scopeRequiresDidPhone($query)
    {
        return $query->whereRaw(
            'NOT (source_kind = ? AND (COALESCE(available_for_superpart, 0) = 1 OR superpart_local_source_id IS NOT NULL))',
            [self::KIND_PARTY]
        );
    }

    /**
     * Парт-источники SuperPart без требования DID-телефона.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Source>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Source>
     */
    public function scopeExemptFromDidPhone($query)
    {
        return $query->where('source_kind', self::KIND_PARTY)
            ->where(function ($q) {
                $q->where('available_for_superpart', true)
                    ->orWhereNotNull('superpart_local_source_id');
            });
    }

    public function formatLabel(): ?string
    {
        if (! $this->source_format) {
            return null;
        }

        return self::formatLabels()[$this->source_format] ?? $this->source_format;
    }

    /** Название РК для Desk/копирования — без хвоста «· Парты». */
    public function deskRkName(): string
    {
        return trim((string) ($this->source_name ?? ''));
    }

    /** Ссылка на РК для парт-источников (если включена в карточке источника). */
    public function deskRkUrl(): ?string
    {
        if (! $this->isPartyKind()) {
            return null;
        }
        if (! $this->use_source_url) {
            return null;
        }
        $url = trim((string) ($this->source_url ?? ''));

        return $url !== '' ? $url : null;
    }

    /**
     * Подпись для селектов при неуникальных названиях: название · город · телефон · формат.
     */
    protected function displayLabel(): Attribute
    {
        return Attribute::get(function (): string {
            $parts = array_filter([$this->source_name]);
            if ($this->relationLoaded('city') && $this->city) {
                $parts[] = $this->city->city_name;
            }
            if ($this->source_phone) {
                $parts[] = $this->source_phone;
            }
            if ($this->source_format) {
                $parts[] = $this->formatLabel();
            }
            if ($this->source_kind) {
                $parts[] = $this->kindLabel();
            }
            if (count($parts) === 1) {
                return $parts[0];
            }

            return implode(' · ', $parts);
        });
    }
}
