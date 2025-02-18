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
use Mts\Main\Tools\Timeline;

if (php_sapi_name() !== 'cli') {
    exit();
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

class DealsTpoDoublesRestore extends AbstractScript
{
    const CSV_FILE_NAME = 'deals_tpo.csv';
    const LOG_NAME = 'deals_tpo_doubles_unite';
    const UPDATED_DEALS_FILE_NAME = 'updated_deals.csv';
    const DELETED_DEALS_FILE_NAME = 'deleted_deals.csv';

    protected $dealFactory;

    public function __construct()
    {
        parent::__construct();

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

    public function processScriptActions(): void
    {
        $entityTypeId = \CCrmOwnerType::Deal;
        $this->dealFactory = Container::getInstance()->getFactory($entityTypeId);

        $this->logger->info('Начало восстановления удаленных сделок');
        $this->restoreDeleted();
        $this->logger->info('Восстановления удаленных сделок завершено');

        $this->logger->info('Начало восстановления обновленных сделок');
        $this->restoreUpdated();
        $this->logger->info('Восстановления обновленных сделок завершено');
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

    private function csvGetReport($fileHandle, $delimeter=';')
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
                if (!empty($header[0])) {
                    $length = substr($header[0], 0, 3) === chr(0xEF) . chr(0xBB) . chr(0xBF) ? 3 : 0;
                    if (!empty($length)) {
                        $header[0] = substr($header[0], $length);
                    }
                }
            } else {
                $fields = array_combine($header, $data);
                $dealId = intval($fields['ID']);
                if (empty($dealId)) {
                    continue;
                }
                $arFields = $fields['FIELDS'];
                if (empty($arFields) || !is_string($arFields)) {
                    continue;
                }
                $arFields = unserialize($arFields);
                if (!is_array($arFields)) {
                    continue;
                }
                yield [$row, $dealId, $arFields];
            }

            $row++;
        }
        return false;
    }

    private function restoreDeleted(): void
    {
        $path = $_SERVER['DOCUMENT_ROOT'].'/'.static::ROOT_FILE_LOG_PATH.'/'.self::LOG_NAME.'/'.self::DELETED_DEALS_FILE_NAME;
        if (!IO\File::isFileExists($path)) {
            $this->logger->info('не найден csv файл: '.$path);
            return;
        }
        $totalLength = $this->getTotalFileLength($path);

        $file = fopen($path, 'r');

        $arProcessedDeals = [];
        $countRestored = 0;
        try {
            foreach ($this->csvGetReport($file) as $data)
            {
                $rowNumber = intval($data[0]);
                $dealId = intval($data[1]);
                if (empty($dealId) || in_array($dealId, $arProcessedDeals)) {
                    continue;
                }

                $disp = '';
                if ($totalLength > 0 && $rowNumber > 0) {
                    $perc = (double)($rowNumber / $totalLength);
                    $perc = number_format($perc * 100, 0);
                    $disp = '; '.$perc.'%';
                }

                $recycleBinId = intval(RecyclebinTable::getList([
                    'select' => [
                        'ID',
                        'NAME',
                        'ENTITY_ID',
                        'ENTITY_TYPE',
                    ],
                    'filter' => [
                        'ENTITY_ID' => $dealId,
                        'ENTITY_TYPE' => 'crm_deal',

                    ],
                ])->fetch()['ID']);
                if (empty($recycleBinId)) {
                    $this->logger->info('Сделка '.$dealId.' не найдена в корзине '.$recycleBinId.$disp);
                    continue;
                }

                $result = Recyclebin::restore($recycleBinId);

                if ($result instanceof Result && !$result->isSuccess()) {
                    $message = is_array($result->getErrorMessages()) ?
                        implode('; ', $result->getErrorMessages()) :
                        $result->getErrorMessages();
                    $this->logger->error('Ошибка восстановления сделки: '.$dealId.'; корзина '.$recycleBinId.'; '.$message.$disp);
                } else {
                    $countRestored++;
                    $this->logger->info('Сделка '.$dealId.' восстановлена; новый id '.intval($result).$disp);
                    $this->comment('Сделка восстановлена в задаче 2033', intval($result));
                }
                $arProcessedDeals[] = $dealId;
            }
        } catch (\Throwable $throwable) {
            dump($throwable);
            $this->logger->error('Ошибка обработки файла: '.$path.'; '.$throwable->getMessage());
        } finally {
            fclose($file);
        }

        $this->logger->info('Восстановлено сделок: '.$countRestored);
    }

    private function restoreUpdated(): void
    {
        $path = $_SERVER['DOCUMENT_ROOT'].'/'.static::ROOT_FILE_LOG_PATH.'/'.self::LOG_NAME.'/'.self::UPDATED_DEALS_FILE_NAME;
        if (!IO\File::isFileExists($path)) {
            $this->logger->info('не найден csv файл: '.$path);
            return;
        }
        $totalLength = $this->getTotalFileLength($path);

        $file = fopen($path, 'r');

        $arProcessedDeals = [];
        $countUpdated = 0;
        $rowNumber = 0;
        try {
            foreach ($this->csvGetReport($file) as $data)
            {
                $rowNumber++;
                $dealId = intval($data[1]);
                $fields = $data[2];
                if (empty($dealId) || in_array($dealId, $arProcessedDeals) || !is_array($fields) || empty($fields)) {
                    continue;
                }

                $disp = '';
                if ($totalLength > 0 && $rowNumber > 0) {
                    $perc = (double)($rowNumber / $totalLength);
                    $perc = number_format($perc * 100, 0);
                    $disp = '; '.$perc.'%';
                }

                $dealEntity = $this->dealFactory->getItem($dealId);
                if (!$dealEntity) {
                    $this->logger->info('Сделка '.$dealId.' не найдена'.$disp);
                    continue;
                }

                $updateFields = [];
                foreach ($fields as $fieldName => $arValue)
                {
                    if (str_starts_with($fieldName, '~',)) {
                        continue;
                    }
                    if (!array_key_exists('value', $arValue) || !array_key_exists('oldValue', $arValue)) {
                        continue;
                    }
                    $value = $arValue['value'];
                    $oldValue = $arValue['oldValue'];
                    if ($oldValue == $value) {
                        continue;
                    }
                    $actualValue = $dealEntity->get($fieldName);
                    if ($oldValue == $actualValue) {
                        continue;
                    }
                    $updateFields[$fieldName] = $oldValue;
                }

                if (!empty($updateFields)) {
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
                        $this->logger->error('Ошибка обновления сделки: '.$dealId.'; '.$message.$disp);
                        continue;
                    } else {
                        $countUpdated++;
                        $this->logger->info('Сделка '.$dealId.' обновлена'.$disp);
                        $this->comment('Сделка обновлена при восстановлении в задаче 2033', $dealId);
                    }
                }

                $arProcessedDeals[] = $dealId;
            }
        } catch (\Throwable $throwable) {
            dump($throwable);
            $this->logger->error('Ошибка обработки файла: '.$path.'; '.$throwable->getMessage());
        } finally {
            fclose($file);
        }

        $this->logger->info('Обновлено сделок: '.$countUpdated);
    }

    private function comment(string $text, int $dealId): int
    {
        return Timeline::create($text, new ItemIdentifier(\CCrmOwnerType::Deal, $dealId));
    }
}
try {
    (new DealsTpoDoublesRestore())->run();
}
catch (\Throwable $throwable) {
    dump($throwable);
}
finally {
    $USER->Logout();
}

