<?php

/*
 * Created by: Jayesh Shingrakhiya
 * Created on: Sept 20, 2018
 * Purpose: Main timesheet routes
 */
$router->group(['prefix' => 'v1.0'], function() use ($router) {
    $router->group(['middleware' => 'auth'], function () use ($router) {
        $router->group(['namespace' => 'Backend\Timesheet'], function() use ($router) {
            // Manage no job
            $router->get('/timesheet', ['uses' => 'TimesheetController@index']);
            $router->get('/timesheet/excel', ['uses' => 'TimesheetController@index', 'middleware' => ['checkprivilege:timesheet,export']]);
            $router->get('/timesheetsummary/listing', ['uses' => 'TimesheetController@timesheetSummary', 'middleware' => ['checkprivilege:timesheetsummary,view']]);
            $router->get('/timesheetsummary/export', ['uses' => 'TimesheetController@timesheetSummary', 'middleware' => ['checkprivilege:timesheetsummary,export']]);
            $router->post('/timesheet', ['uses' => 'TimesheetController@store']);
            $router->put('/timesheet/{id}', ['uses' => 'TimesheetController@update']);
            $router->get('/timesheet/view/{id}', ['uses' => 'TimesheetController@show']);
            $router->delete('/timesheet/{id}', ['uses' => 'TimesheetController@destroy']);
            $router->get('/timesheet/worksheet', ['uses' => 'TimesheetController@worksheet']);
            $router->get('/timesheet/fetchentity', ['uses' => 'TimesheetController@AssingEntity']);
            $router->get('/timesheet/entitymasteractivity', ['uses' => 'TimesheetController@entityMasterActivity']);
            $router->get('/timesheet/bankinfo', ['uses' => 'TimesheetController@entityBankInfo']);
            $router->get('/timesheet/payrolloption/{id}', ['uses' => 'TimesheetController@payrollOption']);
            $router->get('/timesheet/details', ['uses' => 'TimesheetController@getTimesheetDetailInfo']);
        });
    });
    $router->group(['namespace' => 'Backend\Timesheet'], function() use ($router) {
        //$router->post('/timesheet/srtimesheetimport', ['uses' => 'TimesheetController@getSupperRecordsTimesheet']);
        //$router->post('/timesheet/sruktimesheetimport', ['uses' => 'TimesheetController@getSupperRecordsUKTimesheet']);
        $router->get('/timesheet/getuserlist', ['uses' => 'TimesheetController@getUserlist']);
        $router->post('/timesheet/srtimesheetimport', ['uses' => 'TimesheetController@getTimesheetFromPortal']);
    });
   
});
