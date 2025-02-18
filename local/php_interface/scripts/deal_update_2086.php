<?php
use Bitrix\Main\Loader;
use Mts\Main\Crm\DealServices\DealUpdateStageLose;

define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 3);

require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

$service = new DealUpdateStageLose(
    '/local/php_interface/scripts/csv/deals_update_2086.csv',
    'Провалено по запросу Самойлова Д. задача 2086',
    'C5:LOSE',
    '51310',
    ['C5:NEW', 'C5:PREPARATION'],
    '3650',
    'deal_update_2086'
);




try {
    $service->run();
} catch (Throwable $exception) {
    echo $exception->getMessage().PHP_EOL;
}