<?php
use Bitrix\Main\Loader;
use Bitrix\Main\IO;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Type\Date;
use Bitrix\Crm\Service\Container;
use Bitrix\Crm\ItemIdentifier;
use Bitrix\Crm\Category\DealCategory;
use Psr\Log\LoggerInterface;
use Mts\Main\Scripts\AbstractScript;
use Mts\Main\Tools\Timeline;
ini_set('memory_limit', '1024M');
set_time_limit(0);
if (php_sapi_name() !== 'cli') die();

define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 4);

require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/local/php_interface/vendor/autoload.php');

Loader::includeModule('crm');
Loader::includeModule('mts.main');

class DealsUpdateStageLose2330 extends AbstractScript
{
    const CSV_FILE_NAME = 'Сделки для закрытия 180+.csv';

    const DEAL_CATEGORY_ID = 5;
    const AR_RESPONSIBLE = [
        5927, 5003, 5381, 5755, 5941, 5928, 5679, 5555, 5700, 5675,
    ];
    const DEAL_DATE = '31.01.2024';
    const DEAL_STAGE_NEW = 'C5:NEW';
    const DEAL_STAGE_LOSE = 'C5:LOSE';
    const DEAL_SEMANTIC_PROGRESS = 'P';
    const DEAL_SEMANTIC_LOSE = 'F';
    const FIELD_REASON_FAIL = 'UF_CRM_1646846305877';
    const FIELD_REASON_FAIL_VALUE = 'b15cbbb8818630241fcaded989b5bf51';

    const LOG_FILE_NAME = 'deals_update_stage_lose_2330';

    public int $reasonFailEnumId;
    protected $dealFactory;
    public array $arCategories = [];
    public array $arUsers = [];

    public function __construct(LoggerInterface $logger = null)
    {
        parent::__construct($logger);

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

    private function getEnumIdByXml($xmlId)
    {
        $obEnum = new \CUserFieldEnum;
        return intval($obEnum->GetList([], ['XML_ID' => $xmlId])->fetch()['ID']);
    }

    protected function getLogName(): string
    {
        return self::LOG_FILE_NAME;
    }

    public function run()
    {
        global $USER;

        try {
            $this->start();
            $USER->Authorize(1);
            $this->processScriptActions();
        } catch (\Throwable $e) {
            $this->logThrowable($e);
        } finally {
            $USER->Logout();
            $this->finish();
        }
    }


    public function processScriptActions(): void
    {
        $path = __DIR__.'/'.self::CSV_FILE_NAME;
        $totalLength = $this->getTotalFileLength($path);

        $file = fopen($path, 'r');

        $countUpdated = 0;
        try {
            $this->dealFactory = Container::getInstance()->getFactory(\CCrmOwnerType::Deal);

            foreach ($this->csvGetDeal($file) as $data)
            {
                $rowNumber = intval($data[0]);
                $id = intval($data[1]);
                if (empty($id)) {
                    continue;
                }

                $message = $this->checkDeal($id, $countUpdated);

                $disp = '';
                if ($totalLength > 0 && $rowNumber > 0) {
                    $perc = (double)($rowNumber / $totalLength);
                    $perc = number_format($perc * 100, 0);
                    $disp = '; '.$perc.'% '.$rowNumber.'/'.$totalLength;
                }

                $this->logger->info('Сделка '.$id.'; '.$message.$disp);
            }
        } catch (\Throwable $throwable) {
            $this->logger->error('Ошибка обработки файла: '.__DIR__.'/'.self::CSV_FILE_NAME.'; '.$throwable->getMessage());
        } finally {
            fclose($file);
        }

        $this->logger->info('Обновлено сделок: '.$countUpdated);
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
            if ($totalLength > 0) {
                $totalLength--;
            }
        } catch (\Throwable $throwable) {
            $this->logger->error('Ошибка обработки файла: '.$path.'; '.$throwable->getMessage());
        } finally {
            fclose($file);
        }

        return $totalLength;
    }

