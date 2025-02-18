<?php
define('NOT_CHECK_PERMISSIONS', true);
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 3);

require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

use Mts\Main\Scripts\AbstractScript;

class DealUpdateFromMapping extends AbstractScript
{
    const STR_LOG_LOSE = 'Сделка не обновлена ';
    const STR_LOG_WIN = 'Успешно';
    const STR_LOG_FOUNDED = 'Поле тип источника заполнено';
    const LOG_NAME = 'deals_update_1828';

    /**
     * @return string
     */
    protected function getLogName(): string
    {
        return self::LOG_NAME;
    }

    /**
     * @return void
     */
    protected function processScriptActions(): void
    {
        $mapFieldsArr = $this->getAndPrepareMapping();
        $this->processDeals($mapFieldsArr);
    }

    /**
     * @return array
     */
    private function getAndPrepareMapping(): array
    {
        $rs = \CIBlockElement::GetList(
            arFilter: [
                "IBLOCK_CODE" => 'mapping_fields',
                "PROPERTY_FUNNEL" => 5
            ],
            arSelectFields: [
                "PROPERTY_FUNNEL",
                "PROPERTY_CODE_UPR",
                "PROPERTY_CODE_CHECK",
                "PROPERTY_VALUE_CHECK",
                "PROPERTY_VALUE_UPR",
            ],
        );
        $resFieldsArr = [];
        while ($resFields = $rs->Fetch()) {
            $controlCode = $resFields['PROPERTY_CODE_UPR_VALUE'];
            $mappingValue = $resFields['PROPERTY_VALUE_UPR_VALUE'];
            $resFieldsArr[$controlCode][$mappingValue] = [
                "PROPERTY_CODE_CHECK" => $resFields['PROPERTY_CODE_CHECK_VALUE'],
                "PROPERTY_CHECK_VALUE" => $resFields['PROPERTY_VALUE_CHECK_VALUE'],
            ];
        }

        return $resFieldsArr;
    }

    /**
     * @return Generator
     */
    private function getDealsbyCategory(): \Generator
    {
        $entityResult = \CCrmDeal::GetListEx(
            arOrder: ['ID' => 'DESC'],
            arFilter: ['CHECK_PERMISSIONS' => 'N', 'CATEGORY_ID' => 5],
            arSelectFields: [
                'ID',
                'TITLE',
                'SOURCE_ID',
                'UF_SOURCE_TYPE_REQUEST'
            ]
        );
        $i = 1;
        while ( $entity = $entityResult->fetch() ) {
            if (!$entity['UF_SOURCE_TYPE_REQUEST']) {
                yield $entity;
            } else {
                $this->logger->info('Сделка: ' . $i . ' | ' . self::STR_LOG_FOUNDED . ' | ' . $entity['ID']);
            }
            $i++;
        }
    }

    /**
     * @param array $mapFields
     * @return void
     */
    public function processDeals(array $mapFields) :void
    {
        $entityObject = new \CCrmDeal(false);
        foreach ($this->getDealsbyCategory() as $entity) {
            if (!empty($mapFields['SOURCE_ID']) && !empty($mapFields['SOURCE_ID'][$entity['SOURCE_ID']])) {
                $mappingValue = $mapFields['SOURCE_ID'][$entity['SOURCE_ID']]['PROPERTY_CHECK_VALUE'];
                $mappingCode = $mapFields['SOURCE_ID'][$entity['SOURCE_ID']]['PROPERTY_CODE_CHECK'];
                $entityFields = [
                    $mappingCode => $mappingValue
                ];
                $isUpdateSuccess = $entityObject->Update(
                    ID: $entity['ID'],
                    arFields: $entityFields,
                    options: [
                        'DISABLE_USER_FIELD_CHECK' => true,
                        'DISABLE_REQUIRED_USER_FIELD_CHECK' => true,
                    ]
                );
                $status = self::STR_LOG_WIN;
                if (!$isUpdateSuccess) {
                    $status = self::STR_LOG_LOSE . $entityObject->LAST_ERROR;
                }
                $checkUpdateArr = [
                    'ID' => $entity['ID'],
                    'TITLE' => $entity['TITLE'],
                    $mappingCode => $mappingValue,
                    'STATUS' => $status,
                ];
                $this->logger->info('Сделка: ' . $entity['ID'] . ' | ' . $status);
                $this->logger->info(print_r($checkUpdateArr, true));
            }
        }
    }
}

$service = new DealUpdateFromMapping();

try {
    $service->run();
} catch (Throwable $exception) {
    echo $exception->getMessage().PHP_EOL;
}