<?php

/**
 * Политика отображения телефонов клиентов в UI.
 */
return [

    /**
     * Роли, которым полный номер виден сразу (без клика).
     * Остальные видят маску и раскрывают по клику (AJAX + audit).
     */
    'phone_full_roles' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'PHONE_FULL_ROLES',
            'developer,call_center,senior_dispatcher'
        ))
    ))),

    /** Раскрытие по клику (для ролей без phone_full). */
    'phone_reveal_enabled' => (bool) env('PHONE_REVEAL_ENABLED', true),
];
