<?php
/*
  |--------------------------------------------------------------------------
  | Contact routes for admin
  |--------------------------------------------------------------------------
  |
 */
$router->group(['prefix' => 'v1.0'], function() use ($router) {
    $router->group(['middleware' => 'auth'], function () use ($router) {
        $router->group(['namespace' => 'Backend\Contact'], function() use ($router) {
            // Manage no job
            $router->post('/contact', ['uses' => 'ContactController@store', 'middleware' => ['checkprivilege:contact,add_edit']]);
            $router->get('/contact', ['uses' => 'ContactController@index', 'middleware' => ['checkprivilege:contact,view']]);
            $router->get('/contact/export', ['uses' => 'ContactController@index', 'middleware' => ['checkprivilege:contact,export']]);           
            $router->put('/contact/{id}', ['uses' => 'ContactController@update', 'middleware' => ['checkprivilege:contact,add_edit']]);
            $router->get('/contact/{id}', ['uses' => 'ContactController@show', 'middleware' => ['checkprivilege:contact,view']]);
            $router->post('/contact/archive/{id}', ['uses' => 'ContactController@archive', 'middleware' => ['checkprivilege:contact,view']]);
            $router->get('/contact/history/{id}', ['uses' => 'ContactController@history', 'middleware' => ['checkprivilege:contact,view']]);
            
            $router->get('/contact/newsletter/list', ['uses' => 'ContactNewsletterController@index', 'middleware' => ['checkprivilege:contactnewsletter,view']]);
            $router->get('/contact/newsletter/export', ['uses' => 'ContactNewsletterController@index', 'middleware' => ['checkprivilege:contactnewsletter,export']]);
            $router->post('/contact/newsletter/movetoarchive/{id}', ['uses' => 'ContactNewsletterController@moveToArchive', 'middleware' => ['checkprivilege:contactnewsletter,add_edit']]);
           
            //$router->get('/contact/client/list', ['uses' => 'ContactController@clientList', 'middleware' => ['checkprivilege:contact,add_edit']]);
            
            $router->get('/contact/client/list', ['uses' => 'ClientUserController@index', 'middleware' => ['checkprivilege:contact,add_edit']]);
            $router->post('/contact/client/add', ['uses' => 'ClientUserController@store', 'middleware' => ['checkprivilege:contact,add_edit']]);
            $router->get('/contact/client/export', ['uses' => 'ClientUserController@index', 'middleware' => ['checkprivilege:contact,add_edit']]);
            
            $router->put('/contact/client/update/{id}', ['uses' => 'ClientUserController@update', 'middleware' => ['checkprivilege:contact,add_edit']]);
            
            $router->get('/contact/relatedEntity/{id}', ['uses' => 'ContactController@getRelatedEntityList','middleware' => ['checkprivilege:contact,add_edit']]);
            $router->post('/contact/copycontact/{id}', ['uses' => 'ContactController@copyRelatedEntity','middleware' => ['checkprivilege:contact,add_edit']]);
            
            //For Entity address
            $router->get('/entity/address', ['uses' => 'AddressController@index', 'middleware' => ['checkprivilege:entityaddress,view']]);
            $router->post('/entity/address/add', ['uses' => 'AddressController@store', 'middleware' => ['checkprivilege:entityaddress,add_edit']]);
            $router->put('/entity/address/update/{id}', ['uses' => 'AddressController@update', 'middleware' => ['checkprivilege:entityaddress,add_edit']]);
            $router->get('/entity/address/view/{id}', ['uses' => 'AddressController@show', 'middleware' => ['checkprivilege:entityaddress,view']]);
            
            //For Contact remark listing 
            $router->get('/entity/contactremark/{id}', ['uses' => 'ContactRemarkController@index']);
            $router->post('/entity/contactremark/add', ['uses' => 'ContactRemarkController@store']);
            $router->put('/entity/contactremark/update/{id}', ['uses' => 'ContactRemarkController@update']);
            $router->get('/entity/contactremark/view/{id}', ['uses' => 'ContactRemarkController@show']);
        });
    });
});
