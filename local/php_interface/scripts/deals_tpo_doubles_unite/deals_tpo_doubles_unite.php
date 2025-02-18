<?php
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\IO;
use Bitrix\Main\Diag\Debug;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Type\Date;
use Bitrix\Main\UserTable;
use Bitrix\Main\Result;
use Bitrix\Crm\Service\Container;
use Bitrix\Crm\ItemIdentifier;
use Bitrix\Crm\DealTable;
use Bitrix\Recyclebin\Internals\Models\RecyclebinTable;
use Bitrix\Recyclebin\Recyclebin;
use Mts\Main\Scripts\AbstractScript;

if (php_sapi_name() !== 'cli'){
   // exit();
}

define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 4);

require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/local/php_interface/vendor/autoload.php');

global $USER;
$USER->Authorize(1);
if(!Loader::includeModule('mts.main')  || !Loader::includeModule('crm') || !Loader::includeModule('recyclebin')){
    exit();
}

class DealsTpoDoublesUnite extends AbstractScript
{
    const CSV_FILE_NAME = 'deals_tpo.csv';
    const DEAL_CATEGORY_ID = 7;
    const DEAL_STAGE = 'C7:WON';
    const DEAL_DATE_FROM = '01.12.2022';
    const DEAL_DATE_TO = '31.12.2023';
    const DEAL_DAYS_INTERVAL = 61;
    const FIELD_TPO = 'UF_CRM_1646993425489';
    const FIELD_INN = 'UF_CRM_1646057629398';
    const AR_RESPONSIBLE_EXCLUDE = [
        7106, 5761, 7138, 5804, 7366,
    ];
    const AR_MANAGERS = [
        5780 => 'Виктория Шумилина',
        4988 => 'Вадим Гуденко',
        5753 => 'Василий Титов',
        4282 => 'Глебов Константин',
        5455 => 'Ия Жлудова',
        5858 => 'Елена Люликова',
        7138 => 'Елена Бирюлина',
        7366 => 'Тимур Канцеров',
        7171 => 'Анастасия Попова',
        6576 => 'Максим Алексеев',
        1964 => 'Николай Петров',
        4095 => 'Лялина Алена',
        5241 => 'Стародубцев Вадим',
        5768 => 'Симонов Виталий',
        5363 => 'Грауль Анастасия',
        7129 => 'Кумпан Дарья',
        5876 => 'Шикера Лаурита',
        5351 => 'Третьяк Ирина',
        5245 => 'Гадецкая Екатерина',
        6014 => 'Колесникова Ирина',
        5246 => 'Черняновская Марина',
        7856 => 'Земцова Владлена',
        5443 => 'Лосева Александра',
        5048 => 'Юлия Есипова',
        8255 => 'Сухорукова Валерия',
        7641 => 'Демиденко Ксения',
        7300 => 'Молокович Дарья',
        5795 => 'Пащенко Ирина',
        6134 => 'Ульянова Елена',
        5693 => 'Панкова Светлана',
        5852 => 'Живчук Екатерина',
        6558 => 'Володина Ольга',
        5244 => 'Лия Каталкина',
        6970 => 'Комарова Дарья',
        7470 => 'Мышленникова Анна',
        5238 => 'Рыжук Олег',
        5686 => 'Редько Кристина',
        5237 => 'Костюнина Марина',
        5339 => 'Тимошкова Наталья',
        5442 => 'Шахова Ирина',
        6229 => 'Екатерина Энграф',
        5505 => 'Анцупова Карина',
        8288 => 'Бочагова Надежда',
        5624 => 'Процюк Вирсавия',
        5507 => 'Маслевская Анжелика',
        8573 => 'Ширина Кристина',
        1629 => 'Татьяна Пивоварова',
        5622 => 'Рощина Анна',
        5243 => 'Карпенко Анна',
        5444 => 'Скульская Ирина',
        6460 => 'Шахова Анастасия',
        5330 => 'Плотникова Ирина',
        5226 => 'Солодовник Екатерина',
        5242 => 'Маковецкая Линда',
        5623 => 'Туривненко Алина',
        5239 => 'Кирилина Ольга',
        5599 => 'Бугракова Мария',
        3354 => 'Виктория Гончарук',
        7256 => 'Шумакова Ольга',
    ];

