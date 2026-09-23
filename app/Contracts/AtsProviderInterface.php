<?php

namespace App\Contracts;

/**
 * Интерфейс провайдера АТС (телефонии).
 * Позволяет подменять реализацию (Mango Office, другая АТС) без изменения контроллеров и шаблонов.
 */
interface AtsProviderInterface
{
    /**
     * Настроена ли интеграция с АТС (заполнены ключи/учётные данные).
     */
    public function isConfigured(): bool;

    /**
     * Инициация исходящего звонка: оператор (extension) → клиент (toNumber).
     *
     * @param string $fromExtension Внутренний номер оператора
     * @param string $toNumber Номер телефона клиента (10 цифр или с префиксом)
     * @return array ['success' => bool, 'data' => array|null, 'error' => string|null]
     */
    public function initiateCall(string $fromExtension, string $toNumber): array;
}
