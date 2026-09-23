<?php

namespace App\Helpers;

/**
 * Вспомогательный класс для работы с телефонными номерами
 * 
 * Нормализует, форматирует и валидирует телефонные номера
 */
class PhoneHelper
{
    /**
     * Нормализовать телефонный номер до 10 цифр
     * Удаляет все нецифровые символы и код страны (7 или 8)
     * 
     * @param string|null $phone Телефон в любом формате
     * @return string 10-значный номер или пустая строка
     */
    public static function normalize(?string $phone): string
    {
        if (empty($phone)) {
            return '';
        }
        
        // Удаляем все нецифровые символы
        $digits = preg_replace('/\D/', '', $phone);
        
        // Если 11 цифр и начинается с 7 или 8 — убираем код страны
        if (strlen($digits) === 11 && in_array($digits[0], ['7', '8'])) {
            $digits = substr($digits, 1);
        }
        
        return $digits;
    }
    
    /**
     * Очистить телефон (только цифры)
     * 
     * @param string|null $phone Телефон в любом формате
     * @return string Только цифры
     */
    public static function clean(?string $phone): string
    {
        if (empty($phone)) {
            return '';
        }
        
        return preg_replace('/\D/', '', $phone);
    }
    
    /**
     * Форматировать телефон для отображения
     * Формат: +7 (XXX) XXX-XX-XX
     * 
     * @param string|null $phone 10-значный номер
     * @return string Отформатированный номер
     */
    public static function format(?string $phone): string
    {
        if (empty($phone)) {
            return '';
        }
        
        $normalized = self::normalize($phone);
        
        if (strlen($normalized) !== 10) {
            return $phone; // Возвращаем как есть, если не 10 цифр
        }
        
        return sprintf(
            '+7 (%s) %s-%s-%s',
            substr($normalized, 0, 3),
            substr($normalized, 3, 3),
            substr($normalized, 6, 2),
            substr($normalized, 8, 2)
        );
    }
    
    /**
     * Проверить, является ли номер валидным российским/казахстанским
     * 
     * @param string|null $phone Телефон в любом формате
     * @return bool
     */
    public static function isValid(?string $phone): bool
    {
        if (empty($phone)) {
            return false;
        }
        
        $normalized = self::normalize($phone);
        
        // Должно быть ровно 10 цифр
        if (strlen($normalized) !== 10) {
            return false;
        }
        
        // Первая цифра должна быть 9 (мобильные) или 3-8 (стационарные)
        $firstDigit = $normalized[0];
        
        return in_array($firstDigit, ['3', '4', '5', '6', '7', '8', '9']);
    }
    
    /**
     * Маскировать телефон для логов и отображения
     * Формат: 9XX***XX67
     * 
     * @param string|null $phone 10-значный номер
     * @return string Замаскированный номер
     */
    public static function mask(?string $phone): string
    {
        if (empty($phone)) {
            return '';
        }
        
        $normalized = self::normalize($phone);
        
        if (strlen($normalized) < 10) {
            return str_repeat('*', strlen($normalized));
        }
        
        return substr($normalized, 0, 3) . '***' . substr($normalized, -4);
    }

    /**
     * Маска для UI: +7 (900) ***-**-67
     */
    public static function maskDisplay(?string $phone): string
    {
        if (empty($phone)) {
            return '—';
        }

        $normalized = self::normalize($phone);
        if (strlen($normalized) !== 10) {
            return self::mask($phone) ?: '—';
        }

        return sprintf(
            '+7 (%s) ***-**-%s',
            substr($normalized, 0, 3),
            substr($normalized, -2)
        );
    }

    /**
     * Роли с полным номером без клика.
     */
    public static function userSeesFullPhone(?\App\Models\User $user): bool
    {
        if (! $user) {
            return false;
        }

        $roles = config('privacy.phone_full_roles', [
            'developer',
            'call_center',
            'senior_dispatcher',
        ]);

        return $user->hasAnyRole($roles);
    }

    /**
     * Кнопка «копировать для СД» (ID + статус + телефон).
     */
    public static function userCanCopySdLink(?\App\Models\User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole([
            'developer',
            'call_center',
            'senior_dispatcher',
            'general_director',
        ]);
    }
    /**
     * Получить последние N цифр для поиска
     * 
     * @param string|null $phone Телефон в любом формате
     * @param int $digits Количество цифр (по умолчанию 10)
     * @return string
     */
    public static function getLastDigits(?string $phone, int $digits = 10): string
    {
        $cleaned = self::clean($phone);
        
        if (strlen($cleaned) <= $digits) {
            return $cleaned;
        }
        
        return substr($cleaned, -$digits);
    }

    /**
     * E.164 для РФ/КЗ: +7 и 10 цифр (после normalize).
     */
    public static function toE164(?string $phone): string
    {
        $normalized = self::normalize($phone);

        return strlen($normalized) === 10 ? '+7'.$normalized : '';
    }
}
