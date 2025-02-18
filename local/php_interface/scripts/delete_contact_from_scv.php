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

$_SERVER['DOCUMENT_ROOT'] = '/home/bitrix/www';
if (preg_match($pattern, $string, $matches)) {
    $_SERVER['DOCUMENT_ROOT'] = $matches[0];
}

include($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

const CSV_FILE = __DIR__ . '/csv/contacts.csv';

if (!Bitrix\Main\IO\Directory::isDirectoryExists(Application::getDocumentRoot()
    . "/upload/logs/contacts/")) {
    Bitrix\Main\IO\Directory::createDirectory(Application::getDocumentRoot()
        . "/upload/logs/contacts/");
}

function writeLog(string $log)
{
    $log .= PHP_EOL;
    file_put_contents(
        Application::getDocumentRoot()
        . "/upload/logs/contacts/"
        . 'delete_contact_from_scv.log',
        $log,
        FILE_APPEND
    );
}

if (!file_exists(CSV_FILE)) {
    echo 'Файл ' . CSV_FILE . ' не найден';
}

$contacts = explode("\n", file_get_contents(CSV_FILE));

global $USER;
$USER->Authorize(1);

foreach ($contacts as $value) {
    $contactId = (int) $value;

    if ($contactId === 0) continue;

    writeLog('Обрабатывается Контакт с ID=' . $contactId);

    $contactExists = \Bitrix\Crm\ContactTable::getList([
        'select' => ['ID'],
        'filter' => ['ID' => $contactId]
    ]);

    if ($contactExists === false) {
        writeLog('Не найден');
        continue;
    }

    $contact = new \CCrmContact();

    $deleted = $contact->Delete($contactId);

    if ($deleted === false) {
        writeLog('Не удалился - ' . $contact->LAST_ERROR);
    } else {
        writeLog('Удалился');
    }

    writeLog('');
}