    const REPORT_FILE_NAME = 'Сделки без сервис менеджеров.csv';
    const LOG_NAME = 'deals_tpo_doubles_unite';
    const UPDATED_DEALS_FILE_NAME = 'updated_deals.csv';
    const DELETED_DEALS_FILE_NAME = 'deleted_deals.csv';

    protected $dealFactory;

    public function __construct()
    {
        parent::__construct();

        $this->createFile(self::REPORT_FILE_NAME);
        $this->createFile(self::UPDATED_DEALS_FILE_NAME);
        $this->createFile(self::DELETED_DEALS_FILE_NAME);

        $path = __DIR__.'/'.self::CSV_FILE_NAME;
        if (!IO\File::isFileExists($path)) {
            throw new Exception('не найден csv файл: '.$path);
        }
    }

    protected function getLogName(): string
    {
        return self::LOG_NAME;
    }

    private function createFile($fileName)
    {
        $path = $_SERVER['DOCUMENT_ROOT'].'/'.static::ROOT_FILE_LOG_PATH.'/'.self::LOG_NAME.'/'.$fileName;

        if (IO\File::isFileExists($path)) {
            return;
        }
        IO\File::putFileContents($path, '', IO\File::APPEND);

        if (!IO\File::isFileExists($path)) {
            throw new Exception('cant create file: '.$path);
        }
    }


    private function getArDealsByTpo($tpo)
    {
        if (empty($tpo)) {
            return [];
        }
        $filter = [
            '>=DATE_CREATE' => self::DEAL_DATE_FROM,
            '<=DATE_CREATE' => self::DEAL_DATE_TO,
            'STAGE_ID' => self::DEAL_STAGE,
            'CATEGORY_ID' => self::DEAL_CATEGORY_ID,
            '!ASSIGNED_BY_ID' => self::AR_RESPONSIBLE_EXCLUDE,
        ];
        $filter[self::FIELD_TPO] = $tpo;

        $select = [
            'ID',
            'DATE_CREATE',
            self::FIELD_TPO,
        ];

        $arDeals = $this->getArDeals($filter, $select);

        return $arDeals;
    }

    private function fillDealFields($deal)
    {
        $id = intval($deal['ID']);
        if (empty($id)) {
            return $deal;
        }

        $filter = [
            'ID' => $id,
        ];
        $select = [
            '*',
            'UF_*',
        ];
        $arDeal = $this->getArDeals($filter, $select)[$id];
        if (empty($arDeal)) {
            return $deal;
        }

        return array_merge($deal, $arDeal);
    }

    private function getArDeals($filter, $select=['ID'], $order=['DATE_CREATE' => 'ASC'])
    {
        if (empty($filter)) {
            return false;
        }
        $resDeals = DealTable::GetList([
            'select' => $select,
            'filter' => $filter,
            'order' => $order,
        ]);

        $arDeals = [];
        while ($deal = $resDeals->fetch())
        {
            foreach ($deal as $fieldName => $fieldValue)
            {
                if ($fieldValue instanceof DateTime || $fieldValue instanceof Date) {
                    $deal[$fieldName] = $fieldValue->format('d.m.Y H:i:s');
                }
            }

            $arDeals[$deal['ID']] = $deal;
        }

        return $arDeals;
    }

    public function getFio($userId)
    {
        $select = ['ID', 'NAME', 'LAST_NAME', 'SECOND_NAME', 'LOGIN'];
        $filter = ['ID' => $userId];
        $user = UserTable::getList(['select' => $select, 'filter' => $filter])->fetch();
        if (empty($user)) {
            return '';
        }

        return \CUser::FormatName(\CSite::GetNameFormat(), $user, true, false);
    }


