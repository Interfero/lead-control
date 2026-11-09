<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Уведомление службы безопасности для руководства (показывается баннером, пока не отмечено прочитанным). */
class SecurityAlert extends Model
{
    public $timestamps = false;

    protected $fillable = ['type', 'user_id', 'message', 'meta', 'seen_by', 'seen_at', 'created_at'];

    protected $casts = ['meta' => 'array', 'seen_at' => 'datetime', 'created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
