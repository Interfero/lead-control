<?php

return [
    /*
    | Каталог готовых бэкапов (внутри storage или абсолютный путь).
    */
    'path' => env('BACKUP_PATH', storage_path('app/backups')),

    /*
    | Ключи вне архива. На VPS: /etc/lead-control/backup
    */
    'keys_path' => env('BACKUP_KEYS_PATH', storage_path('app/backup-keys')),

    /*
    | OpenSSL conf с gost-engine (staging VPS).
    */
    'openssl_gost_conf' => env('BACKUP_OPENSSL_GOST_CONF', '/opt/lead-control-crypto-poc/openssl-gost.cnf'),

    /*
    | CLI AEGIS-256 (libaegis). Пусто = автопоиск aegis256-file / AES-GCM fallback.
    */
    'aegis_cli' => env('BACKUP_AEGIS_CLI', 'aegis256-file'),

    /*
    | Сколько дней хранить бэкапы (≥ 30 по ТЗ на проде; на staging можно меньше).
    */
    'retain_days' => (int) env('BACKUP_RETAIN_DAYS', 30),

    /*
    | mysqldump binary
    */
    'mysqldump' => env('BACKUP_MYSQLDUMP', 'mysqldump'),
];
