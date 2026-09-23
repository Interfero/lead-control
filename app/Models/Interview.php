<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Interview extends Model
{
    protected $primaryKey = 'interview_id';
    
    protected $fillable = [
        'user_id',
        'scheduled_at',
        'manager_user_id',
        'interview_status',
        'interview_note',
    ];
    
    protected $casts = [
        'scheduled_at' => 'datetime',
    ];
    
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }
}
