<?php
use Bitrix\Main\Loader;
use Bitrix\Main\IO;
use Bitrix\Main\Diag\Debug;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Type\Date;
use Bitrix\Crm\DealTable;
use Bitrix\Crm\Category\DealCategory;

define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 4);

require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

class DealsUpdateStageLose
{
    public int $lastRowNumber = 0;
    public int $reasonFailEnumId;
    public int $startTime;

    public array $arCategories = [];
    public array $arUsers = [];
    public array $arStages = [];

    const CSV_FILE_NAME = 'Сделки для закрытия 180+.csv';
    const DEAL_CATEGORY_ID = 5;
    const FIELD_REASON_FAIL = 'UF_CRM_1646846305877';
    const FIELD_REASON_FAIL_VALUE = 'b15cbbb8818630241fcaded989b5bf51';
    const AR_RESPONSIBLE = [
        5927, 5003, 5381, 5755, 5941, 5928, 5679, 5555, 5700, 5675,
    ];
    const AR_STAGES = [
        'C5:NEW', 'C5:PREPARATION',
    ];
    const DEAL_STAGE_UPDATE = 'C5:LOSE';
    const DEAL_DATE = '31.01.2024';

    const DIR_LOGS = 'upload/logs/deals_update_stage_lose';
    const STATE_FILE_NAME = 'state.log';
    const LOG_FILE_NAME = 'log.log';

    public function __construct()
    {
        $this->startTime = time();

        global $USER;
        $USER->Authorize(1);

        Loader::includeModule('crm');

        $this->createLogsDir();
        $this->createLogFile(self::STATE_FILE_NAME);
        $this->createLogFile(self::LOG_FILE_NAME);

        $path = __DIR__.'/'.self::CSV_FILE_NAME;
        if (!IO\File::isFileExists($path)) {
            throw new Exception('не найден csv файл: '.$path);
        }

        $this->reasonFailEnumId = $this->getEnumIdByXml(self::FIELD_REASON_FAIL_VALUE);
        if (empty($this->reasonFailEnumId)) {
            $message = 'у поля '.self::FIELD_REASON_FAIL.' остутствует значение "180+ дней" с кодом '.self::FIELD_REASON_FAIL_VALUE;
            throw new Exception($message);
        }
    }


    private function createLogsDir()
    {
        $dir = $_SERVER['DOCUMENT_ROOT'].'/'.self::DIR_LOGS;

        if (IO\Directory::isDirectoryExists($dir)) {
            return;
        }
        IO\Directory::createDirectory($dir);

        if (!IO\Directory::isDirectoryExists($dir)) {
            throw new Exception('cant create logs directory: '.$dir);
        }
    }

    private function createLogFile($fileName)
    {
        $path = $_SERVER['DOCUMENT_ROOT'].'/'.self::DIR_LOGS.'/'.$fileName;

        if (IO\File::isFileExists($path)) {
            return;
        }
        IO\File::putFileContents($path, '', IO\File::APPEND);

        if (!IO\File::isFileExists($path)) {
            throw new Exception('cant create log file: '.$path);
        }
    }

    private function saveState($clear = false)
    {
        $path = $_SERVER['DOCUMENT_ROOT'].'/'.self::DIR_LOGS.'/'.self::STATE_FILE_NAME;

        if (!IO\File::isFileExists($path)) {
            throw new Exception('error save state file: '.$path);
        }
        if ($clear) {
            $data = '';
        } else {
            $data = serialize($this);
        }

        IO\File::putFileContents($path, $data, IO\File::REWRITE);
    }

    public function getState()
    {
        $path = $_SERVER['DOCUMENT_ROOT'].'/'.self::DIR_LOGS.'/'.self::STATE_FILE_NAME;

        if (!IO\File::isFileExists($path)) {
            throw new Exception('error get state file: '.$path);
        }

        $data = IO\File::getFileContents($path);

        return unserialize($data);
    }

    private function log($message, $fileName)
    {
        $path = $_SERVER['DOCUMENT_ROOT'].'/'.self::DIR_LOGS.'/'.$fileName;

        if (!IO\File::isFileExists($path)) {
            throw new Exception('error get log file: '.$path);
        }

        IO\File::putFileContents($path, $message.PHP_EOL, IO\File::APPEND);
    }


    private function getEnumIdByXml($xmlId)
    {
        $obEnum = new \CUserFieldEnum;
        return intval($obEnum->GetList([], ['XML_ID' => $xmlId])->fetch()['ID']);
    }