    public function processScriptActions(): void
    {

        $this->logger->info('Начало поиска дубликатов сделок');

        $ufData = Application::getUserTypeManager()->GetUserFields('CRM_DEAL');

        $path = __DIR__.'/'.self::CSV_FILE_NAME;
        $totalLength = $this->getTotalFileLength($path);

        $file = fopen($path, 'r');

        $arTpoProcessedTpo = [];
        $countUpdated = 0;
        $countDeleted = 0;
        try {
            $entityTypeId = \CCrmOwnerType::Deal;
            $this->dealFactory = Container::getInstance()->getFactory($entityTypeId);
            $rowNumber = 0;
            foreach ($this->csvGetTpo($file) as $data)
            {
                $rowNumber++;
                $tpo = intval($data[1]);
                if (empty($tpo) || in_array($tpo, $arTpoProcessedTpo)) {
                    continue;
                }

                $this->logger->info('Ищем сделки по ТПО ' . $tpo);
                $this->logger->info($rowNumber.'/'.$totalLength);
                $arDeals = $this->getArDealsByTpo($tpo);
                if (empty($arDeals)) {
                    $this->logger->info('Сделки не найдены');
                    continue;
                }

                $arDealsGroupsToUnite = $this->getDealsGroupsToUnite($arDeals);
                if (empty($arDealsGroupsToUnite)) {
                    continue;
                }

                foreach ($arDealsGroupsToUnite as $key => $dealsToUnite)
                {
                    $disp = '';
                    if ($totalLength > 0 && $rowNumber > 0) {
                        $perc = (double)($rowNumber / $totalLength);
                        $perc = number_format($perc * 100, 0);
                        $disp = '; обработано - '.$perc.'% '.$rowNumber.'/'.$totalLength;
                    }

                    $this->logger->info('Найдено дубликатов с ТПО '.$tpo.': '.count($dealsToUnite).'; часть №'.($key+1).$disp);

                    $arDealsNoManager = [];
                    $uniteResult = $this->doUniteDeals($dealsToUnite, $ufData, $arDealsNoManager, $countUpdated, $countDeleted);
                    if (!$uniteResult) {
                        throw new \Exception('Ошибка объединения сделок с ТПО: '.$tpo);
                    }

                    $reportSave = true;
                    if (!empty($arDealsNoManager)) {
                        $this->logger->info('Сохранение отчета');
                        $reportSave = $this->saveReport($arDealsNoManager);
                    }
                    if (!$reportSave) {
                        throw new \Exception('Ошибка сохранения отчета');
                    }
                }

                $arTpoProcessedTpo[] = $tpo;
            }
        } catch (\Throwable $throwable) {
            dump($throwable);
            $this->logger->error('Ошибка обработки файла: '.__DIR__.'/'.self::CSV_FILE_NAME.'; '.$throwable->getMessage());
        } finally {
            fclose($file);
        }

        $this->logger->info('Обновлено сделок: '.$countUpdated);
        $this->logger->info('Удалено сделок: '.$countDeleted);

        $this->logger->info('Объединение дубликатов сделок завершено');
    }

    private function getTotalFileLength($path)
    {
        $file = fopen($path,'r');
        if (!$file) {
            return 0;
        }
        $totalLength = 0;

        try {
            while (fgetcsv($file) !== FALSE)
            {
                $totalLength++;
            }
        } catch (\Throwable $throwable) {
            dump($throwable);
            $this->logger->error('Ошибка обработки файла: '.$path.'; '.$throwable->getMessage());
        } finally {
            fclose($file);
        }

        return $totalLength;
    }

    private function csvGetTpo($fileHandle, $delimeter=';')
    {
        $header = [];
        $row = 0;

        if ($fileHandle === false) {
            return false;
        }

        while (($data = fgetcsv($fileHandle, 0, $delimeter)) !== false)
        {
            if (0 == $row) {
                $header = $data;
            } else {
                $fields = array_combine($header, $data);
                $tpo = intval($fields['ТПО']);
                if (empty($tpo)) {
                    continue;
                }
                yield [$row, $tpo];
            }

            $row++;
        }
        return false;
    }

