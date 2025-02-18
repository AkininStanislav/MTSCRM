<?php

use Mts\Main\Crm\DealCategory\DealCategory;
use Mts\Main\Tools\File;
use Mts\Main\Tools\UserField;
use Stream\Main\Option;

if (php_sapi_name() !== 'cli') {
    die;
}

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 3);

define('NOT_CHECK_PERMISSIONS', true);

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

\CModule::IncludeModule('crm');
\CModule::IncludeModule('mts.main');

class ExpiredDeals1923 extends \Mts\Main\Scripts\AbstractScript
{
    const ROOT_FILE_LOG_PATH = 'upload/logs/180_lose_0824';

    private string $csvFile;

    protected function getLogName(): string
    {
        return date('Y_m_d') . '.log';
    }

    public function setCsvFile(string $filePath): self
    {
        $this->csvFile = $filePath;
        return $this;
    }

    protected function processScriptActions(): void
    {
        $category = DealCategory::MAAS;

        $categoryIdMaas = $category->getId();

        $increment = $this->getIncrement();

        $categories = $this->getCategories();

        [$refusalId, $refusalName] = $this->getRefusal();

        $file = new File($this->csvFile);
        $file->open();

        $deal = new \CCrmDeal(false);

        $i = 0;
        $current = 0;

        $overall = $this->getCountLine();

        while(($data = $file->getCsvLine()) !== false) {
            dump($current . '/' . $overall);
            $current++;

            $dealId = (int) $data[0];

            if ($dealId === 0) {
                continue;
            }

            if ($increment > $i) {
                ++$i;
                continue;
            }

            $dealData = \CCrmDeal::GetListEx(
                arFilter: ['ID' => $dealId],
                arSelectFields: ['ID', 'TITLE', 'STAGE_ID', 'UF_CRM_1646846305877', 'CATEGORY_ID']
            )->Fetch();

            if ($dealData === false) {
                $this->log('Сделка с ID=' . $dealId . ' не найдена');
                $this->increment();
                continue;
            }

            if (($categoryId = (int) $dealData['CATEGORY_ID']) !== $categoryIdMaas) {
                $this->log('Сделка с ID=' . $dealId . ' находится в другой воронке');
                $this->increment();
                continue;
            }

            $arFields = [
                'UF_CRM_1646846305877' => $refusalId,
                'STAGE_ID' => 'C5:LOSE'
            ];

            $deal->Update($dealId, $arFields);

            if (!empty($message = $arFields['RESULT_MESSAGE'])) {
                $this->log('Ошибка при обновлении сделки с ID=' . $dealId . ': ' . $message);
                $this->increment();
                continue;
            }

            $this->increment();
            $this->log('ID: ' . $dealId);
            $this->log('TITLE: ' . $dealData['TITLE']);
            $this->log('STAGE_ID: C5:LOSE');
            $this->log($refusalName);
        }

        $file->close();
    }

    private function getCountLine(): int
    {
        $count = 0;
        $fp = fopen($this->csvFile,"r");
        if($fp){
            while(!feof($fp)){
                $content = fgets($fp);
                if($content)    $count++;
            }
        }
        fclose($fp);
        return $count;
    }

    private function getRefusal(): array
    {
        $userField = new UserField('CRM_' . \CCrmOwnerType::DealName);
        $userField->getUserFields('UF_CRM_1646846305877');

        $value = $userField->getEnums([
            'XML_ID' => 'b15cbbb8818630241fcaded989b5bf51'
        ]);

        return [$value['ID'], $value['VALUE']];
    }

    private function increment(): void
    {
        $increment = $this->getIncrement();

        ++$increment;

        $this->setIncrement($increment);
    }

    private function getIncrement(): int
    {
        return Option::get('180_day_expired_deals', 0);
    }

    private function setIncrement(int $increment): void
    {
        Option::set('180_day_expired_deals', $increment);
    }

    private function getCategories(): array
    {
        $res = \Bitrix\Crm\Category\Entity\DealCategoryTable::getList();

        $categories = [];
        while ($data = $res->fetch()) {
            $categories[$data['ID']] = $data['NAME'];
        }

        return $categories;
    }

    private function log(string $message): void
    {
        $this->logger->info($message);
    }
}

try {
    global $USER;
    $USER->Authorize(1);

    (new ExpiredDeals1923())
        ->setCsvFile(__DIR__ . '/csv/180_day_expired_deals.csv')
        ->run()
    ;
} catch (\Throwable $exception) {

} finally {
    $USER->Logout();
}