    private function getArDeal($dealId)
    {
        $filter = [
            'ID' => $dealId,
        ];
        $select = [
            'ID',
            'DATE_CREATE',
            'TITLE',
            'CATEGORY_ID',
            'STAGE_ID',
            'ASSIGNED_BY_ID',
            self::FIELD_REASON_FAIL,
        ];

        return DealTable::GetList([
            'select' => $select,
            'filter' => $filter,
        ])->fetch();
    }

    private function updateDeal($id)
    {
        $fields = [
            'STAGE_ID' => self::DEAL_STAGE_UPDATE,
            self::FIELD_REASON_FAIL => $this->reasonFailEnumId,
        ];

        return DealTable::Update($id, $fields);
    }

    private function getCategoryName($categoryId)
    {
        return DealCategory::get($categoryId)['NAME'];
    }

    private function getUserLogin($userId)
    {
        return \CUser::GetByID($userId)->Fetch()['LOGIN'];
    }

    private function getStageName($stageId)
    {
        $entityId = 'DEAL_STAGE_'.self::DEAL_CATEGORY_ID;
        if (empty(self::DEAL_CATEGORY_ID)) {
            $entityId = 'DEAL_STAGE';
        }

        $filter = [
            'ENTITY_ID' => $entityId,
            'STATUS_ID' => $stageId,
        ];
        return \CCrmStatus::GetList(['SORT' => 'ASC'], $filter)->fetch()['NAME'];
    }


    public function execute()
    {
        $this->spitOut('Начало обновления сделок');

        if ($state = $this->getState()) {
            $this->lastRowNumber = intval($state->lastRowNumber);
        }

        $file = fopen(__DIR__.'/'.self::CSV_FILE_NAME, 'r');

        $arDealId = [];
        $i = 0;
        while (($fields = fgetcsv($file, null, ',')) !== false)
        {
            $i++;
            if ($i === 1 || $i <= $this->lastRowNumber) {
                continue;
            }
            $dealId = intval($fields[0]);
            if (empty($dealId)) {
                continue;
            }

            $arDealId[$i] = $dealId;
        }
        fclose($file);
        $count = count($arDealId);

        if ($count > 0) {
            $this->showStatus(0, $count);

            $countUpdated = 0;
            $i = 0;
            foreach ($arDealId as $rowNumber => $dealId)
            {
                $i++;

                if ($this->checkDeal($dealId, $rowNumber)) {

                    $result = $this->updateDeal($dealId);
                    if (!$result->isSuccess()) {

                        $this->spitOut('');
                        $message = implode('; ', $result->getErrorMessages()).'; строка - '.$rowNumber;
                        throw new Exception($message);
                    }
                    $countUpdated++;
                }

                $this->lastRowNumber = $rowNumber;
                $this->saveState();

                $this->showStatus($i, $count);
            }

            $this->spitOut('Обновлено сделок '.$countUpdated.' из '.$count);
        }

        $this->lastRowNumber = 0;
        $this->saveState(true);

        $this->spitOut('Обновление сделок завершено');
    }

