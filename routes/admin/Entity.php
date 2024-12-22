<?php

$router->group(['prefix' => 'v1.0'], function() use ($router) {
    $router->group(['middleware' => 'auth'], function () use ($router) {
        $router->group(['namespace' => 'Backend\Entity'], function() use ($router) {

            //For Entity details
            $router->get('/entity', ['uses' => 'EntityController@index']);
            $router->post('/entity/store', ['uses' => 'EntityController@store', 'middleware' => ['checkprivilege:entity,add_edit']]);
            $router->get('/entity/show/{id}', ['uses' => 'EntityController@show', 'middleware' => ['checkprivilege:entity,view']]);
            $router->put('/entity/update/{id}', ['uses' => 'EntityController@update', 'middleware' => ['checkprivilege:entity,add_edit']]);
            $router->get('/entity/checkduplication', ['uses' => 'EntityController@checkDuplication', 'middleware' => ['checkprivilege:entity,view']]);
            $router->get('/entity/history/{id}', ['uses' => 'EntityController@history', 'middleware' => ['checkprivilege:entity,view']]);
            $router->get('/entity/checklist/downlaod', ['uses' => 'EntityController@checklistDownload', 'middleware' => ['checkprivilege:entitychecklist,view']]);

            //For Entity checklist
            $router->get('/entity/checklist/{id}', ['uses' => 'EntityChecklistController@index']);
            $router->get('/entity/checklist/history/{id}', ['uses' => 'EntityChecklistController@history', 'middleware' => ['checkprivilege:entitychecklist,view']]);
            $router->post('/entity/checklist/add/{id}', ['uses' => 'EntityChecklistController@store', 'middleware' => ['checkprivilege:entitychecklist,add_edit']]);
            $router->put('/entity/checklist/update/{id}', ['uses' => 'EntityChecklistController@update', 'middleware' => ['checkprivilege:entitychecklist,add_edit']]);
            $router->get('/entity/checklist/history/{id}', ['uses' => 'EntityChecklistController@history', 'middleware' => ['checkprivilege:entitychecklist,view']]);
            
            
            //For Entity checklist question 
            $router->get('/entity/checklistquestion/{id}', ['uses' => 'EntityChecklistQuestionController@index']);
            $router->post('/entity/checklistquestion/store/{id}', ['uses' => 'EntityChecklistQuestionController@store', 'middleware' => ['checkprivilege:entitychecklistquestion,add_edit']]);
            $router->put('/entity/checklistquestion/update/{id}', ['uses' => 'EntityChecklistQuestionController@update', 'middleware' => ['checkprivilege:entitychecklistquestion,add_edit']]);
            $router->get('/entity/checklistquestion/view/{id}', ['uses' => 'EntityChecklistQuestionController@show', 'middleware' => ['checkprivilege:entitychecklistquestion,view']]);
            $router->get('/entity/checklistquestion/getGroup/{id}', ['uses' => 'EntityChecklistQuestionController@getGroup', 'middleware' => ['checkprivilege:additionalentitychecklistquestion,view']]);
            $router->post('/entity/checklistquestion/additionalQuestion/{id}', ['uses' => 'EntityChecklistQuestionController@additionalQuestion', 'middleware' => ['checkprivilege:additionalentitychecklistquestion,add_edit']]);
            $router->get('/entity/checklistquestion/history/{id}', ['uses' => 'EntityChecklistQuestionController@history', 'middleware' => ['checkprivilege:entitychecklistquestion,view']]);

            //For Entity software
            $router->get('/entity/software', ['uses' => 'EntitySoftwareController@index', 'middleware' => ['checkprivilege:entitysoftware,view']]);
            $router->post('/entity/software/add', ['uses' => 'EntitySoftwareController@store', 'middleware' => ['checkprivilege:entitysoftware,add_edit']]);
            $router->put('/entity/software/update/{id}', ['uses' => 'EntitySoftwareController@update', 'middleware' => ['checkprivilege:entitysoftware,add_edit']]);
            $router->get('/entity/software/view/{id}', ['uses' => 'EntitySoftwareController@show', 'middleware' => ['checkprivilege:entitysoftware,view']]);
            $router->get('/entity/software/fetch', ['uses' => 'EntitySoftwareController@software', 'middleware' => ['checkprivilege:entitysoftware,view']]);
            $router->delete('/entity/software/delete/{id}', ['uses' => 'EntitySoftwareController@destroy', 'middleware' => ['checkprivilege:entitysoftware,delete']]);

            //For Entity documents
            $router->get('/entity/document/{id}', ['uses' => 'DocumentController@index', 'middleware' => ['checkprivilege:entitydocument,view']]);
            $router->post('/entity/document/store/{id}', ['uses' => 'DocumentController@store', 'middleware' => ['checkprivilege:entitydocument,add_edit']]);
            $router->get('/entity/document/view/{id}', ['uses' => 'DocumentController@show', 'middleware' => ['checkprivilege:entitydocument,view']]);
            $router->get('/entity/document/download/{id}', ['uses' => 'DocumentController@download', 'middleware' => ['checkprivilege:entitydocument,download']]);
            $router->delete('/entity/document/delete/{id}', ['uses' => 'DocumentController@destroy', 'middleware' => ['checkprivilege:entitydocument,delete']]);
            $router->get('/entity/document/downloadzip/{id}', ['uses' => 'DocumentController@downloadzip', 'middleware' => ['checkprivilege:entitydocument,download']]);
            $router->get('/entity/document/getmodule/{id}', ['uses' => 'DocumentController@getmodule', 'middleware' => ['checkprivilege:entitydocument,view']]);

            //For Entity special notes
            $router->get('/entity/specialnotes/{id}', ['uses' => 'SpecailnotesController@index']);
            $router->post('/entity/specialnotes/store', ['uses' => 'SpecailnotesController@store', 'middleware' => ['checkprivilege:entityspecialnotes,add_edit']]);
            $router->put('/entity/specialnotes/{id}', ['uses' => 'SpecailnotesController@update', 'middleware' => ['checkprivilege:entityspecialnotes,add_edit']]);
            $router->get('/entity/specialnotes/view/{id}', ['uses' => 'SpecailnotesController@show', 'middleware' => ['checkprivilege:entityspecialnotes,view']]);
            $router->delete('/entity/specialnotes/{id}', ['uses' => 'SpecailnotesController@destroy', 'middleware' => ['checkprivilege:entityspecialnotes,delete']]);

            //Fro dynamic group
            $router->get('/entity/dynamicgroup', ['uses' => 'DynamicgroupController@index']);
            $router->post('/entity/dynamicgroup', ['uses' => 'DynamicgroupController@store', 'middleware' => ['checkprivilege:dynamicgroup,add_edit']]);
            $router->put('/entity/dynamicgroup/{id}', ['uses' => 'DynamicgroupController@update', 'middleware' => ['checkprivilege:dynamicgroup,add_edit']]);
            $router->get('/entity/dynamicgroup/{id}', ['uses' => 'DynamicgroupController@show', 'middleware' => ['checkprivilege:dynamicgroup,view']]);
            $router->delete('/entity/dynamicgroup/{id}', ['uses' => 'DynamicgroupController@destroy', 'middleware' => ['checkprivilege:dynamicgroup,delete']]);


            // for dynamic field
            $router->get('/entity/dynamicfield', ['uses' => 'DynamicfieldController@index']);
            $router->get('/entity/dynamicfield/export', ['uses' => 'DynamicfieldController@index', 'middleware' => ['checkprivilege:dynamicfield,export']]);
            $router->post('/entity/dynamicfield', ['uses' => 'DynamicfieldController@store', 'middleware' => ['checkprivilege:dynamicfield,add_edit']]);
            $router->get('/entity/dynamicfield/listing/{id}', ['uses' => 'DynamicfieldController@getGroupWiseDynamicField']);

            $router->put('/entity/dynamicfield/{id}', ['uses' => 'DynamicfieldController@update', 'middleware' => ['checkprivilege:dynamicfield,add_edit']]);
            $router->get('/entity/dynamicfield/{id}', ['uses' => 'DynamicfieldController@show', 'middleware' => ['checkprivilege:dynamicfield,view']]);
            $router->delete('/entity/dynamicfield/{id}', ['uses' => 'DynamicfieldController@destroy', 'middleware' => ['checkprivilege:dynamicfield,delete']]);

            // for Bank
            $router->get('/entity/bank', ['uses' => 'BankController@index']);
            $router->get('/entity/bank/export', ['uses' => 'BankController@index', 'middleware' => ['checkprivilege:bank,export']]);
            $router->post('/entity/bank', ['uses' => 'BankController@store', 'middleware' => ['checkprivilege:bank,add_edit']]);
            $router->put('/entity/bank/{id}', ['uses' => 'BankController@update', 'middleware' => ['checkprivilege:bank,add_edit']]);
            $router->get('/entity/bank/{id}', ['uses' => 'BankController@show', 'middleware' => ['checkprivilege:bank,view']]);           


            // for Account
            $router->get('/entity/account', ['uses' => 'AccountTypeController@index']);
            $router->get('/entity/account/export', ['uses' => 'AccountTypeController@index', 'middleware' => ['checkprivilege:account,export']]);
            $router->post('/entity/account', ['uses' => 'AccountTypeController@store', 'middleware' => ['checkprivilege:account,add_edit']]);
            $router->put('/entity/account/{id}', ['uses' => 'AccountTypeController@update', 'middleware' => ['checkprivilege:account,add_edit']]);
            $router->get('/entity/account/{id}', ['uses' => 'AccountTypeController@show', 'middleware' => ['checkprivilege:account,view']]);

            // for Bank Information
            $router->get('/entity/bankinformation/listing/{id}', ['uses' => 'BankInformationController@index', 'middleware' => ['checkprivilege:bankinformation,view']]);
            $router->post('/entity/bankinformation/{id}', ['uses' => 'BankInformationController@store', 'middleware' => ['checkprivilege:bankinformation,add_edit']]);
            $router->put('/entity/bankinformation/{id}', ['uses' => 'BankInformationController@update', 'middleware' => ['checkprivilege:bankinformation,add_edit']]);
            $router->get('/entity/bankinformation/show/{id}', ['uses' => 'BankInformationController@show', 'middleware' => ['checkprivilege:bankinformation,view']]);
            $router->get('/entity/bankinformation/history/{id}', ['uses' => 'BankInformationController@history', 'middleware' => ['checkprivilege:bankinformation,view']]);

             // for Trigger Information
            $router->post('/entity/triggerinformation/{id}', ['uses' => 'InformationTriggerController@store', 'middleware' => ['checkprivilege:bankinformation,add_edit']]);
            $router->put('/entity/triggerinformation/{id}', ['uses' => 'InformationTriggerController@update', 'middleware' => ['checkprivilege:bankinformation,add_edit']]);
            $router->get('/entity/triggerinformation/show/{id}', ['uses' => 'InformationTriggerController@show', 'middleware' => ['checkprivilege:bankinformation,view']]);
            $router->get('/entity/triggerinformation/stop/{id}', ['uses' => 'InformationTriggerController@stopTrigger', 'middleware' => ['checkprivilege:bankinformation,view']]);
            
            
            // for call management
            $router->get('/entity/callmanagement', ['uses' => 'CallManagementController@index', 'middleware' => ['checkprivilege:callmanagement,view']]);
            $router->get('/entity/callmanagement/export', ['uses' => 'CallManagementController@index', 'middleware' => ['checkprivilege:callmanagement,export']]);
            $router->post('/entity/callmanagement', ['uses' => 'CallManagementController@store', 'middleware' => ['checkprivilege:callmanagement,add_edit']]);
            $router->put('/entity/callmanagement/{id}', ['uses' => 'CallManagementController@update', 'middleware' => ['checkprivilege:callmanagement,add_edit']]);
            $router->get('/entity/callmanagement/{id}', ['uses' => 'CallManagementController@show', 'middleware' => ['checkprivilege:callmanagement,view']]);
            $router->delete('/entity/callmanagement/{id}', ['uses' => 'CallManagementController@destroy', 'middleware' => ['checkprivilege:callmanagement,delete']]);

            // for crm notes
            $router->get('/entity/crmnotes', ['uses' => 'CrmnotesController@index', 'middleware' => ['checkprivilege:crmnotes,view']]);
            $router->get('/entity/crmnotes/export', ['uses' => 'CrmnotesController@index', 'middleware' => ['checkprivilege:crmnotes,export']]);
            $router->post('/entity/crmnotes', ['uses' => 'CrmnotesController@store', 'middleware' => ['checkprivilege:crmnotes,add_edit']]);
            $router->put('/entity/crmnotes/{id}', ['uses' => 'CrmnotesController@update', 'middleware' => ['checkprivilege:crmnotes,add_edit']]);
            $router->get('/entity/crmnotes/{id}', ['uses' => 'CrmnotesController@show', 'middleware' => ['checkprivilege:crmnotes,view']]);
            $router->delete('/entity/crmnotes/{id}', ['uses' => 'CrmnotesController@destroy', 'middleware' => ['checkprivilege:crmnotes,delete']]);


            // for client turnover
            $router->get('/entity/clientturnover', ['uses' => 'ClientTurnoverController@index']);
            $router->get('/entity/clientturnover/export', ['uses' => 'ClientTurnoverController@index']);
            $router->post('/entity/clientturnover', ['uses' => 'ClientTurnoverController@store']);
            $router->put('/entity/clientturnover/{id}', ['uses' => 'ClientTurnoverController@update']);
            $router->get('/entity/clientturnover/{id}', ['uses' => 'ClientTurnoverController@show']);
            $router->delete('/entity/clientturnover/{id}', ['uses' => 'ClientTurnoverController@destroy']);
            $router->get('/entity/yearlist', ['uses' => 'ClientTurnoverController@yearList']);


            // for employee info
            $router->get('/entity/employeeinfo', ['uses' => 'EmployeeInfoController@index', 'middleware' => ['checkprivilege:employeeinfo,view']]);
            $router->get('/entity/employeeinfo/export', ['uses' => 'EmployeeInfoController@index', 'middleware' => ['checkprivilege:employeeinfo,export']]);
            $router->post('/entity/employeeinfo', ['uses' => 'EmployeeInfoController@store', 'middleware' => ['checkprivilege:employeeinfo,add_edit']]);
            $router->put('/entity/employeeinfo/{id}', ['uses' => 'EmployeeInfoController@update', 'middleware' => ['checkprivilege:employeeinfo,add_edit']]);
            $router->get('/entity/employeeinfo/{id}', ['uses' => 'EmployeeInfoController@show', 'middleware' => ['checkprivilege:employeeinfo,view']]);
            $router->delete('/entity/employeeinfo/{id}', ['uses' => 'EmployeeInfoController@destroy', 'middleware' => ['checkprivilege:employeeinfo,delete']]);

            //for entity allocation

            $router->get('/entity/allocation/{id}', ['uses' => 'EntityAllocationController@index', 'middleware' => ['checkprivilege:allocation,view']]);
            $router->post('/entity/allocation/{id}', ['uses' => 'EntityAllocationController@store', 'middleware' => ['checkprivilege:allocation,add_edit']]);
            $router->get('/entity/allocationhistory/{id}', ['uses' => 'EntityAllocationController@history', 'middleware' => ['checkprivilege:allocation,view']]);
            $router->get('/entity/allocationotherhistory/{id}', ['uses' => 'EntityAllocationController@otherhistory', 'middleware' => ['checkprivilege:allocation,view']]);

            // for Other Account
            $router->get('/entity/otheraccount', ['uses' => 'OtherAccountController@index']);
            $router->post('/entity/otheraccount', ['uses' => 'OtherAccountController@store', 'middleware' => ['checkprivilege:bankinformation,add_edit']]);
            $router->put('/entity/otheraccount/{id}', ['uses' => 'OtherAccountController@update', 'middleware' => ['checkprivilege:bankinformation,add_edit']]);
            $router->get('/entity/otheraccount/{id}', ['uses' => 'OtherAccountController@show', 'middleware' => ['checkprivilege:bankinformation,view']]); 
            
            
            $router->get('/entity/befreecomment', ['uses' => 'BefreeCommentController@index']);
            $router->post('/entity/befreecomment', ['uses' => 'BefreeCommentController@store', 'middleware' => ['checkprivilege:befreecomment,add_edit']]);
            $router->put('/entity/befreecomment/{id}', ['uses' => 'BefreeCommentController@update', 'middleware' => ['checkprivilege:befreecomment,add_edit']]);
            $router->get('/entity/befreecomment/{id}', ['uses' => 'BefreeCommentController@show', 'middleware' => ['checkprivilege:befreecomment,view']]); 

            // for Other Information
            $router->get('/entity/otherinfo/listing/{id}', ['uses' => 'OtherInformationController@index']);
            $router->post('/entity/otherinfo/{id}', ['uses' => 'OtherInformationController@store', 'middleware' => ['checkprivilege:bankinformation,add_edit']]);
            $router->put('/entity/otherinfo/{id}', ['uses' => 'OtherInformationController@update', 'middleware' => ['checkprivilege:bankinformation,add_edit']]);
            $router->get('/entity/otherinfo/{id}', ['uses' => 'OtherInformationController@show', 'middleware' => ['checkprivilege:bankinformation,view']]);
            $router->get('/entity/otherinfo/history/{id}', ['uses' => 'OtherInformationController@history', 'middleware' => ['checkprivilege:bankinformation,view']]);       

            $router->get('/entity/information', ['uses' => 'EntityController@infoDashboard', 'middleware' => ['checkprivilege:information,view']]);        

            // for Client user query
            $router->get('/entity/query/index/{id}', ['uses' => 'ClientUserQueryController@index']);
            $router->post('/entity/query/store', ['uses' => 'ClientUserQueryController@store']);
            $router->put('/entity/query/update/{id}', ['uses' => 'ClientUserQueryController@update']);
            $router->get('/entity/query/show/{id}', ['uses' => 'ClientUserQueryController@show']);


            //for  client user document
            $router->get('/entity/client/document/index/{id}', ['uses' => 'ClientUserDocumentController@index']);
            $router->post('/entity/client/document/store', ['uses' => 'ClientUserDocumentController@store']);
            $router->put('/entity/client/document/update/{id}', ['uses' => 'ClientUserDocumentController@update']);
            $router->get('/entity/client/document/show/{id}', ['uses' => 'ClientUserDocumentController@show']);
            $router->delete('/entity/client/document/delete/{id}', ['uses' => 'ClientUserDocumentController@destroy']);
            
            $router->get('/entity/subclient/{id}', ['uses' => 'EntityController@getSubclient']);
            
           
        });
    });
});

