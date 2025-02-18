<?php if(!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\SystemException;
use Bitrix\UI\Toolbar\Facade\Toolbar;
use Mts\Main\User\UserService;
use Mts\Main\Crm\DealSource\DealSources;
use Bitrix\Main\UserTable;
use Bitrix\Iblock\IblockTable;
use Bitrix\Iblock\Component\Tools;
use Bitrix\Iblock\Model\Section;
use Bitrix\Main\Text\StringHelper;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Page\Asset;
use Bitrix\Main\UI\Extension;
use Bitrix\Iblock\PropertyTable;
use Bitrix\Iblock\PropertyEnumerationTable;
use Bitrix\Crm\EO_Status;
use Bitrix\Crm\EO_Status_Collection;
use Bitrix\Iblock\Iblock;
use Bitrix\Main\Engine\Response\AjaxJson;
class UserGridControllerComponent extends CBitrixComponent implements Controllerable{

    public $iblockId = 0;
    private const DEFAULT_SORT = 99999;

    public function onPrepareComponentParams($arParams)
    {
        if (!isset($arParams['CACHE_TIME']))
        {
            $arParams['CACHE_TIME'] = 3600;
        }
        else
        {
            $arParams['CACHE_TIME'] = intval($arParams['CACHE_TIME']);
        }
        $arParams['IBLOCK_CODE'] = !empty($arParams['IBLOCK_CODE']) ? $arParams['IBLOCK_CODE'] : 'blockotv';
        $arParams['GROUP_CODE'] = !empty($arParams['GROUP_CODE']) ? $arParams['GROUP_CODE'] : 'UF_SOURCES';
        $arParams['USER_CODE'] = !empty($arParams['USER_CODE']) ? $arParams['USER_CODE'] : 'OTVETSTVENNYY';
        $arParams['SORT_CODE'] = !empty($arParams['SORT_CODE']) ? $arParams['SORT_CODE'] : 'OCHERED_VYZOVA';
        $arParams['ACTIVE_CODE'] = !empty($arParams['ACTIVE_CODE']) ? $arParams['ACTIVE_CODE'] : 'AKTIVEN';
        $arParams['LAST_USER'] = !empty($arParams['UF_LAST_USER']) ? $arParams['UF_LAST_USER'] : 'UF_LAST_USER';
        return $arParams;
    }

    /**
     * @throws SystemException
     * @throws Exception
     */
    public function executeComponent():void
    {
        Extension::load("ui.bootstrap4");
        CJSCore::Init(array("jquery3"));
        Extension::load("ui.buttons");
        Extension::load('ui.entity-selector');
        Extension::load("ui.select");
        Extension::load("ui.dialogs.messagebox");
        $this->getResult();
    }

    protected function getResult():void
    {
        $this->arResult['SOURCES'] = DealSources::getSourcesList(select:['NAME','ID','STATUS_ID'],filter:['ENTITY_ID'=>'SOURCE']);
        $dataRes = $this->getListUsersData();
        $this->arResult['USERS'] = $dataRes['USERS'];
        $this->arResult['DATA'] = $dataRes['DATA'];
        $this->arResult['IBLOCK_ID'] = $this->iblockId;

        $this->arResult['CAN_SECTION_ADD'] = CIBlockRights::UserHasRightTo($this->iblockId, $this->iblockId, 'section_add');
        $this->arResult['CAN_SECTION_EDIT'] = CIBlockRights::UserHasRightTo($this->iblockId, $this->iblockId, 'section_edit');
        $this->arResult['CAN_SECTION_DELETE'] = CIBlockRights::UserHasRightTo($this->iblockId, $this->iblockId, 'section_delete');
        $this->arResult['CAN_ELEMENT_ADD'] = CIBlockRights::UserHasRightTo($this->iblockId, $this->iblockId, 'element_add');
        $this->arResult['CAN_ELEMENT_EDIT'] = CIBlockRights::UserHasRightTo($this->iblockId, $this->iblockId, 'element_edit');
        $this->arResult['CAN_ELEMENT_DELETE'] = CIBlockRights::UserHasRightTo($this->iblockId, $this->iblockId, 'element_delete');
        $this->IncludeComponentTemplate();

    }
    /**
     * @throws LoaderException
     */
    public function includeModule():void
    {
        Loader::includeModule('mts.main');
        Loader::includeModule('iblock');
    }
    //источник => сотрудник
    public function getListUsersData():array
    {
        $arRes = [];
        $iblock = IblockTable::getList([
            'select' => ['ID', 'API_CODE'],
            'filter' => [
                "CODE" => $this->arParams['IBLOCK_CODE']
            ],
        ])->fetchObject();
        if (!$iblock || empty($iblock->getApiCode()))
        {
            return $arRes;
        }
        $this->iblockId = $iblock['ID'];
        $entity = Section::compileEntityByIblock($iblock['ID']);
        $sectionUser = $entity::getList(array(
            "select" => [
                $this->arParams['GROUP_CODE'],
                $this->arParams['LAST_USER'],
                "ID",
                "NAME",
            ],
            "filter" => [
                "IBLOCK_ID" => $iblock['ID'],
            ]
        ))->fetchCollection();
        $iblockEntity = $iblock->getEntityDataClass();
        foreach($sectionUser as $groupSection)
        {
            if(!CIBlockSectionRights::UserHasRightTo($iblock['ID'],$groupSection['ID'],'section_read'))
            {
                continue;
            }
            foreach ($groupSection["UF_SOURCES"] as $sourse)
            {
               $key = array_search($sourse, array_column($this->arResult['SOURCES'], 'STATUS_ID'));
               $arRes[$groupSection['ID']]['SECTIONS'][] = $this->arResult['SOURCES'][$key];
            }
        }
        $elementUsers = $iblockEntity::getList([
            'select' => [
                    'ID',
                    'SORT',
                    $this->arParams['SORT_CODE'],
                    'IBLOCK_SECTION_ID',
                    $this->arParams['USER_CODE'],
                    $this->arParams['ACTIVE_CODE'] . '.ITEM',
            ],
            'order' => ['SORT' => 'ASC'],
            'filter' => [
                    'IBLOCK_ID' => $iblock['ID'],
                    'IBLOCK_SECTION_ID' => array_keys($arRes),
            ],
        ])->fetchCollection();
        $methodUserId = 'get'.ucfirst(StringHelper::snake2camel($this->arParams['USER_CODE']));
        $methodUserSort = 'get'.ucfirst(StringHelper::snake2camel($this->arParams['SORT_CODE']));
        $methodUserActive = 'get'.ucfirst(StringHelper::snake2camel($this->arParams['ACTIVE_CODE']));

        $userListIds = [];
        foreach ($elementUsers as $user)
        {
            $userListIds[] = $user->$methodUserId()->getValue();
        }
        $userList = $this->getUserList($userListIds);
        foreach($sectionUser as $groupSection)
        {
            foreach ($elementUsers as $user)
            {
                if(!CIBlockElementRights::UserHasRightTo($iblock['ID'], $user['ID'], 'element_read'))
                {
                    continue;
                }
                if($user['IBLOCK_SECTION_ID'] == $groupSection['ID'])
                {
                    $arRes[$groupSection['ID']]['ELEMENTS'][] = [
                        'ID' => $user['ID'],
                        'ID_USER' => $user->$methodUserId()->getValue(),
                        'FIO' => $userList[$user->$methodUserId()->getValue()],
                        'ACTIVE' => $user->$methodUserActive()->getItem()->getValue(),
                        'SORT' => $user->$methodUserSort()->getValue(),
                        'IS_CURRENT' => ($user->$methodUserId()->getValue() == $groupSection[$this->arParams['LAST_USER']]) ? 'Y' : 'N',
                    ];
                }
            }
        }
        return [
            "DATA" => $arRes,
            "USERS" => $userList
        ];
    }
    public function getUserList(array $userIds):array
    {

        $userList = UserService::getInstance()->getByIds($userIds);
        $formatUserArr = [];
        foreach ($userList as $item)
        {
            $formatUserArr[$item->getId()] = $item->getLastName().' '.$item->getName().' '.$item->getSecondName();
        }
        return $formatUserArr;
    }
    public function getUserFioById(int $userId):string
    {
        $fio = '';
        $user = UserService::getInstance()->getById($userId);

        if(!empty($user))
        {
            $fio = $user->getLastName().' '.$user->getName().' '.$user->getSecondName();
        }
        return $fio;
    }
    private function getSectionNameBySource(array $elems):string
    {
        $nameSection = "";
        $sourcesItems = DealSources::getSourcesListCollection(select:['NAME','ID','STATUS_ID'], filter:['STATUS_ID'=>$elems]);
        foreach ($sourcesItems as $source)
        {
            $nameSection .= $source['NAME'].' ';
        }
        return $nameSection;
    }
    //
    public function configureActions():array
    {
        $prefilters = [
            'prefilters' => [
                new ActionFilter\Authentication(),
                new ActionFilter\HttpMethod(
                    array(ActionFilter\HttpMethod::METHOD_GET, ActionFilter\HttpMethod::METHOD_POST)
                ),
                new ActionFilter\Csrf(),
            ],
            'postfilters' => []
        ];
        return [
            'addSectionIblock' => $prefilters,
            'addSourceSection' => $prefilters,
            'replaceSourceSection' => $prefilters,
            'removeSourceSection'=> $prefilters,
            'deleteSectionIblock'=> $prefilters,
            'setUserActive'=> $prefilters,
            'changeUserSort'=> $prefilters,
            'changeUserElement'=> $prefilters,
            'addElementIblock'=> $prefilters,
			'deleteElementIblock'=> $prefilters,
        ];
    }
    public function getIblockData(int $idIBlock):Iblock
    {
        return IblockTable::getByPrimary($idIBlock, ['select' => ['ID', 'API_CODE']])->fetchObject();
    }

    //удаление элемента
    public function deleteElementIblockAction(int $iblockId, int $idElem)
    {
        //проверяем права на удаление элемента
        if(!CIBlockSectionRights::UserHasRightTo($iblockId, $idElem, 'element_delete'))
        {
            return AjaxJson::createSuccess([
                'result' => 'access_denied',
            ]);
        }
        $iblock = $this->getIblockData($iblockId);
        $iblockEntity = $iblock->getEntityDataClass();
        $elementUser = $iblockEntity::getByPrimary($idElem, ['select' => ['ID']])->fetchObject();
        $elementUser->delete();
        return AjaxJson::createSuccess([
            'result' => 'element_deleted',
        ]);
    }

    //удаление раздела
    public function deleteSectionIblockAction(int $iblockId, int $idSection)
    {
        //проверяем права на удаление раздела
        if(!CIBlockSectionRights::UserHasRightTo($iblockId, $idSection, 'section_delete')){
            return AjaxJson::createSuccess([
                'result' => 'access_denied',
            ]);
        }
        //удаляем элементы в разделе
        $iblock = $this->getIblockData($iblockId);
        $iblockEntity = $iblock->getEntityDataClass();
        $elementUsers = $iblockEntity::getList([
            'select' => ['ID'],
            'filter' => [
                'IBLOCK_ID' => $iblock['ID'],
                'IBLOCK_SECTION_ID' => $idSection,
            ],
        ])->fetchCollection();
        foreach ($elementUsers as $elementUser)
        {
            $elementUser->delete();
        }
        //
        $entity = Section::compileEntityByIblock($iblockId);
        $sectionUser = $entity::getByPrimary($idSection,['select' => ['ID']])->fetchObject();
        $sectionUser->delete();
        return AjaxJson::createSuccess([
            'result' => 'section_deleted',
        ]);
    }

    //добавление раздела
    public function addSectionIblockAction(int $iblockId, string $sourceId, string $params)
    {
        if(!CIBlockRights::UserHasRightTo($iblockId, $iblockId, 'section_add'))
        {
            return AjaxJson::createSuccess([
                'result' => 'access_denied',
            ]);
        }
        $params = json_decode($params, true);
        $name = DealSources::getSourceItem(select:['NAME','ID','STATUS_ID'],idSourceItem:$sourceId);
        $bs = new CIBlockSection;
        $newSection = $bs->Add([
            "ACTIVE" => "Y",
            "IBLOCK_ID" => $iblockId,
            "NAME" => $name['NAME'],
            $params['GROUP_CODE'] => [$name['STATUS_ID']],
        ]);
        return AjaxJson::createSuccess([
            'result' => 'section_add',
            'idSection' => $newSection,
            'idSource' => $name['ID'],
            'nameSource' => $name['NAME'],
            'statusId' => $name['STATUS_ID'],
        ]);
    }

    //добавление элемента
    public function addElementIblockAction(int $iblockId, int $sectionId, int $userId, string $params)
    {
        if(!CIBlockSectionRights::UserHasRightTo($iblockId, $sectionId, 'section_element_bind'))
        {
            return AjaxJson::createSuccess([
                'result' => 'access_denied',
            ]);
        }
        $iblock = $this->getIblockData($iblockId);
        $iblockEntity = $iblock->getEntityDataClass();
        $params = json_decode($params, true);
        //
        $rsProperty = PropertyTable::getList([
            'filter' => ['IBLOCK_ID'=>$iblockId,'CODE'=>$params['ACTIVE_CODE']],
            'select' =>['ID'],
        ])->fetchObject();
        //
        $rsEnum = PropertyEnumerationTable::getList(['filter' => ['XML_ID'=>'NOT_ACTIVE','PROPERTY_ID' => $rsProperty['ID']]]);
        $enum=$rsEnum->fetch();
        //
        $methodUserId = 'set'.ucfirst(StringHelper::snake2camel($params['USER_CODE']));
        $methodUserSort = 'set'.ucfirst(StringHelper::snake2camel($params['SORT_CODE']));
        $methodUserActive = 'set'.ucfirst(StringHelper::snake2camel($params['ACTIVE_CODE']));

        //
        $name = $this->getUserFioById($userId);
        $newUser = $iblockEntity::createObject();
        $newUser->setName($name)
                ->$methodUserId($userId)
                ->$methodUserActive($enum['ID'])
                ->$methodUserSort(self::DEFAULT_SORT)
                ->setSort(self::DEFAULT_SORT)
                ->setIblockSectionId((int)$sectionId)
                ->save();
        return AjaxJson::createSuccess([
            'result' => 'element_add',
            'idElem' => $newUser->getId(),
            'idEmployee' => $userId,
            'userName' =>$name,
        ]);
    }

    //замена источника
    public function replaceSourceSectionAction(int $iblockId, int $sectionId, string $oldSourceId, string $newSourceId, string $params)
    {
        if(!CIBlockSectionRights::UserHasRightTo($iblockId, $sectionId, 'section_edit'))
        {
            return AjaxJson::createSuccess([
                'result' => 'access_denied',
            ]);
        }
        //
        $params = json_decode($params, true);
        $entity = Section::compileEntityByIblock($iblockId);
        $sectionUser = $entity::getByPrimary($sectionId, ['select' => ['ID','NAME',$params['GROUP_CODE']]])->fetchObject();
        $nameSection = "";
        $newSourceItem = DealSources::getSourceItem(select:['NAME','ID','STATUS_ID'],idSourceItem:$newSourceId);
        if(in_array($newSourceItem['STATUS_ID'], $sectionUser[$params['GROUP_CODE']]))
        {
            return AjaxJson::createSuccess([
                'result' => 'source_exist'
            ]);
        }
        $oldSourceItem = DealSources::getSourceItem(select:['NAME','ID','STATUS_ID'],idSourceItem:$oldSourceId);
        $elems = $sectionUser[$params['GROUP_CODE']];
        $index = 0;
        foreach($elems as $elem)
        {
            if($elem == $oldSourceItem['STATUS_ID']){
                $elems[$index] = $newSourceItem['STATUS_ID'];
            }
            $index++;
        }
        $sectionUser['UF_SOURCES'] = $elems;
        $sectionUser['NAME'] = $this->getSectionNameBySource($elems);
        $sectionUser->save();
        return AjaxJson::createSuccess([
            'result' => 'source_replace',
            'idSource' => $newSourceItem['ID'],
            'nameSource' => $newSourceItem['NAME'],
            'statusId' => $newSourceItem['STATUS_ID'],
        ]);
    }

    //добавление источника
    public function addSourceSectionAction(int $iblockId, int $sectionId, string $sourceId, string $params)
    {
        if(!CIBlockSectionRights::UserHasRightTo($iblockId, $sectionId, 'section_edit')){
            return AjaxJson::createSuccess([
                'result' => 'access_denied',
            ]);
        }
        //
        $params = json_decode($params, true);
        $name = DealSources::getSourceItem(select:['NAME','ID','STATUS_ID'],idSourceItem:$sourceId);
        $entity = Section::compileEntityByIblock($iblockId);
        $sectionUser = $entity::getByPrimary($sectionId, ['select' => ['ID','NAME',$params['GROUP_CODE']]])->fetchObject();
        if(in_array($name['STATUS_ID'], $sectionUser[$params['GROUP_CODE']]))
        {
            return AjaxJson::createSuccess([
                'result' => 'source_exist'
            ]);
        }
        $sourceList = $sectionUser[$params['GROUP_CODE']];
        $sourceList[] = $name['STATUS_ID'];
        $sectionUser['UF_SOURCES'] = $sourceList;
        $sectionUser['NAME'] .=' '.$name['NAME'];
        $sectionUser->save();
        return AjaxJson::createSuccess([
            'result' => 'source_add',
            'idSource' => $name['ID'],
            'nameSource' => $name['NAME'],
            'statusId' => $name['STATUS_ID'],
        ]);
    }

    //удаление источника
    public function removeSourceSectionAction(int $iblockId, int $sectionId, string $sourceCode, string $params)
    {
        if(!CIBlockSectionRights::UserHasRightTo($iblockId, $sectionId, 'section_edit')){
            return AjaxJson::createSuccess([
                'result' => 'access_denied',
            ]);
        }
        $params = json_decode($params, true);
        $entity = Section::compileEntityByIblock($iblockId);
        $sectionUser = $entity::getByPrimary($sectionId, ['select' => ['ID','NAME',$params['GROUP_CODE']]])->fetchObject();
        $elems = $sectionUser[$params['GROUP_CODE']];
        $index = 0;
        foreach($elems as $elem)
        {
            if($elem == $sourceCode)
            {
                unset($elems[$index]);
            }
            $index++;
        }
        //если удалены все источники - удаляем раздел с элементами
        if(empty($elems))
        {
            $this->deleteSectionIblockAction($iblockId,$sectionId);
            return AjaxJson::createSuccess([
                'result' => 'section_delete',
            ]);
        }
        $sectionUser[$params['GROUP_CODE']] = $elems;
        $sectionUser['NAME'] = !empty($elems) ? $this->getSectionNameBySource($elems) : $sectionUser['NAME'];
        $sectionUser->save();
        return AjaxJson::createSuccess([
            'result' => 'source_delete',
            'elem' => $elem
        ]);
    }

    //активность/неактивность пользователя
    public function setUserActiveAction(int $iblockId, int $userId, string $activeUser, string $params)
    {
        if(!CIBlockElementRights::UserHasRightTo($iblockId, $userId, 'element_edit')){
            return AjaxJson::createSuccess([
                'result' => 'access_denied',
            ]);
        }
        $params = json_decode($params, true);
        $rsProperty = PropertyTable::getList([
            'filter' => ['IBLOCK_ID'=>$iblockId,'CODE'=>$params['ACTIVE_CODE']],
            'select' =>['ID'],
        ])->fetchObject();
        //
        $rsEnum = PropertyEnumerationTable::getList(['filter' => ['PROPERTY_ID' => $rsProperty['ID']]]);
        //
        $iblock = $this->getIblockData($iblockId);
        $iblockEntity = $iblock->getEntityDataClass();
        //
        $elementUser = $iblockEntity::getByPrimary($userId, ['select' => ['ID', $params['ACTIVE_CODE']]])->fetchObject();
        $methodUserActive = 'set'.ucfirst(StringHelper::snake2camel($params['ACTIVE_CODE']));
        while($enum=$rsEnum->fetch())
        {
            if(
                $activeUser == 'Y' && $enum['XML_ID'] == 'ACTIVE'
                ||$activeUser == 'N' && $enum['XML_ID'] == 'NOT_ACTIVE'
            )
            {
                $elementUser->$methodUserActive((int)$enum['ID']);
                $elementUser->save();
            }
        }
        $elementUser->save();
        return AjaxJson::createSuccess([
            'result' => 'change_status',
        ]);
    }

    //сортировка пользователей
    public function changeUserSortAction(int $iblockId, string $sortArray, string $params)
    {
        $params = json_decode($params, true);
        $elemsSorts = json_decode($sortArray, true);
        //
        $iblock = $this->getIblockData($iblockId);
        $iblockEntity = $iblock->getEntityDataClass();
        $elementUsers = $iblockEntity::getList([
            'select' => [
                'ID',
                'SORT',
                $params['SORT_CODE'],
            ],
            'order' => ['SORT' => 'ASC'],
            'filter' => [
                'ID' => array_keys($elemsSorts),
            ],
        ])->fetchCollection();
        $methodUserSort = 'set'.ucfirst(StringHelper::snake2camel($this->arParams['SORT_CODE']));
        foreach ($elementUsers as $user)
        {
            if(empty($elemsSorts[$user['ID']]))
            {
                continue;
            }
            $user->$methodUserSort((int)$elemsSorts[$user['ID']]);
            $user['SORT'] = $elemsSorts[$user['ID']];
            $user->save();
        }
        return AjaxJson::createSuccess([
            'result' => 'change_order',
        ]);
    }

    //смена одного пользователя на другой
    public function changeUserElementAction(int $iblockId, int $elementId, int $newUser, string $params)
    {
        if(!CIBlockElementRights::UserHasRightTo($iblockId, $elementId, 'element_edit'))
        {
            return AjaxJson::createSuccess([
                'result' => 'access_denied',
            ]);
        }
        $params = json_decode($params, true);
        $iblock = $this->getIblockData($iblockId);
        $iblockEntity = $iblock->getEntityDataClass();
        $elementUser = $iblockEntity::getByPrimary($elementId, [
            'select' => [
                'ID',
                'NAME',
                $params['USER_CODE'],
            ]
        ])->fetchObject();

        $method = 'set'.ucfirst(StringHelper::snake2camel($params['USER_CODE']));
        $elementUser->$method($newUser);
        $newName = $this->getUserFioById($newUser);
        $elementUser['NAME'] = $newName;
        $elementUser->save();
        return AjaxJson::createSuccess([
            'result' => 'user_change',
            'userId' => $newUser,
            'userName' => $newName
        ]);
    }

}