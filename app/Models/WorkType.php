<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Справочник видов работ — заменяет хардкод Order::EQUIPMENT_TYPES/CORE_EQUIPMENT (см. миграцию
 * 2026_10_08_160000). `code` — тот же строковый код, что раньше был ключом массива: Order::equipment_type
 * хранит именно его, и он же уходит в SuperPart/GM/Desk как crm_code/equipmentType — числовой
 * work_type_id используется только внутри CRM (эта таблица), наружу не отдаётся.
 */
class WorkType extends Model
{
    protected $primaryKey = 'work_type_id';

    protected $fillable = [
        'code', 'name', 'order_core', 'partner_rate', 'partner_cap', 'master_rate', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'partner_rate' => 'decimal:2',
        'partner_cap' => 'decimal:2',
        'master_rate' => 'decimal:2',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /** code => name, только активные — для подписей в селектах/дампов в JS. */
    public static function activeOptions(): array
    {
        return static::active()->ordered()->pluck('name', 'code')->all();
    }

    /** code => order_core, только активные — заменяет Order::CORE_EQUIPMENT в клиентском JS. */
    public static function activeOrderCoreMap(): array
    {
        return static::active()->pluck('order_core', 'code')->all();
    }

    /**
     * code => name по ВСЕМ записям (включая деактивированные) — для подписи уже стоящего в старой
     * заявке значения, которое могло исчезнуть из активного списка. Используется в логе изменений
     * и на странице заявки, где нужно показать подпись, даже если вид работ потом деактивировали.
     */
    public static function allLabelsMap(): array
    {
        static $cache = null;
        if ($cache === null) {
            $cache = static::query()->pluck('name', 'code')->all();
        }

        return $cache;
    }

    public static function labelFor(?string $code): string
    {
        if ($code === null) {
            return '';
        }

        return static::allLabelsMap()[$code] ?? $code;
    }
}
