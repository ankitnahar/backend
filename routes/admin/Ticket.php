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
        $router->group(['namespace' => 'Backend\Ticket'], function() use ($router) {
            $router->get('/ticket/', ['uses' => 'TicketController@index','middleware' => ['checkprivilege:ticket,view']]);
            $router->get('/ticket/export', ['uses' => 'TicketController@index','middleware' => ['checkprivilege:ticket,export']]);
            $router->post('/ticket', ['uses' => 'TicketController@store','middleware' => ['checkprivilege:ticket,add_edit']]);
            $router->put('/ticket/{id}', ['uses' => 'TicketController@update','middleware' => ['checkprivilege:ticket,add_edit']]);
            $router->get('/ticket/{id}', ['uses' => 'TicketController@show','middleware' => ['checkprivilege:ticket,view']]);
           
            $router->get('/ticket/history/{id}', ['uses' => 'TicketController@history','middleware' => ['checkprivilege:ticket,view']]);
            $router->get('/ticket/download/{id}', ['uses' => 'TicketController@downloadDocument','middleware' => ['checkprivilege:ticket,view']]);
            $router->get('/ticket/downloadZip/{id}', ['uses' => 'TicketController@downloadZip','middleware' => ['checkprivilege:ticket,view']]);
            $router->get('/ticket/remove/{id}', ['uses' => 'TicketController@removeDocument','middleware' => ['checkprivilege:ticket,view']]);
            
            $router->get('/ticket/type/list', ['uses' => 'TicketTypeController@index']);
            $router->post('/ticket/type/{id}', ['uses' => 'TicketTypeController@update','middleware' => ['checkprivilege:ticket,view']]);
            $router->get('/ticket/type/show/{id}', ['uses' => 'TicketTypeController@show','middleware' => ['checkprivilege:ticket,view']]);
            
            $router->get('/ticket/problemfromourside/{id}', ['uses' => 'TicketController@ticketCount']);
            
        });
    });
});

