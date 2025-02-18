<?php

use Bitrix\Crm\Category\Entity\DealCategoryTable;
use Mts\Main\Scripts\AbstractScript;
use Mts\Main\Tools\File;
use Mts\Main\Tools\UserField;
use Stream\Main\Option;

if (php_sapi_name() !== 'cli') {
    die;
}

const NOT_CHECK_PERMISSIONS = true;
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 3);

require_once $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php";

\CModule::IncludeModule('crm');
\CModule::IncludeModule('mts.main');
\CModule::IncludeModule('stream.main');

global $USER;
$USER->Authorize(1);

class UpdateDealDirection1613 extends AbstractScript
{
    const CATEGORY_ID = 5;

    const ROOT_FILE_LOG_PATH = 'upload';

    private readonly string $csvFile;

    public function setCsvFile(string $csvFile): self
    {
        $this->csvFile = $csvFile;
        return $this;
    }
    protected function getLogName(): string
    {
        return 'filling_deal_flow/' . date('Y_m_d') . '.log';
    }

    private function getCategories(): array
    {
        $res = DealCategoryTable::getList([
            'select' => ['ID', 'NAME']
        ]);

        $categories = [];

        while ($category = $res->fetch()) {
            $categories[$category['ID']] = $category['NAME'];
        }

        return $categories;
    }

    protected function processScriptActions(): void
    {
        $increment = $this->getIncrement();
        $i = 0;

        $directions = $this->getDirections();
        $categories = $this->getCategories();

        $reader = new File($this->csvFile);
        $reader->open();

        try {
            while(([$dealId, $nameDirection, $xmlId] = $reader->getCsvLine()) != false) {
                $dealId = (int) $dealId;

                if ($dealId === 0) {
                    continue;
                }

                if ($increment > $i) {
                    $i++;
                    continue;
                }

                $xmlId = trim($xmlId);

                $data = \CCrmDeal::GetListEx(
                    arFilter: ['ID' => $dealId],
                    arSelectFields: ['ID', 'TITLE', 'STAGE_ID', 'CATEGORY_ID']
                )->Fetch();

                if ($data === false) {
                    $this->log('Сделка ' . $dealId . ' не найдена.');
                    $this->increment();
                    continue;
                }

                $categoryId = (int) $data['CATEGORY_ID'];

                if ($categoryId !== self::CATEGORY_ID) {
                    $name = $categories[$categoryId];
                    $this->log('Сделка ' . $dealId . ' находится в другой воронке "' . $name . '", что не соответствует указанным параметрам запроса');
                    $this->increment();
                    continue;
                }

                $directionId = $directions[$xmlId] ?? 0;

                if ($directionId === 0) {
                    $this->log('Направление сделки не найдено');
                    $this->increment();
                    continue;
                }

                $deal = new \CCrmDeal(false);
                $arFields = ['UF_CRM_6413FA26E2687' => $directionId];

                $deal->Update($dealId, $arFields);

                if (!empty($message = $arFields['RESULT_MESSAGE'])) {
                    $this->log('Произошла ошибка при обновлении: ' . $message);
                    $this->increment();
                    continue;
                }


                $this->log('Сделка ID=' . $dealId);
                $this->log('TITLE: ' . $data['TITLE']);
                $this->log('STAGE_ID: ' . $data['STAGE_ID']);
                $this->log('UF_CRM_6413FA26E2687: ' . $nameDirection);
                $this->increment();
            }
        } catch (\Throwable $exception) {
            $this->logThrowable($exception);
        } finally {
            $reader->close();
        }
    }

    private function increment(): void
    {
        $increment = $this->getIncrement();

        ++$increment;

        $this->log($increment);

        $this->setIncrement($increment);
    }

    private function getIncrement(): int
    {
        return Option::get('deal_update_direction', 0);
    }

    private function setIncrement(int $increment): void
    {
        Option::set('deal_update_direction', $increment);
    }

    private function getDirections(): array
    {
        $userField = new UserField('CRM_' . \CCrmOwnerType::DealName, false);

        $userField->getUserFields('UF_CRM_6413FA26E2687');
        $enums = $userField->getEnums();

        $directions = [];

        foreach ($enums as $enum) {
            $directions[$enum['XML_ID']] = $enum['ID'];
        }

        return $directions;
    }

    private function log(string $message): void
    {
        $this->getLogger()->info($message);
    }
}

try {
    (new UpdateDealDirection1613())
        ->setCsvFile(__DIR__ . '/csv/deal_direction.csv')
        ->run()
    ;
} catch (\Throwable $exception) {
    print_r($exception);
} finally {
    $USER->Logout();
}