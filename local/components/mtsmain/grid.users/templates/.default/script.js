BX.ready(function(){

    new GridSetComponent();
    $.each( BX.message('sources'), function( key, value ) {
        options.push({value:value.ID,label:value.NAME});
    });
});
var options = [];

function GridSetComponent(parameters){
    this.addNewRow = $('[data-add-new-row]');
    this.editRecord = $('[data-edit-rec]');
    this.cancelRecord = $('[data-cancel-rec]');
    this.deleteRecord = $('[data-delete-rec]');
    //
    this.sourceSingleEditBtn = $('[data-source-single-edit]');
    this.sourceSingleCloseBtn = $('[data-close-source]');
    this.sourceSingleDeleteBtn = $('[data-source-single-delete]');
    //
    this.dragDropUser = $('[data-handle]');
    this.userSingleDeleteBtn = $('[data-user-single-delete]');
    this.checkActiveUser = $('[data-active-user]');
    this.userSingleCloseBtn = $('[data-close-user]');
    this.userSingleEditBtn = $('[data-user-single-edit]');
    this.loaderFon = $('[data-loader]');
    this.init();
}
GridSetComponent.prototype = {
    removeNewRec: function(event)
    {
        var target = BX.getEventTarget(event);
        $(target).closest('.added_new-row').remove();
    }
};
GridSetComponent.prototype.init = function ()
{
    this.addNewRow.click(BX.proxy(this.addNewRowClick, this));
    this.editRecord.click(BX.proxy(this.editRecordClick, this));
    this.deleteRecord.click(BX.proxy(this.deleteRecordClick, this));
    this.cancelRecord.click(BX.proxy(this.cancelRecordClick, this));
    this.sourceSingleEditBtn.click(BX.proxy(this.sourceSingleEditBtnClick, this));
    this.sourceSingleDeleteBtn.click(BX.proxy(this.sourceSingleDeleteBtnClick, this));
    this.userSingleDeleteBtn.click(BX.proxy(this.userSingleDeleteBtnClick, this));
    this.userSingleCloseBtn.click(BX.proxy(this.userSingleCloseBtnClick, this));
    this.userSingleEditBtn.click(BX.proxy(this.userSingleEditBtnClick, this));
    this.sourceSingleCloseBtn.click(BX.proxy(this.sourceSingleCloseBtnClick, this));
    this.checkActiveUser.change(BX.proxy(this.checkActiveUserChange, this));
    this.dragDropUser.hide();
    this.cancelRecord.hide();
    this.sourceSingleCloseBtn.hide();
    this.userSingleCloseBtn.hide();
    this.sourceSingleEditBtn.hide();
    this.sourceSingleDeleteBtn.hide();
    this.userSingleEditBtn.hide();
    this.userSingleDeleteBtn.hide();
    this.checkActiveUser.attr('disabled','disabled');
    $('.sortable-ul').sortable({handle: '.handle'});
    this.loaderFon.hide();
};

