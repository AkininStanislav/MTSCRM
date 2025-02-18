<?php

use Bitrix\Crm\Timeline\Entity\EO_Timeline_Result;
use Bitrix\Crm\Timeline\Entity\TimelineBindingTable;
use Bitrix\Crm\Timeline\Entity\TimelineTable;
use Bitrix\Main\Entity\ReferenceField;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\ORM\Query\Result;
use Mts\Main\Scripts\AbstractScript;

if (php_sapi_name() !== 'cli') {
    die;
}

const NOT_CHECK_PERMISSIONS = true;

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 3);

require_once $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php";

\CModule::IncludeModule('crm');
\CModule::IncludeModule('mts.main');

class ParseTimeline2042 extends AbstractScript
{
    protected function getLogName(): string
    {
        return 'parse_timeline_date_start_rk/' . date('Y_m_d') . '.log';
    }

    private function getComments(): Result
    {
        return TimelineTable::getList([
            'select' => [
                'COMMENT_ID' => 'ID',
                'COMMENT',
                'CREATED',
                'DEAL_ID' => 'DEAL.ID'
            ],
            'filter' => [
                '>=CREATED' => \Bitrix\Main\Type\DateTime::createFromTimestamp(strtotime('2024-09-01 00:00:00')),
                'DEAL.STAGE_ID' => ['C5:LOSE', 'C5:WON', 'C5:UC_IJSKBK', 'C5:2', 'C5:1']
            ],
            'runtime' => [
                (new ReferenceField(
                    'BIND',
                    TimelineBindingTable::class,
                    Join::on('this.ID', 'ref.OWNER_ID')
                ))->configureJoinType('inner'),
                (new ReferenceField(
                    'DEAL',
                    \Bitrix\Crm\DealTable::class,
                    Join::on('this.BIND.ENTITY_ID', 'ref.ID')
                ))->configureJoinType('inner')
            ]
        ]);
    }

    protected function processScriptActions(): void
    {
        $res = $this->getComments();

        $deal = new \CCrmDeal(false);

        while ($data = $res->fetch()) {
            $comment = $data['COMMENT'];

            if (!str_contains($comment, 'Дата начала РК изменена с')) {
                continue;
            }

            $oldDate = $this->parseCommentAndGetOldData($comment);

            if ($oldDate === false) {
                continue;
            }

            $dealId = $data['DEAL_ID'];

            $arFields = [
                'UF_CRM_1571153011596' => $oldDate
            ];

            $deal->Update($dealId, $arFields);

            if (!empty($message = $arFields['RESULT_MESSAGE'])) {
                $this->log('Ошибка при обновлении сделки с ID=' . $dealId . ': ' . $message);
                continue;
            }

            $this->log('Сделка с ID=' . $dealId . ' успешно обновилась. новое значение: ' . $oldDate);

            $commentId = $data['COMMENT_ID'];

            TimelineTable::delete($commentId);

            $this->log('Коммент с ID=' . $commentId);
        }
    }

    private function log(string $message): void
    {
        $this->logger->info($message);
    }

    private function parseCommentAndGetOldData(string $comment): bool|string
    {
        preg_match('/^Дата начала РК изменена с (?<old_date>\S+)/', $comment, $matches);

        if (empty($oldDate = $matches['old_date'])) {
            return false;
        }

        return $oldDate;
    }
}

try {
    global $USER;
    $USER->Authorize(1);
    (new ParseTimeline2042())->run();

} catch (\Throwable $exception) {

} finally {
    $USER->Logout();
}