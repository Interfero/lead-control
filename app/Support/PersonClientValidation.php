<?php

namespace App\Support;

/**
 * Общие правила валидации для клиентов КЦ (телефон, адрес) — один источник правды (DRY).
 */
class PersonClientValidation
{
    public const PHONE_DIGITS_10 = 'regex:/^\d{10}$/';

    /** Улица, дом, квартира: буквы, цифры, пробел, дефис, точка, запятая, №, слэш */
    public const ADDRESS_FRAGMENT = 'regex:/^[\p{L}\p{N}\s\-\.,№\/]*$/u';

    public const ADDRESS_ADDS_MAX = 1000;
}
