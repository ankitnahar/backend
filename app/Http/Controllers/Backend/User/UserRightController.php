<?php

namespace App\Http\Controllers\Backend\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;

class UserRightController extends Controller {

    /**
     * Get user right detail
     *
     * @param  Illuminate\Http\Request  $request id= designation_id , type =(tab,field,button)
     * @return Illuminate\Http\JsonResponse
     */
    public function index(Request $request, $id) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'type' => 'required|in:tab,field,button,worksheet',
                'sortOrder' => 'in:asc,desc'
                    ], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            // define soring parameters
            $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            if ($request->get('type') == 'tab') {
                $listingData = \App\Models\Backend\Tabs::usertabData($id);
            } else if ($request->get('type') == 'field') {
                $listingData = \App\Models\Backend\Dynamicfield::userfieldData($id);
            } else if ($request->get('type') == 'button') {
                $listingData = \App\Models\Backend\Button::userbuttonData($id);
            } else if ($request->get('type') == 'worksheet') {
                $listingData = \App\Models\Backend\WorksheetStatus::userworksheetData($id);
            }

            //show all records  
            $listingData = $listingData->orderBy($sortBy, $sortOrder)->get();

            return createResponse(config('httpResponse.SUCCESS'), "User right list data.", ['data' => $listingData], $pager);
        } catch (\Exception $e) {
            app('log')->error("Right listing data failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing right data", ['error' => 'Server error.']);
        }
    }

    public function updateRight(REQUEST $request, $id) {
        //Update rights        
        try {
            $validator = app('validator')->make($request->all(), [
                'type' => 'required|in:tab,field,button,worksheet',
                'data' => 'json'
                    ], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);


            $loginUser = loginUser();
            $arrRights = array();
            //showArray($request->get('data'));exit;
            $postValues = json_decode($request->get('data'), true);
            $type = $request->get('type');
            // showArray($postValues);exit;
            $noRight = array();
            foreach ($postValues as $key => $tabFlag) {
                if ($type == 'button') {
                    if ($tabFlag['view'] == 1) {
                        $arrRight[$tabFlag['tab_id']][] = $tabFlag['id'];
                    } else {
                        $noRight[$tabFlag['tab_id']][] = $tabFlag['id'];
                    }
                } else if ($type == 'worksheet') {
                    //update user wise worksheet right
                    $checkRight = \App\Models\Backend\WorksheetStatusUserRight::checkRight($tabFlag['id'], $id);

                    if (empty($checkRight)) {
                        \App\Models\Backend\WorksheetStatusUserRight::create([
                            'worksheet_status_id' => $tabFlag['id'],
                            'user_id' => $id,
                            'right' => $tabFlag['view'],
                            'created_on' => date('Y-m-d H:i:s'),
                            'created_by' => loginUser()]
                        );
                    } else {

                        //For Save History in update case
                        $view = ($checkRight->right == $tabFlag['view']) ? "" : (($tabFlag['view'] == 1) ? 'View Right has been given' : 'View Right has been removed');
                        if ($view != '') {
                            $changesArray[] = array(
                                'changeDetail' => \App\Models\Backend\WorksheetStatus::getname($tabFlag['id']),
                                'new_value' => $view);
                        }

                        //Update worksheet right
                        \App\Models\Backend\WorksheetStatusUserRight::where('id', $checkRight->id)
                                ->update(['right' => $tabFlag['view']]);
                    }
                } //showArray($value['id']);exit;            
                else if ($type == 'tab') {
                    $typehistory = 'user_tab_right';
                    $checkRight = \App\Models\Backend\UserTabRight::checkRight($tabFlag['id'], $id);
                    $name = \App\Models\Backend\Tabs::getname($tabFlag['id']);
                    if (!isset($name))
                        return createResponse(config('httpResponse.NOT_FOUND'), 'The tab does not exist', ['error' => 'The tab does not exist']);
                    if (empty($checkRight)) {
                        \App\Models\Backend\UserTabRight::insert([
                            ["tab_id" => $tabFlag['id'],
                                'user_id' => $id,
                                'view' => $tabFlag['view'],
                                'add_edit' => $tabFlag['add_edit'],
                                'delete' => $tabFlag['delete'],
                                'export' => $tabFlag['export'],
                                'download' => $tabFlag['download'],
                                'created_on' => date('Y-m-d H:i:s'),
                                'created_by' => loginUser()
                            ]
                        ]);
                    } else {
                        //For Save History in update case
                        $view = ($checkRight->view == $tabFlag['view']) ? "" : (($tabFlag['view'] == 1) ? 'View Right has been given' : 'View Right has been removed');
                        $addedit = ($checkRight->add_edit == $tabFlag['add_edit']) ? "" : (($tabFlag['add_edit'] == 1) ? ',Add Edit Right has been given' : ',Add Edit Right has been removed');
                        $delete = ($checkRight->delete == $tabFlag['delete']) ? "" : (($tabFlag['delete'] == 1) ? ',Delete Right has been given' : ',Delete Right has been removed');
                        $export = ($checkRight->export == $tabFlag['export']) ? "" : (($tabFlag['export'] == 1) ? ',Export Right has been given' : ',Export Right has been removed');
                        $download = ($checkRight->download == $tabFlag['download']) ? "" : (($tabFlag['download'] == 1) ? ',Download Right has been given' : ',Download Right has been removed');

                        if ($view != "" || $addedit != "" || $delete != "" || $export != "" || $download != "") {
                            $rightDetail = $view . $addedit . $delete . $export . $download;
                            $rightDetail = ltrim($rightDetail, ',');
                            $changesArray[] = array(
                                'changeDetail' => $name,
                                'new_value' => $rightDetail);
                        }
                        //update record
                        \App\Models\Backend\UserTabRight::where('id', $checkRight->id)
                                ->update([
                                    'view' => $tabFlag['view'],
                                    'add_edit' => $tabFlag['add_edit'],
                                    'delete' => $tabFlag['delete'],
                                    'export' => $tabFlag['export'],
                                    'download' => $tabFlag['download']]);
                    }
                } else {
                    $checkRight = \App\Models\Backend\UserFieldRight::checkRight($tabFlag['id'], $id);
                    $name = \App\Models\Backend\Dynamicfield::getname($tabFlag['id']);
                    if (!isset($name)) {
                        return createResponse(config('httpResponse.NOT_FOUND'), 'The field does not exist', ['error' => 'The field does not exist']);
                    }
                    //Check Value already exiest or not
                    if (empty($checkRight)) {
                        \App\Models\Backend\UserFieldRight::insert([
                            ['field_id' => $tabFlag['id'],
                                'user_id' => $id,
                                'view' => $tabFlag['view'],
                                'add_edit' => $tabFlag['add_edit'],
                                'created_on' => date('Y-m-d H:i:s'),
                                'created_by' => loginUser()
                            ]
                        ]);
                    } else {
                        //For Save History in update case
                        $view = ($checkRight->view == $tabFlag['view']) ? "" : (($tabFlag['view'] == 1) ? 'View Right has been given' : 'View Right has been removed');
                        $addedit = ($checkRight->add_edit == $tabFlag['add_edit']) ? "" : (($tabFlag['add_edit'] == 1) ? ',Add Edit Right has been given' : ',Add Edit Right has been removed');

                        if ($view != "" || $addedit != "") {
                            $rightDetail = $view . $addedit;
                            $rightDetail = ltrim($rightDetail, ',');
                            $changesArray[] = array(
                                'changeDetail' => $name,
                                'new_value' => $rightDetail
                            );
                        }
                        //update record
                        \App\Models\Backend\UserFieldRight::where('id', $checkRight->id)
                                ->update([
                                    'view' => $tabFlag['view'],
                                    'add_edit' => $tabFlag['add_edit']]);
                    }
                }
            }
            //showArray($arrRight);exit;
            //showArray($noRight);exit;
            if ($type == 'button') {
                $typehistory = 'user_button_right';
                if (!empty($arrRight)) {
                    foreach ($arrRight as $key => $val) {
                        $userTabRight = \App\Models\Backend\UserTabRight::checkRight($key, $id);
                        if (empty($userTabRight->other_right) && !empty($val)) {
                            $ids = implode(",", $val);
                            $buttonNames = \App\Models\Backend\Button::getButtonNames($ids);
                            //Check button name 
                            if (!isset($buttonNames))
                                return createResponse(config('httpResponse.NOT_FOUND'), 'The other right does not exist', ['error' => 'The other right does not exist']);

                            $changesArray[] = array(
                                'changeDetail' => $buttonNames->button_label,
                                'new_value' => 'Other Right has been given');
                        } else if ($userTabRight->other_right != '') {
                            //Save history
                            //compare array and find out which value has been changed
                            //showArray($buttonIds);
                            $newButton = (!empty($val)) ? $val : array();
                            $oldButton = ($userTabRight->other_right != '0') ? explode(",", $userTabRight->other_right) : array();
                            $addButtonIds = array_diff($newButton, $oldButton);
                            $removeButtonIds = array_diff($oldButton, $newButton);

                            if (!empty($addButtonIds)) {
                                $abid = implode(",", $addButtonIds);
                                //Get all button name
                                $buttonNames = \App\Models\Backend\Button::getButtonNames($abid);
                                if (!isset($buttonNames))
                                    return createResponse(config('httpResponse.NOT_FOUND'), 'The other right does not exist', ['error' => 'The other right does not exist']);

                                //store button right history 
                                $changesArray[] = array(
                                    'changeDetail' => $buttonNames->button_label,
                                    'new_value' => 'Other Right has been given');
                            }

                            if (!empty($removeButtonIds)) {
                                $rbid = implode(",", $removeButtonIds);
                                //Get all button name
                                $buttonNames = \App\Models\Backend\Button::getButtonNames($rbid);
                                if (isset($buttonNames)) {
                                    //store button right history 
                                    $changesArray[] = array(
                                        'changeDetail' => $buttonNames->button_label,
                                        'new_value' => 'Other Right has been removed');
                                }
                            }
                        }
                        //echo implode(",", $val); exit;
                        //Update value              
                        \App\Models\Backend\UserTabRight::where('id', $userTabRight->id)
                                ->update(['other_right' => implode(",", $val)]);
                    }
                }
                //remove button
                if (!empty($noRight)) {
                    foreach ($noRight as $key => $val) {
                        if (!isset($arrRight[$key])) {
                            $userTabRight = \App\Models\Backend\UserTabRight::checkRight($key, $id);

                            if ($userTabRight->other_right != '') {
                                //Save history
                                //compare array and find out which value has been changed
                                //showArray($buttonIds);
                                $newButton = (!empty($val)) ? $val : array();
                                $oldButton = ($userTabRight->other_right != '0') ? explode(",", $userTabRight->other_right) : array();
                                $addButtonIds = array_diff($newButton, $oldButton);

                                if (!empty($addButtonIds)) {
                                    $abid = implode(",", $addButtonIds);
                                    //Get all button name
                                    $buttonNames = \App\Models\Backend\Button::getButtonNames($abid);
                                    if (!isset($buttonNames))
                                        return createResponse(config('httpResponse.NOT_FOUND'), 'The other right does not exist', ['error' => 'The other right does not exist']);

                                    //store button right history 
                                    $changesArray[] = array(
                                        'changeDetail' => $buttonNames->button_label,
                                        'new_value' => 'Other Right has been removed');
                                }

                                //Update value              
                                \App\Models\Backend\UserTabRight::where('id', $userTabRight->id)
                                        ->update(['other_right' => '']);
                            }
                        }
                    }
                }
            }
            //showArray($changesArray);exit;
            //Save history
            if (!empty($changesArray)) {
                if ($type == 'tab' || $type == 'button') {
                    \App\Models\Backend\UserTabRightAudit::insert([
                        'user_id' => $id,
                        'type' => $typehistory,
                        'changes' => json_encode($changesArray),
                        'modified_on' => date('Y-m-d H:i:s'),
                        'modified_by' => $loginUser]);
                } else if ($type == 'field') {
                    \App\Models\Backend\UserFieldRightAudit::insert([
                        'user_id' => $id,
                        'changes' => json_encode($changesArray),
                        'modified_on' => date('Y-m-d H:i:s'),
                        'modified_by' => $loginUser]);
                } else {
                    \App\Models\Backend\WorksheetStatusUserRightAudit::insert([
                        'user_id' => $id,
                        'changes' => json_encode($changesArray),
                        'modified_on' => date('Y-m-d H:i:s'),
                        'modified_by' => $loginUser]);
                }
            }
            return createResponse(config('httpResponse.SUCCESS'), 'User ' . $type . ' right has been updated successfully', ['message' => 'User ' . $type . ' right has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("User '.$type.' right updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update user ' . $type . ' right details.', ['error' => 'Could not update designation ' . $type . ' right details.']);
        }
    }

}
?>