//создание новой записи
GridSetComponent.prototype.addNewRowClick = function () {
    if($(document).find('.added_new-row').length > 0){
        return;
    }
    var newDiv = BX.create('TR', {
        props: {className: 'added_new-row'},
        children: [
            BX.create('TD', {
                props: {className: 'table-row block-section'},
                children: [
                    BX.create('DIV', {
                        props: {className: 'record-text add_new_source'},
                        children: [
                            BX.create('INPUT', {
                                attrs:{
                                    'type':'hidden',
                                    'value':'0'
                                }
                            }),
                            BX.create('DIV', {
                                className: 'create_new_source',
                                attrs:{
                                    'data-create-new-source':'',
                                    'id':'create_new_source'
                                }
                            }),
                        ],
                    }),
                ],
            }),
            BX.create('TD', {
                props: {className: 'table-row block-user'},
                children: [
                    BX.create('INPUT', {
                        attrs:{
                            'type':'hidden',
                            'value':'0',

                        }
                    }),
                    BX.create('DIV', {
                        className: "create_new_user",
                        attrs:{
                            'data-create-new-user':'',
                            'id': this.newUserId,
                            'style': 'display:none'
                        }
                    }),
                ],
            }),
            BX.create('td', {
                props: {className: 'table-row block-edit', 'data-block-edit':''},
                attrs:{
                    'data-block-edit':" "
                },
                children: [
                    BX.create('BUTTON', {
                        text: 'Отмена',
                        props: {className: 'ui-btn ui-btn-danger ui-btn-icon-remove mb-2 delete-rec-new'},
                        attrs:{
                            'data-delete-new-rec':" "
                        },
                        events: {
                            click: BX.proxy(this.removeNewRec, this)
                        },
                    }),
                ]
            })
        ]
    });
    var newSelect = new BX.Ui.Select({
        options,
        isSearchable: true,
        containerClassname: 'create_new_source',
    });
    newSelect.subscribe('update', (e) => {
        var elem = $("#main_table").find('.added_new-row');
        var param = this;
        //создание нового раздела
        var request = BX.ajax.runComponentAction('mtsmain:grid.users', 'addSectionIblock', {
            mode:'class',
            data: {
                iblockId: BX.message('iblockId'),
                sourceId: e.data,
                params: JSON.stringify(BX.message('params')),
            }
        });
        request.then(function(response){
            if(response.data.result == 'access_denied'){
                BX.UI.Dialogs.MessageBox.alert("Ошибка!", "Нет прав на добавление источника сделок!");
            }
            if(response.data.result == "section_add") {
                elem.attr('data-id', response.data['idSection']);
                var createdRowTr1 = BX.create('TD', {
                    props: {className: 'table-row block-section'},
                    children:[
                        BX.create('DIV', {
                            props: {className: 'record-text'},
                            'attrs':{
                                'data-id': response.data['idSource'],
                                'data-status': response.data['statusId'],
                                'id':"source_item_"+response.data['statusId']+"_"+response.data['idSection']
                            },
                            children:[
                                BX.create('SPAN', {
                                    props: {className: 'source-name'},
                                    html: response.data['nameSource']
                                }),
                                BX.create('I', {
                                    props: {className: 'fa fa-pencil-square-o icon-add edit-source'},
                                    'attrs':{
                                        'data-source-single-edit':" "
                                    },
                                    events: {
                                        click: BX.proxy(param.sourceSingleEditBtnClick, this)
                                    },
                                }),
                                BX.create('I', {
                                    props: {className: 'fa fa-minus-circle icon-remove remove-source'},
                                    'attrs':{
                                        'data-source-single-delete':" "
                                    },
                                    events: {
                                        click: BX.proxy(param.sourceSingleDeleteBtnClick, this)
                                    },
                                }),
                                BX.create('DIV', {
                                    props: {className: 'edit-select-source'},
                                    'attrs':{
                                        'data-edit-source': " ",
                                        'name': "edit_source"
                                    },
                                    children:[
                                        BX.create('INPUT', {
                                            'attrs': {
                                                'type': "hidden",
                                                'value': "0"
                                            },
                                        }),
                                        BX.create('DIV', {
                                            'attrs': {
                                                'id': "edit_single_source_"+response.data['idSection']+"_"+response.data['idSource'],
                                                'data-edit-single-source': " "
                                            },
                                        }),

                                        BX.create('I', {
                                            props: {className: 'fa fa-close close-source'},
                                            'attrs':{
                                                'data-close-source':" ",
                                            },
                                            events: {
                                                click: BX.proxy(param.sourceSingleCloseBtnClick, this)
                                            },
                                        }),
                                    ]
                                }),
                            ]
                        }),
                        BX.create('DIV', {
                            props: {className: 'record-text add_new_source'},
                            'attrs':{
                                'data-add-new-source': " ",
                            },
                            children:[
                                BX.create('INPUT', {
                                    'attrs': {
                                        'type': "hidden",
                                        'value': "0"
                                    },
                                }),
                                BX.create('DIV', {
                                    'attrs': {
                                        'id': 'add_new_source_'+response.data['idSection'],
                                        'name':"add_new_source"
                                    },
                                }),
                            ]
                        })
                    ]
                });
                var createdRowTr2 = BX.create('TD', {
                    props: {className: 'table-row block-user'},
                    children:[
                        BX.create('UL', {
                            props: {className: 'sortable-ul'},
                        }),
                        BX.create('DIV', {
                            props: {className: 'record-text add_new_user'},
                            'attrs':{
                                'data-add-new-user-block': " "
                            },
                            children:[
                                BX.create('input', {
                                    'attrs':{
                                        'type': "hidden",
                                        'value': "0"
                                    },
                                }),
                                BX.create('DIV', {
                                    'attrs':{
                                        'id': "add_new_user_"+response.data['idSection'],
                                        'data-add-new-user': " "
                                    },
                                }),
                            ]
                        }),
                    ],
                });
                var createdRowTr3 = BX.create('TD', {
                    props: {className: 'table-row block-edit'},
                    'attrs':{
                        'data-block-edit': " "
                    },
                    children:[
                        BX.create('DIV', {
                            props: {className: 'd-grid'},
                            children: [
                                BX.create('BUTTON', {
                                    text: 'Редактировать',
                                    props: {className: 'ui-btn ui-btn-primary-dark ui-btn-icon-edit edit-rec-section mb-2'},
                                    attrs:{
                                        'data-edit-rec':" ",
                                        'id':'main_menu_edit_btn_'+response.data['idSection'],
                                        'data-id':response.data['idSection']
                                    },
                                    events: {
                                        click: BX.proxy(param.editRecordClick, this)
                                    },
                                }),
                                BX.create('BUTTON', {
                                    text: 'Закрыть редактирование',
                                    props: {className: 'ui-btn ui-btn-success ui-btn-icon-back save-rec-section mb-2'},
                                    attrs:{
                                        'data-cancel-rec':" ",
                                        'id':'main_menu_close_btn_'+response.data['idSection'],
                                        'data-id':response.data['idSection'],
                                    },
                                    events: {
                                        click: BX.proxy(param.cancelRecordClick, this)
                                    },
                                }),
                                BX.create('BUTTON', {
                                    text: 'Удалить',
                                    props: {className: 'ui-btn ui-btn-danger-light ui-btn-icon-remove delete-rec-section mb-2'},
                                    attrs:{
                                        'data-delete-rec':" ",
                                        'id':'main_menu_delete_btn_'+response.data['idSection'],
                                        'data-id':response.data['idSection']
                                    },
                                    events: {
                                        click: BX.proxy(param.deleteRecordClick, this)
                                    },
                                }),
                            ]
                        }),
                    ]
                });
            }
            elem.empty();
            elem.append(createdRowTr1);
            elem.append(createdRowTr2);
            elem.append(createdRowTr3);
            elem.find('[data-close-source]').hide();
            elem.find('[data-cancel-rec]').hide();
            elem.removeClass("added_new-row");
            elem.find('[data-edit-rec]').trigger('click');
        });
    });

    $("#main_table").prepend(newDiv);
    newSelect.renderTo(document.getElementById('create_new_source'));
};

