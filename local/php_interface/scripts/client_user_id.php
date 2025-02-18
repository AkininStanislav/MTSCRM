<?php

if (php_sapi_name() !== 'cli') {
    die;
}

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 3);

define('NOT_CHECK_PERMISSIONS', true);

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Crm\Service\Container;
use Bitrix\Crm\Service\Factory;
use Bitrix\Highloadblock\DataManager;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\ORM\Query\Result as QueryResult;
use Stream\Main\Crm\Generation\Mapping;
use Bitrix\Main\Diag;

$modules = [
    'crm',
    'tasks',
    'bizproc',
    'highloadblock'
];

foreach ($modules as $module) {
    if (!\CModule::IncludeModule($module)) {
        die('Модуль не установлен ' . $module);
    }
}

global $USER;
$USER->Authorize(1);

/**
 * Класс проверяет все письма по заданным почтам на наличие client_id
 * и задаёт сделкам и контактам client_id
 */
class ClientUserId1745
{
    const EMAILS = [
        'mailer@it-grad.ru' => '',
        'admin@cloud.mts.ru' => '',
        'site@cloud.mts.ru' => '',
        'admin@mws.ru' => '',
        'site@mws.ru' => ''
    ];

    const KEY = 'UF_CRM_CLIENT_ID';

    private string|DataManager|null $hl = null;
    private ?Factory $factoryDeal = null;
    private ?Factory $factoryContact = null;
    private ?Mapping $mapping = null;

    private string $dirLogs = 'upload/logs/client_user_id';
    private string $logFileName = 'log1.log';

    private ?Diag\FileLogger $logger = null;

    private function __construct()
    {
        $this->createDirIfNotExists();
    }

    private function createDirIfNotExists(): void
    {
        $dir = $_SERVER['DOCUMENT_ROOT'] . '/' . $this->dirLogs;

        if (!\Bitrix\Main\IO\Directory::isDirectoryExists($dir)) {
            \Bitrix\Main\IO\Directory::createDirectory($dir);
        }
    }

    private function getLogger(): Diag\FileLogger
    {
        if ($this->logger !== null) {
            return $this->logger;
        }

        $file = $_SERVER['DOCUMENT_ROOT'] . '/' . $this->dirLogs . '/' . $this->logFileName;

        $logger = new Diag\FileLogger($file, 0);

        $this->logger = $logger;

        return $logger;
    }

    private function log(string $message): void
    {
        $this->getLogger()->log('debug', $message . PHP_EOL);
    }

    private function getHlClass(): string|DataManager
    {
        if ($this->hl !== null) {
            return $this->hl;
        }

        $data = HighloadBlockTable::getList([
            'select' => ['ID'],
            'filter' => ['NAME' => 'CrmMailJsonData']
        ])->fetch();

        if ($data === false) {
            throw new \Exception('Highload block not found.');
        }

        $entity = HighloadBlockTable::compileEntity($data['ID']);

        $class = $entity->getDataClass();

        $this->hl = $class;

        return $class;
    }

    /**
     * @return void
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     * Старт логики
     */
    private function init()
    {
        foreach (self::EMAILS as $email => $key) {
            $result = $this->getHlClass()::getList([
                'filter' => ['UF_MAIL_FROM' => $email],
                'order' => ['ID' => 'DESC'],
            ]);

            $this->processResult($result, $key);
        }
    }

    private function processResult(QueryResult $result, string $key)
    {
        $factoryDeal = $this->getFactoryDeal();
        $mapping = $this->getMapping();
        $contact = new \CCrmContact(false);

        while($data = $result->fetch()) {
            $body = $data['UF_MAIL_BODY'] ?? '';

            $body = json_decode($body, true);

            if (empty($body)) {
                continue;
            }

            if (!is_array($body)) {
                continue;
            }

            $dealId = $data['UF_DEAL_ID'] ?? null;

            if ($dealId === null) {
                continue;
            }

            $deal = $factoryDeal->getItem($dealId);

            if ($deal === null) {
                continue;
            }

            $contactId = $deal->getContactId();

            $contactApply = false;

            if (!empty($contactId)) {
                $dataContact = \CCrmContact::GetList(arFilter: ['ID' => $contactId], arSelect: ['ID'])->Fetch();

                if ($dataContact !== false) {
                    $contactApply = true;
                }
            }

            if (!empty($key) && !empty($body[$key])) {
                $body = $body[$key];
            }

            $dataDeal = $mapping->mapFields('DEAL', $body);

            $clientId = $dataDeal[self::KEY] ?? [];

            if (empty($clientId)) {
                continue;
            }

            $deal->set('UF_CRM_CLIENT_ID', $clientId)->save();

            if ($contactApply) {
                $var = [
                    'UF_CRM_CLIENT_ID' => [$clientId]
                ];

                $contact->Update($contactId, $var);

                $this->log('Сделка с ID= ' . $dealId);
                $this->log('Контакт с ID= ' . $contactId);
            } else {
                $this->log('Сделка с ID= ' . $dealId);
            }
        }
    }

    private function getFactoryDeal(): Factory
    {
        if ($this->factoryDeal !== null) {
            return $this->factoryDeal;
        }

        $factory = Container::getInstance()->getFactory(\CCrmOwnerType::Deal);

        $this->factoryDeal = $factory;
        return $factory;
    }

    private function getMapping(): Mapping
    {
        if ($this->mapping !== null) {
            return $this->mapping;
        }

        $mapping = ServiceLocator::getInstance()->get('stream:crm.generation.mapping');

        $this->mapping = $mapping;

        return $mapping;
    }

    private function getFactoryContact(): Factory
    {
        if ($this->factoryContact !== null) {
            return $this->factoryContact;
        }

        $factory = Container::getInstance()->getFactory(\CCrmOwnerType::Deal);

        $this->factoryContact = $factory;
        return $factory;
    }

    public static function start(): void
    {
        (new self())->init();
    }
}

try {
    ClientUserId1745::start();
} catch (\Throwable $exception) {
    print_r($exception);
} finally {
    $USER->Logout();
}