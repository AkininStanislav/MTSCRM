<?php

use Bitrix\Crm\Service\Container;
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Mts\Main\Scripts\AbstractScript;

ini_set('memory_limit', '6000M');
set_time_limit(0);
const NOT_CHECK_PERMISSIONS = true;
$sapi = php_sapi_name();

if ($sapi !=='cli') {
    exit();
}

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 3);
include($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

Loader::includeModule('mts.main');

class DEVCRM_1804_document_deduplication_185 extends AbstractScript
{

    protected function getLogName(): string
    {
        return 'object_deduplication_185';
    }

    protected function processScriptActions(): void
    {
       $arIds = $this->getDoublesIds();
       foreach ($arIds as $id) {
           $this->removeDoubleByFieldValue($id);
       }
    }

    protected function getDoublesIds(): array
    {
        global $DB;
        $result = $DB->Query("SELECT COUNT(ID), ID , UF_CRM_12_1716469577889 FROM b_crm_dynamic_items_185 WHERE UF_CRM_12_1716469577889 IS NOT NULL GROUP BY UF_CRM_12_1716469577889 HAVING COUNT(id) > 1");

        $arIds = [];

        while ($record = $result->fetch()) {
            $arIds[] = $record['UF_CRM_12_1716469577889'];
        }

        if (empty($arIds)) {
            $this->logger->info('Дублей не найдено');
        }

        return $arIds;
    }

    protected function removeDoubleByFieldValue(mixed $id)
    {
        Loader::includeModule('crm');

        $typeid = '185';//Идентификатор смарт-процесса

        $factory = Container::getInstance()->getFactory($typeid);

        $this->logger->info('Ищем дубли по ID Договора в Авроре: ' . $id);

        $items = $factory->getItems(['filter' => ['UF_CRM_12_1716469577889' => $id]]);

        array_shift($items);

        if (!empty($items)) {
            $this->logger->info('Нашли ' . count($items)  . ' дублей');
        }

        foreach ($items as $item) {
            $this->logger->info('Удаляем элемент с ID ' . $item->getId());
            $item->delete();
        }
    }

}

$deduplicator = new DEVCRM_1804_document_deduplication_185();

$deduplicator->run();