//режим редактирования
GridSetComponent.prototype.editRecordClick = function (event) {
    var id = $(event.currentTarget).data('id');
    var elem = $(event.currentTarget).closest('tr');
    var tagSelector = new BX.UI.EntitySelector.TagSelector({
        id: "add_new_source_"+id,
        multiple: 'N',
        addButtonCaption: 'Добавить сотрудника',
        dialogOptions: {
            context: 'MY_MODULE_CONTEXT',
            entities: [
                {
                    id: 'user', // пользователи
                },
                {
                    id: 'meta-user',
                    options: {
                        'all-users': true // Все сотрудники
                    }
                },
            ],
        },
        events: {
            onBeforeTagAdd: function(event) {
                const { tag } = event.getData();

                //добавление пользователя
                var request = BX.ajax.runComponentAction('mtsmain:grid.users', 'addElementIblock', {
                    mode:'class',
                    data: {
                        iblockId:BX.message('iblockId'),
                        sectionId:elem.data('id'),
                        userId:tag.id,
                        params:JSON.stringify(BX.message('params')),
                    }
                });
                request.then(function(response){
                    if(response.data.result == 'access_denied'){
                        BX.UI.Dialogs.MessageBox.alert("Ошибка!", 'Нет прав на добавление элементов!');
                    }
                    if(response.data.result == "element_add") {
                        var newUser = BX.create('LI', {
                            props: {className: 'record-text'},
                            'attrs':{
                                'data-id':response.data['idElem'],
                                'data-employee':response.data['idEmployee']
                            },
                            children:[
                                BX.create('I', {
                                    props: {className: 'fa fa-arrows handle'},
                                    'attrs':{
                                        'data-handle':" ",
                                    }
                                }),
                                BX.create('INPUT', {
                                    props: {className: 'checkbox-value'},
                                    'attrs':{
                                        'type':'checkbox',
                                        'data-active-user':" ",
                                    },
                                    events: {
                                        change: BX.proxy(GridSetComponent.prototype.checkActiveUserChange, this)
                                    },
                                }),
                                BX.create('SPAN', {
                                    props: {className: 'user-name-text'},
                                    html: response.data['userName'],
                                }),
                                BX.create('I', {
                                    props: {className: 'fa fa-pencil-square-o icon-add edit-user'},
                                    'attrs':{
                                        'data-user-single-edit':" "
                                    },
                                    events: {
                                        click: BX.proxy(GridSetComponent.prototype.userSingleEditBtnClick, this)
                                    },
                                }),
                                BX.create('I', {
                                    props: {className: 'fa fa-minus-circle icon-remove remove-user'},
                                    'attrs':{
                                        'data-user-single-delete':" "
                                    },
                                    events: {
                                        click: BX.proxy(GridSetComponent.prototype.userSingleDeleteBtnClick, this)
                                    },
                                }),
                                BX.create('DIV', {
                                    props: {className: 'edit-select-user'},
                                    children:[
                                        BX.create('INPUT', {
                                            'attrs':{
                                                'type':"hidden",
                                                'value':"0",
                                            },
                                        }),
                                        BX.create('DIV', {
                                            props: {className: 'edit_single_user_'+id+'_'+response.data['idElem']},
                                            'attrs':{
                                                'id':'edit_single_user_'+id+'_'+response.data['idElem'],
                                                'data-edit-single-user':" ",
                                            },
                                        }),
                                        BX.create('I', {
                                            props: {className: 'fa fa-close close-user'},
                                            'attrs':{
                                                'data-close-user':" ",
                                            },
                                            events: {
                                                click: BX.proxy(GridSetComponent.prototype.userSingleCloseBtnClick, this)
                                            },
                                        }),
                                    ]
                                }),
                            ]
                        });
                        elem.find('.sortable-ul').append(newUser);
                        elem.find('[data-close-user]').hide();
                        $(document).find('.sortable-ul').sortable({
                            handle: '.handle'
                        });
                        console.log("#add_new_user_"+elem.data('id'));
                        $(document).find("#add_new_user_"+elem.data('id')).find('ui-tag-selector-tag-remove').trigger('mouseup');
                        $(document).find("#add_new_user_"+elem.data('id')).find('ui-tag-selector-tag-remove').trigger('click');
                        elem.find('.sortable-ul').trigger('sortstop');
                    }
                });
            },
        }
    });

    const select = new BX.Ui.Select({
        options,
        isSearchable: true,
        containerClassname: 'select_source',
    });

    //добавление источника
    select.subscribe('update', (event) => {
        var param = this;
        var request = BX.ajax.runComponentAction('mtsmain:grid.users', 'addSourceSection', {
            mode:'class',
            data: {
                iblockId:BX.message('iblockId'),
                sourceId:event.data,
                params:JSON.stringify(BX.message('params')),
                sectionId:id,
            }
        });
        request.then(function(response){
            if(response.data.result == 'access_denied'){
                BX.UI.Dialogs.MessageBox.alert("Ошибка!", "Нет прав на добавление источника сделок!");
            }
            if(response.data.result == "source_add") {
                var newSource = BX.create('DIV', {
                    props: {className: 'record-text'},
                    'attrs': {
                        'data-id': response.data['idSource'],
                        'data-status': response.data['statusId'],
                    },
                    children:[
                        BX.create('SPAN', {
                            props: {className: 'source-name'},
                            html:response.data['nameSource']
                        }),
                        BX.create('I', {
                            props: {className: 'fa fa-pencil-square-o icon-add edit-source'},
                            'attrs':{
                                'data-source-single-edit':" "
                            },
                            events: {
                                click: BX.proxy(this.sourceSingleEditBtnClick, this)
                            },
                        }),
                        BX.create('I', {
                            props: {className: 'fa fa-minus-circle icon-remove remove-source'},
                            'attrs':{
                                'data-source-single-delete':" "
                            },
                            events: {
                                click: BX.proxy(this.removeNewRec, this)
                            },
                        }),
                        BX.create('DIV', {
                            props: {className: 'edit-source'},
                        })
                    ]
                });
                $(document).find("#add_new_source_"+id).closest('[data-add-new-source]').before(newSource);
            }
        });
    });
    select.renderTo(document.getElementById("add_new_source_"+id));
    tagSelector.renderTo(document.getElementById('add_new_user_'+id));
    //
    $(event.currentTarget).hide();
    $(event.currentTarget).closest('div').find('[data-cancel-rec]').show();
    elem.find('[data-handle]').show();
    elem.find('[data-source-single-edit]').show();
    elem.find('[data-source-single-delete]').show();
    elem.find('[data-user-single-edit]').show();
    elem.find('[data-user-single-delete]').show();
    elem.find('[data-active-user]').removeAttr('disabled');
};

