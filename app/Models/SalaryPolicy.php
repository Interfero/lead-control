<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryPolicy extends Model
{
    protected $table = 'salary_policies';

    protected $primaryKey = 'salary_policy_id';

    protected $fillable = [
        'city_id',
        'salary_enabled',
        'salary_amount',
        'effective_month',
        'version',
        'created_by',
    ];

    protected $casts = [
        'salary_enabled' => 'boolean',
        'salary_amount' => 'integer',
        'effective_month' => 'date',
        'version' => 'integer',
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
