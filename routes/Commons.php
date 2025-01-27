<?php

/*
  |--------------------------------------------------------------------------
  | Common routes in the application
  |--------------------------------------------------------------------------
  |
  | Here is where you can register all of the routes which are common for different user types.
  |
 */

$router->get('/', function() {
    return app('hash')->make('test');
});

/* Register all the routes of version 1.0 */
$router->group(['prefix' => 'v1.0'], function() use ($router) {
    // User authentication
    $router->get('admin/checkIP', ['uses' => 'AuthController@checkIP']);
    $router->get('admin/checkPunchIn', ['uses' => 'AuthController@checkPunchIn']);
    $router->post('admin/login', ['uses' => 'AuthController@authenticate']);
    $router->post('admin/forgotpassword', 'AuthController@forgotPassword');
    $router->post('admin/resetpassword', 'AuthController@resetPassword');
    // Define all the routes in this group which are accessible by only logged in users
    $router->group(['middleware' => 'auth'], function () use ($router) {
        $router->post('admin/refresh', ['uses' => 'AuthController@refresh']); // Refresh jwt token
        $router->post('admin/logout', ['uses' => 'AuthController@logout']); // Invalidate jwt token

        $router->get('/dropdown', function () {
            //try {
                $validator = app('validator')->make(app('request')->all(), ['table' => 'required', 'condition' => 'json'], []);
                if ($validator->fails()) // Return error message if validation fails
                    return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

                $table = app('request')->get('table');
                $column = app('request')->get('column');
                $condition = '';
                $sortBy = 'asc';
                $sortOrder = 'id';
                $groupBy = null;
                
                if (app('request')->has('search'))
                    $condition = app('request')->get('search');

                if (app('request')->has('sortBy'))
                    $sortBy = app('request')->get('sortBy');

                if (app('request')->has('sortOrder'))
                    $sortOrder = app('request')->get('sortOrder');

                if (app('request')->has('groupBy'))
                    $groupBy = app('request')->get('groupBy');
                
                return dropDown($table, $column, $condition, $sortBy, $sortOrder, $groupBy);
//            } catch (\Exception $e) {
//                app('log')->error("Drop down listing failed : " . $e->getMessage());
//                return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing failed", ['error' => 'Server error.']);
//            }
        });

       
    });

    

    
});