//отмена режима редактирования
GridSetComponent.prototype.cancelRecordClick = function (event) {
    var elem = $(event.currentTarget).closest('tr');
    $(event.currentTarget).hide();
    elem.find('.source-name').show();
    elem.find('.user-name-text').show();
    elem.find('[data-edit-rec]').show();
    elem.find('[data-add-new-user]').empty();
    elem.find('[name="add_new_source"]').empty();
    elem.find('[data-edit-single-source').empty();
    elem.find('[data-edit-single-user').empty();
    elem.find('[data-close-source]').hide();
    elem.find('[data-close-user]').hide();
    elem.find('[data-user-single-edit]').hide();
    elem.find('[data-user-single-delete]').hide();
    elem.find('[data-source-single-delete]').hide();
    elem.find('[data-source-single-edit]').hide();
    elem.find('[data-handle]').hide();
    elem.find('[data-active-user]').show();
    elem.find('[data-active-user]').attr('disabled','disabled');
}

//удаление одного источника
GridSetComponent.prototype.sourceSingleDeleteBtnClick = function (event) {
    var sectionId = $(event.currentTarget).closest('tr').data('id');
    var sourceCode = $(event.currentTarget).closest('div').data('status');
    var elem = $(event.currentTarget).closest('tr');
    var param = this;
    var messageBox = new BX.UI.Dialogs.MessageBox(
        {
            message: "Вы действительно хотите удалить выбранный источник сделок?",
            title: "Подтверждение удаления",
            buttons: BX.UI.Dialogs.MessageBoxButtons.OK_CANCEL,
            okCaption: "Да",
            onOk: function()
            {
                var request = BX.ajax.runComponentAction('mtsmain:grid.users', 'removeSourceSection', {
                    mode:'class',
                    data: {
                        iblockId: BX.message('iblockId'),
                        sectionId: sectionId,
                        sourceCode: sourceCode,
                        params: JSON.stringify(BX.message('params')),
                    }
                });
                request.then(function(response){
                    if(response.data.result == 'access_denied'){
                        BX.UI.Dialogs.MessageBox.alert("Ошибка!", "Нет прав на удаление источника сделок!");
                    }
                    if(response.data.result == "section_delete") {
                        elem.remove();
                    }
                    if(response.data.result == "source_delete") {
                        elem.find("#source_item_"+sourceCode+"_"+sectionId).remove();
                    }
                    messageBox.close();
                });

            },
        }
    );
    messageBox.show();
}

