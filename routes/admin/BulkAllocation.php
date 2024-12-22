<?php

$router->group(['prefix' => 'v1.0'], function() use ($router) {
    $router->group(['middleware' => 'auth'], function () use ($router) {
        $router->group(['namespace' => 'Backend\BulkAllocation'], function() use ($router) {
            $router->post('/bulkallocation/allocation', ['uses' => 'BulkAllocationController@allocation']);
            $router->post('/bulkallocation/fetchentity', ['uses' => 'BulkAllocationController@fetchEntity']);
            $router->post('/bulkallocation/deallocation', ['uses' => 'BulkAllocationController@deallocation']);
            $router->get('/bulkallocation/entitylist/{id}', ['uses' => 'BulkAllocationController@entityList']);
            $router->get('/bulkallocation/allocatedservice/{id}', ['uses' => 'BulkAllocationController@allocatedService']);
        });
    });
});