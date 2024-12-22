<?php

$router->group(['prefix' => 'v1.0'], function() use ($router) {
    $router->group(['namespace' => 'Backend\Hr'], function() use ($router) {
        $router->get('/hr/checkpendingtimesheet', ['uses' => 'AttendanceController@checkpendingtimesheet']);
    });

    $router->group(['middleware' => 'auth'], function () use ($router) {
        $router->group(['namespace' => 'Backend\Hr'], function() use ($router) {
            // Manage dashboard
            $router->get('/hr/dashboard', ['uses' => 'DashboardController@index']);
            
            // Manage no job
            $router->post('/hr/nojob/store', ['uses' => 'NojobController@store','middleware' => ['checkprivilege:nojob,add_edit']]);
            $router->get('/hr/nojob', ['uses' => 'NojobController@index','middleware' => ['checkprivilege:nojob,view']]);
            $router->put('/hr/nojob/{id}', ['uses' => 'NojobController@update']);
            $router->get('/hr/nojob/export', ['uses' => 'NojobController@index','middleware' => ['checkprivilege:nojob,export']]);

            // Manage Holiday
            $router->get('/hr/holidaymaster', ['uses' => 'HolidayController@index','middleware' => ['checkprivilege:holiday,view']]);
            $router->get('/hr/holidaymaster/export', ['uses' => 'HolidayController@index','middleware' => ['checkprivilege:holiday,export']]);
            $router->post('/hr/holidaymaster/store', ['uses' => 'HolidayController@store','middleware' => ['checkprivilege:holiday,add_edit']]);
            $router->post('/hr/holidaymaster/upload', ['uses' => 'HolidayController@storeholidaywithcsv','middleware' => ['checkprivilege:holiday,add_edit']]);
            $router->get('/hr/holidaymaster/{id}', ['uses' => 'HolidayController@show','middleware' => ['checkprivilege:holiday,view']]);
            $router->put('/hr/holidaymaster/{id}', ['uses' => 'HolidayController@update','middleware' => ['checkprivilege:holiday,add_edit']]);
            $router->delete('/hr/holidaymaster/{id}', ['uses' => 'HolidayController@destroy','middleware' => ['checkprivilege:holiday,delete']]);
            
             // Manage Holiday
            $router->get('/hr/holiday', ['uses' => 'HolidayControllerDetail@index','middleware' => ['checkprivilege:holiday,view']]);
            $router->get('/hr/holiday/export', ['uses' => 'HolidayControllerDetail@index','middleware' => ['checkprivilege:holiday,export']]);
            $router->post('/hr/holiday/store', ['uses' => 'HolidayControllerDetail@store','middleware' => ['checkprivilege:holiday,add_edit']]);
            $router->get('/hr/holiday/{id}', ['uses' => 'HolidayControllerDetail@show','middleware' => ['checkprivilege:holiday,view']]);
            $router->put('/hr/holiday/update/{id}', ['uses' => 'HolidayControllerDetail@update','middleware' => ['checkprivilege:holiday,add_edit']]);
            $router->delete('/hr/holiday/{id}', ['uses' => 'HolidayControllerDetail@destroy','middleware' => ['checkprivilege:holiday,delete']]);

            // Manage Exception shift
            $router->get('/hr/exception', ['uses' => 'ExceptionshiftController@index','middleware' => ['checkprivilege:exceptionshift,view']]);
            $router->get('/hr/exception/export', ['uses' => 'ExceptionshiftController@index','middleware' => ['checkprivilege:exceptionshift,export']]);
            $router->post('/hr/exception/store', ['uses' => 'ExceptionshiftController@store','middleware' => ['checkprivilege:exceptionshift,add_edit']]);
            $router->get('/hr/exception/{id}', ['uses' => 'ExceptionshiftController@show','middleware' => ['checkprivilege:exceptionshift,view']]);
            $router->put('/hr/exception/{id}', ['uses' => 'ExceptionshiftController@update','middleware' => ['checkprivilege:exceptionshift,add_edit']]);
            $router->delete('/hr/exception/{id}', ['uses' => 'ExceptionshiftController@destroy','middleware' => ['checkprivilege:exceptionshift,delete']]);

            // Manage shift
            $router->get('/hr/shift', ['uses' => 'ShiftController@index']);
            $router->get('/hr/shift/export', ['uses' => 'ShiftController@index','middleware' => ['checkprivilege:shift,export']]);
            $router->post('/hr/shift/store', ['uses' => 'ShiftController@store','middleware' => ['checkprivilege:shift,add_edit']]);
            $router->get('/hr/shift/{id}', ['uses' => 'ShiftController@show','middleware' => ['checkprivilege:shift,view']]);
            $router->put('/hr/shift/{id}', ['uses' => 'ShiftController@update','middleware' => ['checkprivilege:shift,add_edit']]);
            $router->put('/hr/shift/delete/{id}', ['uses' => 'ShiftController@destroy','middleware' => ['checkprivilege:shift,delete']]);

            // Manage Punch in/out time
            $router->get('/hr/punchinout', ['uses' => 'PunchInOutController@index','middleware' => ['checkprivilege:punchinout,view']]);
            $router->get('/hr/punchinout/userlist', ['uses' => 'PunchInOutController@userList','middleware' => ['checkprivilege:punchinout,view']]);           
            $router->get('/hr/amendmentpunchinout', ['uses' => 'PunchInOutController@listAmedmentinout','middleware' => ['checkprivilege:emendmentinouttime,view']]);
            $router->get('/hr/punchinout/export', ['uses' => 'PunchInOutController@index','middleware' => ['checkprivilege:punchinout,export']]);
            $router->post('/hr/punchinout/store', ['uses' => 'PunchInOutController@store','middleware' => ['checkprivilege:punchinout,add_edit']]);
            $router->get('/hr/punchinout/{id}', ['uses' => 'PunchInOutController@show','middleware' => ['checkprivilege:punchinout,view']]);
            $router->put('/hr/punchinout/{id}', ['uses' => 'PunchInOutController@update','middleware' => ['checkprivilege:punchinout,add_edit']]);
            $router->put('/hr/punchinoutamedment/{id}', ['uses' => 'PunchInOutController@approveAmedmentRequest','middleware' => ['checkprivilege:emendmentinouttime,add_edit']]);
            $router->delete('/hr/punchinout/{id}', ['uses' => 'PunchInOutController@destroy','middleware' => ['checkprivilege:punchinout,delete']]);
            
            // Manage attendance
            $router->get('/hr/attendance/summary', ['uses' => 'AttendanceController@summary']);
            $router->get('/hr/attendance/summary/export', ['uses' => 'AttendanceController@summary','middleware' => ['checkprivilege:attendancesummary,export']]);
            
            $router->post('/hr/attendance/followupMail/{id}', ['uses' => 'AttendanceController@followupMail']);
            
            $router->get('/hr/attendance/summaryreport', ['uses' => 'AttendanceController@summaryReport','middleware' => ['checkprivilege:attendancesummaryreport,view']]);
            $router->get('/hr/attendance/summaryreport/export', ['uses' => 'AttendanceController@summaryReport','middleware' => ['checkprivilege:attendancesummaryreport,export']]);
            $router->get('/hr/attendance/latecomingexception', ['uses' => 'AttendanceController@latecomingException','middleware' => ['checkprivilege:latecomingexception,view']]);
            $router->get('/hr/attendance/latecomingexception/export', ['uses' => 'AttendanceController@latecomingException','middleware' => ['checkprivilege:latecomingexception,export']]);
            $router->put('/hr/attendance/approved/{id}', ['uses' => 'AttendanceController@approvedRequest']);
            $router->put('/hr/attendance/{id}', ['uses' => 'AttendanceController@approvalRequest']);
            $router->get('/hr/attendance/{id}', ['uses' => 'AttendanceController@show','middleware' => ['checkprivilege:attendance,view']]);
            $router->get('/hr/attendance/pendingtimesheet/list', ['uses' => 'AttendanceController@pendingTimesheet']);
            $router->get('/hr/pendingtimesheet/export', ['uses' => 'AttendanceController@pendingTimesheet','middleware' => ['checkprivilege:pendingtimesheet,export']]);
            $router->post('/hr/attendance/adjustment/{id}', ['uses' => 'AttendanceController@updateAdjustment']);
            $router->post('/hr/attendance/summaryreport/uploadcsv', ['uses' => 'AttendanceController@uploadleavecsv','middleware' => ['checkprivilege:attendance,add_edit']]);
           
            
            
            $router->get('/hr/attendance/{id}', ['uses' => 'AttendanceController@show','middleware' => ['checkprivilege:attendance,view']]);
            $router->put('/hr/pendingtimesheet/approved/{id}', ['uses' => 'AttendanceController@approved']);
            
            // Manage location
            $router->get('/hr/location', ['uses' => 'LocationController@index']);
            $router->get('/hr/location/export', ['uses' => 'LocationController@index']);
            $router->post('/hr/location/store', ['uses' => 'LocationController@store']);
            $router->get('/hr/location/{id}', ['uses' => 'LocationController@show']);
            $router->put('/hr/location/{id}', ['uses' => 'LocationController@update']);
            $router->delete('/hr/location/{id}', ['uses' => 'LocationController@destroy']);
            
            // Manage daily report
            $router->get('/hr/dailyreport', ['uses' => 'DailyReportController@index']);
            $router->get('/hr/dailyreport/userlist', ['uses' => 'DailyReportController@userList']);
            
            // HR cron link
            $router->post('/hr/updateremark', ['uses' => 'HRController@updateRemarkPreviousDay']);
            $router->post('/hr/fetchinout', ['uses' => 'HRController@fetchInOut']); 
            
            $router->post('/hr/addmaunalinout', ['uses' => 'HRController@addManualInOut']);
            
            $router->post('/hr/addmaunalinout', ['uses' => 'HRController@addManualInOut']);
            
            
            $router->post('/hr/punchinquestion', ['uses' => 'PunchinQuestionController@store']);
            
            $router->get('/hr/gethrdeatil', ['uses' => 'HRController@getHrDetail']);
            
             // Manage no job
            $router->post('/hr/leavebalance/store', ['uses' => 'LeaveBalanceController@storeleavewithcsv','middleware' => ['checkprivilege:leavebalance,add_edit']]);
            $router->get('/hr/leavebalance', ['uses' => 'LeaveBalanceController@index','middleware' => ['checkprivilege:leavebalance,view']]);
            $router->put('/hr/leavebalance/{id}', ['uses' => 'LeaveBalanceController@update','middleware' => ['checkprivilege:leavebalance,add_edit']]);
            $router->get('/hr/leavebalance/export', ['uses' => 'LeaveBalanceController@index','middleware' => ['checkprivilege:leavebalance,export']]);
            
            $router->post('/hr/leaverequest/store', ['uses' => 'LeaveRequestController@store']);
            $router->get('/hr/leaverequest', ['uses' => 'LeaveRequestController@index']);
            $router->put('/hr/leaverequest/{id}', ['uses' => 'LeaveRequestController@update']);
            $router->post('/hr/leaverequest/approve/{id}', ['uses' => 'LeaveRequestController@approved']);
            $router->get('/hr/leaverequest/export', ['uses' => 'LeaveRequestController@index']);
            $router->delete('/hr/leaverequest/{id}', ['uses' => 'LeaveRequestController@destroy']);
           
            $router->post('/hr/holidayrequest/store', ['uses' => 'HolidayRequestController@store']);
            $router->get('/hr/holidayrequest', ['uses' => 'HolidayRequestController@index']);
            $router->put('/hr/holidayrequest/{id}', ['uses' => 'HolidayRequestController@update']);
            $router->post('/hr/holidayrequest/approve/{id}', ['uses' => 'HolidayRequestController@approved']);
            $router->get('/hr/holidayrequest/export', ['uses' => 'HolidayRequestController@index']);
            $router->delete('/hr/holidayrequest/{id}', ['uses' => 'HolidayRequestController@destroy']);
            $router->get('/hr/userupcomingholiday/{id}', ['uses' => 'HolidayController@userHolidayDetail']);
            
            $router->get('/hr/welcomekit', ['uses' => 'WelcomeKitController@index','middleware' => ['checkprivilege:welcomekit,view']]);
            $router->get('/hr/welcomekit/export', ['uses' => 'WelcomeKitController@index','middleware' => ['checkprivilege:welcomekit,view']]);
            $router->get('/hr/welcomekit/{id}', ['uses' => 'WelcomeKitController@show','middleware' => ['checkprivilege:welcomekit,view']]);
            $router->post('/hr/welcomekit', ['uses' => 'WelcomeKitController@store','middleware' => ['checkprivilege:welcomekit,add_edit']]);
            $router->post('/hr/welcomekit/{id}', ['uses' => 'WelcomeKitController@update','middleware' => ['checkprivilege:welcomekit,add_edit']]);
            $router->delete('/hr/welcomekit/{id}', ['uses' => 'WelcomeKitController@destroy','middleware' => ['checkprivilege:welcomekit,delete']]);
           $router->get('/hr/welcomekit/downloadZip/{id}', ['uses' => 'WelcomeKitController@downloadZip','middleware' => ['checkprivilege:welcomekit,view']]);
            
        });
    });
});