    private function checkDeal($dealId, $rowNumber)
    {
        $arDeal = self::getArDeal($dealId);

        $errorMessage = '';
        $checkResult = true;

        if (empty($arDeal)) {
            $this->spitOut('');
            $errorMessage = 'некорректные данные в файле - строка '.$rowNumber;
            //throw new Exception($errorMessage);
            $checkResult = false;

            $elapsed = time() - $this->startTime;
            $arMessage = [
                'ID - '.$dealId,
                'общее время выполнения - '.$elapsed,
                $errorMessage,
            ];
            $this->log(implode('; ', $arMessage), self::LOG_FILE_NAME);
            return $checkResult;
        }

        $dealDateCreate = $arDeal['DATE_CREATE'];
        $dealTitle = $arDeal['TITLE'];
        $dealCategory = intval($arDeal['CATEGORY_ID']);
        $dealStageId = $arDeal['STAGE_ID'];
        $dealResponsibleId = intval($arDeal['ASSIGNED_BY_ID']);
        $dealFailReason = $arDeal[self::FIELD_REASON_FAIL];

        if ($checkResult && $dealCategory !== self::DEAL_CATEGORY_ID) {
            if (empty($this->arCategories[$dealCategory])) {
                $this->arCategories[$dealCategory] = self::getCategoryName($dealCategory);
            }
            $categoryName = $this->arCategories[$dealCategory];

            $errorMessage = 'Сделка "'.$dealId.'" находится в другой воронке "'.$categoryName.'", , что не соответствует указанным параметрам запроса';
            $checkResult = false;
        }

        $checkResponsible = true;
        if ($checkResult && !in_array($dealResponsibleId, self::AR_RESPONSIBLE)) {
            if (empty($this->arUsers[$dealResponsibleId])) {
                $this->arUsers[$dealResponsibleId] = self::getUserLogin($dealResponsibleId);
            }
            $userLogin = $this->arUsers[$dealResponsibleId];

            $errorMessage = 'Ответственный в сделке "'.$dealId.'" - "'.$userLogin.'", что не соответствует указанным параметрам запроса';
            $checkResult = $checkResponsible = false;
        }

        if ($checkResult && !in_array($dealStageId, self::AR_STAGES)) {
            if (empty($this->arStages[$dealStageId])) {
                $this->arStages[$dealStageId] = self::getStageName($dealStageId);
            }
            $stageName = $this->arStages[$dealStageId];

            $errorMessage = 'Сделка "'.$dealId.'" находится в стадии "'.$dealStageId.' '.$stageName.'", что не соответствует указанным параметрам запроса';
            $checkResult = false;
        }

        if ($checkResult) {
            if ($dealDateCreate instanceof DateTime || $dealDateCreate instanceof Date) {
                $dealDateCreate = $dealDateCreate->format('d.m.Y');
            }

            if (strtotime($dealDateCreate) > strtotime(self::DEAL_DATE)) {
                $errorMessage = 'Сделка "'.$dealId.'" создана "'.$dealDateCreate.'", это позднее '.self::DEAL_DATE.', что не соответствует указанным параметрам запроса';
                $checkResult = false;
            }
        }

        $elapsed = time() - $this->startTime;
        $arMessage = [
            'ID - '.$dealId,
            'TITLE - '.$dealTitle,
            'STAGE_ID - '.$dealStageId,
            'ASSIGNED_BY_ID - '.$dealResponsibleId,
            self::FIELD_REASON_FAIL.' - '.$dealFailReason,
            'общее время выполнения - '.$elapsed,
        ];
        if (!empty($errorMessage)) {
            $arMessage[] = $errorMessage;
        }
        $this->log(implode('; ', $arMessage), self::LOG_FILE_NAME);

        /*if (!$checkResponsible) {
            $this->spitOut('');
            throw new Exception($errorMessage);
        }*/
        return $checkResult;
    }


    private function showStatus(int $done, int $total, int $size = 50)
    {
        if ($total === 0) {
            return;
        }
        static $startTime;

        while (ob_get_level())
        {
            ob_end_flush();
        }

        if ($done > $total) {
            return;
        }

        if (empty($startTime)) {
            $startTime = time();
        }
        $now = time();

        $perc = (double)($done / $total);
        $bar = floor($perc * $size);

        $statusBar = "\r[";
        $statusBar .= str_repeat('=', $bar);
        if ($bar < $size) {
            $statusBar .= '>';
            $statusBar .= str_repeat(' ', $size - $bar);
        } else {
            $statusBar .= '=';
        }

        $disp = number_format($perc * 100, 0);

        $statusBar .= "] $disp%  $done/$total";

        if ($done > 0) {
            $rate = ($now - $startTime) / $done;
            $left = $total - $done;
            $eta = round($rate * $left, 2);

            $elapsed = $now - $startTime;

            $statusBar .= ' remaining: ' . number_format($eta) . ' sec.  elapsed: ' . number_format($elapsed) . ' sec.';
        }

        echo "$statusBar  ";

        flush();

        if ($done === $total) {
            echo "\n";
        }
    }

    private function spitOut(string $message)
    {
        while (ob_get_level()) {
            ob_end_flush();
        }
        echo $message . "\n";
    }
}

try {
    (new DealsUpdateStageLose())->execute();
} catch (Throwable $exception) {
    echo $exception->getMessage().PHP_EOL;
    Debug::writeToFile(
        [
            $exception->getMessage(),
            $exception->getLine(),
            $exception->getTraceAsString(),
        ],
        date('d.m.Y H:i:s'),
        '/upload/logs/deals_update_stage_lose/error.log'
    );
}