//удаление записи - источник+сделка
GridSetComponent.prototype.deleteRecordClick = function (event) {
    var id = $(event.currentTarget).data('id');
    var elem = $(event.currentTarget);
    var messageBox = new BX.UI.Dialogs.MessageBox(
        {
            message: "Вы действительно хотите удалить выбранный источник сделок и всех привязанных пользователей?",
            title: "Подтверждение удаления",
            buttons: BX.UI.Dialogs.MessageBoxButtons.OK_CANCEL,
            okCaption: "Да",
            onOk: function()
            {
                var request = BX.ajax.runComponentAction('mtsmain:grid.users', 'deleteSectionIblock', {
                    mode:'class',
                    data: {
                        iblockId: BX.message('iblockId'),
                        idSection: id,
                        params: JSON.stringify(BX.message('params')),
                    }
                });
                request.then(function(response){
                    if(response.data.result == 'access_denied'){
                        BX.UI.Dialogs.MessageBox.alert("Ошибка!", "Нет прав на удаление источника сделок!");
                    }
                    if(response.data.result == "section_deleted") {
                        elem.closest('tr').remove();
                    }
                });
                messageBox.close();

            },
        }
    );
    messageBox.show();
}

//замена источника один на другой
GridSetComponent.prototype.sourceSingleEditBtnClick = function (event){
    var id = $(event.currentTarget).closest('tr').data('id');
    var elemRec = $(event.currentTarget).closest('div');
    elemRec.find('[data-source-single-edit]').hide();
    elemRec.find('[data-source-single-delete]').hide();
    elemRec.find('.source-name').hide();
    elemRec.find('[data-close-source]').show();
    var selectCurrent = new BX.Ui.Select({
        value:elemRec.data('id').toString(),
        options,
        isSearchable: true,
        containerClassname: 'select_source',
    });
    selectCurrent.subscribe('update', (event) => {
        var request = BX.ajax.runComponentAction('mtsmain:grid.users', 'replaceSourceSection', {
            mode:'class',
            data: {
                iblockId: BX.message('iblockId'),
                sectionId: id,
                oldSourceId: elemRec.attr('data-id'),
                newSourceId: event.data,
                params: JSON.stringify(BX.message('params')),
            }
        });
        request.then(function(response){
            if(response.data.result == 'access_denied'){
                BX.UI.Dialogs.MessageBox.alert("Ошибка!", "Нет прав на изменение источника сделки!");
            }
            if(response.data.result == "source_replace") {
                elemRec.find('#edit_single_source_'+id+'_'+elemRec.data('id')).attr('id','edit_single_source_'+id+'_'+response.data['idSource']);
                elemRec.attr('data-id',response.data['idSource']);
                elemRec.attr('data-status',response.data['statusId']);
                elemRec.find('.source-name').html(response.data['nameSource']).show();
                elemRec.find('[data-edit-single-source]').empty();
                elemRec.find('[data-source-single-edit]').show();
                elemRec.find('[data-source-single-delete]').show();
                elemRec.find('[data-close-source]').hide();
            }
        });
    });
    selectCurrent.renderTo(document.getElementById('edit_single_source_'+id+'_'+elemRec.data('id')));
}

