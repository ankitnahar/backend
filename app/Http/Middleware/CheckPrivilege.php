<?php

/**
 * created by - Pankaj 
 * Check privileges for check all route for user access given or not
 *
 *
 * @param  char   $tabUniqueName
 * @param  char   $formType "tab,tabButton"
 * @return char   $privileges "view,add_edit,delete"
 */

namespace App\Http\Middleware;

use Closure;

class CheckPrivilege {

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, $tabUniqueName, $privileges, $formType = 'tab', $tabButtonName = '', $fields = '') {
        $privileges = explode("&", $privileges);
        $userid = app('auth')->guard()->id();
        $designation = getLoginUserHierarchy();
        // check for superadmin 
       if ($designation->designation_id == config('constant.SUPERADMIN')) {
            return $next($request);
        }
        if ($formType == 'tab') {
            // Get tabId
            $tab = \App\Models\Backend\Tabs::where('tab_unique_name', $tabUniqueName)->select('id');
            // check if such a page exists or not
            if ($tab->count() == 0) {
                return createResponse(config('httpResponse.UNAUTHORIZED'), config('message.UNAUTHORIZED'), ['error' => config('message.UNAUTHORIZED')]);
            }  
            $tab = $tab->first();
            $userPrivilege = \App\Models\Backend\UserTabRight::where('user_id', $userid)->where('tab_id', $tab->id);

            // Check  for each privilege
            foreach ($privileges as $privilege)
                $userPrivilege->where($privilege, 1);

            if (!$userPrivilege->count()) {
                return createResponse(config('httpResponse.UNAUTHORIZED'), config('message.UNAUTHORIZED'), ['error' => config('message.UNAUTHORIZED')]);
            }
            return $next($request);
        } else if ($formType == 'tabButton') {
            //get Tabid
            $tab = \App\Models\Backend\Tabs::where('tab_unique_name', $tabUniqueName)->select('id')->first();
            // Get sub pageId
            $tabButton = \App\Models\Backend\Button::where('tab_id', $tab->id)
                            ->where('button_name', $tabButtonName)
                            ->select('id');

            // check if such a page exists or not
            if ($tabButton->count() == 0) {
                return createResponse(config('httpResponse.UNAUTHORIZED'), config('message.UNAUTHORIZED'), ['error' => config('message.UNAUTHORIZED')]);
            }
            $tabButton = $tabButton->first();
            $userPrivilege = \App\Models\Backend\UserTabRight::where('user_id', $userid)->whereRaw("FIND_IN_SET(" . $tabButton->id . ",other_right)")->get(['other_right']);

            if (!$userPrivilege->count()) {
                return createResponse(config('httpResponse.UNAUTHORIZED'), config('message.UNAUTHORIZED'), ['error' => config('message.UNAUTHORIZED')]);
            }
            return $next($request);
        }
    }

}
