<?php

namespace App\Http\Controllers\Backend\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Backend\Designation;
use App\Models\Backend\Tabs;
use App\Models\Backend\Dynamicfield;
use App\Models\Backend\Button;
use App\Models\Backend\WorksheetStatus,
    App\Models\Backend\DesignationTabRight,
    App\Models\Backend\DesignationFieldRight;

/**
 * This is a designation class controller.
 * 
 */
class DesignationController extends Controller {

    /**
     * Get designation detail
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function index(Request $request) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'sortOrder' => 'in:asc,desc',
                'pageNumber' => 'numeric|min:1',
                'recordsPerPage' => 'numeric|min:0',
                'search' => 'json'
                    ], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            // define soring parameters
            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'designation.id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $designation = Designation::designationData();
            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $designation = search($designation, $search);
            }
            // for relation ship sorting
            if ($sortBy == 'created_by' || $sortBy == 'modified_by') {
                $designation = $designation->leftjoin("user as u", "u.id", "designation.$sortBy");
                $sortBy = 'userfullname';
            }

            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $designation = $designation->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;

                //count number of total records
                $totalRecords = $designation->count();

                $designation = $designation->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $designation = $designation->get(['designation.*']);

                $filteredRecords = count($designation);

                $pager = ['sortBy' => $sortByName,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            return createResponse(config('httpResponse.SUCCESS'), "Designation list.", ['data' => $designation], $pager);
        } catch (\Exception $e) {
            app('log')->error("Designation listing failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing designation", ['error' => 'Server error.']);
        }
    }

    /**
     * Store designation details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'parent_id' => 'required|numeric',
                'is_mandatory' => 'required|in:1,0',
                'designation_name' => 'required|unique:designation,designation_name',
                'is_active' => 'required|numeric|in:1,0',
                    ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            // store client details
            $loginUser = loginUser();
            $designation = Designation::create([
                        'parent_id' => $request->input('parent_id'),
                        'is_mandatory' => $request->input('is_mandatory'),
                        'designation_name' => $request->input('designation_name'),
                        'is_active' => $request->input('is_active'),
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => $loginUser
            ]);

            return createResponse(config('httpResponse.SUCCESS'), 'Designation has been added successfully', ['data' => $designation]);
        } catch (\Exception $e) {
            app('log')->error("Designation creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add designation', ['error' => 'Could not add designation']);
        }
    }

    /**
     * update designation details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // designation id
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'parent_id' => 'numeric',
                'is_mandatory' => 'in:1,0',
                'designation_name' => 'unique:designation,designation_name,' . $id,
                'is_active' => 'numeric|in:1,0',
                    ], []);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
            $designation = Designation::find($id);

            if (!$designation)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Designation does not exist', ['error' => 'The User does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $loginUser = loginUser();
            $updateData = filterFields(['designation_name', 'is_active', 'parent_id', 'is_mandatory'], $request);
            $updateData['modified_on'] = date('Y-m-d H:i:s');
            $updateData['modified_by'] = $loginUser;
            //update the details
            $designation->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Designation has been updated successfully', ['message' => 'Designation has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Designation updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update designation details.', ['error' => 'Could not update designation details.']);
        }
    }

    /**
     * get particular designation details
     *
     * @param  int  $id   //designation id
     * @return Illuminate\Http\JsonResponse
     */
    public function show($id) {
        try {
            $designation = Designation::with('parent', 'createdBy:userfullname as created_by,id', 'modifiedBy:userfullname as modified_by,id')->find($id);

            if (!isset($designation))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Designation does not exist', ['error' => 'The Designation does not exist']);

            //send user information
            return createResponse(config('httpResponse.SUCCESS'), 'Designation data', ['data' => $designation]);
        } catch (\Exception $e) {
            app('log')->error("Designation details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get Designation.', ['error' => 'Could not get Designation.']);
        }
    }

    /**
     * Get right detail
     *
     * @param  Illuminate\Http\Request  $request id= designation_id , type =(tab,field,button)
     * @return Illuminate\Http\JsonResponse
     */
    public function rightdata(Request $request, $id) {
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
                $listingData = Tabs::tabData($id);
            } else if ($request->get('type') == 'field') {
                $listingData = Dynamicfield::fieldData($id);
            } else if ($request->get('type') == 'button') {
                $listingData = Button::buttonData($id);
            }else if ($request->get('type') == 'worksheet') {
                $listingData = WorksheetStatus::worksheetData($id);
            }

            // all records 

            $listingData = $listingData->orderBy($sortBy, $sortOrder)->get();

            return createResponse(config('httpResponse.SUCCESS'), "Right list data.", ['data' => $listingData], $pager);
       } catch (\Exception $e) {
            app('log')->error("Right listing data failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing right data", ['error' => 'Server error.']);
        }
    }

    /**
     * update designation right details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // designation id , type =(tab,field,button)
     * @return Illuminate\Http\JsonResponse
     */
    public function updateRight(REQUEST $request, $id) {
        //Update rights        
        try {
            $validator = app('validator')->make($request->all(), [
                'type' => 'required|in:tab,field,button,worksheet',
                'data' => 'required|json'
                    ], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            $type = $request->get('type');
            $loginUser = loginUser();
            $arrRights = array();
            //showArray($request->get('data'));exit;
            $postValues = json_decode($request->input('data'), true);
            // showArray($postValues);exit;
            foreach ($postValues as $key => $tabFlag) {
                //showArray($tabFlag);exit;
                //Update button right value
                if ($type == 'button') {
                    if ($tabFlag['view'] != 0) {
                        $arrRight[$tabFlag['tab_id']][] = $tabFlag['id'];
                    } else {
                        $noRight[$tabFlag['tab_id']][] = $tabFlag['id'];
                    }
                }else if ($type == 'worksheet') {
                    $checkRight = \App\Models\Backend\DesignationWorksheetRight::checkRight($tabFlag['id'], $id);
                    if (empty($checkRight)) {
                        \App\Models\Backend\DesignationWorksheetRight::store($tabFlag['id'], $id, $tabFlag);
                    } else {
                        \App\Models\Backend\DesignationWorksheetRight::where('id', $checkRight->id)
                                ->update(['worksheet_status_id' => $tabFlag['id'],
                            'designation_id' => $id,
                            'right' => $tabFlag['view'],
                            'created_on' => date('Y-m-d H:i:s'),
                            'created_by' => loginUser()]);
                    }
                } else if ($type == 'tab') {
                    $checkRight = DesignationTabRight::checkRight($tabFlag['id'], $id);
                    if (empty($checkRight)) {
                        DesignationTabRight::store($tabFlag['id'], $id, $tabFlag);
                    } else {
                        DesignationTabRight::where('id', $checkRight->id)
                                ->update(['view' => $tabFlag['view'],
                                    'add_edit' => $tabFlag['add_edit'],
                                    'delete' => $tabFlag['delete'],
                                    'export' => $tabFlag['export'],
                                    'download' => $tabFlag['download'],
                                    'modified_on' => date('Y-m-d H:i:s'),
                                    'modified_by' => $loginUser]);
                    }
                } else if ($type == 'field') {
                    $checkRight = DesignationFieldRight::select("id")
                            ->where("field_id", "=", $tabFlag['id'])
                            ->where("designation_id", "=", $id);
                    if ($checkRight->count() == 0) {
                        DesignationFieldRight::store($tabFlag['id'], $id, $tabFlag);
                    } else {
                        $checkRight = $checkRight->first();
                        DesignationFieldRight::where('id', $checkRight->id)
                                ->update(['view' => $tabFlag['view'],
                                    'add_edit' => $tabFlag['add_edit'],
                                    'modified_on' => date('Y-m-d H:i:s'),
                                    'modified_by' => $loginUser]);
                    }
                }
            }
            //showArray($arrRight);
            //showArray($noRight);exit;
            if ($type == 'button') {
                if (!empty($arrRight)) {
                    foreach ($arrRight as $key => $val) {
                        $desTabRight = DesignationTabRight::checkRight($key, $id);
                        //update data
                        if (!empty($desTabRight)) {
                            DesignationTabRight::where('id', $desTabRight->id)
                                    ->update(['other_right' => implode(",", $val)]);
                        }
                    }
                }
                if (!empty($noRight)) {
                    foreach ($noRight as $key => $val) {
                        if (!isset($arrRight[$key])) {
                            $desTabRight = DesignationTabRight::checkRight($key, $id);
                            //update data
                            if (!empty($desTabRight)) {
                                DesignationTabRight::where('id', $desTabRight->id)
                                        ->update(['other_right' => '']);
                            }
                        }
                    }
                }
            }
            return createResponse(config('httpResponse.SUCCESS'), 'Designation ' . $type . ' right has been updated successfully', ['message' => 'Designation ' . $type . ' right has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Designation '.$type.' right updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update designation ' . $type . ' right details.', ['error' => 'Could not update designation ' . $type . ' right details.']);
        }
    }

}

?>