//удаление пользователя
GridSetComponent.prototype.userSingleDeleteBtnClick = function (event){
    var elem = $(event.currentTarget).closest('li');
    var messageBox = new BX.UI.Dialogs.MessageBox(
        {
            message: "Вы действительно хотите удалить выбранного пользователя?",
            title: "Подтверждение удаления",
            buttons: BX.UI.Dialogs.MessageBoxButtons.OK_CANCEL,
            okCaption: "Да",
            onOk: function()
            {
                var request = BX.ajax.runComponentAction('mtsmain:grid.users', 'deleteElementIblock', {
                    mode:'class',
                    data: {
                        iblockId: BX.message('iblockId'),
                        idElem: elem.data('id'),
                    }
                });
                request.then(function(response){
                    if(response.data.result == 'access_denied'){
                        BX.UI.Dialogs.MessageBox.alert("Ошибка!", 'Нет прав на удаление элементов!');
                    }
                    if(response.data.result == "element_deleted") {
                        elem.remove();
                    }
                });
                messageBox.close();
            },
        }
    );
    messageBox.show();
}

//активен/неактивен пользователь
GridSetComponent.prototype.checkActiveUserChange = function (event){
    var request = BX.ajax.runComponentAction('mtsmain:grid.users', 'setUserActive', {
        mode:'class',
        data: {
            iblockId: BX.message('iblockId'),
            userId: $(event.currentTarget).closest('li').attr('data-id'),
            activeUser: $(event.currentTarget).is(':checked') ? 'Y' : 'N',
            params: JSON.stringify(BX.message('params')),
        }
    });
    request.then(function(response){
        if(response.data.result == 'access_denied'){
            BX.UI.Dialogs.MessageBox.alert("Ошибка!", 'Нет прав на изменение элементов!');
        }
    });
}

