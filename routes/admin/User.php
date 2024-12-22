<?php

/*
  |--------------------------------------------------------------------------
  | User routes for admin
  |--------------------------------------------------------------------------
  |
  |
 */
$router->group(['prefix' => 'v1.0'], function() use ($router) {
    $router->group(['middleware' => 'auth'], function () use ($router) {
        $router->group(['namespace' => 'Backend\User'], function() use ($router) {
            $router->post('/user', ['uses' => 'UserController@store','middleware' => ['checkprivilege:user,add_edit']]);
            $router->get('/user', ['uses' => 'UserController@index']);
            $router->get('/user/export', ['uses' => 'UserController@index','middleware' => ['checkprivilege:user,export']]);
            $router->post('/user/{id}', ['uses' => 'UserController@update','middleware' => ['checkprivilege:user,add_edit']]);
            $router->get('/user/{id}', ['uses' => 'UserController@show','middleware' => ['checkprivilege:user,view']]);   
            $router->get('/user/right/report', ['uses' => 'UserController@allUserRightReport']);
            $router->get('/user/newuser/zohoreport', ['uses' => 'UserController@newuserZohoDetail']);
            
            $router->get('/user/list/data', ['uses' => 'UserController@userList']);
            
            $router->get('/user/history/{id}', ['uses' => 'UserController@history','middleware' => ['checkprivilege:user,view']]);
            $router->post('/user/bulk/approval', ['uses' => 'UserController@userApprovalAllocation','middleware' => ['checkprivilege:user,add_edit']]);
            
            //change password
            $router->post('/user/changepassword/{id}', ['uses' => 'UserController@changePassword','middleware' => ['checkprivilege:change_password,add_edit']]);
            //Designation
            $router->post('/designation', ['uses' => 'DesignationController@store']);
            $router->get('/designation', ['uses' => 'DesignationController@index']);
            $router->put('/designation/{id}', ['uses' => 'DesignationController@update']);
            $router->get('/designation/{id}', ['uses' => 'DesignationController@show']);
            $router->get('/designation/right/{id}', ['uses' => 'DesignationController@rightdata']);
            $router->post('/designation/right/{id}', ['uses' => 'DesignationController@updateRight']);      
            
            
            /* user right*/            
            $router->get('/user/right/{id}', ['uses' => 'UserRightController@index','middleware' => ['checkprivilege:userright,add_edit']]);
            $router->post('/user/right/{id}', ['uses' => 'UserRightController@updateRight','middleware' => ['checkprivilege:userright,add_edit']]);
            
            /* user hierarchy*/            
            $router->put('/user/hierarchy/{id}', ['uses' => 'UserHierarchyController@update','middleware' => ['checkprivilege:userhierarchy,add_edit']]);
            $router->get('/user/hierarchy/show/{id}', ['uses' => 'UserHierarchyController@show']);
            $router->get('/user/hierarchy/department', ['uses' => 'UserHierarchyController@getDepartment']);
            $router->get('/user/hierarchy/team/{id}', ['uses' => 'UserHierarchyController@getTeamDepartmentWise','middleware' => ['checkprivilege:userhierarchy,add_edit']]);
            $router->get('/user/hierarchy/teamlist', ['uses' => 'UserHierarchyController@getTeam']);
            
            
            $router->get('/user/team/{designationid}', ['uses' => 'UserHierarchyController@getDesignationTeamWise']);
            $router->get('/user/designation/userlist', ['uses' => 'UserHierarchyController@getUserDesignationWise']);
            $router->get('/user/userprofiledetail/{id}', ['uses' => 'UserController@userDetailList']);
            $router->get('/userzohodetail/{id}', ['uses' => 'UserController@userZohoDetail']);   
            
        });
    });
});

