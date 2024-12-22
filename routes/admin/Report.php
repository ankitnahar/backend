<?php
$router->group(['prefix' => 'v1.0'], function() use ($router) {
    $router->group(['middleware' => 'auth'], function () use ($router) {
        $router->group(['namespace' => 'Backend\Report'], function() use ($router) {
            
             //for bank report
            $router->get('/report/bank/generatereport', ['uses' => 'BankReportController@generateReport', 'middleware' => ['checkprivilege:bankreport,view']]);
            $router->get('/report/bank/export', ['uses' => 'BankReportController@generateReport', 'middleware' => ['checkprivilege:bankreport,export']]);
            
            // for allocation
            $router->get('/report/allocation/generatereport', ['uses' => 'EntityAllocationReportController@generateReport', 'middleware' => ['checkprivilege:allocationreport,view']]);
            $router->get('/report/allocation/export', ['uses' => 'EntityAllocationReportController@generateReport', 'middleware' => ['checkprivilege:allocationreport,export']]);
            
            // for entity
            $router->get('/report/entity/generatereport', ['uses' => 'EntityReportController@generateReport', 'middleware' => ['checkprivilege:entityreport,view']]);
            $router->get('/report/entity/export', ['uses' => 'EntityReportController@generateReport', 'middleware' => ['checkprivilege:entityreport,export']]);
            
            //for invoice report
            $router->get('/report/invoice/generatereport', ['uses' => 'InvoiceReportController@generateReport', 'middleware' => ['checkprivilege:invoicereport,view']]);
            $router->get('/report/invoice/export', ['uses' => 'InvoiceReportController@generateReport', 'middleware' => ['checkprivilege:invoicereport,export']]);
            
            //for client wise invoice report
            $router->get('/report/clientinvoice/generatereport', ['uses' => 'ClientInvoiceReportController@generateReport', 'middleware' => ['checkprivilege:clientinvoice,view']]);
            $router->get('/report/clientinvoice/export', ['uses' => 'ClientInvoiceReportController@generateReport', 'middleware' => ['checkprivilege:clientinvoice,export']]);
            
            //for client wise invoice report
            $router->get('/report/monthlyinvoice/generatereport', ['uses' => 'MonthlyInvoiceReportController@generateReport', 'middleware' => ['checkprivilege:monthlyinvoicereport,view']]);
            $router->get('/report/monthlyinvoice/export', ['uses' => 'MonthlyInvoiceReportController@generateReport', 'middleware' => ['checkprivilege:monthlyinvoicereport,export']]);
            
            //for billing report
            $router->get('/report/billing/generatereport', ['uses' => 'BillingReportController@generateReport', 'middleware' => ['checkprivilege:billingreport,view']]);
            $router->get('/report/billing/export', ['uses' => 'BillingReportController@generateReport', 'middleware' => ['checkprivilege:billingreport,export']]);
            
            //for billing services report
            $router->get('/report/billingservices/generatereport', ['uses' => 'BillingServiceReportController@generateReport', 'middleware' => ['checkprivilege:billingservicesreport,view']]);
            $router->get('/report/billingservices/export', ['uses' => 'BillingServiceReportController@generateReport', 'middleware' => ['checkprivilege:billingservicesreport,export']]);
            
             //for billing subactivity report
            $router->get('/report/billingsubactivity/generatereport', ['uses' => 'BillingSubactivityReportController@generateReport', 'middleware' => ['checkprivilege:billingsubactivityreport,view']]);
            $router->get('/report/billingsubactivity/export', ['uses' => 'BillingSubactivityReportController@generateReport', 'middleware' => ['checkprivilege:billingsubactivityreport,export']]);
                        
             //for billing subactivity report
            $router->get('/report/billinghostinguser/generatereport', ['uses' => 'BillingHostingUserReportController@generateReport', 'middleware' => ['checkprivilege:billinghostinguserreport,view']]);
            $router->get('/report/billinghostinguser/export', ['uses' => 'BillingHostingUserReportController@generateReport', 'middleware' => ['checkprivilege:billinghostinguserreport,export']]);
            
             //for billing subactivity report
            $router->get('/report/billingtaxturnover/generatereport', ['uses' => 'BillingTaxTurnoverReportController@generateReport', 'middleware' => ['checkprivilege:billingtaxturnoverreport,view']]);
            $router->get('/report/billingtaxturnover/export', ['uses' => 'BillingTaxTurnoverReportController@generateReport', 'middleware' => ['checkprivilege:billingtaxturnoverreport,export']]);
            
             //for ticket report
            $router->get('/report/ticket/generatereport', ['uses' => 'TicketReportController@generateReport', 'middleware' => ['checkprivilege:ticketreport,view']]);
            $router->get('/report/ticket/export', ['uses' => 'TicketReportController@generateReport', 'middleware' => ['checkprivilege:ticketreport,export']]);
            
            
            //for fixed fee report
            $router->get('/report/fixedfeerevision/generatereport', ['uses' => 'FFRevisionReportController@generateReport', 'middleware' => ['checkprivilege:fixedfeerevisionreport,view']]);
            $router->get('/report/fixedfeerevision/export', ['uses' => 'FFRevisionReportController@generateReport', 'middleware' => ['checkprivilege:fixedfeerevisionreport,export']]);
            
             //for fixed fee report
            $router->get('/report/fixedfee/generatereport', ['uses' => 'FixedFeeReportController@generateReport', 'middleware' => ['checkprivilege:fixedfeereport,view']]);
            $router->get('/report/fixedfee/export', ['uses' => 'FixedFeeReportController@generateReport', 'middleware' => ['checkprivilege:fixedfeereport,export']]);
            
            //for Rsheet report
            $router->get('/report/rsheet/generatereport', ['uses' => 'RsheetReportController@generateReport', 'middleware' => ['checkprivilege:rsheet,view']]);
            $router->get('/report/rsheet/export', ['uses' => 'RsheetReportController@generateReport', 'middleware' => ['checkprivilege:rsheet,export']]);
            
             //for Rsheet Summary report
            $router->get('/report/rsheetsummary/generatereport', ['uses' => 'RsheetSummaryReportController@generateReport', 'middleware' => ['checkprivilege:rsheetsummary,view']]);
            $router->get('/report/rsheetsummary/export', ['uses' => 'RsheetSummaryReportController@generateReport', 'middleware' => ['checkprivilege:rsheetsummary,export']]);
            
             //for Rsheet report
            $router->get('/report/worksheetreport/generatereport', ['uses' => 'WorksheetReportController@generateReport', 'middleware' => ['checkprivilege:worksheetreport,view']]);
            $router->get('/report/worksheetreport/export', ['uses' => 'WorksheetReportController@generateReport', 'middleware' => ['checkprivilege:worksheetreport,export']]);
            
            // Report
            $router->get('/report/saved/{id}', ['uses' => 'ReportController@index']);
            $router->post('/report/store/{id}', ['uses' => 'ReportController@store']);
            $router->get('/report/view/{id}', ['uses' => 'ReportController@show']);
            $router->put('/report/update/{id}', ['uses' => 'ReportController@update']);
            $router->get('/report/shareduser/{id}', ['uses' => 'ReportController@viewShared']);
            $router->post('/report/reportshare/{id}', ['uses' => 'ReportController@reportShare']);
            $router->delete('/report/delete/{id}', ['uses' => 'ReportController@destroy']);
            $router->get('/report/fields/{id}', ['uses' => 'ReportController@fetchFields']);
        });
    });
});