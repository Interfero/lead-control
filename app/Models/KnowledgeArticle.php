<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeArticle extends Model
{
    protected $primaryKey = 'article_id';
    
    protected $fillable = [
        'article_title',
        'article_content',
        'content_format',
        'article_category',
        'sort_order',
        'is_published',
        'visible_roles',
    ];
    
    /**
     * Поля, которые нельзя массово заполнять (устанавливаются явно в коде)
     * created_by, updated_by - автор/редактор статьи
     */
    
    protected $casts = [
        'is_published' => 'boolean',
        'visible_roles' => 'array',
    ];
    
    /**
     * Автор статьи
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    /**
     * Кто последний редактировал
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