//отмена редактирования пользователя
GridSetComponent.prototype.userSingleCloseBtnClick = function (event){
    var elemRec = $(event.currentTarget).closest('li');
    elemRec.find('[data-edit-single-user]').empty();
    elemRec.find('.user-name-text').show();
    elemRec.find('[data-user-single-edit]').show();
    elemRec.find('[data-user-single-delete]').show();
    elemRec.find('[data-handle]').show();
    elemRec.find('[data-active-user]').show();
    elemRec.find('[data-close-user]').hide();
}

//отмена редактирования источника
GridSetComponent.prototype.sourceSingleCloseBtnClick = function (event){
    var elemRec = $(event.currentTarget).closest('.record-text');
    elemRec.find('[data-edit-single-source]').empty();
    elemRec.find('[data-source-single-edit]').show();
    elemRec.find('[data-source-single-delete]').show();
    elemRec.find('.source-name').show();
    elemRec.find('[data-close-source]').hide();
}

//замена одного пользователя на другого
GridSetComponent.prototype.userSingleEditBtnClick = function (event){
    var id = $(event.currentTarget).closest('tr').data('id');
    var elemRec = $(event.currentTarget).closest('li');
    elemRec.find('[data-user-single-edit]').hide();
    elemRec.find('[data-user-single-delete]').hide();
    elemRec.find('[data-active-user]').hide();
    elemRec.find('[data-handle]').hide();
    //elemRec.find('.user-name-text').hide();
    elemRec.find('[data-close-user]').show();
    var tagSelector = new BX.UI.EntitySelector.TagSelector({
        id: 'edit_single_user_'+id+"_"+elemRec.data('id'),
        multiple: false,
        addButtonCaption: 'Изменить сотрудника',
        dialogOptions: {
            context: 'MY_MODULE_CONTEXT',
            entities: [
                {
                    id: 'user'
                },
            ],
        },
        events: {
            onBeforeTagAdd: function(event) {
                const { tag } = event.getData();
                //замена пользователля
                var request = BX.ajax.runComponentAction('mtsmain:grid.users', 'changeUserElement', {
                    mode:'class',
                    data: {
                        iblockId: BX.message('iblockId'),
                        elementId: elemRec.data('id'),
                        newUser: tag.id,
                        params: JSON.stringify(BX.message('params')),
                    }
                });
                request.then(function(response){
                    if(response.data.result == 'access_denied'){
                        BX.UI.Dialogs.MessageBox.alert("Ошибка!", 'Нет прав на изменение элементов!');
                    }
                    if(response.data.result == "user_change") {
                        elemRec.attr('data-employee',response.data['userId']);
                        elemRec.find('.user-name-text').html(response.data['userName']);
                        elemRec.find('[data-edit-single-user]').empty();
                        elemRec.find('.user-name-text').show();
                        elemRec.find('[data-user-single-edit]').show();
                        elemRec.find('[data-user-single-delete]').show();
                        elemRec.find('[data-handle]').show();
                        elemRec.find('[data-active-user]').show();
                        elemRec.find('[data-close-user]').hide();
                    }
                });
            },
        }
    });
    tagSelector.renderTo(document.getElementById('edit_single_user_'+id+"_"+elemRec.data('id')));
}

$(document).on('sortstop','.sortable-ul', function(event, ui) {
    var sortUser = {};
    var index = 10;
    $(this).find('li').each(function(){
        sortUser[$(this).attr('data-id')] = index;
        index+=10;
    });
    var request = BX.ajax.runComponentAction('mtsmain:grid.users', 'changeUserSort', {
        mode:'class',
        data: {
            iblockId:BX.message('iblockId'),
            sortArray:JSON.stringify(sortUser),
            params:JSON.stringify(BX.message('params')),
        }
    });
    request.then(function(response){
        if(response.data.result == 'access_denied'){
            BX.UI.Dialogs.MessageBox.alert("Ошибка!", 'Нет прав на изменение элементов!');
        }
    });
});