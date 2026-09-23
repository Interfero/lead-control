<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bank extends Model
{
    protected $primaryKey = 'bank_id';
    
    protected $fillable = [
        'bank_name',
        'is_active',
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
    ];
    
    public function promoters(): HasMany
    {
        return $this->hasMany(Promoter::class, 'bank_id');
    }
    
    public function payments(): HasMany
    {
        return $this->hasMany(PromPayment::class, 'payment_bank_id');
    }
}