    private function getDealsGroupsToUnite($arDeals)
    {
        if (empty($arDeals) || count($arDeals) <= 1) {
            return [];
        }

        $groups = [];
        $i = 0;
        foreach ($arDeals as $deal)
        {
            $dealDate = $deal['DATE_CREATE'];
            if (empty($dealDate)) {
                continue;
            }

            if (!empty($groups[$i])) {
                $lastKey = array_key_last($groups[$i]);
                $lastDealDate = $groups[$i][$lastKey]['DATE_CREATE'];
                if (empty($lastDealDate)) {
                    continue;
                }

                $dealDateTime = new \DateTime($dealDate);
                $lastDealDateTime = new \DateTime($lastDealDate);

                $dateDiffDays = $lastDealDateTime->diff($dealDateTime)->format('%r%a');
                if (abs($dateDiffDays) > self::DEAL_DAYS_INTERVAL) {
                    $i++;
                }
            }

            $groups[$i][] = $deal;
        }
        if (empty($groups)) {
            return [];
        }

        foreach ($groups as $key => $intervalDeals)
        {
            if (count($intervalDeals) <= 1) {
                unset($groups[$key]);
            }
        }
        if (empty($groups)) {
            return [];
        }

        return $groups;
    }

    private function doUniteDeals($arDealsToUnite, $ufData, &$arDealsNoManager=[], &$countUpdated=0, &$countDeleted=0)
    {
        if (empty($arDealsToUnite)) {
            return true;
        }

        $arFindAssigned = [];
        foreach ($arDealsToUnite as &$deal)
        {
            $deal = $this->fillDealFields($deal);

            $assigned = intval($deal['ASSIGNED_BY_ID']);

            if (array_key_exists($assigned, self::AR_MANAGERS)) {
                if (!in_array($assigned, $arFindAssigned)) {
                    $arFindAssigned[] = $assigned;
                }
            } else {
                $arDealsNoManager[] = $deal;
            }
        }

        foreach ($ufData as $fieldName => $field)
        {
            if ($fieldName === self::FIELD_TPO) {
                continue;
            }

            $hasValue = false;
            foreach ($arDealsToUnite as $dealFields)
            {
                $value = $dealFields[$fieldName];
                if (!empty($value)) {
                    $hasValue = true;
                    break;
                }
            }

            $allSame = true;
            $value = null;
            foreach ($arDealsToUnite as $dealFields)
            {
                if (!isset($value)) {
                    $value = $dealFields[$fieldName];
                    continue;
                }
                if ($value !== $dealFields[$fieldName]) {
                    $allSame = false;
                    break;
                }
            }

            if (!$hasValue || $allSame) {
                foreach ($arDealsToUnite as $key => $dealFields)
                {
                    unset($arDealsToUnite[$key][$fieldName]);
                }
            }
        }

        $firstDeal = array_shift($arDealsToUnite);
        $firstDealId = intval($firstDeal['ID']);
        $lastDeal = $arDealsToUnite[array_key_last($arDealsToUnite)];
        if (empty($firstDeal) || empty($firstDealId) || empty($arDealsToUnite) || empty($lastDeal)) {
            return;
        }

        $updateFields = $firstDeal;
        unset($updateFields['ID']);
        unset($updateFields['DATE_CREATE']);
        unset($updateFields['DATE_MODIFY']);
        unset($updateFields['CREATED_BY_ID']);
        unset($updateFields['MODIFY_BY_ID']);
        unset($updateFields['OPENED']);
        unset($updateFields['TITLE']);
        unset($updateFields['CATEGORY_ID']);
        unset($updateFields['STAGE_ID']);
        unset($updateFields['STAGE_SEMANTIC_ID']);
        unset($updateFields['IS_NEW']);
        unset($updateFields['CLOSED']);
        unset($updateFields['TYPE_ID']);
        unset($updateFields['BEGINDATE']);
        unset($updateFields['CLOSEDATE']);
        unset($updateFields['SEARCH_CONTENT']);
        unset($updateFields['MOVED_BY_ID']);
        unset($updateFields['MOVED_TIME']);

        $opportunity = floatval($lastDeal['OPPORTUNITY']);
        if ($opportunity > 0) {
            $updateFields['OPPORTUNITY'] = $opportunity;
        }
        $updateFields['IS_MANUAL_OPPORTUNITY'] = $lastDeal['IS_MANUAL_OPPORTUNITY'];
        $updateFields['OPPORTUNITY_ACCOUNT'] = $lastDeal['OPPORTUNITY_ACCOUNT'];
        $updateFields['CURRENCY_ID'] = $lastDeal['CURRENCY_ID'];
        $updateFields['TAX_VALUE'] = $lastDeal['TAX_VALUE'];
        $updateFields['TAX_VALUE_ACCOUNT'] = $lastDeal['TAX_VALUE_ACCOUNT'];
        $updateFields['ACCOUNT_CURRENCY_ID'] = $lastDeal['ACCOUNT_CURRENCY_ID'];

        if (empty($arFindAssigned) || count($arFindAssigned) > 1) {
            $updateFields['ASSIGNED_BY_ID'] = $lastDeal['ASSIGNED_BY_ID'];
        } else {
            $updateFields['ASSIGNED_BY_ID'] = $arFindAssigned[0];
        }

        $arComments = [];
        if (!empty($updateFields['COMMENTS'])) {
            $arComments[] = $updateFields['COMMENTS'];
        }
        foreach ($arDealsToUnite as $dealFields)
        {
            $comment = $dealFields['COMMENTS'];
            if (empty($comment) || in_array($comment, $arComments)) {
                continue;
            }
            $arComments[] = $comment;
        }
        if (!empty($arComments)) {
            if (count($arComments) === 1) {
                $comments = $arComments[0];
            } else {
                $comments = '';
                foreach ($arComments as $comment)
                {
                    if (empty($comments)) {
                        $comments = $comment;
                        continue;
                    }
                    $comments = <<<STR
                                $comments
                                $comment
                                STR;
                }
            }

            if ($updateFields['COMMENTS'] == $comments) {
                unset($updateFields['COMMENTS']);
            } else {
                $updateFields['COMMENTS'] = $comments;
            }
        }

        $arFields = [
            'LEAD_ID',
            'COMPANY_ID',
            'CONTACT_ID',
            'QUOTE_ID',
            'PRODUCT_ID',
            'IS_RECURRING',
            'IS_RETURN_CUSTOMER',
            'IS_REPEATED_APPROACH',
            'PROBABILITY',
            'EVENT_DATE',
            'EVENT_ID',
            'EVENT_DESCRIPTION',
            'EXCH_RATE',
            'LOCATION_ID',
            'WEBFORM_ID',
            'SOURCE_ID',
            'SOURCE_DESCRIPTION',
            'ORIGINATOR_ID',
            'ORIGIN_ID',
            'ADDITIONAL_INFO',
            'ORDER_STAGE',
            'LAST_ACTIVITY_BY',
            'LAST_ACTIVITY_TIME',
        ];
        foreach ($arFields as $fieldName)
        {
            foreach ($arDealsToUnite as $dealFields)
            {
                $value = $dealFields[$fieldName];
                if (!empty($updateFields[$fieldName])) {
                    break;
                }
                if (empty($value)) {
                    continue;
                }
                $updateFields[$fieldName] = $value;
            }

            if ($firstDeal[$fieldName] === $updateFields[$fieldName]) {
                unset($updateFields[$fieldName]);
            }
        }

        foreach ($ufData as $fieldName => $field)
        {
            $isMultiple = $field['MULTIPLE'] === 'Y';

            if ($isMultiple) {
                foreach ($arDealsToUnite as $dealFields)
                {
                    $values = $dealFields[$fieldName];
                    foreach ($values as $value)
                    {
                        if (empty($updateFields[$fieldName])) {
                            $updateFields[$fieldName] = [$value];

                        } else if (!in_array($value, $updateFields[$fieldName])) {
                            $updateFields[$fieldName][] = $value;
                        }
                    }
                }

            } else if (empty($updateFields[$fieldName])) {
                foreach ($arDealsToUnite as $dealFields)
                {
                    $value = $dealFields[$fieldName];
                    if (empty($value) || !empty($updateFields[$fieldName])) {
                        continue;
                    }
                    $updateFields[$fieldName] = $value;
                }
            }
        }

        foreach ($updateFields as $fieldName => $fieldValue)
        {
            if ($fieldValue === $firstDeal[$fieldName]) {
                unset($updateFields[$fieldName]);
            }
        }

        foreach ($updateFields as &$field)
        {
            if ($field instanceof Date) {
                $field = $field->format('d.m.Y');
            } else if ($field instanceof DateTime) {
                $field = $field->format('d.m.Y H:i:s');
            }
        }

        $arDealsToDelete = [];
        foreach ($arDealsToUnite as $dealFields)
        {
            $dealId = intval($dealFields['ID']);
            if (in_array($dealId, $arDealsToDelete)) {
                continue;
            }
            $arDealsToDelete[] = $dealId;
        }

        if (!empty($updateFields)) {
            $updateResult = $this->processUpdateDeals($firstDealId, $firstDeal, $updateFields);
            if (!$updateResult) {
                return false;
            } else {
                $countUpdated++;
            }
        }

//        if (!empty($arDealsToDelete)) {
//            $deletedItems = $this->processDeleteDeals($arDealsToDelete, $arDealsToUnite);
//            if ($deletedItems === false) {
//                return false;
//            }
//            $countDeleted += $deletedItems;
//        }
        return true;
    }

