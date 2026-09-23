<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportCityDaily extends Model
{
    protected $table = 'report_city_daily';

    protected $fillable = [
        'city_id',
        'report_date',
        'accepted_count',
        'closed_total',
        'closed_our',
        'closed_partner',
        'turnover',
        'net',
        'parts',
        'complaints_count',
        'promo_pay',
        'warranty_closed',
        'repeat_closed',
        'non_core_closed',
        'refusals',
        'rejected',
        'stale',
        'rebuilt_at',
    ];

    protected $casts = [
        'report_date' => 'date',
        'stale' => 'boolean',
        'rebuilt_at' => 'datetime',
        'accepted_count' => 'integer',
        'closed_total' => 'integer',
        'closed_our' => 'integer',
        'closed_partner' => 'integer',
        'turnover' => 'integer',
        'net' => 'integer',
        'parts' => 'integer',
        'complaints_count' => 'integer',
        'promo_pay' => 'integer',
        'warranty_closed' => 'integer',
        'repeat_closed' => 'integer',
        'non_core_closed' => 'integer',
        'refusals' => 'integer',
        'rejected' => 'integer',
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id', 'city_id');
    }
}
