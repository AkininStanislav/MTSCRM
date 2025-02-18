<?php

use Bitrix\Crm\Service\Container;
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Mts\Main\Scripts\AbstractScript;

ini_set('memory_limit', '1024M');
set_time_limit(0);
const NOT_CHECK_PERMISSIONS = true;
$sapi = php_sapi_name();

if ($sapi !=='cli') {
    exit();
}

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 3);
include($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

Loader::includeModule('mts.main');

class DEVCRM_1803_object_deduplication_135 extends AbstractScript
{

    protected function getLogName(): string
    {
        return 'object_deduplication_135';
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
        $smartProcessStage = $this->getSmartProcessStage();
        $result = $DB->Query("SELECT COUNT(ID), ID , UF_CRM_9_1713723388976 FROM b_crm_dynamic_items_135 WHERE STAGE_ID = '" . $smartProcessStage . "' GROUP BY UF_CRM_9_1713723388976 HAVING COUNT(id) > 1");

        $arIds = [];

        while ($record = $result->fetch()) {
            $arIds[] = $record['UF_CRM_9_1713723388976'];
        }

        if (empty($arIds)) {
            $this->logger->info('Дублей не найдено');
        }

        return $arIds;
    }

    protected function removeDoubleByFieldValue(mixed $id)
    {
        Loader::includeModule('crm');

        $typeid = $this->getSmartProcessId();//Идентификатор смарт-процесса

        $factory = Container::getInstance()->getFactory($typeid);

        $this->logger->info('Ищем дубли по ID Отеля Авроры: ' . $id);

        $items = $factory->getItems(['filter' => [
            'STAGE_ID' => $this->getSmartProcessStage(),
            'UF_CRM_9_1713723388976' => $id
        ]]);

        array_shift($items);

        if (!empty($items)) {
            $this->logger->info('Нашли ' . count($items)  . ' дублей');
        }

        foreach ($items as $item) {
            $this->logger->info('Удаляем элемент с ID ' . $item->getId());
            $item->delete();
        }
    }

    private function getSmartProcessId()
    {
        if (!$smartProcessId = \Bitrix\Main\Config\Option::get('mts.main', 'PLACEMENT_OBJECT_SMART_PROCESS_ID'))
        {
            throw new Exception('Не указан ID смарт процесса');
        }
        return $smartProcessId;
    }

    private function getSmartProcessStage()
    {
        return 'DT' . $this->getSmartProcessId() . '_' . $this->getSmartProcessCamFunnel() . ':FAIL';
    }

    private function getSmartProcessCamFunnel()
    {
        if (!$smartProcessCamFunnelId = \Bitrix\Main\Config\Option::get('mts.main', 'PLACEMENT_OBJECT_SMART_PROCESS_CAM_FUNNEL_ID')){
            throw new Exception('Не указан ID воронки');
        }
        return $smartProcessCamFunnelId;
    }

}

$deduplicator = new DEVCRM_1803_object_deduplication_135();

$deduplicator->run();