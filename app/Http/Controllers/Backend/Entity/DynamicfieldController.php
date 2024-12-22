<?php

namespace App\Http\Controllers\Backend\Entity;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Backend\Dynamicfield;

class DynamicfieldController extends Controller {

    /**
     * Get Dynamic field detail
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function index(Request $request) {
       // try {
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
            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'dynamic_field.id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $Dynamicfield = Dynamicfield::getDynamicFieldListing();
            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                 $alias = array("is_active" => "dynamic_field");
                $Dynamicfield = search($Dynamicfield, $search,$alias);
            }

            // for relation ship sorting
            if ($sortBy == 'created_by' || $sortBy == 'modified_by') {
                $Dynamicfield = $Dynamicfield->leftjoin("user as u", "u.id", "dynamic_field.$sortBy");
                $sortBy = 'userfullname';
            }
            if ($sortBy == 'group_name') {
                $Dynamicfield = $Dynamicfield->leftjoin("dynamic_group as dg", "dg.id", "dynamic_field.group_id");
            }
            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $Dynamicfield = $Dynamicfield->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $Dynamicfield->count();

                $Dynamicfield = $Dynamicfield->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $Dynamicfield = $Dynamicfield->get(['dynamic_field.*']);

                $filteredRecords = count($Dynamicfield);

                $pager = ['sortBy' => $sortByName,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }
            //For download excel if excel =1
            if ($request->has('excel') && $request->get('excel') == 1) {
                //format data in array 
                $data = $Dynamicfield->toArray();
                $column = array();
                $column[] = ['Sr.No','Group name', 'Field name', 'Field title', 'Field value','Field value type','Field parent condition', 'Is Manadatory','Is Active', 'Created on', 'Created by', 'Modified On', 'Modified By'];
                if (!empty($data)) {
                    $columnData = array();
                    $i = 1;
                    foreach ($data as $data) {
                        $columnData[] = $i;
                        $columnData[] = $data['group_id']['group_name'];
                        $columnData[] = $data['field_name'];
                        $columnData[] = $data['field_title'];
                        $columnData[] = $data['field_value'];
                        $columnData[] = $data['field_value_type'];
                        $columnData[] = $data['field_parent_condition'];
                        $columnData[] = $data['is_mandatory'];
                        $columnData[] = ($data['is_active'] == 1) ? 'Yes':'No';
                        $columnData[] = $data['created_on'];
                        $columnData[] = $data['created_by']['created_by'];
                        $columnData[] = $data['modified_on'];
                        $columnData[] = $data['modified_by']['modified_by'];
                        $column[] = $columnData;
                        $columnData = array();
                        $i++;
                    }
                }
                return exportExcelsheet($column, 'DynamicField', 'xlsx', 'A1:M1');
            }
            return createResponse(config('httpResponse.SUCCESS'), "Dynamic list.", ['data' => $Dynamicfield], $pager);
       /* } catch (\Exception $e) {
            app('log')->error("Dynamic listing failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing dynamic", ['error' => 'Server error.']);
        }*/
    }

    /**
     * Store dynamic field details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'field_title' => 'required',
                'group_id' => 'required|numeric',
                'field_type' => 'required|in:TB,TA,DD,CL,MV',
                'sort_order' => 'numeric',
                'is_active' => 'required|in:0,1',
                'is_mandatory' => 'required|in:0,1',
                'field_value_type' => 'required_if:field_type,TB|in:A,N,B,',
                'field_value' => 'required_if:field_type,DD',
                    ], [
                'field_value_type.required_if' => 'The value accepted field is required']);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            // store dynamic field details
            $loginUser = loginUser();
            $dynamicField = Dynamicfield::create([
                        'group_id' => $request->input('group_id'),
                        'field_name' => strtolower(str_replace(' ', '_', $request->input('field_title'))),
                        'field_title' => $request->input('field_title'),
                        'field_value' => $request->input('field_value'),
                        'field_type' => $request->input('field_type'),
                        'is_mandatory' => $request->input('is_mandatory'),
                        'field_length' => $request->input('field_length'),
                        'field_value_type' => $request->input('field_value_type'),
                        'help_text' => $request->input('help_text'),
                        'sort_order' => $request->input('sort_order'),
                        'is_active' => $request->input('is_active'),
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => $loginUser,
                        'modified_on' => date('Y-m-d H:i:s'),
                        'modified_by' => $loginUser
            ]);

            return createResponse(config('httpResponse.SUCCESS'), 'Dynamic Field has been added successfully', ['data' => $dynamicField]);
        } catch (\Exception $e) {
            app('log')->error("Dynamic Field creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add Dynamic field', ['error' => 'Could not add Dynamic Field']);
        }
    }

    /**
     * update dynamic field details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // dynamic field id
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'group_id' => 'numeric',
                'field_type' => 'in:TB,TA,DD,CL,MV',
                'sort_order' => 'numeric',
                'is_active' => 'in:0,1',
                'field_parent_condition' => 'numeric',
                'field_value_type' => 'in:A,N,B|required_if:field_type,TB',
                'field_value' => 'required_if:field_type,DD',
                    ], [
                 'field_value_type.required_if' => 'The value accepted field is required']);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
            $Dynamicfield = Dynamicfield::find($id);

            if (!$Dynamicfield)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Dynamic Field does not exist', ['error' => 'The Dynamic field does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $loginUser = loginUser();
            $updateData = filterFields(['field_title', 'group_id', 'help_text', 'sort_order', 'is_active', 'is_mandatory', 'field_value_type', 'field_value'], $request);
            $updateData['modified_on'] = date('Y-m-d H:i:s');
            $updateData['modified_by'] = $loginUser;
            //update the details
            $Dynamicfield->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Dynamic Field has been updated successfully', ['message' => 'Dynamic Field has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Dynamic Field updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update dynamic field details.', ['error' => 'Could not update dynamic field details.']);
        }
    }

    /**
     * get particular dynamic field details
     *
     * @param  int  $id   //dynamic field id
     * @return Illuminate\Http\JsonResponse
     */
    public function show($id) {
        try {
            $dynamicfield = Dynamicfield::find($id);

            if (!isset($dynamicfield))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The dynamic field does not exist', ['error' => 'The dynamic field does not exist']);

            //send dynamic group information
            return createResponse(config('httpResponse.SUCCESS'), 'Dynamic field data', ['data' => $dynamicfield]);
        } catch (\Exception $e) {
            app('log')->error("Dynamic Field details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get dynamic field.', ['error' => 'Could not get dynamic field.']);
        }
    }

    public function destroy(Request $request, $id) {
        try {
            $dynamicfield = Dynamicfield::find($id);
            // Check weather dynamic group exists or not
            if (!isset($dynamicfield))
                return createResponse(config('httpResponse.NOT_FOUND'), 'Dynamic Field does not exist', ['error' => 'Dynamic Field does not exist']);

            $dynamicfield->delete();

            return createResponse(config('httpResponse.SUCCESS'), 'Dynamic Field has been deleted successfully', ['message' => 'Dynamic Field has been deleted successfully']);
        } catch (\Exception $e) {
            app('log')->error("Dynamic Field deletion failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not delete dynamic field.', ['error' => 'Could not delete dynamic field.']);
        }
    }

    /**
     * Get Dynamic field detail
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function getGroupWiseDynamicField(Request $request, $id) {
        try {
        $validator = app('validator')->make($request->all(), [
            'view' => 'in:0,1',
            'add_edit' => 'in:0,1'
                ], []);

        if ($validator->fails()) // Return error message if validation fails
            return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

        $dynamicgroup = \App\Models\Backend\Dynamicgroup::find($id);
        if (!isset($dynamicgroup))
            return createResponse(config('httpResponse.NOT_FOUND'), 'Dynamic Group does not exist', ['error' => 'Dynamic Group does not exist']);

        $FieldListing = array();
        //Check user wise field  
        $user = getLoginUserHierarchy();
        if ($user->designation_id != config('constant.SUPERADMIN')) {
            $Dynamicfield = \App\Models\Backend\UserFieldRight::getGroupfieldData($user->user_id, $id);
            if ($request->has('view')) {
                $Dynamicfield = $Dynamicfield->where("user_field_right.view", $request->input('view'));
            }
            if ($request->has('add_edit')) {
                $Dynamicfield = $Dynamicfield->where("user_field_right.add_edit", $request->input('add_edit'));
            }
        } else {
            $Dynamicfield = Dynamicfield::where("group_id", $dynamicgroup->id)->where("is_active","1")->where("disable", "0");
            $view = 1;
            $add_edit = 1;
        }
        if (!isset($Dynamicfield))
            return createResponse(config('httpResponse.NOT_FOUND'), 'The dynamic field listing does not exist', ['error' => 'The dynamic field listing does not exist']);

        // Check if all records are requested 

        $Dynamicfield = $Dynamicfield->orderBy('dynamic_field.id')->get();

        //create array for dynamic field list
        $FieldListing['group_id'] = $id;
        $FieldListing['group_name'] = $dynamicgroup->group_name;
        foreach ($Dynamicfield as $field) {

            if ($user->designation_id != config('constant.SUPERADMIN')) {
                $view = $field->view;
                $add_edit = $field->add_edit;
            }
            $field['view'] = $view;
            $field['add_edit'] = $add_edit;
            $FieldListing['fields'][] = $field;
        }

        if (!isset($FieldListing))
            return createResponse(config('httpResponse.NOT_FOUND'), 'The dynamic field listing does not exist', ['error' => 'The dynamic field listing does not exist']);

        //send dynamic group information
        return createResponse(config('httpResponse.SUCCESS'), 'Dynamic field listing data', ['data' => $FieldListing]);
         } catch (\Exception $e) {
          app('log')->error("Dynamic Field listing details api failed : " . $e->getMessage());

          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get dynamic field listing.', ['error' => 'Could not get dynamic field listing.']);
          } 
    }

}
