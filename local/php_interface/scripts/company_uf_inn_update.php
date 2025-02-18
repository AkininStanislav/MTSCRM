<?php
use Bitrix\Main\Loader;
use Bitrix\Main\IO;
use Bitrix\Main\Diag\Debug;
use Bitrix\Crm\CompanyTable;

define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 3);

require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

class CompanyUfInnUpdate
{
    public int $lastItemId = 0;

    const FIELD_NAME = 'UF_CRM_1571127464715';
    const FIELD_NAME_TMP = 'UF_CRM_1571127464715_STR';

    const DIR_LOGS = 'upload/logs/company_uf_inn_update';
    const STATE_FILE_NAME = 'state.log';
    const LOG_FILE_NAME = 'log.log';
    const LOG_NEW_FILE_NAME = 'log_new.log';

    public function __construct()
    {
        global $USER;
        $USER->Authorize(1);

        Loader::includeModule('crm');

        $this->createLogsDir();
        $this->createLogFile(self::STATE_FILE_NAME);
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


    public function getArUserField($fieldName, $lang = null)
    {
        $filter = [
            'ENTITY_ID' => 'CRM_COMPANY',
            'FIELD_NAME' => $fieldName,
        ];
        if (!empty($lang)) {
            $filter['LANG'] = $lang;
        }
        return \CUserTypeEntity::GetList([], $filter)->fetch();
    }

    private function deleteUserField(int $fieldId)
    {
        $obEntity = new \CUserTypeEntity;
        return $obEntity->delete($fieldId);
    }

    private function addTmpUserField()
    {
        $arUserField = [
            'ENTITY_ID' => 'CRM_COMPANY',
            'FIELD_NAME' => self::FIELD_NAME_TMP,
            'USER_TYPE_ID' => 'string',
            'XML_ID' => '',
            'SORT' => 100,
            'MULTIPLE' => 'N',
            'MANDATORY' => 'N',
            'SHOW_FILTER' => 'E',
            'SHOW_IN_LIST' => 'N',
            'EDIT_IN_LIST' => 'N',
            'IS_SEARCHABLE' => 'N',
            'SETTINGS'  => [
                'DEFAULT_VALUE' => '',
                'SIZE'  => '20',
                'ROWS'  => '1',
                'MIN_LENGTH'  => '0',
                'MAX_LENGTH'  => '0',
                'REGEXP' => '',
            ],
            'EDIT_FORM_LABEL' => [
                'ru' => '',
                'en' => '',
            ],
            'LIST_COLUMN_LABEL' => [
                'ru' => '',
                'en' => '',
            ],
            'LIST_FILTER_LABEL' => [
                'ru' => '',
                'en' => '',
            ],
            'ERROR_MESSAGE' => [
                'ru' => '',
                'en' => '',
            ],
            'HELP_MESSAGE' => [
                'ru' => '',
                'en' => '',
            ],
        ];

        $fieldId = $this->addUserField($arUserField);

        if (empty($fieldId)) {
            throw new Exception('cant create tmp field: '.self::FIELD_NAME_TMP);
        }
        return $fieldId;
    }

    private function addNewStringUserField()
    {
        $arUserField = [
            'ENTITY_ID' => 'CRM_COMPANY',
            'FIELD_NAME' => self::FIELD_NAME,
            'USER_TYPE_ID' => 'string',
            'XML_ID' => '',
            'SORT' => 100,
            'MULTIPLE' => 'N',
            'MANDATORY' => 'Y',
            'SHOW_FILTER' => 'E',
            'SHOW_IN_LIST' => 'Y',
            'EDIT_IN_LIST' => 'Y',
            'IS_SEARCHABLE' => 'N',
            'SETTINGS'  => [
                'DEFAULT_VALUE' => '',
                'SIZE'  => '20',
                'ROWS'  => '1',
                'MIN_LENGTH'  => '0',
                'MAX_LENGTH'  => '0',
                'REGEXP' => '',
            ],
            'EDIT_FORM_LABEL' => [
                'ru' => 'ИНН*',
                'en' => 'ИНН*',
            ],
            'LIST_COLUMN_LABEL' => [
                'ru' => 'ИНН*',
                'en' => 'ИНН*',
            ],
            'LIST_FILTER_LABEL' => [
                'ru' => 'ИНН*',
                'en' => 'ИНН*',
            ],
            'ERROR_MESSAGE' => [
                'ru' => '',
                'en' => '',
            ],
            'HELP_MESSAGE' => [
                'ru' => '',
                'en' => '',
            ],
        ];

        $fieldId = $this->addUserField($arUserField);

        if (empty($fieldId)) {
            throw new Exception('cant create string field: '.self::FIELD_NAME);
        }
        return $fieldId;
    }

    private function updateUserField(int $fieldId, array $arField)
    {
        $obEntity = new \CUserTypeEntity;
        return $obEntity->Update($fieldId, $arField);
    }

    private function addUserField($arField)
    {
        $obEntity = new \CUserTypeEntity;
        return $obEntity->Add($arField);
    }


    private function getCompanyQuery($filter, $select, $order = ['ID' => 'ASC'])
    {
        return CompanyTable::GetList([
            'order' => $order,
            'select' => $select,
            'filter' => $filter,
            'count_total' => true,
        ]);
    }

    private function updateCompany($id, $fields)
    {
        $result = CompanyTable::Update($id, $fields);

        if (!$result->isSuccess()) {
            throw new Exception(implode('; ', $result->getErrorMessages()));
        }
        return true;
    }


    /**
     * Выполнение разделено на 2 отдельных запуска скрипта
     * Первая часть завершается удалением старого поля
     * Вторая часть начинается с создания обновленного поля
     * Это нужно чтобы в таблицах crm обновились связи
     * Чтобы избежать ошибок Query error, Duplicate primary при открытии карточки Компании
     *
     * @throws Exception
     */
    public function execute()
    {
        $arField = $this->getArUserField(self::FIELD_NAME);
        $fieldType = $arField['USER_TYPE_ID'];

        if ($state = $this->getState()) {
            $this->lastItemId = intval($state->lastItemId);
        }

        if ($fieldType === 'integer' || $fieldType === 'double') {
            $this->step1($arField);

        } else if ($fieldType === 'string' || empty($arField)) {
            $this->step2($arField);

        } else {
            $this->lastItemId = 0;
            $this->saveState(true);
            throw new Exception('error unsupported field type: '.self::FIELD_NAME);
        }
    }

    private function step1($arField)
    {
        $this->spitOut('Начало переноса во временное поле');

        $this->createLogFile(self::LOG_FILE_NAME);

        $fieldId = intval($arField['ID']);

        $arFieldTmp = $this->getArUserField(self::FIELD_NAME_TMP);
        if (!empty($arFieldTmp)) {
            $tmpFieldId = intval($arFieldTmp['ID']);

        } else {
            $this->spitOut('Создание временного поля');
            $tmpFieldId = $this->addTmpUserField();
            $this->lastItemId = 0;
            $this->saveState();
        }

        $filter = [
            '!'.self::FIELD_NAME => false,
            '>ID' => $this->lastItemId,
        ];
        $select = [
            'ID',
            self::FIELD_NAME,
        ];
        $companyQuery = $this->getCompanyQuery($filter, $select);
        $count = $companyQuery->getCount();
        $this->showStatus(0, $count);

        $i = 0;
        while ($company = $companyQuery->fetch())
        {
            $i++;
            $id = intval($company['ID']);
            $value = strval($company[self::FIELD_NAME]);

            if (strlen($value) === 11) {
                $value = '0'.$value;
            }

            $arFields = [
                self::FIELD_NAME_TMP => $value,
            ];

            $message = 'companyId: '.$id.'; oldValue: '.$value.'; '.date('d.m.Y H:i:s');
            $this->log($message, self::LOG_FILE_NAME);

            $this->updateCompany($id, $arFields);
            $this->lastItemId = $id;
            $this->saveState();

            $this->showStatus($i, $count);
        }

        $this->spitOut('Удаление старого поля');

        @set_time_limit(0);
        global $DB;
        $DB->StartTransaction();
        $deleteResult = $this->deleteUserField($fieldId);

        if (!$deleteResult) {
            $DB->Rollback();
            throw new Exception('error delete field: '.$fieldId);

        } else {
            $DB->Commit();
            $this->lastItemId = 0;
            $this->saveState();
        }

        $rsEvents = GetModuleEvents('main', 'OnAfterUserTypeDelete');
        while ($arEvent = $rsEvents->Fetch())
        {
            ExecuteModuleEvent($arEvent, $arField, $fieldId);
        }
        if (\CUserTypeEntity::GetByID($fieldId) !== false) {
            throw new Exception('error delete field: '.$fieldId);
        }

        $this->spitOut('Конец переноса во временное поле');
    }

    private function step2($arField)
    {
        $this->spitOut('Начало переноса из временного поля');

        $this->createLogFile(self::LOG_NEW_FILE_NAME);

        $fieldId = intval($arField['ID']);

        if (empty($arField)) {
            $this->spitOut('Создание обновленного поля');

            @set_time_limit(0);
            global $DB;
            $DB->StartTransaction();
            $fieldId = $this->addNewStringUserField();

            if (!$fieldId) {
                $DB->Rollback();
                throw new Exception('error add string field: '.self::FIELD_NAME);

            } else {
                $DB->Commit();
                $this->lastItemId = 0;
                $this->saveState();
            }
        }

        $filter = [
            self::FIELD_NAME => false,
            '!'.self::FIELD_NAME_TMP => false,
            '>ID' => $this->lastItemId,
        ];
        $select = [
            'ID',
            self::FIELD_NAME,
            self::FIELD_NAME_TMP,
        ];
        $companyQuery = $this->getCompanyQuery($filter, $select);
        $count = $companyQuery->getCount();
        $this->showStatus(0, $count);

        $i = 0;
        while ($company = $companyQuery->fetch())
        {
            $i++;
            $id = intval($company['ID']);
            $value = strval($company[self::FIELD_NAME_TMP]);

            if (!is_numeric($value)) {
                throw new Exception('wrong inn number: '.$value.'; company id: '.$id);
            }

            if (strlen($value) === 11) {
                $value = '0'.$value;
            }

            $arFields = [
                self::FIELD_NAME => $value,
            ];

            $message = 'companyId: '.$id.'; newValue: '.$value.'; '.date('d.m.Y H:i:s');
            $this->log($message, self::LOG_NEW_FILE_NAME);

            $this->updateCompany($id, $arFields);
            $this->lastItemId = $id;
            $this->saveState();

            $this->showStatus($i, $count);
        }

        $this->lastItemId = 0;
        $this->saveState(true);

        $arFieldsUpdate = [
            'SETTINGS'  => [
                'DEFAULT_VALUE' => '',
                'SIZE'  => '20',
                'ROWS'  => '1',
                'MIN_LENGTH'  => '10',
                'MAX_LENGTH'  => '12',
                'REGEXP' => '',
            ],
        ];
        $updateResult = $this->updateUserField($fieldId, $arFieldsUpdate);
        if (!$updateResult) {
            throw new Exception('error update field settings: '.$fieldId);
        }

        $this->spitOut('Удаление временного поля');

        $tmpFieldId = intval($this->getArUserField(self::FIELD_NAME_TMP)['ID']);

        if (!empty($tmpFieldId)) {
            $deleteResult = $this->deleteUserField($tmpFieldId);

            if (!$deleteResult) {
                throw new Exception('error delete field: '.$tmpFieldId);
            }
        }
        
        $this->spitOut('Конец переноса из временного поля');
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
    (new CompanyUfInnUpdate())->execute();
} catch (Throwable $exception) {
    echo $exception->getMessage().PHP_EOL;
    Debug::writeToFile(
        [
            $exception->getMessage(),
            $exception->getLine(),
            $exception->getTraceAsString(),
        ],
        date('d.m.Y H:i:s'),
        '/upload/logs/company_uf_inn_update/error.log'
    );
}
