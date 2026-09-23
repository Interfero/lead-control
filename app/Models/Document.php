<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\UploadedFile;

class Document extends Model
{
    /** @var list<string> */
    public const ORDER_PHOTO_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif'];

    protected $primaryKey = 'document_id';
    
    protected $fillable = [
        'documentable_type',
        'documentable_id',
        'document_category',
        'file_name',
        'file_path',
        'file_mime',
        'file_size',
        'is_verified',
        'verified_by',
        'verified_at',
    ];
    
    /**
     * Поля, которые нельзя массово заполнять (устанавливаются явно в коде)
     * uploaded_by - кто загрузил документ
     */
    
    protected $casts = [
        'file_size' => 'integer',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];
    
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }
    
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
    
    /**
     * Кто проверил документ
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by', 'user_id');
    }
    
    /**
     * Получить человекочитаемый размер файла
     */
    public function getHumanSizeAttribute(): string
    {
        $bytes = $this->file_size;
        
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }
        
        return round($bytes / 1048576, 1) . ' MB';
    }

    public static function orderPhotoMimeValidationRule(): string
    {
        return 'mimes:'.implode(',', self::ORDER_PHOTO_EXTENSIONS);
    }

    public static function orderPhotoAcceptAttribute(): string
    {
        return 'image/jpeg,image/png,image/gif,.jpg,.jpeg,.png,.gif';
    }

    public static function orderPhotoFormatsLabel(): string
    {
        return implode(', ', self::ORDER_PHOTO_EXTENSIONS);
    }

    public static function isAllowedOrderPhoto(UploadedFile $file): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, self::ORDER_PHOTO_EXTENSIONS, true)) {
            return false;
        }

        $mimeType = (string) $file->getMimeType();

        return str_starts_with($mimeType, 'image/');
    }
}
