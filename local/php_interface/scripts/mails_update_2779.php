<?php

use Bitrix\Main\Type\DateTime;
use Mts\Main\Scripts\AbstractScript;
use Stream\Main\Integration\Mail\Orm\MailMessageTable;

if (php_sapi_name() !=='cli') {
    exit();
} else {
    define('CLI_RUN', true);
}

define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 3);

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

class LeadgenMailsUpdate extends AbstractScript
{
    const LOG_NAME = 'mails_update_2779';

    protected function getLogName(): string
    {
        return self::LOG_NAME;
    }

    protected function processScriptActions(): void
    {
        $result = \Bitrix\Highloadblock\HighloadBlockTable::getList(array('filter' => array('=TABLE_NAME' => 'crm_mail_json_data')));
        $row = $result->fetch();

        /** @var \Bitrix\Highloadblock\DataManager $dataClass */
        $dataClass = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($row['ID'])->getDataClass();

        $mailboxId = \Stream\Main\Option::get('CRM_LEADGEN_MAILBOX_ID', '24');

        $dateFrom = new DateTime("06.11.2024 00:00:00", "d.m.Y H:i:s");
        $dateTo = new DateTime();

        $rs = MailMessageTable::query()
            ->where('FIELD_FROM', 'sa0001dhmail@mts.ru')
            ->whereLike('BODY', '%MTS_CLOUD%')
            ->where('MAILBOX_ID', $mailboxId)
            ->whereBetween('DATE_INSERT', $dateFrom, $dateTo)
            ->setSelect(['*'])
            ->exec();

        \Bitrix\Main\Application::getConnection()->startTransaction();

        try {
            $this->logger->info('Количество найденных писем: ' . $rs->getSelectedRowsCount());

            $items = [];
            while ($item = $rs->fetch()) {

                $this->logger->info('ID старого письма: ' . $item['ID']);

                $item['FIELD_FROM'] = trim($item['FIELD_FROM']);
                $item['SUBJECT'] = trim($item['SUBJECT']);

                $id = $item['ID'];
                unset($item['ID']);

                $res = MailMessageTable::add($item);
                if (!$res->isSuccess()) {
                    $this->logger->critical(implode(';', $res->getErrorMessages()));
                    dump($res->getErrorMessages());
                    throw new \Bitrix\Main\SystemException(implode(';', $res->getErrorMessages()));
                }
                $this->logger->info('ID нового письма: ' . $res->getId());

                $res = MailMessageTable::delete($id);
                if (!$res->isSuccess()) {
                    $this->logger->critical(implode(';', $res->getErrorMessages()));
                    dump($res->getErrorMessages());
                    throw new \Bitrix\Main\SystemException(implode(';', $res->getErrorMessages()));
                }
                $this->logger->info('Старое письмо с ID=' . $id . ' было удалено');

                $items [] = $item;
            }

            $this->logger->info('Количество новых писем: ' . count($items));
            dump(count($items));

            \Bitrix\Main\Application::getConnection()->commitTransaction();

        } catch (\Throwable $throwable) {
            $this->logger->critical($throwable->getMessage());
            dump($throwable->getMessage());
            dump($throwable->getTraceAsString());
            \Bitrix\Main\Application::getConnection()->rollbackTransaction();
        }
    }
}

(new LeadgenMailsUpdate())->run();
