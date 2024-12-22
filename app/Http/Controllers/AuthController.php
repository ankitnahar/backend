<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;

/**
 * This is authentication class controller.
 * All the controller functions related to authentication should be defined in this class
 */
class AuthController extends Controller {

    /**
     * Refresh JWT token to extend the TTL
     *
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkIP(Request $request) {
        try {
            // get IP
            //echo $_SERVER['REMOTE_ADDR'];
            //$ip = $_SERVER['REMOTE_ADDR'];
            if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            } else {
                $ip = $_SERVER['REMOTE_ADDR'];
            }
            if ($ip == '::1') {
                $ip = '127.0.0.1';
            }



            $checkIP = \App\Models\Backend\IpAddress::whereRaw("from_ip = INET_ATON('$ip') OR (from_ip <= INET_ATON('$ip') AND to_ip >= INET_ATON('$ip'))");

            if ($checkIP->count() == 0) {
                return createResponse(config('httpResponse.UNAUTHORIZED'), 'InValid IP Address.', ["success" => "0", "data" => $ip]);
            }
            return createResponse(config('httpResponse.SUCCESS'), 'Valid IP Address.', ["success" => "1", "data" => $checkIP->first()]);
        } catch (\Exception $e) {
            app('log')->error("IP Adress failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Could not find IP Address.", ['error' => 'Could not find IP Address.']);
        }
    }

    public function authenticate(Request $request) {
        // try {
        //validate user details
        /* $validator = app('validator')->make($request->all(), [
          'email' => 'required',
          ], []);

          if ($validator->fails())
          return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]); */
        $MiniToken = $request->input('token');
        if ($request->has('token') && $MiniToken != '') {
            $user_details = \App\Models\User::where("email", $request->input('email'))->where("is_active", "1")->first();
            $pass = generateRandomString();
            $password = password_hash($pass, PASSWORD_BCRYPT);
            if (\App\Models\User::where("id", $user_details->id)->update(["password" => $password])) {

                $request->request->add(['user_login_name' => $user_details->user_login_name]);
                $request->request->add(['password' => $pass]);
            }
        }

        // Get username and password from the request
        $credentials = $request->only(['user_login_name', 'password']);
        // Validate details
        if (!$token = app('auth')->attempt($credentials)) {
            return createResponse(config('httpResponse.UNAUTHORIZED'), "Invalid username or password.", ['error' => "Invalid username or password"]);
        }


        //get login user wise tab detail
        $user = app('auth')->user();
        $user = \App\Models\User::where("id", $user->id)->with('firstApproval:id,userfullname')->with('secondApproval:id,userfullname')->first();
        if ($user->is_active == 0) {
            return createResponse(config('httpResponse.UNAUTHORIZED'), "User in active can't login.", ['error' => "User in active can't login"]);
        } else {
            $userId = $user->id;
            $type = "1";
            $todayDate = date("Y-m-d");
            //Check IP 
            if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            } else {
                $ip = $_SERVER['REMOTE_ADDR'];
            }
            if ($ip == '::1') {
                $ip = '127.0.0.1';
            }
            $checkIP = \App\Models\Backend\IpAddress::whereRaw("from_ip = INET_ATON('$ip') OR (from_ip <= INET_ATON('$ip') AND to_ip >= INET_ATON('$ip'))");

            if ($checkIP->count() == 0) {
                return createResponse(config('httpResponse.UNAUTHORIZED'), 'InValid IP Address.', ["success" => "0", "data" => $ip]);
            }         
            if($user->is_show == 0){
            \App\Models\User::where("id",$userId)->update(["is_show" => 1]);
            }
            $checkPunch = \App\Models\Backend\HrUserInOuttime::where("user_id", $userId)->where("date", $todayDate)->where("type",1);

            if ($checkPunch->count() == 0) {
                Backend\Hr\HRController::addInOutDetail($userId, 1);
              
            }

            $user_hierarchy = \App\Models\Backend\UserHierarchy::leftjoin("designation as d", "d.id", "user_hierarchy.designation_id")
                            ->leftjoin("department as dp", "dp.id", "user_hierarchy.department_id")
                            ->select("d.designation_name", "dp.department_name", "user_hierarchy.designation_id", "user_hierarchy.team_id", "user_hierarchy.other_right")
                            ->where("user_hierarchy.user_id", loginUser())->first();
            $tabs = \App\Models\Backend\Tabs::tabTree($user_hierarchy->designation_id, loginUser());
            $user['team_id'] = '0';
            if ($user_hierarchy->designation_id != config('constant.SUPERADMIN')) {
              
                $allservice = $user_hierarchy->other_right;
                if ($allservice != '') {
                    //$serviceId = \App\Models\Backend\Team::select(DB::raw("group_concat(service_id) as service_id"))->whereRaw("id IN($user_hierarchy->team_id)")->first();
                    $user['team_id'] = $user_hierarchy->team_id . ',' . $allservice;
                } else {
                    $user['team_id'] = $user_hierarchy->team_id;
                }
            } else {
                $user['team_id'] = "1,2,6,4,5,7";
            }
            $user['food_next_date'] = '';
           $userHR =  \App\Models\Backend\HrDetail::where("user_id", loginUser())->where("date", $todayDate)->first();
            
          
            $user['office_location'] = $userHR->office_location;
            $user['designation_id'] = $user_hierarchy;

