<?php

namespace App\Services;

use App\Helpers\PhoneHelper;
use Illuminate\Support\Collection;

class SearchService
{
    /**
     * Поиск по адресу с разбиением на части
     * Поддержка кириллицы, регистронезависимый поиск
     * 
     * @param Collection $items Коллекция с полями для поиска
     * @param string $query Поисковый запрос
     * @param array $searchFields Поля для поиска ['street', 'house', 'flat']
     * @return Collection Отфильтрованная коллекция
     */
    public function searchByAddress(Collection $items, string $query, array $searchFields = ['street', 'house', 'flat']): Collection
    {
        if (empty($query)) {
            return $items;
        }
        
        // Разбиваем запрос на части (слова и числа)
        $parts = preg_split('/[\s,]+/u', mb_strtolower(trim($query)), -1, PREG_SPLIT_NO_EMPTY);
        
        if (empty($parts)) {
            return $items;
        }
        
        return $items->filter(function ($item) use ($parts, $searchFields) {
            // Для каждой части запроса проверяем наличие в указанных полях
            foreach ($parts as $part) {
                $found = false;
                
                foreach ($searchFields as $field) {
                    $fieldValue = data_get($item, $field);
                    
                    if ($fieldValue && mb_stripos(mb_strtolower($fieldValue), $part) !== false) {
                        $found = true;
                        break;
                    }
                }
                
                // Если хотя бы одна часть не найдена - пропускаем элемент
                if (!$found) {
                    return false;
                }
            }
            
            return true;
        });
    }
    
    /**
     * Поиск по имени/названию
     * Регистронезависимый, поддержка частичного совпадения
     * 
     * @param Collection $items Коллекция для поиска
     * @param string $query Поисковый запрос
     * @param string $field Поле для поиска (по умолчанию 'name')
     * @return Collection Отфильтрованная коллекция
     */
    public function searchByName(Collection $items, string $query, string $field = 'name'): Collection
    {
        if (empty($query)) {
            return $items;
        }
        
        $queryLower = mb_strtolower(trim($query));
        
        return $items->filter(function ($item) use ($queryLower, $field) {
            $fieldValue = data_get($item, $field);
            
            if (!$fieldValue) {
                return false;
            }
            
            return mb_stripos(mb_strtolower($fieldValue), $queryLower) !== false;
        });
    }
    
    /**
     * Очистка телефонного номера от нецифровых символов
     * 
     * @param string $phone Телефон
     * @return string Только цифры
     */
    public function cleanPhone(string $phone): string
    {
        return PhoneHelper::clean($phone);
    }
    
    /**
     * Поиск по телефону
     * 
     * @param Collection $items Коллекция для поиска
     * @param string $query Поисковый запрос (телефон)
     * @param string $field Поле для поиска (по умолчанию 'phone')
     * @return Collection Отфильтрованная коллекция
     */
    public function searchByPhone(Collection $items, string $query, string $field = 'phone'): Collection
    {
        if (empty($query)) {
            return $items;
        }
        
        $cleanedQuery = $this->cleanPhone($query);
        
        if (empty($cleanedQuery)) {
            return $items;
        }
        
        return $items->filter(function ($item) use ($cleanedQuery, $field) {
            $fieldValue = data_get($item, $field);
            
            if (!$fieldValue) {
                return false;
            }
            
            $cleanedValue = $this->cleanPhone($fieldValue);
            
            return str_contains($cleanedValue, $cleanedQuery);
        });
    }
}
