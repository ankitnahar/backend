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
        $router->group(['namespace' => 'Backend\Billing'], function() use ($router) {

            $router->get('/billing', ['uses' => 'BillingController@billingbasic', 'middleware' => ['checkprivilege:billing,view']]);
            $router->get('/billing/basic', ['uses' => 'BillingController@index']);
            $router->get('/billing/export', ['uses' => 'BillingController@index', 'middleware' => ['checkprivilege:billing,export']]);
            $router->put('/billing/{id}', ['uses' => 'BillingController@update', 'middleware' => ['checkprivilege:billing,add_edit']]);
            $router->get('/billing/{id}', ['uses' => 'BillingController@show', 'middleware' => ['checkprivilege:billing,view']]);
            $router->get('/billing/history/{id}', ['uses' => 'BillingController@history', 'middleware' => ['checkprivilege:billing,view']]);
           $router->get('/billing/related/{id}', ['uses' => 'BillingController@relatedentity', 'middleware' => ['checkprivilege:billing,view']]);

            // for groupclientbelongsto
            $router->get('/billing/groupclientbelongsto/list', ['uses' => 'EntityGroupController@index']);
            $router->post('/billing/groupclientbelongsto/add', ['uses' => 'EntityGroupController@store']);
            $router->put('/billing/groupclientbelongsto/{id}', ['uses' => 'EntityGroupController@update']);
            $router->get('/billing/groupclientbelongsto/{id}', ['uses' => 'EntityGroupController@show']);


            $router->post('/billing/bk/{id}', ['uses' => 'BillingServicesController@update', 'middleware' => ['checkprivilege:billing,add_edit']]);
            $router->get('/billing/bk/{id}', ['uses' => 'BillingServicesController@show', 'middleware' => ['checkprivilege:billing,view']]);
            
            $router->get('/billing/service/history/{id}', ['uses' => 'BillingServicesController@history']);
            $router->get('/billing/historyBK/{id}', ['uses' => 'BillingServicesController@historyBKService', 'middleware' => ['checkprivilege:billing,view']]);
            
            $router->get('/billing/subactivity/{id}', ['uses' => 'BillingServicesSubactivityController@index']);
            $router->post('/billing/subactivity/{id}', ['uses' => 'BillingServicesSubactivityController@update', 'middleware' => ['checkprivilege:billing,add_edit']]);
           
            $router->get('/billing/subactivity/history/{id}', ['uses' => 'BillingServicesSubactivityController@history']);
            
            //get recurring
            $router->get('/billing/recurring/list', ['uses' => 'BillingServicesController@getrecurring']);

            //payroll
            $router->post('/billing/payroll/{id}', ['uses' => 'PayrollController@update', 'middleware' => ['checkprivilege:billing,add_edit']]);
            $router->get('/billing/payroll/{id}', ['uses' => 'PayrollController@show', 'middleware' => ['checkprivilege:billing,view']]);

            $router->get('/billing/payroll/payrollcalc/list', ['uses' => 'PayrollController@calcindex']);
            $router->post('/billing/payroll/payrollcalc/add', ['uses' => 'PayrollController@calcstore', 'middleware' => ['checkprivilege:payrollcalc,add_edit']]);
            $router->put('/billing/payroll/payrollcalc/{id}', ['uses' => 'PayrollController@calcupdate', 'middleware' => ['checkprivilege:payrollcalc,add_edit']]);
            $router->get('/billing/payroll/payrollcalc/{id}', ['uses' => 'PayrollController@calcshow', 'middleware' => ['checkprivilege:payrollcalc,add_edit']]);

            //tax
            $router->post('/billing/tax/{id}', ['uses' => 'TaxController@update', 'middleware' => ['checkprivilege:billing,add_edit']]);
            $router->get('/billing/tax/{id}', ['uses' => 'TaxController@show', 'middleware' => ['checkprivilege:billing,view']]);

            $router->get('/billing/turnover/{id}', ['uses' => 'TaxController@turnoverList']);
            $router->post('/billing/turnover/{id}', ['uses' => 'TaxController@turnoverStore', 'middleware' => ['checkprivilege:billing,add_edit']]);
            $router->delete('/billing/turnover/{id}', ['uses' => 'TaxController@turnoverDestroy', 'middleware' => ['checkprivilege:billing,add_edit']]);

            $router->post('/billing/smsf/{id}', ['uses' => 'SMSFController@update', 'middleware' => ['checkprivilege:billing,add_edit']]);
            $router->get('/billing/smsf/{id}', ['uses' => 'SMSFController@show', 'middleware' => ['checkprivilege:billing,view']]);

            //subscription
            $router->post('/billing/subscription/{id}', ['uses' => 'SubscriptionController@update', 'middleware' => ['checkprivilege:billing,add_edit']]);
            $router->get('/billing/subscription/{id}', ['uses' => 'SubscriptionController@show', 'middleware' => ['checkprivilege:billing,view']]);
            //software
            $router->get('/billing/software/list', ['uses' => 'SubscriptionController@softwareList']);
            $router->post('/billing/software/add', ['uses' => 'SubscriptionController@softwareStore', 'middleware' => ['checkprivilege:billing,add_edit']]);
            $router->put('/billing/software/{id}', ['uses' => 'SubscriptionController@updateSoftware', 'middleware' => ['checkprivilege:billing,add_edit']]);
            //plan
            $router->get('/billing/plan/list', ['uses' => 'SubscriptionController@planList']);
            $router->post('/billing/plan/add', ['uses' => 'SubscriptionController@planStore', 'middleware' => ['checkprivilege:billing,add_edit']]);
            $router->put('/billing/plan/{id}', ['uses' => 'SubscriptionController@updatePlan', 'middleware' => ['checkprivilege:billing,add_edit']]);
            $router->get('/billing/softwareplan/{id}', ['uses' => 'SubscriptionController@softwareShow', 'middleware' => ['checkprivilege:billing,add_edit']]);

            $router->post('/billing/hosting/{id}', ['uses' => 'HostingController@update', 'middleware' => ['checkprivilege:billing,add_edit']]);
            $router->get('/billing/hosting/{id}', ['uses' => 'HostingController@show', 'middleware' => ['checkprivilege:billing,view']]);

            $router->get('/billing/hosting/user/{id}', ['uses' => 'HostingController@indexUser']);
            $router->post('/billing/hosting/user/{id}', ['uses' => 'HostingController@storeUser', 'middleware' => ['checkprivilege:billing,add_edit']]);
            $router->put('/billing/hosting/user/{id}', ['uses' => 'HostingController@updateUser', 'middleware' => ['checkprivilege:billing,add_edit']]);
            $router->get('/billing/hosting/user/show/{id}', ['uses' => 'HostingController@showUser', 'middleware' => ['checkprivilege:billing,add_edit']]);
        });
    });
});
