<?if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}
use Bitrix\Main\Web\Json;
use Bitrix\Main\Page\Asset;

Asset::getInstance()->addCss("/bitrix/css/main/font-awesome.css");
Asset::getInstance()->addCss("/local/inc/jquery-ui-1.12.1/jquery-ui.css");
Asset::getInstance()->addJs("/local/inc/jquery-ui-1.12.1/jquery-ui.js");
$formatUser = [];
$this->setFrameMode(true);

?>

<div class="col-md-12 p-relative">
    <div class="loader-fon" data-loader></div>
        <?if($arResult['CAN_SECTION_ADD']):?>
            <button class="ui-btn ui-btn-success ui-btn-icon-add add_new_row" data-add-new-row="">
                <?=GetMessage('ADD_NEW_MESS')?>
            </button>
        <?endif;?>
        <div class="table-responsive">
        <table class="table table-striped">
            <thead>
            <tr class="table-primary">
                <th scope="col"><?=GetMessage('COL_1')?></th>
                <th scope="col"><?=GetMessage('COL_2')?></th>
                <?if(
                        $arResult['CAN_SECTION_EDIT']
                        ||$arResult['CAN_SECTION_DELETE']
                        ||$arResult['CAN_CAN_ELEMENT_ADD']
                        ||$arResult['CAN_ELEMENT_EDIT']
                        ||$arResult['CAN_ELEMENT_DELETE']
                ):?>
                    <th></th>
                <?endif;?>
            </tr>
            </thead>
            <tbody id="main_table">
            <?foreach ($arResult['DATA'] as $key=>$element):?>
                <tr data-id="<?=$key?>">
                    <td class="table-row block-section">
                        <?foreach ($element['SECTIONS'] as $section):?>
                            <div class="record-text" id="source_item_<?=$section['STATUS_ID']?>_<?=$key?>" data-id="<?=$section['ID']?>" data-status="<?=$section['STATUS_ID']?>">
                                <span class="source-name"> <?=$section['NAME']?></span>
                                <?if($arResult['CAN_SECTION_EDIT']):?>
                                    <i class="fa fa-pencil-square-o icon-add" data-source-single-edit ></i>
                                <?endif;?>
                                <?if($arResult['CAN_SECTION_DELETE']):?>
                                    <i class="fa fa-minus-circle icon-remove remove-source" data-source-single-delete></i>
                                <?endif;?>
                                <?if($arResult['CAN_SECTION_EDIT']):?>
                                    <div class="edit-select-source" name="edit_source" data-edit-source="">
                                        <input type="hidden" value="0"/>
                                        <div id="edit_single_source_<?=$key?>_<?=$section['ID']?>" name="edit_single_source" data-edit-single-source="">
                                        </div>
                                        <i class="fa fa-close close-source" data-close-source></i>
                                    </div>
                                <?endif;?>
                            </div>
                        <?endforeach?>
                        <?if($arResult['CAN_SECTION_EDIT'] || $arResult['CAN_SECTION_ADD'] ):?>
                            <div class="record-text add_new_source" data-add-new-source>
                                <input type="hidden" value="0"/>
                                <div id="add_new_source_<?=$key?>" name="add_new_source"></div>
                            </div>
                        <?endif;?>
                    </td>
                    <td class="table-row block-user">
                            <ul class="sortable-ul">
                                <?foreach ($element['ELEMENTS'] as $employee):?>
                                    <li class="record-text <?if($employee['IS_CURRENT'] == 'Y'):?>current-user-rec<?endif?>"
                                        data-id="<?=$employee['ID']?>"
                                        data-employee="<?=$employee['ID_USER']?>">
                                        <?if($arResult['CAN_ELEMENT_EDIT']):?>
                                            <i class="fa fa-arrows handle" data-handle></i>
                                        <?endif;?>
                                            <input type="checkbox" class="checkbox-value" data-active-user
                                                   <?if($employee['ACTIVE'] == 'Да'):?>checked="checked"<?endif;?>
                                                   <?if(!$arResult['CAN_ELEMENT_EDIT']):?>
                                                        readonly="readonly"
                                                   <?endif;?>
                                            >
                                            <span class="user-name-text">
                                                <?=$employee['FIO']?>
                                                <?if($employee['IS_CURRENT'] == 'Y'):?>
                                                    <?=GetMessage('CURRENT_TEXT')?>
                                                <?endif?>
                                            </span>
                                            <?if($arResult['CAN_ELEMENT_EDIT']):?>
                                                <i class="fa fa-pencil-square-o icon-add edit-user" data-user-single-edit></i>
                                            <?endif;?>
                                            <?if($arResult['CAN_ELEMENT_DELETE']):?>
                                                <i class="fa fa-minus-circle icon-remove remove-user" data-user-single-delete></i>
                                            <?endif;?>
                                            <?if($arResult['CAN_ELEMENT_EDIT']):?>
                                                <div class="edit-select-user">
                                                    <input type="hidden" value="0"/>
                                                    <div id="edit_single_user_<?=$key?>_<?=$employee['ID']?>" name="edit_single_user" data-edit-single-user="">
                                                    </div>
                                                    <i class="fa fa-close close-user" data-close-user></i>
                                                </div>
                                            <?endif;?>
                                    </li>
                                <?endforeach?>
                            </ul>
                            <?if($arResult['CAN_ELEMENT_EDIT'] || $arResult['CAN_ELEMENT_ADD'] ):?>
                                <div class="record-text add_new_user" data-add-new-user-block>
                                    <input type="hidden" value="0"/>
                                    <div id="add_new_user_<?=$key?>" name="add_new_user" data-add-new-user></div>
                                </div>
                            <?endif;?>
                    </td>
                    <?if(
                         $arResult['CAN_SECTION_EDIT']
                         ||$arResult['CAN_SECTION_DELETE']
                         ||$arResult['CAN_CAN_ELEMENT_ADD']
                         ||$arResult['CAN_ELEMENT_EDIT']
                         ||$arResult['CAN_ELEMENT_DELETE']
                    ):
                    ?>
                        <td  class="table-row block-edit" data-block-edit >
                            <div class="d-grid">
                                <button class="ui-btn ui-btn-primary-dark ui-btn-icon-edit edit-rec-section mb-2"
                                        id="main_menu_edit_btn_<?=$key?>"
                                        data-id="<?=$key?>"
                                        data-edit-rec>
                                     <?=GetMessage('EDIT_TEXT')?>
                                </button>
                                <button class="ui-btn ui-btn-success ui-btn-icon-back save-rec-section mb-2"
                                        id="main_menu_close_btn_<?=$key?>"
                                        data-cancel-rec>
                                     <?=GetMessage('CLOSE_TEXT')?>
                                </button>
                                <?if($arResult['CAN_SECTION_DELETE']):?>
                                    <button class="ui-btn ui-btn-danger-light ui-btn-icon-remove delete-rec-section mb-2"
                                            id="main_menu_delete_btn_<?=$key?>"
                                            data-id="<?=$key?>"
                                            data-delete-rec>
                                        <?=GetMessage('DEL_TEXT')?>
                                    </button>
                                <?endif;?>
                            </div>
                        </td>
                    <?endif;?>
                </tr>
            <?endforeach?>
            </tbody>
        </table>
        </div>
</div>

<div class="modal fade" id="deleteDialog" tabindex="-1" aria-labelledby="deleteDialogModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?=GetMessage('WARNING_TEXT')?></h5>
                <button type="button" class="btn-close btn-close-modal" data-bs-dismiss="modal" aria-label="Close">
                    <i class="fa fa-close"></i>
                </button>
            </div>
            <div class="modal-body">
            </div>
            <div class="modal-footer">
                <button type="button" class="ui-btn btn-close-modal" data-bs-dismiss="modal">
                    <?=GetMessage('CANCEL_TEXT')?>
                </button>
                <button type="button" class="ui-btn ui-btn-success btn-action">
                    <?=GetMessage('YES_TEXT')?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    BX.message({
        params: <?=CUtil::PhpToJSObject($arParams)?>,
        sources: <?=CUtil::PhpToJSObject($arResult['SOURCES'])?>,
        iblockId: <?=$arResult['IBLOCK_ID']?>,
    });

</script>