    private function csvGetDeal($fileHandle, $delimeter=';')
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
                if (is_string($header[0])){
                    $length = substr($header[0], 0, 3) === chr(0xEF) . chr(0xBB) . chr(0xBF) ? 3 : 0;
                    if ($length) {
                        $header[0] = substr($header[0], $length);
                    }
                }
            } else {
                $fields = array_combine($header, $data);
                $id = intval($fields['ID']);
                if (empty($id)) {
                    continue;
                }
                yield [$row, $id];
            }

            $row++;
        }
        return false;
    }

    private function checkDeal($id, &$countUpdated=0)
    {
        $dealEntity = $this->dealFactory->getItem($id);
        if (!$dealEntity) {
            return 'не найдена';
        }

        $dealCategory = intval($dealEntity->get('CATEGORY_ID'));
        if ($dealCategory !== self::DEAL_CATEGORY_ID) {
            if (empty($this->arCategories[$dealCategory])) {
                $this->arCategories[$dealCategory] = self::getCategoryName($dealCategory);
            }
            $categoryName = $this->arCategories[$dealCategory];

            return 'не обновлена; находится в другой воронке "'.$categoryName.'", , что не соответствует указанным параметрам запроса';
        }

        $dealResponsibleId = intval($dealEntity->get('ASSIGNED_BY_ID'));
        if (!in_array($dealResponsibleId, self::AR_RESPONSIBLE)) {
            if (empty($this->arUsers[$dealResponsibleId])) {
                $this->arUsers[$dealResponsibleId] = self::getUserLogin($dealResponsibleId);
            }
            $userLogin = $this->arUsers[$dealResponsibleId];

            return 'не обновлена; ответственный в сделке  - "'.$userLogin.'", что не соответствует указанным параметрам запроса';
        }

        $dealDateCreate = $dealEntity->get('DATE_CREATE');
        if ($dealDateCreate instanceof DateTime || $dealDateCreate instanceof Date) {
            $dealDateCreate = $dealDateCreate->format('d.m.Y');
        }
        if (strtotime($dealDateCreate) > strtotime(self::DEAL_DATE)) {
            return 'не обновлена; создана "'.$dealDateCreate.'", это позднее '.self::DEAL_DATE.', что не соответствует указанным параметрам запроса';
        }

        $stageId = $dealEntity->get('STAGE_ID');
        $stageSemanticId = $dealEntity->get('STAGE_SEMANTIC_ID');
        $reasonFail = intval($dealEntity->get(self::FIELD_REASON_FAIL));

        if ($stageId !== self::DEAL_STAGE_LOSE || $stageSemanticId !== self::DEAL_SEMANTIC_PROGRESS || $reasonFail !== $this->reasonFailEnumId) {
            return 'не обновлена; стадия - '.$stageId.'; группа стадий - '.$stageSemanticId.'; id причины провала - '.$reasonFail;
        }

        $saveResult = $this->saveDeal($dealEntity);
        if (!$saveResult->isSuccess()) {
            return 'не обновлена; '.implode('; ', $saveResult->getErrorMessages());
        }



        $updateResult = $this->updateDeal($dealEntity);
        if (!$updateResult->isSuccess()) {
            return 'не обновлена; '.implode('; ', $updateResult->getErrorMessages());
        }

        $stageId = $dealEntity->get('STAGE_ID');
        $stageSemanticId = $dealEntity->get('STAGE_SEMANTIC_ID');
        $reasonFail = intval($dealEntity->get(self::FIELD_REASON_FAIL));

        if ($stageId !== self::DEAL_STAGE_LOSE || $stageSemanticId !== self::DEAL_SEMANTIC_LOSE || $reasonFail !== $this->reasonFailEnumId) {
            return 'не обновлена; стадия - '.$stageId.'; группа стадий - '.$stageSemanticId.'; id причины провала - '.$reasonFail;
        }

        $countUpdated++;
        $this->comment('Стадия сделки была изменена в задаче №2330', $id);
        return 'обновлена';
    }

    private function saveDeal($dealEntity)
    {
        $dealEntity->set('STAGE_ID', self::DEAL_STAGE_NEW);
        $dealEntity->set(self::FIELD_REASON_FAIL, null);
        return $dealEntity->save();
    }

    private function updateDeal($dealEntity)
    {
        $dealEntity->set('STAGE_ID', self::DEAL_STAGE_LOSE);
        $dealEntity->set(self::FIELD_REASON_FAIL, $this->reasonFailEnumId);

        $operation = $this->dealFactory->getUpdateOperation($dealEntity);

        $operation->disableAllChecks();
        $operation->disableBeforeSaveActions();
        $operation->disableAfterSaveActions();

        return $operation->launch();
    }

    private function getCategoryName($categoryId)
    {
        return DealCategory::get($categoryId)['NAME'];
    }

    private function getUserLogin($userId)
    {
        return \CUser::GetByID($userId)->Fetch()['LOGIN'];
    }

    private function comment(string $text, int $dealId): int
    {
        return Timeline::create($text, new ItemIdentifier(\CCrmOwnerType::Deal, $dealId));
    }
}

(new DealsUpdateStageLose2330())->run();
