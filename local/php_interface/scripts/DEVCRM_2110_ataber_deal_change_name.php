<?php

use Bitrix\Crm\DealTable;
use Bitrix\Crm\Item\Deal;
use Bitrix\Crm\Service\Container;
use Bitrix\Main\Loader;
use Mts\Main\Crm\DealCategory\DealCategory;
use Mts\Main\Crm\DealStages\Enums\A2PStages;
use Mts\Main\Scripts\AbstractScript;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Web\Json;
use Bitrix\Crm\UtmTable;
use Mts\Main\Crm\Handlers\Deal\DealChangeName;
use Bitrix\Main\Entity\ExpressionField;
use Bitrix\Main\Entity\ReferenceField;
use Bitrix\Crm\CompanyTable;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\ORM\Query\Result;
use Mts\Main\Crm\DealFields\DealFieldsEnum;
use Mts\Main\UserFields\UserFieldService;
use Mts\Main\UserFields;
use Mts\Main\Orm\EnumTable\UserFieldEnumTable;
use Bitrix\Main\Type\Date;
use Bitrix\Main\Type\DateTime;

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

if(!Loader::includeModule('mts.main')){
    exit();
}

global $USER;
$USER->Authorize(1);

class DEVCRM_2110_ataber_deal_change_name extends AbstractScript
{
    protected function getLogName(): string
    {
        return 'DEVCRM_2110_ataber_deal_change_name';
    }

    protected function processScriptActions(): void
    {
        $resDeals = DealTable::GetList([
            'select' => [
                        'ID',
                        DealFieldsEnum::UF_TOOLS_AC->value,
                        'COMPANY_ID',
                        'TITLE',
                        DealFieldsEnum::UF_DATE_END_RK->value,
                        'SOURCE.VALUE',
                        'COMPANY.TITLE'
            ],
            'filter' => [
                        Deal::FIELD_NAME_CATEGORY_ID => DealCategory::A2P->getId(),
                        Deal::FIELD_NAME_STAGE_ID => [
                                A2PStages::COMMERCIAL_OFFER->getStageCode(),
                                A2PStages::DEMO->getStageCode(),
                                A2PStages::LAUNCH->getStageCode(),
                                A2PStages::DOCUMENTS->getStageCode(),
                                A2PStages::WON->getStageCode()
                        ]
            ],
            'runtime' => [
                (new ReferenceField(
                    'COMPANY',
                    CompanyTable::class,
                    Join::on('this.COMPANY_ID', 'ref.ID')
                ))->configureJoinType('inner')
            ],
            'runtime' => [
                (new ReferenceField(
                    'SOURCE',
                    UserFieldEnumTable::class,
                    Join::on('this.'.DealFieldsEnum::UF_TOOLS_AC->value, 'ref.ID')
                ))->configureJoinType('inner')
            ],
        ])->fetchCollection();
        $overall = !empty($resDeals) ? count($resDeals):0;
        $current = 0;
        foreach ($resDeals as $deal) {
            $this->logger->info('Метод changeName вызван для сделки ID: ' . $deal['ID']);
            $date = $deal[DealFieldsEnum::UF_DATE_END_RK->value];
            $date = is_a($date, Date::class) ? $date->format('d.m.Y') : '';
            $companyTitle = $deal->get('COMPANY')['TITLE'];
            $rkEnumName = $deal->get('SOURCE')['VALUE'];
            $title = $companyTitle . '/' . $rkEnumName . '/' . $date;
            $oldTitle = $deal['TITLE'];
            $deal['TITLE'] = $title;
            $result = $deal->save();
            if (!$result->isSuccess()) {
                $this->logger->error('Ошибка при сохранении сделки.', [
                    'ID' => $deal['ID'],
                    'ERROR' => $result->getErrorMessages()
                ]);
            } else {
                $this->logger->info('Успешно обновлено имя сделки: c '.$oldTitle.' на '.$title);
            }
            $this->logger->info($current++ . '/' . $overall);
        }
    }
}
try {
    (new DEVCRM_2110_ataber_deal_change_name())->run();
}
catch (\Throwable $throwable) {
    dump($throwable);
}
finally {
    $USER->Logout();
}