<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FlyerMaket extends Model
{
    protected $table = 'flyer_makets';

    protected $primaryKey = 'flyer_maket_id';

    protected $fillable = [
        'flyer_maket_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function sources(): HasMany
    {
        return $this->hasMany(Source::class, 'flyer_maket_id');
    }
}
