<?php

return [
    // Локальная база DB-IP Lite (City, формат mmdb). Данные: © DB-IP.com, CC BY 4.0. Обновляется раз в месяц вручную.
    'database' => env('GEOIP_DB_PATH', storage_path('app/geoip/dbip-city-lite.mmdb')),

    // Регионы, вход из которых вызывает уведомление (подстрока в названии региона, без учёта регистра).
    // В DB-IP Приморье встречается как «Primorye» и «Primorskiy (Maritime) Kray».
    'alert_regions' => array_filter(array_map('trim', explode(',', (string) env('GEOIP_ALERT_REGIONS', 'primor')))),

    // Не чаще одного уведомления на сотрудника и регион за столько минут (чтобы не спамить при частых входах).
    'alert_throttle_minutes' => (int) env('GEOIP_ALERT_THROTTLE_MINUTES', 360),
];
