<?php

return [
    /*
    | Корневая папка шаблонов документов (относительно корня проекта).
    | docsofmasters — шаблоны для мастеров
    | docofilial — шаблоны для директоров и компании
    */
    'templates_root' => env('DOCUMENT_TEMPLATES_ROOT', 'document-templates'),

    'masters_dir' => 'docsofmasters',

    'filial_dir' => 'docofilial',
];
