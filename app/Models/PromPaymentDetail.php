<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromPaymentDetail extends Model
{
    protected $primaryKey = 'detail_id';
    
    protected $fillable = [
        'payment_id',
        'route_action_id',
        'action_date',
        'leaflets_count',
        'route_name',
    ];
    
    protected $casts = [
        'action_date' => 'date',
        'leaflets_count' => 'integer',
    ];
    
    public function payment(): BelongsTo
    {
        return $this->belongsTo(PromPayment::class, 'payment_id');
    }
    
    public function routeAction(): BelongsTo
    {
        return $this->belongsTo(RouteAction::class, 'route_action_id');
    }
}
