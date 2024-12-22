<?php
/*
  |--------------------------------------------------------------------------
  | Contact routes for admin
  |--------------------------------------------------------------------------
  |
 */
$router->group(['prefix' => 'v1.0'], function() use ($router) {
    $router->group(['middleware' => 'auth'], function () use ($router) {
        $router->group(['namespace' => 'Backend\Query'], function() use ($router) {
            // Manage no job

            // Query Related
            $router->post('/query', ['uses' => 'QueryController@store', 'middleware' => ['checkprivilege:query,add_edit']]);
            $router->get('/query/{id}', ['uses' => 'QueryControllerData@index', 'middleware' => ['checkprivilege:query,view']]);
            $router->get('/query/export/{id}', ['uses' => 'QueryControllerData@index', 'middleware' => ['checkprivilege:query,export']]);           
            $router->put('/query/update/{id}', ['uses' => 'QueryController@update', 'middleware' => ['checkprivilege:query,add_edit']]);
            $router->post('/query/add/{id}', ['uses' => 'QueryController@store', 'middleware' => ['checkprivilege:query,add_edit']]);
            $router->get('/query/showbank/{id}', ['uses' => 'QueryController@showBankAccount', 'middleware' => ['checkprivilege:query,add_edit']]);
            $router->get('/query/showdetails/{id}', ['uses' => 'QueryController@showQueryDetails', 'middleware' => ['checkprivilege:query,add_edit']]);
            $router->delete('/query/{id}', ['uses' => 'QueryController@removeQuery', 'middleware' => ['checkprivilege:query,delete']]);
            $router->delete('/query/queryDetail/{id}', ['uses' => 'QueryController@destroy', 'middleware' => ['checkprivilege:query,delete']]);
            
            $router->post('/query/addextraquery/{id}', ['uses' => 'QueryController@addQueryLine', 'middleware' => ['checkprivilege:query,add_edit']]);
                       
            $router->get('/query/bankinactive/{id}', ['uses' => 'QueryController@bankActiveInactive', 'middleware' => ['checkprivilege:query,add_edit']]);
            $router->get('/query/show/{id}', ['uses' => 'QueryController@show', 'middleware' => ['checkprivilege:query,view']]);
            $router->get('/query/log/{id}', ['uses' => 'QueryController@Log']);
            $router->get('/query/reminderlog/{id}', ['uses' => 'QueryController@reminderLog']);
            $router->get('/query/stage/list', ['uses' => 'QueryController@queryStage']);
            $router->post('/query/movetotl/{id}', ['uses' => 'QueryController@moveToTlTam', 'middleware' => ['checkprivilege:query,add_edit']]);
            $router->post('/query/movetotam/{id}', ['uses' => 'QueryController@moveToTlTam', 'middleware' => ['checkprivilege:query,add_edit']]);
            $router->post('/query/sendback/{id}', ['uses' => 'QueryController@sendBack', 'middleware' => ['checkprivilege:query,add_edit']]);
            $router->get('/query/assigneedropdown/{id}', ['uses' => 'QueryController@infoAssigneeDropdown']);
            $router->post('/query/additioalassignee', ['uses' => 'QueryController@infoAssignee']);
            $router->post('/query/snoozeinfo/{id}', ['uses' => 'QueryController@snoozeInfo', 'middleware' => ['checkprivilege:query,snooze_info']]);
            $router->put('/query/partialCreate/{id}', ['uses' => 'QueryController@partialCreate', 'middleware' => ['checkprivilege:query,partial_create']]);

            
            // Query Question
            $router->post('/query/question/store', ['uses' => 'QueryQuestionController@store', 'middleware' => ['checkprivilege:query_question,add_edit']]);
            $router->get('/query/question/list', ['uses' => 'QueryQuestionController@index']);
            $router->put('/query/question/update/{id}', ['uses' => 'QueryQuestionController@update', 'middleware' => ['checkprivilege:query_question,delete']]);
           
            
            // Documents Related
            $router->post('/query/uploaddocument/store/{id}', ['uses' => 'DocumentController@store', 'middleware' => ['checkprivilege:query,add_edit']]);
            $router->get('/query/downloaddocument/{id}', ['uses' => 'DocumentController@downloadDocument']);
            $router->delete('/query/documentDelete/{id}', ['uses' => 'DocumentController@destroy', 'middleware' => ['checkprivilege:query,delete']]);
            
            // Additional Query Related
            $router->post('/query/additionalQuery/store/{id}', ['uses' => 'QueryAddtionalController@store','middleware' => ['checkprivilege:query,add_edit']]);
            $router->get('/query/additionalQuery/{id}', ['uses' => 'QueryAddtionalController@index','middleware' => ['checkprivilege:query,view']]);
            $router->delete('/query/additionalQueryDelete/{id}', ['uses' => 'QueryAddtionalController@destroy', 'middleware' => ['checkprivilege:query,delete']]);
            
             $router->post('/query/additionalQuery/upload/{id}', ['uses' => 'QueryAdditionalDocumentController@store', 'middleware' => ['checkprivilege:query,add_edit']]);
            $router->get('/query/additionalQuery/download/{id}', ['uses' => 'QueryAdditionalDocumentController@downloadDocument']);
            $router->delete('/query/additionalQuery/delete/{id}', ['uses' => 'QueryAdditionalDocumentController@destroy', 'middleware' => ['checkprivilege:query,delete']]);
            
            //Send Query Detail
            $router->get('/query/sendInfo/{id}', ['uses' => 'QuerySendController@index', 'middleware' => ['checkprivilege:query,add_edit']]);
            $router->post('/query/sendInfo/store/{id}', ['uses' => 'QuerySendController@store', 'middleware' => ['checkprivilege:query,add_edit']]);
            $router->get('/query/remindercallList/list/{id}', ['uses' => 'QuerySendController@callList', 'middleware' => ['checkprivilege:query,call_list']]);
        });
    });
});
