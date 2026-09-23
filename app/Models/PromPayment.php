<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromPayment extends Model
{
    protected $primaryKey = 'payment_id';
    
    protected $fillable = [
        'city_id',
        'promoter_id',
        'week_start',
        'week_end',
        'total_leaflets',
        'rate_per_leaflet',
        'amount_base',
        'amount_adjustment',
        'amount_total',
        'payment_requisites',
        'payment_bank_id',
        'payment_comment',
        'payment_status',
        'cfm_operation_id',
        'paid_at',
    ];
    
    /**
     * Поля, которые нельзя массово заполнять (устанавливаются явно в коде)
     * created_by - автор выплаты
     */
    
    protected $casts = [
        'week_start' => 'date',
        'week_end' => 'date',
        'total_leaflets' => 'integer',
        'rate_per_leaflet' => 'integer',
        'amount_base' => 'integer',
        'amount_adjustment' => 'integer',
        'amount_total' => 'integer',
        'paid_at' => 'datetime',
    ];
    
    // Константы статусов
    const STATUS_CREATED = 'created';
    const STATUS_PAID = 'paid';
    
    // Шкала оплаты за листовки
    const RATE_TIERS = [
        ['min' => 10001, 'rate' => 12],
        ['min' => 5001, 'rate' => 9],
        ['min' => 1256, 'rate' => 6],
        ['min' => 1, 'rate' => 3],
    ];
    
    public static function getStatusLabels(): array
    {
        return [
            self::STATUS_CREATED => 'Сформировано',
            self::STATUS_PAID => 'Оплачено',
        ];
    }
    
    /**
     * Рассчитать ставку за листовку по количеству
     */
    public static function calculateRate(int $leaflets): int
    {
        foreach (self::RATE_TIERS as $tier) {
            if ($leaflets >= $tier['min']) {
                return $tier['rate'];
            }
        }
        return 0;
    }
    
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }
    
    public function promoter(): BelongsTo
    {
        return $this->belongsTo(Promoter::class, 'promoter_id');
    }
    
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'payment_bank_id');
    }
    
    public function cfmOperation(): BelongsTo
    {
        return $this->belongsTo(CfmOperation::class, 'cfm_operation_id', 'cfm_id');
    }
    
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    public function details(): HasMany
    {
        return $this->hasMany(PromPaymentDetail::class, 'payment_id');
    }
    
    /**
     * Получить период в формате "dd.MM - dd.MM.YYYY"
     */
    public function getWeekLabel(): string
    {
        return $this->week_start->format('d.m') . ' - ' . $this->week_end->format('d.m.Y');
    }
}
