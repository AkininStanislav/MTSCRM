<?php

use Bitrix\Crm\Category\Entity\DealCategoryTable,
    Bitrix\Crm,
    Bitrix\Main\IO,
    Bitrix\Main\Application,
    Bitrix\Crm\Service\Container,
    Bitrix\Main\Type\DateTime,
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

class DEVCRM_3171_ExtendExpiredDeals extends \Mts\Main\Scripts\AbstractScript
{
    const CATEGORY_ID = 5;
    const PK_STATUS = ['C5:PREPARATION', 'C5:NEW', 'C5:PREPAYMENT_INVOICE', 'C5:EXECUTING', 'C5:UC_CV83JW', 'C5:UC_A7BE6X'];
    const DATE_FIELD = 'UF_CRM_1571153011596'; //дата начала РК

    protected function getLogName(): string
    {
        return 'update_deal_datestartrk_' . date('Y_m_d');
    }

    protected function processScriptActions(): void
    {
        $this->logger->info('Запуск скрипта - Проверка полей "Дата начала РК" ' . date('Y_m_d H:i:s'));
        $countFoundedRec = 0;

        $currentDate = new DateTime(date('Y')."-".date('m')."-01 00:00:00", "Y-m-d H:i:s");
        $setLastDateTime = new DateTime(date('Y')."-".date('m')."-28 19:00:00", "Y-m-d H:i:s");
        $entityResult = Crm\DealTable::getList([
            'select' => [
                'ID',
                'TITLE',
                'STAGE_ID',
                self::DATE_FIELD
            ],
            'filter' => [
                'CATEGORY_ID' => self::CATEGORY_ID,
                'STAGE_ID' => self::PK_STATUS,
                '<='.self::DATE_FIELD => $currentDate,
            ],
            'order' => [
                self::DATE_FIELD => 'ASC'
            ]
        ])->fetchCollection();

        foreach ($entityResult as $deal) {
            //добавляем в историю запись
            $CCrmEvent = new \CCrmEvent();
            $CCrmEvent->Add(
                array(
                    'ENTITY_TYPE' => 'DEAL',
                    'ENTITY_ID' => $deal['ID'],
                    'EVENT_ID' => 'INFO',
                    'EVENT_TEXT_1' => "Дата начала РК изменена с " . $deal[self::DATE_FIELD]->format('d.m.Y') . " на " . $setLastDateTime->format('d.m.Y'),
                    'DATE_CREATE' => new DateTime()
                )
            );
            //
            $this->logger->info('Сделка ID - ' . $deal['ID'] . ' ' . $deal['TITLE'] . ' Дата начала РК ' . $deal[self::DATE_FIELD]->format('d.m.Y') . ' изменено на ' . $setLastDateTime->format('d.m.Y'));
            //проставляем дату
            $deal[self::DATE_FIELD] = $setLastDateTime->format('d.m.Y');
            $countFoundedRec++;
        }
        $result = $entityResult->save();
        if (!$result->isSuccess()) {
            $this->logger->info('Ошибка обновления - Ошибки: ' . implode(', ', $result->getErrorMessages()));
        }

        if ($countFoundedRec == 0) {
            $this->logger->info('Работа скрипта завершена без актуализации данных, т.к. сделки с просроченными датами начала РК не найдены');
        }
    }
}

try {
    (new DEVCRM_3171_ExtendExpiredDeals())->run();
} catch (\Throwable $throwable) {
    dump($throwable);
} finally {
    $USER->Logout();
}
