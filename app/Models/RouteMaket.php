<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RouteMaket extends Model
{
    protected $table = 'route_makets';
    protected $primaryKey = 'maket_id';
    
    protected $fillable = [
        'maket_name',
        'is_active',
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
    ];
    
    public function actions(): HasMany
    {
        return $this->hasMany(RouteAction::class, 'maket_id');
    }
}
