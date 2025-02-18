<?php

if (php_sapi_name() !== 'cli') {
    die;
}

use Bitrix\Crm\ItemIdentifier;
use Mts\Main\Tools\Timeline;
use Stream\Main\Option;

const NOT_CHECK_PERMISSIONS = true;
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 3);

require_once $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php";

\CModule::IncludeModule('crm');

global $USER;
$USER->Authorize(1);

class UpdateTypeCompany1614 extends \Mts\Main\Scripts\AbstractScript
{
    const CATEGORY_ID = 5;

    const ROOT_FILE_LOG_PATH = 'upload/logs/update_deal_campaign_type';

    private string $filePath;

    protected function getLogName(): string
    {
        return date('Y_m_d') . ' .log';
    }

    public function setCsvFile(string $filePath): self
    {
        $this->filePath = $filePath;
        return $this;
    }

    private function log(string $message): void
    {
        $this->logger->info($message);
    }

    protected function processScriptActions(): void
    {
        $increment = $this->getIncrement();
        $typeCompanies = $this->getTypeCompanies();

        $file = new \Mts\Main\Tools\File($this->filePath);
        $file->open();

        $i = 0;
        while(($line = $file->getCsvLine()) !== false) {
            [$dealId, $xmlId] = $line;

            $dealId = (int) $dealId;

            if ($dealId == 0) {
                continue;
            }

            if ($i < $increment) {
                $i++;
                continue;
            }

            $dataDeal = \CCrmDeal::GetListEx(
                arFilter: ['ID' => $dealId, 'CATEGORY_ID' => self::CATEGORY_ID],
                arSelectFields: ['ID', 'TITLE']
            )->Fetch();

            if ($dataDeal === false) {
                $this->log('Сделка с ID=' . $dealId . ' не найдена');
                $this->increment();
                continue;
            }

            $typeCompany = $typeCompanies[$xmlId] ?? null;

            if ($typeCompany === null) {
                $this->log('Значение с XML_ID=' . $xmlId . ' не найдено');
                $this->increment();
                continue;
            }

            $typeCompanyId = $typeCompany['ID'];
            $typeCompanyName = $typeCompany['NAME'];

            $deal = new \CCrmDeal(false);

            $data = [
                'UF_CRM_1679565697' => $typeCompanyId
            ];

            $deal->Update($dealId, $data);

            if (!empty($message = $data['RESULT_MESSAGE'])) {
                $this->log('Ошибка при обновлении сделки с ID=' . $dealId . ': ' . $message);
                $this->increment();
                continue;
            }

            $this->log('Сделка ID=' . $dealId);
            $this->log('Сделка TITLE=' . $dataDeal['TITLE']);
            $this->comment('Значение изменено на ' . $typeCompanyName, $dealId);
            $this->increment();
        }
    }

    private function comment(string $text, int $dealId): int
    {
        return Timeline::create($text, new ItemIdentifier(\CCrmOwnerType::Deal, $dealId));
    }

    private function getTypeCompanies(): array
    {
        $userField = new Mts\Main\Tools\UserField('CRM_DEAL', false);

        $userField->getUserFields('UF_CRM_1679565697');

        $enums = $userField->getEnums();

        $xmlId2id = [];

        foreach ($enums as $enum) {
            $xmlId2id[$enum['XML_ID']] = [
                'ID' => $enum['ID'],
                'NAME' => $enum['VALUE']
            ];
        }

        return $xmlId2id;
    }

    private function increment(): void
    {
        $increment = $this->getIncrement();

        ++$increment;

        $this->setIncrement($increment);
    }

    private function getIncrement(): int
    {
        return Option::get('deal_update_type_company', 0);
    }

    private function setIncrement(int $increment): void
    {
        Option::set('deal_update_type_company', $increment);
    }
}

try {
    (new UpdateTypeCompany1614())
        ->setCsvFile(__DIR__ . '/csv/deal_type_company.csv')
        ->run()
    ;
} catch (\Throwable $throwable) {
    print_r($throwable);
} finally {
    $USER->Logout();
}
