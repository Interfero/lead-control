<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CfmOperation extends Model
{
    protected $table = 'cfm_operations';
    protected $primaryKey = 'cfm_id';
    
    protected $fillable = [
        'city_id',
        'cfm_cat_id',
        'amount_cfm',
        'amount_from_master',
        'cfm_adds',
        'cfm_subcat',
        'cfm_payer',
        'cfm_recipient',
        'external_cfm_ref',
        'cfm_created_at',
        'cfm_closed_at',
        'related_order_id',
        'related_city_id',
        'related_user_id',
        'salary_calculation_id',
    ];
    
    /**
     * Поля, которые нельзя массово заполнять (устанавливаются явно в коде)
     * cfm_created_by, cfm_closed_by - автор/закрывший операцию
     */
    
    protected $casts = [
        'amount_cfm' => 'integer',
        'amount_from_master' => 'integer',
        'cfm_created_at' => 'datetime',
        'cfm_closed_at' => 'datetime',
    ];

    /** Полная сумма возврата клиенту (касса + мастер). */
    public function getRefundTotalAttribute(): ?int
    {
        if ($this->amount_from_master === null) {
            return null;
        }

        return (int) $this->amount_cfm + (int) $this->amount_from_master;
    }
    
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }
    
    public function category(): BelongsTo
    {
        return $this->belongsTo(CfmCategory::class, 'cfm_cat_id');
    }
    
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cfm_created_by');
    }
    
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cfm_closed_by');
    }
    
    public function relatedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'related_order_id');
    }
    
    public function relatedCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'related_city_id');
    }
    
    public function relatedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'related_user_id');
    }

    public function salaryCalculation(): BelongsTo
    {
        return $this->belongsTo(SalaryCalculation::class, 'salary_calculation_id');
    }
    
    /**
     * Документы операции
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(\App\Models\Document::class, 'documentable');
    }
    
    /**
     * Проверить, проведена ли операция
     */
    public function isClosed(): bool
    {
        return $this->cfm_closed_at !== null;
    }
    
    /**
     * Получить знаковую сумму (+ для поступлений, - для выбытий)
     */
    public function getSignedAmountAttribute(): int
    {
        if ($this->category && $this->category->isOutflow()) {
            return -$this->amount_cfm;
        }
        
        return $this->amount_cfm;
    }
}
