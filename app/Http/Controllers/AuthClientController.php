<?php

namespace App\Http\Controllers;

use App\User; // Use model class
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
/**
 * This is authentication class controller.
 * All the controller functions related to authentication should be defined in this class
 */
class AuthClientController extends Controller {

    /**
     * Authenticate user using username and password
     *
     *
     * @param  Illuminate\Http\Request   $request 
     * @return \Illuminate\Http\JsonResponse
     */
    public function authenticate(Request $request) {
        try {
            //validate user details
            $validator = app('validator')->make($request->all(), [
                'username' => 'required',
                'password' => 'required'
                    ], []);
            
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            config(['auth.providers.users.model' => 'App\Models\EntityUser']);
            // Get username and password from the request
            $credentials = $request->only(['username', 'password']);

            // Validate details
            if (!$token = app('auth')->attempt($credentials)) {
                return createResponse(config('httpResponse.UNAUTHORIZED'), "Invalid username or password.", ['error' => "Invalid username or password"]);
            }
            
            //get login user wise tab detail
            $payload = [
                'user' => app('auth')->user(),
                'token' => $token,
                'expires_in' => app('auth')->factory()->getTTL() * 1200
            ];
           

            return createResponse(config('httpResponse.SUCCESS'), 'Login Successful.', $payload);
        } catch (\Exception $e) {
            app('log')->error("User login failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Could not login. Please try again later.", ['error' => 'Could not login. Please try again later.']);
        }
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
    public function logout() {
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
        try {
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
            $token = str_random(64);
            //Update token
            \App\Models\User::where('id', $user->id)
                    ->update(['password_reset_token' => $token]);
            //get forgotpassword template
            $template = \App\Models\Backend\EmailTemplate::getTemplate('FPE');
            $data = array();
            if ($template['is_active']) {
                $data['to'] = $user->email;
                $data['cc'] = $template->cc;
                $data['subject'] = $template->subject;
                $msg = html_entity_decode($template->content);

                $link = 'resetpassword.php?token=' . $token;
                $content = str_replace("[USERNAME]", $user->userfullname, $msg);
                $content = str_replace("[LOGINNAME]", $user->user_login_name, $content);
                $content = str_replace("[HERE]", $link, $content);
                $data['content'] = $content;
                $data['from'] = "noreply-bdms@befree.com.au";
                $data['fromName'] = "Befree noreply";

                $store = storeMail($request, $data);
            }
            //$sendingResponse = $broker->sendResetLink($request->only('user_email'));
            return createResponse(config('httpResponse.SUCCESS'), 'Forgotpassword token', ['data' => $store]);
        } catch (\Exception $e) {
            app('log')->error("Forgot password failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Something went wrong.", ['error' => 'Could not generate forgot password token. Please try again later.']);
        }
    }

     /**
     * Authenticate user change password
     *
     *
     * @param  Illuminate\Http\Request   $request,$token 
     * @return \Illuminate\Http\JsonResponse 
     */
    
    public function resetPassword(Request $request, $token) {
        try {
            $validator = app('validator')->make($request->all(), [
                'password' => 'required|min:6',
                'confirm_password' => 'required|min:6|same:password'
                    ], []);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

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
