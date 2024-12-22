<?php
/*
  |--------------------------------------------------------------------------
  | Contact routes for admin
  |--------------------------------------------------------------------------
  |
 */
$router->group(['prefix' => 'v1.0'], function() use ($router) {
    $router->group(['middleware' => 'auth'], function () use ($router) {
        $router->group(['namespace' => 'Backend\Information'], function() use ($router) {
            // Manage no job

            // Information Related
            $router->post('/information', ['uses' => 'InformationController@store', 'middleware' => ['checkprivilege:information,add_edit']]);
            $router->get('/information/{id}', ['uses' => 'InformationController@index', 'middleware' => ['checkprivilege:information,view']]);
            $router->get('/information/export/{id}', ['uses' => 'InformationController@index', 'middleware' => ['checkprivilege:information,export']]);           
            $router->put('/information/update/{id}', ['uses' => 'InformationController@update', 'middleware' => ['checkprivilege:information,add_edit']]);
            $router->get('/information/show/{id}', ['uses' => 'InformationController@show', 'middleware' => ['checkprivilege:information,view']]);
            $router->get('/information/log/{id}', ['uses' => 'InformationController@Log']);
            $router->get('/information/reminderlog/{id}', ['uses' => 'InformationController@reminderLog']);
            $router->get('/information/stage/list', ['uses' => 'InformationController@informationStage']);
            $router->post('/information/movetotl/{id}', ['uses' => 'InformationController@moveToTlTam', 'middleware' => ['checkprivilege:information,add_edit']]);
            $router->post('/information/movetotam/{id}', ['uses' => 'InformationController@moveToTlTam', 'middleware' => ['checkprivilege:information,add_edit']]);
            $router->post('/information/sendback/{id}', ['uses' => 'InformationController@sendBack', 'middleware' => ['checkprivilege:information,add_edit']]);
            $router->get('/information/assigneedropdown/{id}', ['uses' => 'InformationController@infoAssigneeDropdown']);
            $router->post('/information/additioalassignee', ['uses' => 'InformationController@infoAssignee']);
            $router->post('/information/snoozeinfo/{id}', ['uses' => 'InformationController@snoozeInfo', 'middleware' => ['checkprivilege:information,snooze_info']]);
            $router->put('/information/partialCreate/{id}', ['uses' => 'InformationController@partialCreate', 'middleware' => ['checkprivilege:information,partial_create']]);
            $router->delete('/information/{id}', ['uses' => 'InformationController@destroy', 'middleware' => ['checkprivilege:information,delete']]);
           

            // Documents Related
            $router->post('/information/uploaddocument/store/{id}', ['uses' => 'DocumentController@store', 'middleware' => ['checkprivilege:information,add_edit']]);
            $router->get('/information/downloaddocument/{id}', ['uses' => 'DocumentController@downloadDocument']);
            $router->delete('/information/documentDelete/{id}', ['uses' => 'DocumentController@destroy', 'middleware' => ['checkprivilege:information,delete']]);
            
            // Additional Information Related
            $router->post('/information/additionalInfo/store/{id}', ['uses' => 'InformationAddtionalController@store','middleware' => ['checkprivilege:information,add_edit']]);
            $router->get('/information/additionalInfo/{id}', ['uses' => 'InformationAddtionalController@index','middleware' => ['checkprivilege:information,view']]);
            $router->delete('/information/additionalInfoDelete/{id}', ['uses' => 'InformationAddtionalController@destroy', 'middleware' => ['checkprivilege:information,delete']]);
            
            // Additional Information Related
            $router->post('/information/additionalInfo/upload/{id}', ['uses' => 'InformationAdditionalDocumentController@store','middleware' => ['checkprivilege:information,add_edit']]);
            $router->get('/information/additionalInfo/download/{id}', ['uses' => 'InformationAdditionalDocumentController@downloadDocument','middleware' => ['checkprivilege:information,view']]);
            $router->delete('/information/additionalInfo/delete/{id}', ['uses' => 'InformationAdditionalDocumentController@destroy', 'middleware' => ['checkprivilege:information,delete']]);
           
            //Send Information Detail
            $router->get('/information/sendInfo/{id}', ['uses' => 'InformationSendController@index', 'middleware' => ['checkprivilege:information,add_edit']]);
            $router->post('/information/sendInfo/store/{id}', ['uses' => 'InformationSendController@store', 'middleware' => ['checkprivilege:information,add_edit']]);
            $router->get('/information/remindercallList/list/{id}', ['uses' => 'InformationSendController@callList', 'middleware' => ['checkprivilege:information,call_list']]);
        });
    });
});
