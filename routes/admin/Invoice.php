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
        $router->group(['namespace' => 'Backend\Invoice'], function() use ($router) {
            $router->post('/invoice', ['uses' => 'InvoiceController@store', 'middleware' => ['checkprivilege:invoice,add_edit']]);
            $router->post('/oneoffinvoice', ['uses' => 'InvoiceController@oneoff', 'middleware' => ['checkprivilege:oneoff,add_edit']]);
            $router->get('/invoice/{id}', ['uses' => 'InvoiceController@index', 'middleware' => ['checkprivilege:invoice,view']]);
            $router->get('/invoice/export/{id}', ['uses' => 'InvoiceController@index', 'middleware' => ['checkprivilege:invoice,export']]);
            $router->post('/invoice/update/{id}', ['uses' => 'InvoiceController@update', 'middleware' => ['checkprivilege:invoice,add_edit']]);
            $router->get('/invoice/show/{id}', ['uses' => 'InvoiceController@show', 'middleware' => ['checkprivilege:invoice,view']]);
            $router->get('/invoice/log/{id}', ['uses' => 'InvoiceController@Log']);
            $router->get('/invoice/status/list', ['uses' => 'InvoiceController@invoiceStatus']);
            $router->get('/invoice/wip/{id}', ['uses' => 'InvoiceWipController@wipinvoice']);
            $router->post('/invoice/notes/{id}', ['uses' => 'InvoiceController@addInvoiceNotes']);
            $router->get('/invoice/advance/{entityid}', ['uses' => 'InvoiceController@advanceInvoice', 'middleware' => ['checkprivilege:invoice,view']]);
            $router->post('/invoice/dismiss/{id}', ['uses' => 'InvoiceController@dismissInvoice', 'middleware' => ['checkprivilege:invoice,add_edit']]);
            $router->post('/invoice/statuschange/{id}', ['uses' => 'InvoiceController@invoiceStatusChange', 'middleware' => ['checkprivilege:invoice,add_edit']]);


            //invoice recurring
            $router->get('/invoice/recurring/getservice/{id}', ['uses' => 'InvoiceRecurringController@getService', 'middleware' => ['checkprivilege:recurring,add_edit']]);
            $router->get('/invoice/recurring/getentity', ['uses' => 'InvoiceRecurringController@getEntity', 'middleware' => ['checkprivilege:recurring,add_edit']]);
            $router->post('/invoice/recurring/store/{id}', ['uses' => 'InvoiceRecurringController@store', 'middleware' => ['checkprivilege:recurring,add_edit']]);
            $router->put('/invoice/recurring/active/{id}', ['uses' => 'InvoiceRecurringController@update', 'middleware' => ['checkprivilege:recurring,add_edit']]);
            $router->get('/invoice/recurring/show/{id}', ['uses' => 'InvoiceRecurringController@show', 'middleware' => ['checkprivilege:recurring,view']]);
            $router->get('/invoice/recurring/previewshow/{id}', ['uses' => 'InvoiceRecurringController@previewShow', 'middleware' => ['checkprivilege:recurring,view']]);
            $router->get('/invoice/recurring/history/{id}', ['uses' => 'InvoiceRecurringController@history', 'middleware' => ['checkprivilege:recurring,view']]);
            $router->get('/invoice/recurring/list', ['uses' => 'InvoiceRecurringController@index']);

            //invoice preview
            $router->post('/invoice/savepreview/{id}', ['uses' => 'InvoicePreviewController@saveInvoiceDescription', 'middleware' => ['checkprivilege:invoice,add_edit']]);
            $router->get('/invoice/preview/{id}', ['uses' => 'InvoicePreviewController@preview']);
            $router->get('/invoice/account/list', ['uses' => 'InvoicePreviewController@invoiceAccount']);

             $router->get('/invoice/importcsv/list', ['uses' => 'InvoiceXeroController@importCSV', 'middleware' => ['checkprivilege:invoice,add_edit']]);
            $router->post('/invoice/move/debtors', ['uses' => 'InvoiceXeroController@moveToDebtors', 'middleware' => ['checkprivilege:invoice,add_edit']]);
            $router->get('/invoice/xero/postinvoice', ['uses' => 'InvoiceXeroController@invoiceMoveToXero', 'middleware' => ['checkprivilege:invoice,add_edit']]);
            $router->get('/invoice/xero/getinvoice', ['uses' => 'InvoiceXeroController@invoiceSendToPaid', 'middleware' => ['checkprivilege:invoice,view']]);
       

            $router->get('/invoice/sendpreview/{id}', ['uses' => 'InvoiceSendController@sendInvoiceDetail', 'middleware' => ['checkprivilege:invoice,view']]);
            $router->post('/invoice/sendtoclient/{id}', ['uses' => 'InvoiceSendController@store', 'middleware' => ['checkprivilege:invoice,add_edit']]);
            $router->get('/invoice/download/pdf', ['uses' => 'InvoiceSendController@downloadPDF', 'middleware' => ['checkprivilege:invoice,view']]);
            $router->get('/invoice/showsendpreview/detail', ['uses' => 'InvoiceSendController@showSendInvoiceDetail']);
            
           
            $router->get('/invoice/unchargeunits/list', ['uses' => 'UnchargeUnitController@index', 'middleware' => ['checkprivilege:uncharge,view']]);
            $router->get('/invoice/unchargeunits/export', ['uses' => 'UnchargeUnitController@index', 'middleware' => ['checkprivilege:uncharge,export']]);
            $router->get('/invoice/unchargeunits/timesheetunit', ['uses' => 'UnchargeUnitController@unchargeSummary', 'middleware' => ['checkprivilege:uncharge,view']]);
            $router->get('/invoice/unchargeunits/timesheetunit/export', ['uses' => 'UnchargeUnitController@unchargeSummary', 'middleware' => ['checkprivilege:uncharge,view']]);

            });
    });
});