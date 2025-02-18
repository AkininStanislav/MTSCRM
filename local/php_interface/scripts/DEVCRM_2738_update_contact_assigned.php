<?php

use Bitrix\Crm\ContactTable;
use Mts\Main\Logger\LoggerManager;
use Mts\Main\Scripts\AbstractScript;
use Psr\Log\LoggerInterface;

if (php_sapi_name() !== 'cli') {
    die;
}

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 3);

const NOT_CHECK_PERMISSIONS = true;

require_once $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php";

global $USER;
$USER->Authorize(1);

\CModule::IncludeModule('crm');
\CModule::IncludeModule('stream.main');
\CModule::IncludeModule('mts.main');

class UpdateContactAssigned  extends AbstractScript
{
    private const FILTERED_IDS = [5179, 5660, 6859, 6382, 6418, 7414, 8303];

    private const ROBOT_USER_ID = 5959;

    protected ?LoggerInterface $innerLogger = null;

    private $isRestart = false;

    private $total = 0;

    private $limit = 500;

    private $updated = 0;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->innerLogger = $this->prepareFileLogger();

        parent::__construct($logger);
    }

    private function prepareFileLogger()
    {
        $logger = LoggerManager::getDefaultLogger($this->getLogName());

        return $logger;
    }

    protected function getLogName(): string
    {
        return 'DEVCRM_2738_contact_update_assigned_by_id';
    }

    protected function processScriptActions(): void
    {
        do {
            $this->updateContacts();
        } while ($this->isRestart);
    }

    protected function updateContacts()
    {
        $this->isRestart = false;

        $dbRes = ContactTable::getList([
            'filter' => [
                [
                    'LOGIC' => 'OR',
                    ['ASSIGNED_BY_ID' => self::FILTERED_IDS],
                    ['ASSIGNED_BY_ID' => false],
                ]
            ],
            'select' => ['ID', 'ASSIGNED_BY_ID'],
            'count_total' => true,
            'limit' => $this->limit,
        ]);

        if (empty($this->total)) {
            $total = $dbRes->getCount();

            $this->total = $total;
        }

        $items = $dbRes->fetchAll();
        $count = count($items);

        if (empty($items)) {
            return;
        }

        foreach ($items as $item) {
            $context = [];

            try {
                $res = ContactTable::update($item['ID'], ['ASSIGNED_BY_ID' => self::ROBOT_USER_ID]);
                $isSuccess = $res->isSuccess();

                $context = [
                    'contactId' => $item['ID'],
                    'status' => $isSuccess ? 'SUCCESS' : 'FAIL',
                    'beforeAssigned' => $item['ASSIGNED_BY_ID'],
                    'afterAssigned' => $isSuccess ? self::ROBOT_USER_ID : $item['ID'],
                ];

                if (!$isSuccess) {
                    $context['dbResErrors'] = $res->getErrorMessages();
                }

                $this->innerLogger->info('Контакт обработан', $context);
            } catch (\Exception $e) {
                $context['error'] = [
                    'message' => $e->getMessage(),
                    'item' => $item,
                ];

                $this->innerLogger->error('Ошибка', $context);
            }
        }

        $this->updated += $count;
        $percent = ($this->updated * 100) / $this->total;
        $percent = round($percent, 2);

        $this->outProgress("progress: {$percent}%, {$this->updated}/{$this->total}");

        if ($this->updated <= $this->total ) {
            $this->restart();
        }
    }

    protected function outProgress($msg = '')
    {
        fwrite(STDOUT, "\r$msg");
    }

    protected function restart()
    {
        $this->isRestart = true;
    }
}

$service = new UpdateContactAssigned();

try {
    $service->run();
} catch (Throwable $exception) {
    echo $exception->getMessage().PHP_EOL;
}