    private function processUpdateDeals($dealId, $arDeal, $updateFields)
    {
        $dealEntity = $this->dealFactory->getItem($dealId);

        foreach ($updateFields as $fieldName => $value)
        {
            $dealEntity->set($fieldName, $value);
        }

        $operation = $this->dealFactory->getUpdateOperation($dealEntity);
        $operation->disableAllChecks();
        $operation->disableBeforeSaveActions();
        $operation->disableAfterSaveActions();

        $updateResult = $operation->launch();

        if (!$updateResult->isSuccess()) {
            $message = is_array($updateResult->getErrorMessages()) ?
                implode('; ', $updateResult->getErrorMessages()) :
                $updateResult->getErrorMessages();
            $this->logger->error('Ошибка обновления сделки: '.$dealId.'; '.$message);
            return false;
        }

        $fields = [];
        foreach ($updateFields as $fieldName => $value)
        {
            $oldValue = $arDeal[$fieldName];
            if ($oldValue instanceof DateTime) {
                $oldValue = $oldValue->format('d.m.Y H:i:s');
            } else if ($oldValue instanceof Date) {
                $oldValue = $oldValue->format('d.m.Y');
            }

            $fields[$fieldName] = [
                'value' => $value,
                'oldValue' => $oldValue,
            ];
        }
        $fields[self::FIELD_TPO] = [
            'value' => array_key_exists(self::FIELD_TPO, $updateFields) ? $updateFields[self::FIELD_TPO] : $arDeal[self::FIELD_TPO],
            'oldValue' => $arDeal[self::FIELD_TPO],
        ];

        $this->logger->info('Обновление сделки: '.$dealId);

        $path = $_SERVER['DOCUMENT_ROOT'].'/'.static::ROOT_FILE_LOG_PATH.'/'.self::LOG_NAME.'/'.self::UPDATED_DEALS_FILE_NAME;
        $headerFields = [
            'ID',
            'FIELDS',
        ];

        $fileHandle = $this->getCsvWriteFileHandle($path, $headerFields);
        if (!$fileHandle) {
            return false;
        }

        $result = false;
        try {
            $data = [
                $dealId,
                serialize($fields),
            ];

            $result = $this->saveDealCsv($fileHandle, $data);
        } catch (\Throwable $throwable) {
            dump($throwable);
            $this->logger->error('Ошибка обработки файла: '.$path.'; '.$throwable->getMessage());
        } finally {
            fclose($fileHandle);
        }

        return $result;
    }

