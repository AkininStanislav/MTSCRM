<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
/**
 ** @var array $arCurrentValues
   */
?>
<tr>
    <td align="right" width="40%"><span class="adm-required-field">Код инфоблока со списком ответственных:</span></td>
    <td width="60%">
        <?=CBPDocument::ShowParameterField('text', 'IBLOCK_ID', $arCurrentValues['IBLOCK_ID'])?>
    </td>
</tr>
<tr>
    <td align="right" width="40%"><span class="adm-required-field">Код сделки:</span></td>
    <td width="60%">
        <?=CBPDocument::ShowParameterField('text', 'DEAL_ID', $arCurrentValues['DEAL_ID']) ?>
    </td>
</tr>
<tr>
    <td align="right" width="40%"><span class="adm-required-field">Код поля с группой источников:</span></td>
    <td width="60%">
        <?=CBPDocument::ShowParameterField('text', 'GROUP_CODE', $arCurrentValues['GROUP_CODE'])?>
    </td>
</tr>
<tr>
    <td align="right" width="40%"><span class="adm-required-field">Код поля с последним назначенным ответственным:</span></td>
    <td width="60%">
        <?=CBPDocument::ShowParameterField('text', 'DEAL_STAGE_CODE',$arCurrentValues['DEAL_STAGE_CODE'])?>
    </td>
</tr>
<tr>
    <td align="right" width="40%"><span class="adm-required-field">Код поля ответственного:</span></td>
    <td width="60%">
        <?=CBPDocument::ShowParameterField('text', 'USER_CODE', $arCurrentValues['USER_CODE'])?>
    </td>
</tr>
<tr>
    <td align="right" width="40%"><span class="adm-required-field">Код поля очереди ответственного:</span></td>
    <td width="60%">
        <?=CBPDocument::ShowParameterField('text', 'SORT_CODE', $arCurrentValues['SORT_CODE']) ?>
    </td>
</tr>
<tr>
    <td align="right" width="40%"><span class="adm-required-field">Код поля активности ответственного:</span></td>
    <td width="60%">
        <?=CBPDocument::ShowParameterField('text', 'ACTIVE_CODE', $arCurrentValues['ACTIVE_CODE'])?>
    </td>
</tr>
