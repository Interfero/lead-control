<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryCalculation extends Model
{
    protected $table = 'salary_calculations';

    protected $primaryKey = 'salary_calculation_id';

    public const STATUS_PRELIMINARY = 'preliminary';

    public const STATUS_FIXED = 'fixed';

    public const STATUS_NEEDS_RECALC = 'needs_recalc';

    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'city_id',
        'recipient_user_id',
        'period_month',
        'incas_base',
        'rate_percent',
        'commission_amount',
        'salary_amount',
        'accrued_amount',
        'policy_version',
        'calculation_version',
        'status',
        'error_message',
        'calc_code',
        'fixed_at',
        'recalc_requested_at',
    ];

    protected $casts = [
        'period_month' => 'date',
        'incas_base' => 'integer',
        'rate_percent' => 'integer',
        'commission_amount' => 'integer',
        'salary_amount' => 'integer',
        'accrued_amount' => 'integer',
        'policy_version' => 'integer',
        'calculation_version' => 'integer',
        'fixed_at' => 'datetime',
        'recalc_requested_at' => 'datetime',
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CfmOperation::class, 'salary_calculation_id', 'salary_calculation_id');
    }

    public function isPayable(): bool
    {
        return in_array($this->status, [self::STATUS_FIXED, self::STATUS_PRELIMINARY], true)
            && $this->status !== self::STATUS_ERROR
            && $this->status !== self::STATUS_NEEDS_RECALC
            && $this->incas_base >= 0
            && $this->error_message === null;
    }

    /** Предварительный (текущий) месяц — выплата запрещена. */
    public function allowsPayout(): bool
    {
        return $this->status === self::STATUS_FIXED && $this->error_message === null;
    }
}
