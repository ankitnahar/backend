<?php
$router->group(['prefix' => 'v1.0'], function() use ($router) {
    $router->group(['middleware' => 'auth'], function () use ($router) {
        $router->group(['namespace' => 'Backend\Worksheet'], function() use ($router) {
            
            // Master activity      
            $router->get('/worksheet/master', ['uses' => 'MasterActivityController@index']);
            $router->get('/worksheet/master/export', ['uses' => 'MasterActivityController@index']);
            $router->post('/worksheet/master', ['uses' => 'MasterActivityController@store']);
            $router->put('/worksheet/master/{id}', ['uses' => 'MasterActivityController@update']);
            $router->get('/worksheet/master/{id}', ['uses' => 'MasterActivityController@show']);
           
            // Task
            $router->get('/worksheet/task', ['uses' => 'TaskActivityController@index']);
            $router->get('/worksheet/task/export', ['uses' => 'TaskActivityController@index','middleware' => ['checkprivilege:task,export']]);
            $router->post('/worksheet/task', ['uses' => 'TaskActivityController@store','middleware' => ['checkprivilege:task,add_edit']]);
            $router->put('/worksheet/task/{id}', ['uses' => 'TaskActivityController@update','middleware' => ['checkprivilege:task,add_edit']]);
            $router->get('/worksheet/task/{id}', ['uses' => 'TaskActivityController@show','middleware' => ['checkprivilege:task,view']]);
            
             // Subactivity
            $router->get('/worksheet/subactivity', ['uses' => 'SubActivityController@index']);
            $router->get('/worksheet/subactivity/export', ['uses' => 'SubActivityController@index']);
            $router->post('/worksheet/subactivity', ['uses' => 'SubActivityController@store']);
            $router->put('/worksheet/subactivity/{id}', ['uses' => 'SubActivityController@update']);
            $router->get('/worksheet/subactivity/{id}', ['uses' => 'SubActivityController@show']);
            
            // Manage training
            $router->get('/worksheet/training', ['uses' => 'TrainingController@index']);
            $router->get('/worksheet/training/export', ['uses' => 'TrainingController@index']);
            $router->post('/worksheet/training/store', ['uses' => 'TrainingController@store']);
            $router->get('/worksheet/training/view/{id}', ['uses' => 'TrainingController@show']);
            $router->put('/worksheet/training/update/{id}', ['uses' => 'TrainingController@update']);
            $router->delete('/worksheet/training/{id}', ['uses' => 'TrainingController@destroy']);

            
            // Manage master checklist
            $router->get('/worksheet/masterchecklist', ['uses' => 'MasterChecklistController@index']);
            $router->get('/worksheet/masterchecklist/export', ['uses' => 'MasterChecklistController@index']);
            $router->get('/worksheet/masterchecklist/getmasteractivity', ['uses' => 'MasterChecklistController@getMasterActivity']);
            $router->post('/worksheet/masterchecklist/store', ['uses' => 'MasterChecklistController@store']);
            $router->get('/worksheet/masterchecklist/view/{id}', ['uses' => 'MasterChecklistController@show']);
            $router->put('/worksheet/masterchecklist/update/{id}', ['uses' => 'MasterChecklistController@update']);
            $router->delete('/worksheet/masterchecklist/{id}', ['uses' => 'MasterChecklistController@destroy']);
            
            // Manage master checklist question
            $router->get('/worksheet/masterchecklistquestion', ['uses' => 'MasterChecklistQuestionController@index']);
            $router->get('/worksheet/masterchecklistquestion/export', ['uses' => 'MasterChecklistQuestionController@index']);
            $router->get('/worksheet/masterchecklistquestion/getmasteractivity', ['uses' => 'MasterChecklistQuestionController@getMasterActivity']);
            $router->post('/worksheet/masterchecklistquestion/store', ['uses' => 'MasterChecklistQuestionController@store']);
            $router->get('/worksheet/masterchecklistquestion/view/{id}', ['uses' => 'MasterChecklistQuestionController@show','middleware' => ['checkprivilege:masterchecklistquestion,view']]);
            $router->put('/worksheet/masterchecklistquestion/update/{id}', ['uses' => 'MasterChecklistQuestionController@update']);
            $router->put('/worksheet/masterchecklistquestion/updatestatus/{id}', ['uses' => 'MasterChecklistQuestionController@updatestatus']);
            
            // Manage checklist group
            $router->get('/worksheet/checklistgroup', ['uses' => 'ChecklistGroupController@index','middleware' => ['checkprivilege:masterchecklistgroup,view']]);
            $router->get('/worksheet/checklistgroup/export', ['uses' => 'ChecklistGroupController@index']);
            $router->get('/worksheet/checklistgroup/getsubactivity', ['uses' => 'ChecklistGroupController@getSubactivity']);
            $router->post('/worksheet/checklistgroup/store', ['uses' => 'ChecklistGroupController@store']);
            $router->get('/worksheet/checklistgroup/view/{id}', ['uses' => 'ChecklistGroupController@show','middleware' => ['checkprivilege:masterchecklistgroup,view']]);
            $router->put('/worksheet/checklistgroup/update/{id}', ['uses' => 'ChecklistGroupController@update']);
            $router->put('/worksheet/checklistgroup/updatestatus/{id}', ['uses' => 'ChecklistGroupController@updatestatus']);
            
            // Subclient
            $router->get('/worksheet/subclient', ['uses' => 'SubClientController@index','middleware' => ['checkprivilege:subclient,view']]);
            $router->get('/worksheet/subclient/export', ['uses' => 'SubClientController@index','middleware' => ['checkprivilege:subclient,export']]);
            $router->post('/worksheet/subclient/store', ['uses' => 'SubClientController@store','middleware' => ['checkprivilege:subclient,add_edit']]);
            $router->get('/worksheet/subclient/view/{id}', ['uses' => 'SubClientController@show','middleware' => ['checkprivilege:subclient,view']]);
            $router->put('/worksheet/subclient/update/{id}', ['uses' => 'SubClientController@update','middleware' => ['checkprivilege:subclient,add_edit']]);
            $router->put('/worksheet/subclient/updatestatus/{id}', ['uses' => 'SubClientController@updatestatus','middleware' => ['checkprivilege:subclient,add_edit']]);
            $router->get('/worksheet/subclient/dropdown', ['uses' => 'SubClientController@getEntityList']);

            // Worksheet
            $router->get('/worksheet/myworksheet', ['uses' => 'WorksheetController@index']);
            $router->get('/worksheet/completedworksheet', ['uses' => 'WorksheetController@index']);
            $router->get('/worksheet/incompletedworksheet', ['uses' => 'WorksheetController@index']);
            $router->get('/worksheet/befreeworksheet', ['uses' => 'WorksheetController@index']);
            $router->get('/worksheet/status', ['uses' => 'WorksheetController@status']);
            $router->post('/worksheet/store', ['uses' => 'WorksheetController@store']);            
            $router->put('/worksheet/update/{id}', ['uses' => 'WorksheetController@update']);
            $router->get('/worksheet/view/{id}', ['uses' => 'WorksheetController@show']);
            $router->delete('/worksheet/delete/multiple/{id}', ['uses' => 'WorksheetController@destroy']);
            $router->put('/worksheet/additionalassignee/{id}', ['uses' => 'WorksheetController@additionalAssign']);
            $router->get('/worksheet/assignreviewer', ['uses' => 'WorksheetController@worksheetReviewerAssignee']);
            $router->post('/worksheet/multipleupdate', ['uses' => 'WorksheetController@mupltipleUpdate']);
            $router->get('/worksheet/additionalassignee', ['uses' => 'WorksheetController@worksheetAdditionalAssignee']);
            $router->get('/worksheet/getpeerreviewerassignee', ['uses' => 'WorksheetController@worksheetPeerAssignee']);
             $router->get('/worksheet/count', ['uses' => 'WorksheetController@worksheetCount']);
            //$router->get('/worksheet/statuscounter', ['uses' => 'WorksheetController@worksheetStatusCounter']);
            // Review worksheet
            $router->get('/worksheet/reviewknockback', ['uses' => 'ReviewKockbackWorksheetController@index']);
            
            $router->get('/worksheet/getreviewerunit', ['uses' => 'ReviewKockbackWorksheetController@reviewerUnit']);

            // Peer review worksheet
            $router->get('/worksheet/peerreview', ['uses' => 'PeerReviewWorksheetController@index']);
            
            // Worksheet log
            $router->get('/worksheet/worksheetlog/{id}', ['uses' => 'WorksheetLogController@index']);
            
            // Task checklist
            $router->get('/worksheet/taskchecklist/{id}', ['uses' => 'TaskChecklistController@index']);
            $router->post('/worksheet/taskchecklist/store', ['uses' => 'TaskChecklistController@store']);
            $router->get('/worksheet/taskchecklist/emailpreview/{id}', ['uses' => 'TaskChecklistController@checklistEmailpreview']);
            $router->post('/worksheet/taskchecklist/storechecklistemail/{id}', ['uses' => 'TaskChecklistController@checklistEmail']);
            
            // Worksheet notes
            $router->get('/worksheet/taskchecklistnote/{id}', ['uses' => 'TaskChecklistController@fetchnote']);
            $router->post('/worksheet/taskchecklistnote/store', ['uses' => 'TaskChecklistController@storenote']);
            
            // Worksheet documents
            $router->get('/worksheet/uploaddocument/{id}', ['uses' => 'CommanController@index']);
            $router->post('/worksheet/uploaddocument/store', ['uses' => 'CommanController@uploadDocument']);
            $router->post('/worksheet/uploaddocument/drive', ['uses' => 'CommanController@uploadDocumentOnDrive']);
            $router->get('/worksheet/downloaddocument/{id}', ['uses' => 'CommanController@downloadDocument']);
            $router->get('/worksheet/removedocument/{id}', ['uses' => 'CommanController@removeDocument']);
            $router->put('/worksheet/updatedocument/{id}', ['uses' => 'CommanController@updateDocument']);
            
            $router->get('/worksheet/pullworksheet/{id}', ['uses' => 'CommanController@worksheetGet']);
            $router->get('/worksheet/repeattask/{id}', ['uses' => 'WorksheetController@worksheetRepeattask']);
            $router->post('/worksheet/addrepeattask', ['uses' => 'WorksheetController@worksheetRepeatTaskAdd']);
            
            $router->post('/worksheet/taskchecklist/email/preview', ['uses' => 'TaskChecklistController@emailPreview']);
            
            //worksheet schedule
            $router->get('/worksheet/sechdule/list/{id}', ['uses' => 'WorksheetScheduleController@index']);
            $router->post('/worksheet/sechdule/add/{id}', ['uses' => 'WorksheetScheduleController@store']);
            $router->delete('/worksheet/sechdule/delete/{id}', ['uses' => 'WorksheetScheduleController@destroy']);
        });
    });
});