            $payload = [
                'user' => $user,
                'tabs' => $tabs,
                'token' => $token,
                'expires_in' => app('auth')->factory()->getTTL() * 1200
            ];
            //update user last login
            \App\Models\User::where("id", loginUser())->update(['user_lastlogin' => date('Y-m-d h:i:s')]);

            return createResponse(config('httpResponse.SUCCESS'), 'Login Successful.', $payload);
        }
        /* } catch (\Exception $e) {
          app('log')->error("User login failed : " . $e->getMessage());

          return createResponse(config('httpResponse.SERVER_ERROR'), "Could not login. Please try again later.", ['error' => 'Could not login. Please try again later.']);
          } */
    }

    /**
     * Refresh JWT token to extend the TTL
     *
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh() {
        try {
            // Invalidate existing token and create new token with extended TTL
            $token = app('auth')->refresh();

            $payload = [
                'user' => app('auth')->user(),
                'token' => $token,
                'expires_in' => app('auth')->factory()->getTTL() * 60
            ];

            return createResponse(config('httpResponse.SUCCESS'), 'The token has been refreshed successfully.', $payload);
        } catch (\Exception $e) {
            app('log')->error("JWT refresh token failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Could not refresh token.", ['error' => 'Could not refresh token.']);
        }
    }

    /**
     * Logout the user 
     *
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public static function logout() {
        try {
            // Invalidate JWT token
            app('auth')->logout();

            return createResponse(config('httpResponse.SUCCESS'), 'You have been logged out successfully.', []);
        } catch (\Exception $e) {
            app('log')->error("User logout failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Something went wrong.", ['error' => 'Could not logout. Please try again later.']);
        }
    }

    /**
     * Authenticate user change password
     *
     *
     * @param  Illuminate\Http\Request   $request 
     * @return \Illuminate\Http\JsonResponse token and send mail
     */
    public function forgotPassword(Request $request) {
        //try {
        $validator = app('validator')->make($request->all(), [
            'email' => 'required|email',
                ], []);

        // If validation fails then return error response
        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

        $user = \App\Models\User::where('email', '=', $request->get('email'))->first();
        if (!$user) {
            return createResponse(config('httpResponse.NOT_FOUND'), 'The User does not exist', ['error' => 'The User does not exist']);
        }
        $url = config('constant.url.base');
        $token = str_random(64);
        //Update token
        \App\Models\User::where('id', $user->id)
                ->update(['password_reset_token' => $token]);
        //get forgotpassword template
        $template = \App\Models\Backend\EmailTemplate::getTemplate('FPE');
        $data = array();
        if ($template->is_active) {
            $data['to'] = $user->email;
            $data['cc'] = $template->cc;
            $data['subject'] = $template->subject;
            $msg = html_entity_decode($template->content);

            $rawUrl = array('token' => $token);
            $queryString = urlEncrypting($rawUrl);
            $link = '<a>' . $url . "reset-password?" . $queryString . '</a>';
            $content = str_replace("[USER]", $user->userfullname, $msg);
            $content = str_replace("[LOGINNAME]", $user->user_login_name, $content);
            $content = str_replace("[RESETLINK]", $link, $content);
            $data['content'] = $content;
            $data['from'] = "noreply-bdms@befree.com.au";
            $data['fromName'] = "Befree noreply";

            $store = storeMail($request, $data);
        }
        //$sendingResponse = $broker->sendResetLink($request->only('user_email'));
        return createResponse(config('httpResponse.SUCCESS'), 'Forgotpassword token', ['data' => $template->is_active]);
        /* } catch (\Exception $e) {
          app('log')->error("Forgot password failed : " . $e->getMessage());

          return createResponse(config('httpResponse.SERVER_ERROR'), "Something went wrong.", ['error' => 'Could not generate forgot password token. Please try again later.']);
          } */
    }

    /**
     * Authenticate user change password
     *
     *
     * @param  Illuminate\Http\Request   $request,$token 
     * @return \Illuminate\Http\JsonResponse 
     */
    public function resetPassword(Request $request) {
        try {
            $validator = app('validator')->make($request->all(), [
                'token' => 'required',
                'password' => 'required|min:6',
                'confirm_password' => 'required|min:6|same:password'
                    ], []);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
            $token = $request->input('token');
            $user = \App\Models\User::where('password_reset_token', $token)->first();

            if (!$user)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The User does not exist', ['error' => 'The User does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $updateData['password_reset_token'] = '';
            $updateData['password'] = password_hash($request->input('confirm_password'), PASSWORD_BCRYPT);

            //update the details
            $user->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'User Password has been updated successfully', ['message' => 'User password has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("User password updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update user password details.', ['error' => 'Could not update user password details.']);
        }
    }

}
