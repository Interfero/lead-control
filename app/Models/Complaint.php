<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Complaint extends Model
{
    protected $table = 'complaints';
    protected $primaryKey = 'complaint_id';
    public $timestamps = false;

    protected $fillable = [
        'person_id',
        'order_id',
        'city_id',
        'complaint_type',
        'complaint_text',
        'complaint_result',
        'complaint_status',
        'complaint_created_at',
        'complaint_created_by',
        'complaint_closed_at',
        'complaint_closed_by',
    ];

    protected $casts = [
        'complaint_created_at' => 'datetime',
        'complaint_closed_at' => 'datetime',
    ];

    // Статусы
    const STATUS_NEW = 'new';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_REJECTED = 'rejected';

    const STATUSES = [
        self::STATUS_NEW => 'Новая',
        self::STATUS_IN_PROGRESS => 'В работе',
        self::STATUS_RESOLVED => 'Решена',
        self::STATUS_REJECTED => 'Отклонена',
    ];

    // Типы претензий
    const TYPE_POLICE = 'police';
    const TYPE_REPAIR_QUALITY = 'repair_quality';
    const TYPE_NOT_FIXED = 'not_fixed';
    const TYPE_CHARGED_DIAGNOSTIC = 'charged_diagnostic';
    const TYPE_HIGH_PRICES = 'high_prices';
    const TYPE_NO_DOCUMENTS = 'no_documents';
    const TYPE_NO_PRICE_LIST = 'no_price_list';
    const TYPE_NO_DISCOUNT = 'no_discount';
    const TYPE_NO_RETURN_SD = 'no_return_sd';

    const TYPES = [
        self::TYPE_POLICE => '1) Полиция/Заявление/Обращение в суд',
        self::TYPE_REPAIR_QUALITY => '2) Недоволен ремонтом/Отказ от работ/Сломали технику',
        self::TYPE_NOT_FIXED => '3) Не устранили проблему/Оказали не те услуги',
        self::TYPE_CHARGED_DIAGNOSTIC => '4) Взяли деньги за выезд и диагностику',
        self::TYPE_HIGH_PRICES => '5) Завышенные цены на услуги/Завешенные цены на комплектующие',
        self::TYPE_NO_DOCUMENTS => '6) Не предоставили документы (Нет БСО/Нет электронных чеков/Нет чеков на комплектующие)',
        self::TYPE_NO_PRICE_LIST => '7) Не согласовали цену/Не показали прайс-лист',
        self::TYPE_NO_DISCOUNT => '8) Не предоставили скидку',
        self::TYPE_NO_RETURN_SD => '9) Не возвращают СД/Не звонят по СД',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id', 'person_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id', 'city_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'complaint_created_by', 'user_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'complaint_closed_by', 'user_id');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function complaintViews(): HasMany
    {
        return $this->hasMany(ComplaintView::class, 'complaint_id', 'complaint_id');
    }

    /**
     * Можно ли закрыть претензию
     */
    public function isCloseable(): bool
    {
        return in_array($this->complaint_status, [self::STATUS_NEW, self::STATUS_IN_PROGRESS]);
    }

    /**
     * Человекочитаемый статус
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->complaint_status] ?? $this->complaint_status;
    }

    /**
     * Человекочитаемый тип
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->complaint_type] ?? $this->complaint_type;
    }
}
