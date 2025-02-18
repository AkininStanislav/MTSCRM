<?php

use Bitrix\Crm\Category\Entity\DealCategoryTable,
    Mts\Main\Scripts\AbstractScript,
    Bitrix\Crm,
    Bitrix\Main\IO,
    Bitrix\Main\Application,
    PhpOffice\PhpSpreadsheet\IOFactory,
    Bitrix\Crm\Service\Container,
    Bitrix\Main\Entity;

if (php_sapi_name() !== 'cli') {
	die;
}

ini_set('memory_limit', '1024M');
set_time_limit(0);
const NOT_CHECK_PERMISSIONS = true;
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 3);
require_once $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php";

\CModule::IncludeModule('crm');
\CModule::IncludeModule('mts.main');


global $USER;
$USER->Authorize(1);

class DEVCRM_2184_UpdateMAASDealOriginId extends \Mts\Main\Scripts\AbstractScript
{
    const CATEGORY_ID = 5;

    const COUNT_REC = 500;//обрабатываем по 500 записей

    const ORIGIN_ID_COLUMN = 0; //колонка в экселе

    const TPO_COLUMN = 1; //колонка в экселе

    const CRM_TPO = 'UF_CRM_1646993425489'; //поле тпо в срм

    const DIR_EXCEL_FILES = '/upload/import_maas_origin_id/'; //папка с файлами

    protected function getLogName(): string
    {
        return 'DEVCRM_2184_UpdateMAASDealOriginId';
    }

    protected function processScriptActions(): void
    {
        $this->logger->info('Загружаем из xlxs файлов список ORIGNAL_ID для сопоставления по TPO');
        //получаем список файлов xlsx
        if (!file_exists(Application::getDocumentRoot().self::DIR_EXCEL_FILES)) {
            mkdir(Application::getDocumentRoot().self::DIR_EXCEL_FILES);
        }
        $toSearch ='.xls';
        $listFiles = array_filter(
            array_diff(scandir(Application::getDocumentRoot().self::DIR_EXCEL_FILES), array('..', '.')),
            function ($item) use ($toSearch) {
                if (stripos($item, $toSearch ) !== false) {
                    return true;
                }
                return false;
            }
        );
        if(!is_array($listFiles)) {
            $this->logger->info('Не найдено xlsx файлов');
            return;
        }
        //загружаем данные из xlsx файлов
        $listData = [];
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        foreach($listFiles as $file){
            $spreadsheet = IOFactory::load(Application::getDocumentRoot().self::DIR_EXCEL_FILES.$file);
            $data = $spreadsheet->getActiveSheet()->toArray();
            foreach ($data as $item) {
                if(
                    !is_numeric($item[self::ORIGIN_ID_COLUMN]) || !is_numeric($item[self::TPO_COLUMN]) ||
                    $item[self::ORIGIN_ID_COLUMN] == 0 || $item[self::TPO_COLUMN] == 0
                ){
                    continue;
                }
                $listData[strval($item[self::TPO_COLUMN])] = strval($item[self::ORIGIN_ID_COLUMN]);
            }
        }
        !is_array($listData) ? $this->logger->info('Список ORIGINAL_ID не найден') : $this->logger->info('Список ORIGINAL_ID загружен');
        if(!is_array($listData)){
            return;
        }

        //добавляем данные в сделки MAAS
        $isWork = true;
        $limit = self::COUNT_REC;
        $offset = 0;
        $count = 0;
        $index = 1;
        while($isWork)
        {
            $isWork = false;
            $entityResult = Crm\DealTable::getList([
                'select' => [
                    'ID',
                    'TITLE',
                    self::CRM_TPO,
                    'ORIGIN_ID'
                ],
                'filter' => [
                    'CATEGORY_ID' => self::CATEGORY_ID,
                ],
                'limit'   => $limit,
                'offset'  => $offset,
                'order' => [
                    'ID' => 'DESC'
                ]
            ])->fetchCollection();

            foreach ($entityResult as $deal){
                //обновляем запись
                if($deal[self::CRM_TPO] == 0) continue;
                if(!empty($listData[$deal[self::CRM_TPO]]) && $listData[$deal[self::CRM_TPO]] > 0)$deal['ORIGIN_ID'] = $listData[$deal[self::CRM_TPO]];
                $isWork = true;
                $count++;
            }
            if($isWork)
            {
                $result = $entityResult->save();
                if (!$result->isSuccess()) {
                    $this->logger->error('Ошибка апдейта блока ' . $index . ' Ошибки: ' . implode(', ', $result->getErrorMessages()));
                }
            }
            $offset += self::COUNT_REC;
            $index++;
        }
        $this->logger->info('Миграция завершена. Обработано сделок - '.$count);
        dump("Обработано сделок - ".$count);
    }
}
try {
    (new DEVCRM_2184_UpdateMAASDealOriginId())->run();
}
catch (\Throwable $throwable) {
    dump($throwable);
}
finally {
    $USER->Logout();
}

?>