<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourcePhoneAlias extends Model
{
    protected $table = 'source_phone_aliases';

    protected $fillable = [
        'phone',
        'source_id',
        'note',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'source_id', 'source_id');
    }
}
