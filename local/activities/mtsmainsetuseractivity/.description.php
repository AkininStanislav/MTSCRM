<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Bizproc\FieldType;
use Bitrix\Main\Localization\Loc;

$arActivityDescription = [
    // Название действия для конструтора
    'NAME' => '[mts.main] Назначение ответственного у сделки',

    // Описание действия для конструктора.
    'DESCRIPTION' => 'Назначение ответственного у сделки из очереди ответственных',

    // Тип: “activity” - действие, “condition” - ветка составного действия.
    'TYPE' => 'activity',

    // Название класса действия без префикса “CBP”.
    'CLASS' => 'MtsMainSetUserActivity',

    // Название JS-класса для управления внешним видом и поведением в конструкторе.
    // Если нужно только стандартное поведение, указывайте “BizProcActivity”.
    'JSCLASS' => 'BizProcActivity',

    // Категория действия в конструкторе.
    'CATEGORY' => [
        "ID" => "setUserActivity",
        "OWN_ID" => "mtssetactivity",
        "OWN_NAME" => "Назначение ответственных",
    ],
    'RETURN' => [
        'currentUserId' => [
            'NAME' => 'Назначенный ответственный',
            'TYPE' => FieldType::STRING
        ],
    ]
];
?>