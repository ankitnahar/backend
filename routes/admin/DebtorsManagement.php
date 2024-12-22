<?php

/*
  |--------------------------------------------------------------------------
  | Invoice routes for admin
  |--------------------------------------------------------------------------
  |
  |
 */
$router->group(['prefix' => 'v1.0'], function() use ($router) {
    $router->group(['middleware' => 'auth'], function () use ($router) {
        $router->group(['namespace' => 'Backend\DebtorsManagament'], function() use ($router) {
            $router->get('/dm/', ['uses' => 'DebtorsController@index','middleware' => ['checkprivilege:dm,view']]);
            $router->get('/dm/export', ['uses' => 'DebtorsController@index','middleware' => ['checkprivilege:dm,export']]);
           
            $router->post('/dm/', ['uses' => 'DebtorsController@store','middleware' => ['checkprivilege:dm,add_edit']]);
            $router->put('/dm/{id}', ['uses' => 'DebtorsController@update','middleware' => ['checkprivilege:dm,add_edit']]);
            $router->get('/dm/{id}', ['uses' => 'DebtorsController@getTemplate','middleware' => ['checkprivilege:dm,view']]);
            
            $router->get('/dm/template/list', ['uses' => 'DebtorsController@templateList']);
            $router->get('/dm/mailData/data', ['uses' => 'DebtorsController@templateData']);
            $router->get('/dm/maillist/list', ['uses' => 'DebtorsController@maillist','middleware' => ['checkprivilege:dm,add_edit']]);
           
            //For Contact remark listing 
            $router->get('/dm/comment/{id}', ['uses' => 'DebtorsCommentController@index', 'middleware' => ['checkprivilege:dm,view']]);
            $router->post('/dm/comment/{id}', ['uses' => 'DebtorsCommentController@store', 'middleware' => ['checkprivilege:dm,add_edit']]);
            
            $router->get('/dm/updatedebtors/list', ['uses' => 'DebtorsController@updateDebtors']);
            
        });
    });
});

