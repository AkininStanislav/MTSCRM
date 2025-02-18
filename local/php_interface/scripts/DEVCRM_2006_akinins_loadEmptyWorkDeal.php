<?php

use Bitrix\Crm\Service\Container,
    Bitrix\Main\Application,
    Bitrix\Main\Loader,
    Mts\Main\Scripts\AbstractScript,
    Bitrix\Main\Type\DateTime,
    Bitrix\Crm\DealTable,
    Stream\Main\Tools\BizprocHelper,
    Bitrix\Crm\ActivityTable,
    Bitrix\Main\Entity,
    Bitrix\Crm\Activity\Provider\ToDo,
    Bitrix\Main\Entity\ReferenceField;

ini_set('memory_limit', '1024M');
set_time_limit(0);
const NOT_CHECK_PERMISSIONS = true;
$sapi = php_sapi_name();

if ($sapi !=='cli') {
    exit();
}

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 3);
include($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

global $USER;
$USER->Authorize(1);

if(
    !Loader::includeModule('mts.main')
    ||!Loader::includeModule('bizproc')
    ||!Loader::includeModule('crm')
    ||!Loader::IncludeModule("tasks")
){
    exit();
}
class DEVCRM_2006_akinins_loadEmptyWorkDeal extends AbstractScript{

    const STATUS_ID = [
            'C7:PREPARATION',
            'C7:PREPAYMENT_INVOICE',
            'C7:EXECUTING',
            'C7:UC_L2S00N',
            'C7:UC_KMO223',
            'C7:UC_1ZFQDF',
            'C3:NEW',
            'C3:EXECUTING',
            'C3:FINAL_INVOICE',
            'C3:PREPARATION',
            'C3:PREPAYMENT_INVOICE',
            'C3:UC_2TL9GR',
            'C3:UC_HWAJE5',
    ];
    const STAGE_ID = [7,3];
    private $currentDate;

    public function __construct(){
        parent::__construct();
        $this->currentDate = new DateTime();
    }

    protected function getLogName(): string
    {
        return 'loadEmptyWorkDeal_'.date('Y_m_d');
    }
    private function getUserManagerIDs($userId): array
    {
        $managers = array();
        $sections = \CIntranetUtils::GetUserDepartments($userId);
        foreach ($sections as $section) {
            $manager = \CIntranetUtils::GetDepartmentManagerID($section);
            $manager = ($manager == $userId)?false:$manager;
            while (empty($manager)) {
                $res = \CIBlockSection::GetByID($section);
                if ($sectionInfo = $res->GetNext()) {
                    $section = $sectionInfo['IBLOCK_SECTION_ID'];
                    $manager = \CIntranetUtils::GetDepartmentManagerID($section);
                    if ($section < 1) break;
                } else break;
            }
            If ($manager > 0) $managers[] = $manager;
        }
        return $managers;
    }
    private function createTask($deal)
    {
        global $APPLICATION;
        $taskFields = [
            "TITLE" => "Необходимо актуализировать данные по сделке ".$deal['ID'],
            "DESCRIPTION" => "Необходимо актуализировать данные по сделке и запланировать актуальную задачу".PHP_EOL.
                             "[url=/crm/deal/details/".$deal['ID']."/]Ссылка на сделку[/url]",
            "RESPONSIBLE_ID" => $deal['ASSIGNED_BY_ID'],
            "CREATED_BY" => $this->getUserManagerIDs($deal['ASSIGNED_BY_ID']),
            "MATCH_WORK_TIME" => "Y",
            "PRIORITY" => 1,
            "START_DATE_PLAN" => date("d.m.Y 18:00:00"),
            "END_DATE_PLAN" => date("d.m.Y 18:00:00", strtotime("+1 days")),
            "DEADLINE" => date("d.m.Y 18:00:00", strtotime("+1 days")),
            "UF_CRM_TASK" => ['D_' . $deal['ID']]
        ];
        $obTask = new CTasks;
        $addTask = $obTask->Add($taskFields);
        if($addTask > 0){
            $this->logger->info('Сделка ID: '.$deal['ID'].' Назначена задача на уточнение пользователю '.$deal['ASSIGNED_BY_ID']);
        }
        else{
            $e = $APPLICATION->GetException();
            $this->logger->info('Сделка ID: '.$deal['ID'].' Ошибки назначения задачи: '.$e->GetString());
        }
        return true;
    }

    //проверка - все ли дела/задачи завершены
    private function checkIsAllCompletedTaskOnDeal($listTask)
    {
        foreach ($listTask as $item) {
            if(!$item['COMPLETED']){
                return false;
            }
        }
        return true;
    }

    protected function processScriptActions(): void
    {
        //получаем список сделок
        $entityDeal = DealTable::getList([
            'select' => ['ID','ASSIGNED_BY_ID'],
            'order' => ['ID' =>'DESC'],
            'filter' => [
                'STAGE_ID' => self::STATUS_ID,
                'CATEGORY_ID' => self::STAGE_ID,
                '>=DATE_CREATE' =>$this->currentDate->add("-2 months"),//ограничение в 2 месяца, чтобы несколько тысяч задач не создавать(актуально для первого запуска)
            ]
        ])->fetchCollection();
        $countTask = 0;
        $listIdDeals = [];
        foreach ($entityDeal as $deal){
            $listIdDeals[] = $deal['ID'];
        }
        //список задач в сделках
        $listTasks = ActivityTable::getList([
            'select' => ['ID','PROVIDER_TYPE_ID','OWNER_ID','ASSOCIATED_ENTITY_ID','COMPLETED'],
            'order' => ['ID' =>'DESC'],
            'filter' => [
                'OWNER_ID' => $listIdDeals,
            ]
        ])->fetchCollection();
        $formatListIdDeals = [];
        foreach ($listTasks as $act){
            $formatListIdDeals[$act['OWNER_ID']][] = $act;
        }
        //
        foreach ($entityDeal as $deal){
            //если нет ни дел ни задач - назначить задачу или все сделки и задачи завершены
            if(
                !isset($formatListIdDeals[$deal['ID']])
                ||(
                    !empty($formatListIdDeals[$deal['ID']])
                    && $this->checkIsAllCompletedTaskOnDeal($formatListIdDeals[$deal['ID']])
                )
            ){
                $this->createTask($deal);
                $countTask++;
            }
        }
        $this->logger->info('Всего назначено задач: '.$countTask);
        return;
    }
}
try {
    (new DEVCRM_2006_akinins_loadEmptyWorkDeal())->run();
}
catch (\Throwable $throwable) {
    dump($throwable);
}
finally {
    $USER->Logout();
}