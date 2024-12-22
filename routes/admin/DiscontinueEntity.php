<?php

/*
 * Created by: Jayesh Shingrakhiya
 * Created on: Sept 25, 2018
 * Purpose: Main timesheet routes
 */
$router->group(['prefix' => 'v1.0'], function() use ($router) {
    $router->group(['middleware' => 'auth'], function () use ($router) {
        $router->group(['namespace' => 'Backend\DiscontinueEntity'], function() use ($router) {

            // Manage reason management
            $router->get('/discontinueentity/discontinuereason/listing', ['uses' => 'DiscontinueQuestionController@index', 'middleware' => ['checkprivilege:discontinuereason,view']]);
            $router->get('/discontinueentity/discontinuereason/export', ['uses' => 'DiscontinueQuestionController@index', 'middleware' => ['checkprivilege:discontinuereason,export']]);
            $router->post('/discontinueentity/discontinuereason/add', ['uses' => 'DiscontinueQuestionController@store', 'middleware' => ['checkprivilege:discontinuereason,add_edit']]);
            $router->put('/discontinueentity/discontinuereason/update/{id}', ['uses' => 'DiscontinueQuestionController@update', 'middleware' => ['checkprivilege:discontinuereason,add_edit']]);
            $router->get('/discontinueentity/discontinuereason/view/{id}', ['uses' => 'DiscontinueQuestionController@show', 'middleware' => ['checkprivilege:discontinuereason,view']]);

            // Manage discontinue entity
            $router->get('/discontinueentity/listing', ['uses' => 'DiscontinueEntityController@index', 'middleware' => ['checkprivilege:discontinueentity,view']]);
            $router->get('/discontinueentity/export', ['uses' => 'DiscontinueEntityController@index', 'middleware' => ['checkprivilege:discontinueentity,export']]);
            $router->get('/discontinueentity/history/{id}', ['uses' => 'DiscontinueEntityController@history']);
            $router->post('/discontinueentity/add', ['uses' => 'DiscontinueEntityController@store', 'middleware' => ['checkprivilege:discontinueentity,add_edit']]);
            $router->put('/discontinueentity/update/{id}', ['uses' => 'DiscontinueEntityController@update', 'middleware' => ['checkprivilege:discontinueentity,add_edit']]);
            $router->get('/discontinueentity/view/{id}', ['uses' => 'DiscontinueEntityController@show', 'middleware' => ['checkprivilege:discontinueentity,view']]);
            
            $router->get('/discontinueentity/comment/{id}', ['uses' => 'DiscontinueEntityController@comment']);
            
            $router->post('/discontinueentity/comment/add', ['uses' => 'DiscontinueEntityController@commentStore']);
            $router->post('/discontinueentity/restore/{id}', ['uses' => 'DiscontinueEntityController@restore', 'middleware' => ['checkprivilege:discontinueentity,add_edit']]);
            
            $router->post('/discontinueentity/reason/update/{id}', ['uses' => 'DiscontinueEntityController@updateReason']);
            $router->get('/discontinueentity/viewdetail/{id}', ['uses' => 'DiscontinueEntityController@showDiscontinueDetail']);
            
            // Manage discontinue question answer
            $router->get('/discontinueentity/questionanswer/view/{id}', ['uses' => 'DiscontinueQuestionAnswerController@show']);
            $router->put('/discontinueentity/questionanswer/update/{id}', ['uses' => 'DiscontinueQuestionAnswerController@update']);
            $router->get('/discontinueentity/questiondetail/{id}', ['uses' => 'DiscontinueQuestionAnswerController@discontinueQuestionDetail']);
            $router->get('/discontinueentity/view/questiondetail/{id}', ['uses' => 'DiscontinueQuestionAnswerController@showQuestionDetail']);
            
            // Manage discontinue entity history
            $router->get('/discontinueentity/history/{id}', ['uses' => 'DiscontinueQuestionAnswerController@history']);
            
        });
    });
});
