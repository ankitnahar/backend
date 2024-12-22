<?php

namespace App\Http\Controllers\Backend\Tabs;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Tabs\Tabs;

class TabsController extends Controller {

     /**
     * Tabs Right USer wise
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function tabs(Request $request) {
        try {
            $user_id = loginUser();
            $user_designation = \App\Models\Backend\UserHierarchy::where("user_id", $user_id)->get(['designation_id']);
            $tabs = \App\Models\Backend\Tabs::tabTree($user_designation->designation_id, $user_id);
            return createResponse(config('httpResponse.SUCCESS'), 'Allocated user tabs', ['data' => $tabs]);
        } catch (\Exception $e) {
            app('log')->error("tabs not failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get tabs details.', ['error' => 'Could not get user tab details.']);
        }
    }

}
