<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Validator;
use DB,
    Hash,
    Auth;
use Illuminate\Http\Request;


class AppServiceProvider extends ServiceProvider {    
    
   /* protected $request;
    public function __construct(Request $request)
   {
       $this->request = $request;
   }*/
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    
    public function boot(Request $request) {

        Validator::extend('requireDesignation', function ($attribute, $value, $parameter, $validator) {
            app('log')->info($parameter);
            app('log')->info($value);
            /*$designationList = \App\Models\Backend\Designation::designationData()->get($parameter['designation_id']);
            $desArray = array();
            $d = 0;
            $designationList = $designationList[0];
            while (isset($designationList->parent)) {
                if ($designationList->parent->is_mandatory == '1') {
                    $desArray[$designationList->parent->id] = $designationList->parent->designation_name;
                    $d++;
                }
                $designationList = $designationList->parent;
            }
// check value if         
            foreach ($desArray as $key => $value) {
                if ($this->$request->input($desArray[$key]) == '')
                    return false;
            }
            return true;*/
        });

        /* Created By - Pankaj
         * Used - This Function is used for minimum with required if condition only, so dont change it for individual used
         */
        Validator::extend('min_if', function ($attribute, $value, $parameters, $validator) {
            $data = $validator->getData();
            if (isset($data[$parameters[0]]) && $data[$parameters[0]] == $parameters[1] && (int) $value < $parameters[2]) {
                return false;
            }
            return true;
        });
        /* Created By - Pankaj
         * Used - This Function is used for check multiple coulmn unique conditions
         */
        Validator::extend('uniqueTwoColumn', function ($attribute, $value, $parameters, $validator) {
            $count = DB::table($parameters[0])->where($parameters[1], $parameters[2])
                    ->where($parameters[3], $parameters[4])
                    ->where($parameters[5], '!=', $parameters[6])
                    ->count();
            if ($count <= 0) {
                return true;
            } else {
                return false;
            }
        });

        /* Created By - Pankaj
         * Used - This Function is used for check multiple coulmn unique conditions
         */
        Validator::extend('uniqueTwoColumn', function ($attribute, $value, $parameters, $validator) {
            $count = DB::table($parameters[0])->where($parameters[1], $parameters[2])
                    ->where($parameters[3], $parameters[4])
                    ->where($parameters[5], '!=', $parameters[6])
                    ->count();
            if ($count <= 0) {
                return true;
            } else {
                return false;
            }
        });

        Validator::extend('pwdvalidation', function($field, $value, $parameters) {
            return Hash::check($value, Auth::user()->password);
        });

        /* Created By - Pankaj
         * Used - This Function is used for check multiple emails validatio
         */
        Validator::extend('email_array', function($attribute, $value, $parameters, $validator) {
            $value = str_replace(' ', '', $value);
            $array = explode(',', $value);
            foreach ($array as $email) { //loop over values
                $email_to_validate['alert_email'][] = $email;
            }
            $rules = array('alert_email.*' => 'email');
            $messages = array(
                'alert_email.*' => trans('validation.email_array')
            );
            $validator = Validator::make($email_to_validate, $rules, $messages);
            if ($validator->passes()) {
                return true;
            } else {
                return false;
            }
        });
        /* Created By - Pankaj
         * Used - This Function is used for check only alpha character + space only
         */
        Validator::extend('alpha_spaces', function($attribute, $value) {
            return preg_match('/^[\pL\s]+$/u', $value);
        });

        /* Created By - Pankaj
         * Used - This Function is used for check numeric 
         */
        Validator::extend('phone_number', function($attribute, $value) {
            if (trim($value) != '') {
                return preg_match('/^\({0,1}((0|\+61)(2|4|3|7|8)){0,1}\){0,1}(\ |-){0,1}[0-9]{2}(\ |-){0,1}[0-9]{2}(\ |-){0,1}[0-9]{1}(\ |-){0,1}[0-9]{3}$/', $value);
            } else {
                return true;
            }
        });
        
         /* Created By - Pankaj
         * Used - This Function is used for check decimal 
         */
        Validator::extend('decimal', function($attribute, $value) {
            if (trim($value) != '') {
                return preg_match('/^(?=.)([+-]?([0-9]*)(\.([0-9]+))?)$/', $value);
            } else {
                return true;
            }
        });
        
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register() {
        $this->app->singleton('mailer', function ($app) {
            $app->configure('services');
            return $app->loadComponent('mail', 'Illuminate\Mail\MailServiceProvider', 'mailer');
        });
    }

}
