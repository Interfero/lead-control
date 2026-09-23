<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MasterSchedule extends Model
{
    protected $table = 'master_schedules';
    protected $primaryKey = 'schedule_id';
    
    protected $fillable = [
        'user_id',
        'schedule_date',
        'is_working',
        'schedule_note',
    ];
    
    protected $casts = [
        'schedule_date' => 'date',
        'is_working' => 'boolean',
    ];
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
