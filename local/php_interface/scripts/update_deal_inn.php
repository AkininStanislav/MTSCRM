<?php

use Mts\Main\Scripts\AbstractScript;

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

class DealUpdate1612 extends AbstractScript
{
    const CATEGORY_ID = 5;
    private string $separator = ';';

    private string $startTime;
    private string $endTime;

    public readonly string $pathCsv;

    public function setPathCsv(string $path): self
    {
        $this->pathCsv = $path;
        return $this;
    }

    /**
     * @return void
     * Каждая обработанная сделка увеличивает счётчик на 1
     */
    private function increment(): void
    {
        $increment = $this->getIncrement();

        ++$increment;

        $this->setIncrement($increment);
    }

    private function getIncrement(): int
    {
        return \Stream\Main\Option::get('deal_update_inn', 0);
    }

    private function setIncrement(int $increment): void
    {
        \Stream\Main\Option::set('deal_update_inn', $increment);
    }

    private function startTime(): void
    {
        $this->startTime = microtime(true);
    }

    private function endTime(): void
    {
        $this->endTime = microtime(true);
    }

    private function logTime(): void
    {
        $executionTime = $this->endTime - $this->startTime;

        $this->log("Время выполнения скрипта: " . round($executionTime, 2) . " секунд.");
    }

    private function getCategories(): array
    {
        $res = \Bitrix\Crm\Category\Entity\DealCategoryTable::getList([
            'select' => ['ID', 'NAME']
        ]);

        $categories = [];

        while ($category = $res->fetch()) {
            $categories[$category['ID']] = $category['NAME'];
        }

        return $categories;
    }

    protected function getLogName(): string
    {
        return 'update_deal_inn/' .date('Y_m_d') . '.log';
    }

    protected function processScriptActions(): void
    {
        $categories = $this->getCategories();

        $increment = $this->getIncrement();

        $i = 0;

        $handle = fopen($this->pathCsv, "r");

        if ($handle === false) {
            return;
        }

        while(($line = fgets($handle)) !== false) {
            [$dealId, $inn] = explode($this->separator, $line);

            $dealId = (int) $dealId;

            if ($dealId === 0) {
                continue;
            }

            if ($i < $increment) {
                $i++;
                continue;
            }

            preg_match('(\d+)', $inn, $match);

            $inn = $match[0];

            $data = \CCrmDeal::GetListEx(
                arFilter: ['ID' => $dealId],
                arSelectFields: ['ID', 'STAGE_ID', 'CATEGORY_ID', 'TITLE']
            )->Fetch();

            if ($data === false) {
                $this->log('Сделка ' . $dealId . ' не найдена');
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

            $arFields = [
                'UF_CRM_1646057629398' => $inn
            ];

            $deal = new \CCrmDeal(false);
            $deal->Update($dealId, $arFields);

            if (!empty($message = $arFields['RESULT_MESSAGE'])) {
                $this->log('Произошла ошибка при обновлении: ' . $message);
                $this->increment();
                continue;
            }

            $this->increment();

            $this->getLogger()->log('debug','Сделка ID=' . $dealId);
            $this->log('TITLE: ' . $data['TITLE']);
            $this->log('STAGE_ID: ' . $data['STAGE_ID']);
            $this->log('UF_CRM_1646057629398: ' . $inn);
        }

        $this->setIncrement(0);
    }

    private function log(string $message)
    {
        $this->getLogger()->log(\Psr\Log\LogLevel::DEBUG, $message . PHP_EOL);
    }
}

try {
    (new DealUpdate1612())
        ->setPathCsv(__DIR__ . '/csv/update_deal_inn.csv')
        ->run();
} catch (\Throwable $throwable) {
    echo print_r($throwable, true);
} finally {
    $USER->Logout();
}
