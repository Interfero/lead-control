<?php

return [

    'accepted' => 'Поле :attribute должно быть принято.',
    'email' => 'Поле :attribute должно содержать корректный адрес email.',
    'max' => [
        'file' => 'Размер файла :attribute не должен превышать :max КБ.',
        'string' => 'Поле :attribute не должно превышать :max символов.',
    ],
    'min' => [
        'string' => 'Поле :attribute должно содержать минимум :min символов.',
    ],
    'regex' => 'Недопустимый формат поля :attribute.',
    'required' => 'Поле :attribute обязательно для заполнения.',
    'required_with' => 'Поле :attribute обязательно, когда указано :values.',
    'exists' => 'Выбранное значение для :attribute некорректно.',
    'unique' => 'Такое значение поля :attribute уже существует.',
    'uploaded' => 'Не удалось загрузить :attribute. Файл слишком большой или повреждён (лимит сервера).',
    'mimes' => 'Поле :attribute должно быть файлом одного из типов: :values.',
    'file' => 'Поле :attribute должно быть файлом.',

    'attributes' => [
        'file' => 'файл',
        'email' => 'email',
        'phone_number' => 'телефон',
        'street' => 'улица',
        'house' => 'дом',
        'flat' => 'квартира',
        'source_id' => 'источник заказа',
        'order_type' => 'тип заказа',
        'city_id' => 'город',
        'person_name' => 'имя',
    ],
];
