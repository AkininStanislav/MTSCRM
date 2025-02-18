<?php

use Bitrix\Crm\Service\Container;
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Mts\Main\Crm\Import\AdminCompanyImportController;
use Mts\Main\Crm\Import\AdminDealImportController;
use Mts\Main\Crm\Import\FactoryController;
use Mts\Main\Import\Queue\ImportQueueService;
use Mts\Main\Import\Queue\Orm\QueueTable;
use Mts\Main\Scripts\AbstractScript;

ini_set('memory_limit', '1024M');
set_time_limit(0);
const NOT_CHECK_PERMISSIONS = true;
$sapi = php_sapi_name();

if ($sapi !=='cli') {
    exit();
} else {
    define('CLI_RUN', true);
}

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 3);
include($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

Loader::includeModule('mts.main');

ImportQueueService::getInstance()->processQueue(1, [
    QueueTable::CALLBACK => FactoryController::getControllers()
]);