<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonPhone extends Model
{
    protected $table = 'person_phones';
    protected $primaryKey = 'phone_id';
    
    protected $fillable = [
        'person_id',
        'phone_number',
        'phone_adds',
    ];
    
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }
    
    /**
     * Получить форматированный номер телефона
     */
    public function getFormattedPhoneAttribute(): string
    {
        return \App\Helpers\PhoneHelper::format($this->phone_number);
    }
}
