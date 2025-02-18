<?php

define('NOT_CHECK_PERMISSIONS', true);

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 3);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Mts\Main\Activities\SchedulerService;

try {
    SchedulerService::execute();
} catch (Throwable $exception) {
    echo $exception->getMessage() . PHP_EOL;
}
