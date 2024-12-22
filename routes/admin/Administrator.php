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
        $router->group(['namespace' => 'Backend\Administrator'], function() use ($router) {
            $router->get('/services', ['uses' => 'ServicesController@index']);
            $router->get('/frequency', ['uses' => 'FrequencyController@index']);
            
            // Constant setting
            $router->get('/constant/list', ['uses' => 'ConstantController@index']);
            $router->put('/constant/update/{id}', ['uses' => 'ConstantController@update']);
            
            // IP address setting
            $router->get('/ipaddress/list', ['uses' => 'IpAddressController@index']);
            $router->post('/ipaddress/add', ['uses' => 'IpAddressController@store']);
            $router->get('/ipaddress/view/{id}', ['uses' => 'IpAddressController@show']);
            $router->put('/ipaddress/update/{id}', ['uses' => 'IpAddressController@update']);
            $router->delete('/ipaddress/delete/{id}', ['uses' => 'IpAddressController@destroy']);
            
            // Manage email template
            $router->get('/emailtemplate/list', ['uses' => 'EmailTemplateController@index']);
            //$router->post('/emailtemplate/add', ['uses' => 'EmailTemplateController@store']);
            $router->get('/emailtemplate/view/{id}', ['uses' => 'EmailTemplateController@show']);
            $router->put('/emailtemplate/update/{id}', ['uses' => 'EmailTemplateController@update']);
            $router->delete('/emailtemplate/delete/{id}', ['uses' => 'EmailTemplateController@destroy']);
            
            // Manage email signature
            $router->get('/emailsignature/list', ['uses' => 'EmailSignatureController@index']);
            $router->post('/emailsignature/add', ['uses' => 'EmailSignatureController@store']);
            $router->get('/emailsignature/view/{id}', ['uses' => 'EmailSignatureController@show']);
            $router->put('/emailsignature/update/{id}', ['uses' => 'EmailSignatureController@update']);
            $router->delete('/emailsignature/delete/{id}', ['uses' => 'EmailSignatureController@destroy']);
            
            //software login
            $router->get('/softwarelogin/list', ['uses' => 'SoftwareLoginController@index']);
            $router->post('/softwarelogin/add', ['uses' => 'SoftwareLoginController@store']);
            $router->post('/softwarelogin/update/{id}', ['uses' => 'SoftwareLoginController@update']);
            $router->delete('/softwarelogin/delete/{id}', ['uses' => 'SoftwareLoginController@destroy']);
            
        });
    });
});