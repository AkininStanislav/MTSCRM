<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Mts\Main\Crm\LeadGen\Logger;
use Bitrix\Main\Web\Json;
use Bitrix\Main\Application;
use Bitrix\Iblock;
use Bitrix\Main\Loader;

class CBPMtsMainSetUserActivity extends CBPActivity
{
    private string $sLogDir = '/upload/logs/set_user_active/';

    public function __construct($name)
    {
        parent::__construct($name);
        $this->arProperties = [
            "Title" => "",
            'IBLOCK_ID' => '',
            'DEAL_ID' => '',
            'GROUP_CODE' => '',
            'DEAL_STAGE_CODE' => '',
            'USER_CODE' => '',
            'SORT_CODE' => '',
            'ACTIVE_CODE' => '',
            'currentUserId' => '',
        ];
        $this->SetPropertiesTypes([
            'currentUserId' => ['Type' => 'string']
        ]);
    }

    private function addHisloryCrmLog($logText){
        $CCrmEvent = new \CCrmEvent();
        $CCrmEvent->Add(
            array(
                'ENTITY_TYPE'=> 'DEAL',
                'ENTITY_ID' => $this->DEAL_ID,
                'EVENT_ID' => 'INFO',
                'EVENT_TEXT_1' => $logText,
                'DATE_CREATE' => new Bitrix\Main\Type\DateTime()
            )
        );
    }

