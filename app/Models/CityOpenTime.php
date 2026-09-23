<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CityOpenTime extends Model
{
    protected $primaryKey = 'city_open_time_id';

    protected $fillable = [
        'city_id',
        'begin_date',
        'end_date',
        'time_from',
        'comment',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'begin_date' => 'date',
        'end_date' => 'date',
        'time_from' => 'integer',
    ];

    public const MIN_HOUR = 7;

    public const MAX_HOUR = 20;

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id', 'city_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by', 'user_id');
    }

    public function authorLabel(): string
    {
        return $this->editor?->user_name
            ?? $this->creator?->user_name
            ?? '—';
    }

    public function timeFromLabel(): string
    {
        return sprintf('%02d:00', $this->time_from);
    }

    public function summaryText(): string
    {
        $parts = [
            'Следующая заявка с '.$this->begin_date->format('d.m.Y').' по '.$this->end_date->format('d.m.Y'),
            'с '.$this->timeFromLabel(),
        ];

        if ($this->comment) {
            $parts[] = $this->comment;
        }

        return implode('. ', $parts);
    }
}
