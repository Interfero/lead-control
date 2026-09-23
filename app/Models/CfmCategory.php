<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CfmCategory extends Model
{
    protected $table = 'cfm_categories';
    protected $primaryKey = 'cfm_cat_id';
    
    protected $fillable = [
        'cfm_cat_name',
        'cfm_cat_group',
        'cfm_cat_activities',
        'cfm_cat_adds',
        'is_auto',
        'is_visible',
        'visible_for_roles',
        'available_for_city',
        'available_for_mc',
        'available_for_df',
        'subcategories',
    ];
    
    protected $casts = [
        'is_auto' => 'boolean',
        'is_visible' => 'boolean',
        'available_for_city' => 'boolean',
        'available_for_mc' => 'boolean',
        'available_for_df' => 'boolean',
    ];
    
    public function operations(): HasMany
    {
        return $this->hasMany(CfmOperation::class, 'cfm_cat_id');
    }
    
    /**
     * Проверить, видна ли категория для роли
     */
    public function isVisibleForRole(string $roleCode): bool
    {
        if (!$this->is_visible) {
            return false;
        }
        
        if (empty($this->visible_for_roles)) {
            return true;
        }
        
        $roles = array_map('trim', explode(',', $this->visible_for_roles));
        return in_array($roleCode, $roles);
    }
    
    /**
     * Это категория поступления?
     */
    public function isInflow(): bool
    {
        return $this->cfm_cat_group === 'inflows';
    }
    
    /**
     * Это категория выбытия?
     */
    public function isOutflow(): bool
    {
        return $this->cfm_cat_group === 'outflows';
    }
    
    /**
     * Доступна ли категория для типа города?
     */
    public function isAvailableForCityType(string $cityType): bool
    {
        return match($cityType) {
            'city' => $this->available_for_city,
            'mc' => $this->available_for_mc,
            'df' => $this->available_for_df,
            default => false,
        };
    }
    
    /**
     * Получить список подкатегорий
     */
    public function getSubcategoriesArray(): array
    {
        if (empty($this->subcategories)) {
            return [];
        }
        
        $decoded = json_decode($this->subcategories, true);
        return is_array($decoded) ? $decoded : [];
    }
}
