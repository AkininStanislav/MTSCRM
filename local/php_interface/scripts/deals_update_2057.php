<?php
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 3);

require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
use Bitrix\Main\Loader;
use Mts\Main\Crm\DealServices\DealUpdateStageLoseYear;

$service = new DealUpdateStageLoseYear(
    '/local/php_interface/scripts/csv/deals_update_2057.csv',
    'Провалено по запросу БМПкк задача 2057',
    'C5:LOSE',
    '51310',
    2057,
    'deals_update_2057',
);

try {
    $service->run();
} catch (Throwable $exception) {
    echo $exception->getMessage().PHP_EOL;
}