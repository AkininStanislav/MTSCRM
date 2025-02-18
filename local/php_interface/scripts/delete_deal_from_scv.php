<?php

use Bitrix\Main\Application;

ini_set('memory_limit', '1024M');
set_time_limit(0);
const NOT_CHECK_PERMISSIONS = true;

if (!empty($_SERVER['DOCUMENT_ROOT'])) {
    exit();
}

$string = __DIR__;
$pattern = '#^.*?mts\.ru#';

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 3);

include($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

\Bitrix\Main\Loader::includeModule('crm');

const CSV_FILE = __DIR__ . '/csv/deals.csv';

if (!Bitrix\Main\IO\Directory::isDirectoryExists(Application::getDocumentRoot()
    . "/upload/logs/deals/")) {
    Bitrix\Main\IO\Directory::createDirectory(Application::getDocumentRoot()
        . "/upload/logs/deals/");
}

function writeLog(string $log)
{
    $log .= PHP_EOL;
    file_put_contents(
        Application::getDocumentRoot()
        . "/upload/logs/deals/"
        . 'delete_deal_from_scv.log',
        $log,
        FILE_APPEND
    );
}

if (!file_exists(CSV_FILE)) {
    echo 'Файл ' . CSV_FILE . ' не найден';
}

$deals = explode("\n", file_get_contents(CSV_FILE));

global $USER;
$USER->Authorize(1);

foreach ($deals as $value) {
    $dealId = (int) $value;

    if ($dealId === 0) {
        continue;
    }

    writeLog('Обрабатывается Сделка с ID=' . $dealId);

    $dealExists = \Bitrix\Crm\DealTable::getList([
        'select' => ['ID'],
        'filter' => ['ID']
    ])->fetch();

    if ($dealExists === false) {
        writeLog('Не найдена');
        writeLog('');
        continue;
    }

    $deal = new \CCrmDeal();

    $deleted = $deal->Delete($dealId);

    if ($deleted === false) {
        writeLog('Не удалилась - ' . $deal->LAST_ERROR);
    } else {
        writeLog('Удалилась');
    }

    writeLog('');
}