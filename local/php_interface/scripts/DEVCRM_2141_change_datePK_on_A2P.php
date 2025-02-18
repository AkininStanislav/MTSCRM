<?php

use Bitrix\Crm\Category\Entity\DealCategoryTable,
    Bitrix\Crm,
    Bitrix\Crm\DealTable,
    Bitrix\Main\IO,
    Bitrix\Main\Loader,
    Bitrix\Main\Application,
    Bitrix\Crm\Service\Container,
    Mts\Main\Scripts\AbstractScript,
    Bitrix\Main\Type\DateTime,
    Bitrix\Main\Entity;

if (php_sapi_name() !== 'cli') {
    exit();
}

ini_set('memory_limit', '1024M');
set_time_limit(0);
const NOT_CHECK_PERMISSIONS = true;
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 3);
require_once $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php";

if(!Loader::IncludeModule('crm') || !Loader::IncludeModule('mts.main')){
    exit();
}

global $USER;
$USER->Authorize(1);

class DEVCRM_2141_change_datePK_on_A2P extends AbstractScript
{
    const CATEGORY_ID = 7;
    const PK_STATUS = 'C7:WON';//завершенные сделки
    const DATE_FIELD = 'UF_CRM_1571153034203';
    const RK_ACTIVE = 'UF_RK_ACTIVE_SIXMONTHS';//поле активно 6 месяцев
    const COUNT_MONTHS = 6;
    const LIST_STATUS_ID_SCENARIO_B = ['C7:PREPAYMENT_INVOICE','C7:EXECUTING','C7:UC_1ZFQDF','C7:UC_L2S00N','C7:UC_KMO223'];
    private $countFoundedRec;
    private $setLastDateTime;

    public function __construct(){
        parent::__construct();
        $this->countFoundedRec = 0;
        $this->setLastDateTime = new DateTime(date('Y-m-t', mktime(0, 0, 0, date('m'), 1, date("Y")))." 00:00:00", "Y-m-d H:i:s");
    }

    protected function getLogName(): string
    {
        return 'update_deal_datefinishrkA2P_'.date('Y_m_d');
    }

    private function getDealData(array $filter, bool $setRkActive): void
    {
        $entityResult = DealTable::getList([
            'select' => [
                'CLOSEDATE',//дата изменения статуса
                'ID',
                'TITLE',
                'STAGE_ID',
                self::RK_ACTIVE,
                self::DATE_FIELD
            ],
            'filter' => $filter,
            'order' => [
                'CLOSEDATE' => 'ASC'
            ]
        ])->fetchCollection();
        foreach($entityResult as $deal){
            if($setRkActive){
                $this->addToHistoryEventLog("Дата окончания РК изменена с ".
                                                    $deal[self::DATE_FIELD]->format('d.m.Y').
                                                    " на ".
                                                    $this->setLastDateTime->format('d.m.Y'), $deal['ID']);

                $this->logger->info('Сделка ID - '.$deal['ID'].' '.$deal['TITLE'].
                                            ' Дата окончания РК '.$deal[self::DATE_FIELD]->format('d.m.Y').
                                            ' изменено на '.$this->setLastDateTime->format('d.m.Y'));
                //проставляем дату
                $deal[self::DATE_FIELD] = $this->setLastDateTime->format('d.m.Y');
                $deal[self::RK_ACTIVE] = '1';
            }
            else{
                $deal[self::RK_ACTIVE] = '0';//ставим признак нет
                $this->addToHistoryEventLog('Изменено поле "РК Активна (6 мес)" = "Нет"',$deal['ID']);
                $this->logger->info('Сделка ID - '.$deal['ID'].' '.$deal['TITLE'].
                                            ' Изменено поле "РК Активна (6 мес)" = "Нет" ');
            }
            $this->countFoundedRec++;
            $result = $entityResult->save();
            if (!$result->isSuccess()) {
                $this->logger->info('Ошибка обновления сделки '.$deal['ID'].' - Ошибки: ' . implode(', ', $result->getErrorMessages()));
            }
        }
    }
    private function startScenarioA(): void
    {
        $currentDate = new DateTime();
        $filter =  [
            'CATEGORY_ID' => self::CATEGORY_ID,
            'STAGE_ID' => self::PK_STATUS,
            '>=CLOSEDATE' => $currentDate->add("-".self::COUNT_MONTHS." months"),
            [
                'LOGIC' => 'OR',
                '!='.self::DATE_FIELD => $this->setLastDateTime,
                self::RK_ACTIVE => false
            ],

        ];
        $this->getDealData($filter, true);
    }
    private function startScenarioOldDealA(): void
    {
        //проверяем старые сделки с признаком PК 6 месяцев и статусом
        $currentDate = new DateTime();
        $filter = [
            'CATEGORY_ID' => self::CATEGORY_ID,
            'STAGE_ID' => self::PK_STATUS,
            '<CLOSEDATE' => $currentDate->add("-".self::COUNT_MONTHS." months"),
            self::RK_ACTIVE => true
        ];
        $this->getDealData($filter, false);
    }
    private function startScenarioB(): void
    {
        //сценарий В
        $filter = [
            'CATEGORY_ID' => self::CATEGORY_ID,
            'STAGE_ID' => self::LIST_STATUS_ID_SCENARIO_B,
            '!'.self::DATE_FIELD => false, //"Дата окончания РК",  заполнено
            '<'.self::DATE_FIELD => $this->setLastDateTime //и "Дата окончания РК" меньше последнего числа месяца
        ];
        $this->getDealData($filter, true);
    }
    private function addToHistoryEventLog(string $textLog, int $dealId): void
    {
        $CCrmEvent = new \CCrmEvent();
        $CCrmEvent->Add(
            [
                'ENTITY_TYPE'=> 'DEAL',
                'ENTITY_ID' => $dealId,
                'EVENT_ID' => 'INFO',
                'EVENT_TEXT_1' =>  $textLog,
                'DATE_CREATE' => new DateTime()
            ]
        );
    }

    protected function processScriptActions(): void
    {
        $this->logger->info('Запуск скрипта - Проверка полей "Дата окончания РК" ' . date('Y_m_d H:i:s'));
        //проверяем есть ли поле UF_RK_ACTIVE_SIXMONTHS
        $ufRkField = \CUserTypeEntity::GetList(aFilter:["FIELD_NAME"=>self::RK_ACTIVE,'ENTITY_ID' =>'CRM_DEAL']);
        if(!$ufRkField->Fetch())
        {
            $this->logger->info('Поле UF_RK_ACTIVE_SIXMONTHS не найдено');
            return;
        }
        $this->startScenarioA();
        $this->startScenarioOldDealA();
        $this->startScenarioB();

        if($this->countFoundedRec == 0){
            $this->logger->info('Работа скрипта завершена без актуализации данных, т.к. сделки с просроченными датами завершения не найдены');
        }
    }
}
try {
    (new DEVCRM_2141_change_datePK_on_A2P())->run();
}
catch (\Throwable $throwable) {
    dump($throwable);
}
finally {
    $USER->Logout();
}
?>