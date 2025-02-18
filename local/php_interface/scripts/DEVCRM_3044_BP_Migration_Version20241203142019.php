<?php

namespace Sprint\Migration;

use Bitrix\Main\Loader;

Loader::includeModule('stream.main');

class DEVCRM_3044_BP_Migration_Version20241203142019 extends Version
{

    use \Stream\Main\Integration\Migration\Bizproc;

    protected $description = "Миграция БП - расчет плановой выручки для воронки Основные сделки MAAS";

    protected $moduleVersion = "4.1.1";

        public const PARAMS = array (
          0 =>
          array (
            'ID' => '1791',
            'NAME' => 'Расчет плановой выручки',
            'TEMPLATE' =>
            array (
              0 =>
              array (
                'Type' => 'SequentialWorkflowActivity',
                'Name' => 'Template',
                'Activated' => 'Y',
                'Properties' =>
                array (
                  'Title' => 'Последовательный бизнес-процесс',
                  'Permission' =>
                  array (
                  ),
                ),
                'Children' =>
                array (
                  0 =>
                  array (
                    'Type' => 'SetVariableActivity',
                    'Name' => 'A57864_32866_39754_42471',
                    'Activated' => 'Y',
                    'Properties' =>
                    array (
                      'VariableValue' =>
                      array (
                        'dealId' => '{=Document:ID}',
                      ),
                      'Title' => 'Изменение переменных',
                      'EditorComment' => '',
                    ),
                    'Children' =>
                    array (
                    ),
                  ),
                  1 =>
                  array (
                    'Type' => 'CodeActivity',
                    'Name' => 'A82353_48752_13012_31604',
                    'Activated' => 'Y',
                    'Properties' =>
                    array (
                      'ExecuteCode' => '\\Bitrix\\Main\\Loader::includeModule(\'stream.main\');
        $rootActivity = $this->GetRootActivity();
        
        $entityResult = Bitrix\\Crm\\DealTable::getList([
                        \'select\' => [
                            \'ID\',
                            \'TITLE\',
                            \'CATEGORY_ID\'
                        ],
                        \'filter\' => [
                            \'ID\' => $rootActivity->GetVariable(\'dealId\'),
                        ]
                    ])->fetch();
        $rootActivity->SetVariable("planVat", $entityResult[\'CATEGORY_ID\']);',
                      'Title' => 'PHP код',
                      'EditorComment' => '',
                    ),
                    'Children' =>
                    array (
                    ),
                  ),
                  2 =>
                  array (
                    'Type' => 'IfElseActivity',
                    'Name' => 'A74833_10104_9842_91293',
                    'Activated' => 'Y',
                    'Properties' =>
                    array (
                      'Title' => 'Условие',
                    ),
                    'Children' =>
                    array (
                      0 =>
                      array (
                        'Type' => 'IfElseBranchActivity',
                        'Name' => 'A75469_64178_21642_20639',
                        'Activated' => 'Y',
                        'Properties' =>
                        array (
                          'Title' => 'Условие',
                          'propertyvariablecondition' =>
                          array (
                            0 =>
                            array (
                              0 => 'planVat',
                              1 => '=',
                              2 => '5',
                              3 => '0',
                            ),
                          ),
                          'EditorComment' => '',
                        ),
                        'Children' =>
                        array (
                          0 =>
                          array (
                            'Type' => 'SetFieldActivity',
                            'Name' => 'A98148_40709_13006_48418',
                            'Activated' => 'Y',
                            'Properties' =>
                            array (
                              'FieldValue' =>
                              array (
                                'UF_CRM_PLANNED_REVENUE' => '={=Document:UF_CRM_PLANNED_REVENUE_EXCLUDING_VAT}-({=Document:UF_CRM_PLANNED_REVENUE_EXCLUDING_VAT}*20/120)',
                              ),
                              'ModifiedBy' =>
                              array (
                              ),
                              'MergeMultipleFields' => 'N',
                              'Title' => 'Плановая выручка',
                              'EditorComment' => '',
                            ),
                            'Children' =>
                            array (
                            ),
                          ),
                        ),
                      ),
                      1 =>
                      array (
                        'Type' => 'IfElseBranchActivity',
                        'Name' => 'A25130_27284_99092_99683',
                        'Activated' => 'Y',
                        'Properties' =>
                        array (
                          'Title' => 'Условие',
                        ),
                        'Children' =>
                        array (
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
            'PARAMETERS' =>
            array (
            ),
            'VARIABLES' =>
            array (
              'planVat' =>
              array (
                'Name' => 'Воронка',
                'Description' => '',
                'Type' => 'double',
                'Required' => '0',
                'Multiple' => '0',
                'Options' => '',
                'Default' => '0',
              ),
              'dealId' =>
              array (
                'Name' => 'Код сделки',
                'Description' => '',
                'Type' => 'string',
                'Required' => '0',
                'Multiple' => '0',
                'Options' => '',
                'Default' => '',
              ),
            ),
            'CONSTANTS' =>
            array (
            ),
            'AUTO_EXECUTE' => '3',
            'SYSTEM_CODE' => 'crm_raschet_planovoy_vyruchki',
            'DOCUMENT_TYPE' =>
            array (
              0 => 'crm',
              1 => 'CCrmDocumentDeal',
              2 => 'DEAL',
            ),
            'DOCUMENT_STATUS' => NULL,
            'IS_MODIFIED' => 'Y',
            'USER_ID' => '7721',
            'ACTIVE' => 'Y',
            'IS_SYSTEM' => 'N',
            'SORT' => '10',
            'MODULE_ID' => 'crm',
            'ENTITY' => 'CCrmDocumentDeal',
            'POST' =>
            array (
            ),
          ),
        );
    
    /**
     * @throws Exceptions\HelperException
     * @return bool|void
     */
    public function up()
    {
        $this->importBizprocMulti(DEVCRM_3044_BP_Migration_Version20241203142019::PARAMS);
    }

    public function down()
    {
        //откат для роботов и бп не нужен, т.к. может перетереть правки с боя
    }
}
