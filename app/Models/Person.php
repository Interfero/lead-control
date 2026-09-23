<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Person extends Model
{
    protected $table = 'persons';
    protected $primaryKey = 'person_id';
    
    protected $fillable = [
        'person_name',
        'person_age',
    ];
    
    protected $casts = [
        'person_age' => 'integer',
    ];
    
    public function phones(): HasMany
    {
        return $this->hasMany(PersonPhone::class, 'person_id');
    }
    
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class, 'person_id');
    }
    
    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'order_persons', 'person_id', 'order_id');
    }
    
    /**
     * Звонки персоны (история звонков)
     */
    public function calls(): HasMany
    {
        return $this->hasMany(Call::class, 'person_id');
    }
}