    private function processDeleteDeals($arDealsId, $arDeals)
    {
        $path = $_SERVER['DOCUMENT_ROOT'].'/'.static::ROOT_FILE_LOG_PATH.'/'.self::LOG_NAME.'/'.self::DELETED_DEALS_FILE_NAME;
        $headerFields = [
            'ID',
            'FIELDS',
        ];

        $fileHandle = $this->getCsvWriteFileHandle($path, $headerFields);
        if (!$fileHandle) {
            return false;
        }

        $deletedItems = 0;
        try {
            foreach ($arDealsId as $dealId)
            {
                $dealEntity = $this->dealFactory->getItem($dealId);

                $deleteResult = $this->dealFactory->getDeleteOperation($dealEntity)->launch();

                if (!$deleteResult->isSuccess()) {
                    $message = is_array($deleteResult->getErrorMessages()) ?
                        implode('; ', $deleteResult->getErrorMessages()) :
                        $deleteResult->getErrorMessages();
                    $this->logger->error('Ошибка удаления сделки: '.$dealId.'; '.$message);
                } else {
                    $deletedItems++;
                }

                $deleteDeal = null;
                foreach ($arDeals as $dealFields)
                {
                    if (intval($dealFields['ID']) === intval($dealId)) {
                        $deleteDeal = $dealFields;
                    }
                }
                foreach ($deleteDeal as $fieldName => &$value)
                {
                    if ($value instanceof DateTime) {
                        $value = $value->format('d.m.Y H:i:s');
                    } else if ($value instanceof Date) {
                        $value = $value->format('d.m.Y');
                    }
                }

                $this->logger->info('Удаление сделки: '.$dealId);

                $data = [
                    $dealId,
                    serialize($deleteDeal),
                ];
                $this->saveDealCsv($fileHandle, $data);
            }
        } catch (\Throwable $throwable) {
            dump($throwable);
            $this->logger->error('Ошибка обработки файла: '.$path.'; '.$throwable->getMessage());
            $deletedItems = false;
        } finally {
            fclose($fileHandle);
        }

        return $deletedItems;
    }

