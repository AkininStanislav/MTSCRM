<?php

use Bitrix\Crm\Service\Container;
use Bitrix\Crm\Service\Factory;
use Bitrix\Highloadblock\DataManager;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\DI\ServiceLocator;
use Mts\Main\Scripts\AbstractScript;
use Psr\Log\LoggerInterface;
use Stream\Main\Crm\Generation\Mapping;

const NOT_CHECK_PERMISSIONS = true;
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 3);

require_once $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php";

\CModule::IncludeModule('crm');
\CModule::IncludeModule('highloadblock');

global $USER;
$USER->Authorize(1);

class ContactAddPhoneEmail1836 extends AbstractScript
{
    private string|DataManager|null $hlClass = null;

    private ?Mapping $mapping = null;

    private Factory $factoryDeal;

    public readonly \Bitrix\Main\Type\DateTime $dateTimeCreated;

    public function __construct(?LoggerInterface $logger = null)
    {
        parent::__construct($logger);

        $this->mapping = $this->getMapping();

        $this->factoryDeal = Container::getInstance()->getFactory(\CCrmOwnerType::Deal);
    }

    public function setDateTime(\DateTime $dateTime): self
    {
        $dateTime = $dateTime->format('Y-m-d');

        $this->dateTimeCreated = \Bitrix\Main\Type\DateTime::createFromTimestamp(
            strtotime($dateTime)
        );

        return $this;
    }

    private function filter(): array
    {
        return [
            '>UF_DATE' => $this->dateTimeCreated
        ];
    }

    private function getHlClass(): string|DataManager
    {
        if ($this->hlClass !== null) {
            return $this->hlClass;
        }

        $res = HighloadBlockTable::getList([
            'select' => ['ID'],
            'filter' => ['NAME' => 'CrmMailJsonData']
        ])->fetch();

        if ($res === false) {
            throw new \Exception('Хайлод блок не найден');
        }

        $id = $res['ID'];

        $entity = HighloadBlockTable::compileEntity($id);

        $class = $entity->getDataClass();

        $this->hlClass = $class;

        return $class;
    }

    private function handle(array $data): void
    {
        $dealId = (int) $data['UF_DEAL_ID'];

        if ($dealId === 0) {
            return;
        }

        $deal = $this->factoryDeal->getItem($dealId);

        if ($deal === null) {
            return;
        }

        $contactId = $deal->getContactId();

        if ($contactId === null) {
            return;
        }

        $json = $data['UF_MAIL_BODY'] ?? '';

        $mail = json_decode($json, true);

        if ($mail === null) {
            return;
        }

        if (!is_array($mail)) {
            return;
        }

        $contactData = $this->mapping->mapFields('CONTACT', $mail);

        if (empty($contactData)) {
            return;
        }

        $contact = new \CCrmContact(false);
        $contact->Update($contactId, $contactData);

        if (!empty($message = $contactData['RESULT_MESSAGE'])) {
            $this->log('Ошибка при обновлении контакта с ID=' . $contactId . ': ' . $message);
        } else {
            $this->log('Обновился контакт с ID=' . $contactId);
        }
    }

    private function log(string $message): void
    {
        $this->getLogger()->log('debug', $message . PHP_EOL);
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

    protected function getLogName(): string
    {
        return 'contact_add_phone_email/file.log';
    }

    protected function processScriptActions(): void
    {
        $hlClass = $this->getHlClass();

        $res = $hlClass::getList([
            'select' => ['ID', 'UF_DEAL_ID', 'UF_MAIL_BODY'],
            'filter' => $this->filter()
        ]);

        while($data = $res->fetch()) {
            $this->handle($data);
        }
    }
}

try {
    (new ContactAddPhoneEmail1836)
        ->setDateTime(new \DateTime('2024-08-07'))
        ->run();
}catch (\Throwable $throwable) {
    print_r($throwable);
} finally {
    $USER->Logout();
}
