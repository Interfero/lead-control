<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Comment extends Model
{
    protected $table = 'comments';
    protected $primaryKey = 'comment_id';

    // Только created_at, без updated_at (комментарии нельзя редактировать)
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'commentable_type',
        'commentable_id',
        'comment_text',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Автор комментария
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    /**
     * Полиморфная связь (User, Order и т.д.)
     */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }
}