    public function Execute(): int
    {
        $rootActivity = $this->GetRootActivity();
        $documentId = $rootActivity->GetDocumentId();
        try {
            $iblock = \Bitrix\Iblock\IblockTable::getList([
                'select' => ['ID', 'API_CODE'],
                'filter' => [
                    "ID" => $this->IBLOCK_ID
                ],
            ])->fetchObject();
            if (!$iblock || empty($iblock->getApiCode())) {
                $this->writeInfo('Бизнес процесс не запущен - Не найден инфоблок со списком ответственных', $this->sLogDir);
                $this->addHisloryCrmLog('Бизнес процесс не запущен - Не найден инфоблок со списком ответственных');
                return CBPActivityExecutionStatus::Closed;
            }
            $iblockEntity = $iblock->getEntityDataClass();
            //
            //получаем саму сделку
            $entityTypeId = \CCrmOwnerType::Deal;
            $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);
            $entityDeal = $factory->getItem($this->DEAL_ID);
            if(empty($entityDeal->getData()['SOURCE_ID'])) {
                $this->writeInfo('Бизнес процесс не запущен - Не найден источник с ID-'.$entityDeal->getData()['SOURCE_ID'].' у сделки ID-'.$this->DEAL_ID, $this->sLogDir);
                $this->addHisloryCrmLog('Бизнес процесс не запущен - Не найден источник с ID-'.$entityDeal->getData()['SOURCE_ID']);
                return CBPActivityExecutionStatus::Closed;
            }
            //получаем список групп по источнику
            $entity = \Bitrix\Iblock\Model\Section::compileEntityByIblock($this->IBLOCK_ID);
            $sectionUser = $entity::getList(array(
                "select" => array("UF_SOURCES","UF_LAST_USER", "ID", "NAME"),
                "filter" => array("IBLOCK_ID" => $this->IBLOCK_ID, $this->GROUP_CODE => $entityDeal->getData()['SOURCE_ID'])
            ))->fetchCollection();

            //список назначенных
            foreach($sectionUser as $groupSection){
                $elementUsers = $iblockEntity::getList([
                    'select' => ['ID', $this->SORT_CODE, $this->USER_CODE, $this->ACTIVE_CODE.'.ITEM'],
                    'filter' => ['IBLOCK_ID' => $this->IBLOCK_ID, 'IBLOCK_SECTION_ID' => $groupSection['ID']],
                ])->fetchCollection();
                $listUsers = [];

                foreach($elementUsers as $user){
                    $methodUserId = 'get'.ucfirst(\Bitrix\Main\Text\StringHelper::snake2camel($this->USER_CODE));
                    $methodUserSort = 'get'.ucfirst(\Bitrix\Main\Text\StringHelper::snake2camel($this->SORT_CODE));
                    $methodUserActive = 'get'.ucfirst(\Bitrix\Main\Text\StringHelper::snake2camel($this->ACTIVE_CODE));
                    if($user->$methodUserActive()->getItem()->getValue() != 'Да') {
                        continue;
                    }
                    $listUsers[$user['ID']] = [
                        "id" => $user->$methodUserId()->getValue(),
                        "sort" => $user->$methodUserSort()->getValue()
                    ];
                }

                array_multisort(array_column($listUsers, 'sort'), SORT_ASC, $listUsers);
                //текущий ответственный
                if(empty($groupSection["UF_LAST_USER"])){
                    $nextUser = $listUsers[array_key_first($listUsers)];
                }
                else{
                    $currentUser = array_search($groupSection[$this->DEAL_STAGE_CODE], array_column($listUsers, 'id'));
                    //следующий ответственный
                    $nextUser = current(array_slice($listUsers, array_search($currentUser, array_keys($listUsers)) + 1, 1));
                    if(empty($nextUser) || !$nextUser){
                        $nextUser = $listUsers[array_key_first($listUsers)];
                    }
                }
                //Назначаем в сделке ответственного
                $entityDeal->setFromCompatibleData([
                    \Bitrix\Crm\Item::FIELD_NAME_ASSIGNED => $nextUser['id'],
                ]);
                $operation = $factory->getUpdateOperation($entityDeal);
                $operation->disableAllChecks();
                $result = $operation->launch();
                //сохраняем в ИБ
                $groupSection[$this->DEAL_STAGE_CODE] = $nextUser['id'];
                $groupSection->save();
            }

        } catch (Exception $exception) {
            $this->WriteToTrackingService(
                'Exception ' . $exception->getMessage()
            );
            $this->writeInfo('Exception ' . $exception->getMessage() . ':' . $exception->getFile() . $exception->getLine(), $this->sLogDir);
        }
        return CBPActivityExecutionStatus::Closed;
    }

    public static function ValidateProperties($arTestProperties = [], CBPWorkflowTemplateUser $user = null): array
    {
        $arErrors = [];
        if ($arTestProperties["IBLOCK_ID"] == '') {
            $arErrors[] = [
                'code' => 'empty_' . 'IBLOCK_ID',
                'message' => 'Пустое поле "Код инфоблока"',
            ];
        }
        if ($arTestProperties["DEAL_ID"] == '') {
            $arErrors[] = [
                'code' => 'empty_' . 'DEAL_ID',
                'message' => 'Пустое поле "Код сделки"',
            ];
        }
        if ($arTestProperties["GROUP_CODE"] == '') {
            $arErrors[] = [
                'code' => 'empty_' . 'GROUP_CODE',
                'message' => 'Пустое поле "Код поля с группой источников"',
            ];
        }
        if ($arTestProperties["DEAL_STAGE_CODE"] == '') {
            $arErrors[] = [
                'code' => 'empty_' . 'DEAL_STAGE_CODE',
                'message' => 'Пустое поле "Код поля с последним назначенным ответственным"',
            ];
        }
        if ($arTestProperties["USER_CODE"] == '') {
            $arErrors[] = [
                'code' => 'empty_' . 'USER_CODE',
                'message' => 'Пустое поле "Код поля ответственного"',
            ];
        }
        if ($arTestProperties["SORT_CODE"] == '') {
            $arErrors[] = [
                'code' => 'empty_' . 'SORT_CODE',
                'message' => 'Пустое поле "Код поля очереди ответственного"',
            ];
        }
        if ($arTestProperties["ACTIVE_CODE"] == '') {
            $arErrors[] = [
                'code' => 'empty_' . 'ACTIVE_CODE',
                'message' => 'Пустое поле "Код поля очереди ответственного"',
            ];
        }
        return array_merge($arErrors, parent::ValidateProperties($arTestProperties, $user));
    }

    public static function GetPropertiesDialogValues($documentType, $activityName, &$arWorkflowTemplate, &$arWorkflowParameters, &$arWorkflowVariables, $arCurrentValues, &$arErrors): bool
    {
        $arErrors = [];
        $arProperties = [
            'IBLOCK_ID' => $arCurrentValues['IBLOCK_ID'],
            'DEAL_ID' => $arCurrentValues['DEAL_ID'],
            'GROUP_CODE' => $arCurrentValues['GROUP_CODE'],
            'DEAL_STAGE_CODE' => $arCurrentValues['DEAL_STAGE_CODE'],
            'USER_CODE' => $arCurrentValues['USER_CODE'],
            'SORT_CODE' => $arCurrentValues['SORT_CODE'],
            'ACTIVE_CODE' => $arCurrentValues['ACTIVE_CODE']
        ];
        $arErrors = self::ValidateProperties(
            $arProperties,
            new CBPWorkflowTemplateUser(
                CBPWorkflowTemplateUser::CurrentUser
            )
        );
        if (count($arErrors) > 0) {
            return false;
        }
        $arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);
        $arCurrentActivity["Properties"] = $arProperties;
        return true;
    }

    public static function GetPropertiesDialog($documentType, $activityName, $arWorkflowTemplate, $arWorkflowParameters, $arWorkflowVariables, $arCurrentValues = null, $formName = "")
    {
        $arCurrentValues = (empty($arCurrentValues)) ? [] : $arCurrentValues;
        $arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);
        if (is_array($arCurrentActivity['Properties'])) {
            $arCurrentValues = array_merge($arCurrentValues, $arCurrentActivity['Properties']);
        }
        return CBPRuntime::GetRuntime()->ExecuteResourceFile(
            static::getActivityFilePath(),
            "properties_dialog.php",
            [
                "arCurrentValues" => $arCurrentValues
            ]
        );
    }

    /**
     * @description Возвращает путь по исходного активити
     * @return string
     */
    protected static function getActivityFilePath(): string
    {
        return (new \ReflectionClass(static::class))->getFileName();
    }

    private function writeInfo(string $sMessage): void
    {
        Logger::writeInfo($sMessage, mtsmainsetuseractivity . phpApplication::getDocumentRoot() . $this->sLogDir);
    }
}
