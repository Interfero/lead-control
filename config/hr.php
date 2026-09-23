<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Супервизоры колл-центра (видят учётки call_center)
    |--------------------------------------------------------------------------
    |
    | Список user_id через запятую. Пусто = все с ролью senior_dispatcher.
    | По умолчанию — Иса (prod user_id=149).
    |
    */
    'cc_supervisor_user_ids' => array_values(array_filter(array_map(
        static fn ($id) => (int) trim((string) $id),
        explode(',', (string) env('HR_CC_SUPERVISOR_USER_IDS', '149'))
    ), static fn (int $id) => $id > 0)),
];
