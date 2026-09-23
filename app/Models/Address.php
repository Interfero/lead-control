<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Address extends Model
{
    protected $primaryKey = 'address_id';
    
    protected $fillable = [
        'person_id',
        'city_id',
        'street',
        'house',
        'flat',
        'address_adds',
    ];
    
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }
    
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }
    
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'address_id');
    }
    
    /**
     * Получить полный адрес строкой
     */
    public function getFullAddressAttribute(): string
    {
        $parts = [];
        
        if ($this->street) {
            $parts[] = $this->street;
        }
        
        if ($this->house) {
            $parts[] = "д. {$this->house}";
        }
        
        if ($this->flat) {
            $parts[] = "кв. {$this->flat}";
        }
        
        return implode(', ', $parts);
    }
}