    private function getCsvWriteFileHandle($path, $headerFields=[], $delimeter=';')
    {
        $file = fopen($path, 'a+');

        try {
            if (filesize($path) == 0) {
                fputs($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

                if (!empty($headerFields)) {
                    $len = fputcsv(
                        $file,
                        $headerFields,
                        $delimeter,
                    );
                    if (!$len) {
                        throw new Exception('error write file: '.$path);
                    }
                }
            }
        } catch (\Throwable $throwable) {
            dump($throwable);
            $this->logger->error('Ошибка обработки файла: '.$path.'; '.$throwable->getMessage());
            fclose($file);
            return false;
        }

        return $file;
    }

    private function saveReport($arDealsNoManager)
    {
        $path = $_SERVER['DOCUMENT_ROOT'].'/'.static::ROOT_FILE_LOG_PATH.'/'.self::REPORT_FILE_NAME;
        $headerFields = [
            'ТПО',
            'ИНН',
            'ID',
            'ФИО ответственного',
            'ID ответственного',
        ];

        $file = $this->getCsvWriteFileHandle($path, $headerFields);
        if (!$file) {
            return false;
        }

        $result = true;
        try {
            $arFio = [];
            foreach ($arDealsNoManager as $deal)
            {
                $assigned = intval($deal['ASSIGNED_BY_ID']);

                $fio = $arFio[$assigned];
                if (empty($fio)) {
                    $fio = $this->getFio($assigned);
                    if (!empty($fio)) {
                        $arFio[$assigned] = $fio;
                    }
                }

                $len = fputcsv(
                    $file,
                    [
                        $deal[self::FIELD_TPO],
                        $deal[self::FIELD_INN],
                        $deal['ID'],
                        $fio,
                        $assigned,
                    ],
                    ';',
                );
                if (!$len) {
                    throw new Exception('error write report file');
                }
            }
        } catch (\Throwable $throwable) {
            dump($throwable);
            $this->logger->error('Ошибка обработки файла: '.$path.'; '.$throwable->getMessage());
            $result = false;
        } finally {
            fclose($file);
        }
        return $result;
    }

    private function saveDealCsv($fileHandle, $data, $delimeter=';')
    {
        $len = fputcsv(
            $fileHandle,
            $data,
            $delimeter,
        );
        if (!$len) {
            throw new Exception('error write csv file');
        }
        return true;
    }
}

try {
    (new DealsTpoDoublesUnite())->run();
}
catch (\Throwable $throwable) {
    dump($throwable);
}
finally {
    //$USER->Logout();
}


