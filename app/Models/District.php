<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class District extends Model
{
    protected $primaryKey = 'district_id';
    
    protected $fillable = [
        'city_id',
        'district_name',
        'is_active',
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
    ];
    
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }
    
    public function routes(): HasMany
    {
        return $this->hasMany(Route::class, 'district_id');
    }
    
    public function appointments(): HasMany
    {
        return $this->hasMany(PromAppointment::class, 'district_id');
    }
}
