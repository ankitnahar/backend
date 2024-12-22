<?php

/*
  |--------------------------------------------------------------------------
  | Web Routes
  |--------------------------------------------------------------------------
  |
  | Here is where you can register web routes for your application. These
  | routes are loaded by the RouteServiceProvider within a group which
  | contains the "web" middleware group. Now create something great!
  |
 */
$router->group(['prefix' => 'v1.0'], function() use ($router) {
    $router->group(['middleware' => 'auth'], function () use ($router) {
        $router->group(['namespace' => 'Backend\GoogleDrive'], function() use ($router) {

            $router->get('/googledrive/list', ['uses' => 'GoogleDriveFolderController@listDirectory', 'middleware' => ['checkprivilege:client_folder,view']]);
            $router->post('/googledrive/addfolder', ['uses' => 'GoogleDriveFolderController@createCustomeFolder','middleware' => ['checkprivilege:client_folder,view']]);
            $router->get('/googledrive/deletefolder', ['uses' => 'GoogleDriveFolderController@deleteFolder','middleware' => ['checkprivilege:client_folder,view']]);
            $router->get('/googledrive/deleteMasterfolder', ['uses' => 'GoogleDriveFolderController@functionDeleteMasterFolder','middleware' => ['checkprivilege:client_folder,view']]);
            $router->post('/googledrive/renamefolder', ['uses' => 'GoogleDriveFolderController@renameFolder','middleware' => ['checkprivilege:client_folder,view']]);
            $router->post('/googledrive/clientfolder', ['uses' => 'GoogleDriveFolderController@populateFolder','middleware' => ['checkprivilege:client_folder,view']]);
            $router->get('/googledrive/folderlist', ['uses' => 'GoogleDriveFolderController@folderList','middleware' => ['checkprivilege:client_folder,view']]);
            $router->post('/googledrive/backlog', ['uses' => 'GoogleDriveFolderController@backlogFolder','middleware' => ['checkprivilege:client_folder,view']]);
            
            $router->post('/googledrive/uploadfiledrive', ['uses' => 'GoogleDriveFileController@uploadFileFromDrive','middleware' => ['checkprivilege:client_folder,view']]);
            $router->post('/googledrive/uploadfile', ['uses' => 'GoogleDriveFileController@uploadFile','middleware' => ['checkprivilege:client_folder,view']]);
            $router->post('/googledrive/completefile', ['uses' => 'GoogleDriveFileController@completeFile','middleware' => ['checkprivilege:client_folder,view']]);
            $router->get('/googledrive/deletefile', ['uses' => 'GoogleDriveFileController@deleteFile','middleware' => ['checkprivilege:client_folder,view']]);
            $router->get('/googledrive/sharefile', ['uses' => 'GoogleDriveFileController@shareFile','middleware' => ['checkprivilege:client_folder,view']]);
            $router->post('/googledrive/movefile', ['uses' => 'GoogleDriveFileController@moveFile','middleware' => ['checkprivilege:client_folder,view']]);
            $router->post('/googledrive/copyfile', ['uses' => 'GoogleDriveFileController@copyFile','middleware' => ['checkprivilege:client_folder,view']]);
            $router->get('/googledrive/downlaodfile', ['uses' => 'GoogleDriveFileController@downloadFile','middleware' => ['checkprivilege:client_folder,view']]);
            $router->post('/googledrive/renamefile', ['uses' => 'GoogleDriveFileController@renameFile','middleware' => ['checkprivilege:client_folder,view']]);
            $router->get('/googledrive/revokefile', ['uses' => 'GoogleDriveFileController@revokePermission','middleware' => ['checkprivilege:client_folder,view']]);
            $router->post('/googledrive/searchfile', ['uses' => 'GoogleDriveFileController@searchFile','middleware' => ['checkprivilege:client_folder,view']]);
            $router->post('/googledrive/exportfile', ['uses' => 'GoogleDriveFileController@exportFile','middleware' => ['checkprivilege:client_folder,view']]);
            $router->post('/googledrive/createfile', ['uses' => 'GoogleDriveFileController@createFile','middleware' => ['checkprivilege:client_folder,view']]);
            $router->get('/googledrive/movetotrash', ['uses' => 'GoogleDriveFileController@moveAndrestoreTrash','middleware' => ['checkprivilege:client_folder,view']]);
            $router->post('/googledrive/convertfile', ['uses' => 'GoogleDriveFileController@convertFile','middleware' => ['checkprivilege:client_folder,view']]);
            $router->post('/googledrive/movetoxero', ['uses' => 'GoogleDriveFileController@moveToXero','middleware' => ['checkprivilege:client_folder,view']]);
            $router->post('/googledrive/masterfolderename', ['uses' => 'GoogleDriveFolderController@RenameMasterFolder','middleware' => ['checkprivilege:client_folder,view']]);
            
            $router->get('/googledrive/audio/list/{id}', ['uses' => 'AudioVideoController@index', 'middleware' => ['checkprivilege:client_folder,view']]);
            $router->post('/googledrive/audio/add/{id}', ['uses' => 'AudioVideoController@store','middleware' => ['checkprivilege:client_folder,view']]);
            $router->put('/googledrive/audio/update/{id}', ['uses' => 'AudioVideoController@update','middleware' => ['checkprivilege:client_folder,view']]);
            $router->delete('/googledrive/audio/delete/{id}', ['uses' => 'AudioVideoController@destroy','middleware' => ['checkprivilege:client_folder,view']]);
            
        });
    });
});
?>
