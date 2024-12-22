<?php

namespace App\Http\Controllers\Backend\Worksheet;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Backend\SubActivity;

class SubActivityController extends Controller {

    /**
     * Get Sub Activity detail
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function index(Request $request) {
        //try {
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
        $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'subactivity.id';
        $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
        $pager = [];

        $subActivity = SubActivity::with('createdBy:userfullname as created_by,id', 'modifiedBy:userfullname as modified_by,id', 'masterId:code,name as master_name,id', 'taskId:name as task_name,id');
        //for search
        if ($request->has('search')) {
            $search = $request->get('search');
            $subActivity = search($subActivity, $search);
        }
        // for relation ship sorting
        if ($sortBy == 'created_by' || $sortBy = 'modified_by') {
            $subActivity = $subActivity->leftjoin("user as u", "u.id", "subactivity.$sortBy");
            $sortBy = 'userfullname';
        }
        if ($sortBy == 'master_name') {
            $subActivity = $subActivity->leftjoin("master_activity as m", "m.id", "subactivity.master_id");
            $sortBy = 'm.name';
        }
        if ($sortBy == 'task_name') {
            $subActivity = $subActivity->leftjoin("task as t", "t.id", "subactivity.task_id");
            $sortBy = 't.name';
        }

        // Check if all records are requested 
        if ($request->has('records') && $request->get('records') == 'all') {
            $subActivity = $subActivity->orderBy($sortBy, $sortOrder)->get();
        } else { // Else return paginated records
            // Define pager parameters
            $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
            $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
            $skip = ($pageNumber - 1) * $recordsPerPage;
            $take = $recordsPerPage;
            //count number of total records
            $totalRecords = $subActivity->count();

            $subActivity = $subActivity->orderBy($sortBy, $sortOrder)
                    ->skip($skip)
                    ->take($take);

            $subActivity = $subActivity->get(['subactivity.*']);

            $filteredRecords = count($subActivity);

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
            $data = $subActivity->toArray();
            $column = array();
            $column[] = ['Sr.No', 'Master Activity', 'Task','Sub Activity Code', 'Sub Activity Name','Active', 'Modified on', 'Modified By'];
            if (!empty($data)) {
                $columnData = array();
                $i = 1;
                foreach ($data as $data) {
                    $columnData[] = $i;
                    $columnData[] = $data['master_id']['code'] . '-' . $data['master_id']['master_name'];
                    $columnData[] = $data['task_id']['task_name'];
                    $columnData[] = $data['subactivity_code'];
                    $columnData[] = $data['subactivity_name'];
                    $columnData[] = ($data['is_active'] == 0) ? 'No' : 'Yes';
                    $columnData[] = dateFormat($data['modified_on']);
                    $columnData[] = $data['modified_by']['modified_by'];
                    $column[] = $columnData;
                    $columnData = array();
                    $i++;
                }
            }
            return exportExcelsheet($column, 'Sub ActivityList', 'xlsx', 'A1:H1');
        }
        return createResponse(config('httpResponse.SUCCESS'), "Sub Activity list.", ['data' => $subActivity], $pager);
        /* } catch (\Exception $e) {
          app('log')->error("Sub Activity listing failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Sub Activity", ['error' => 'Server error.']);
          } */
    }

    /**
     * Store master activity details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'master_activity_id' => 'required|numeric',
                'task_id' => 'required|numeric',
                'subactivity_name' => 'required|unique:subactivity',
                'is_active' => 'in:0,1',
                    ], ['subactivity_name.unique' => "Sub Activity Name has already been taken"]);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            $subCode = SubActivity::orderBy("subactivity_code", "desc")->first();
            $subCodeVal = $subCode->subactivity_code + 1;
            // store bank details
            $loginUser = loginUser();
            $subactivity = SubActivity::create([
                        'master_id' => $request->input('master_activity_id'),
                        'task_id' => $request->input('task_id'),
                        'subactivity_code' => $subCodeVal,
                        'subactivity_name' => $request->input('subactivity_name'),
                        'subactivity_full_name' => $subCodeVal . '-' . $request->input('subactivity_name'),
                        'is_active' => ($request->has('is_active')) ? $request->input('is_active') : '1'
            ]);

            return createResponse(config('httpResponse.SUCCESS'), 'Sub Activity has been added successfully', ['data' => $subactivity]);
        } catch (\Exception $e) {
            app('log')->error("Sub Activity creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add sub activity', ['error' => 'Could not add sub activity']);
        }
    }

    /**
     * update sub activity details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // sub activity id
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        // try {
        $validator = app('validator')->make($request->all(), [
            'master_activity_id' => 'numeric',
            'task_id' => 'numeric',
            'subactivity_name' => 'unique:subactivity,subactivity_name,' . $id,
            'is_active' => 'in:0,1'
                ], ['subactivity_name.unique' => "Sub Activity name has already been taken"]);

        // If validation fails then return error response
        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
        $subactivity = SubActivity::find($id);

        if (!$subactivity)
            return createResponse(config('httpResponse.NOT_FOUND'), 'Sub Activity does not exist', ['error' => 'The Sub Activity does not exist']);

        $updateData = array();
        // Filter the fields which need to be updated
        $loginUser = loginUser();
        $updateData = filterFields(['master_id', 'task_id', 'subactivity_name', 'is_active'], $request);
        $updateData['subactivity_full_name'] = $subactivity->subactivity_code . '-' . $updateData['subactivity_name'];
        $updateData['modified_on'] = date('Y-m-d H:i:s');
        $updateData['modified_by'] = $loginUser;
        //update the details
        $subactivity->update($updateData);

        return createResponse(config('httpResponse.SUCCESS'), 'Sub Activity has been updated successfully', ['message' => 'Sub Activity has been updated successfully']);
        /*  } catch (\Exception $e) {
          app('log')->error("Sub Activity updation failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update sub activity details.', ['error' => 'Could not update sub activity details.']);
          } */
    }

    /**
     * get particular sub activity details
     *
     * @param  int  $id   //sub activity id
     * @return Illuminate\Http\JsonResponse
     */
    public function show($id) {
        try {
            $subactivity = SubActivity::with('createdBy:userfullname as created_by,id', 'modifiedBy:userfullname as modified_by,id', 'masterId:code,name as master_name,id', 'taskId:name as task_name,id')->find($id);

            if (!isset($subactivity))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Sub Activity does not exist', ['error' => 'The Sub Activity does not exist']);

            //send bank information
            return createResponse(config('httpResponse.SUCCESS'), 'Sub Activity data', ['data' => $subactivity]);
        } catch (\Exception $e) {
            app('log')->error("Sub Activity details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get master activity.', ['error' => 'Could not get master activity.']);
        }
    }

    /**
     * delete particular master activity details
     *
     * @param  int  $id   //master activity id
     * @return Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, $id) {
        try {
            $subactivity = SubActivity::find($id);
            // Check weather bank exists or not
            if (!isset($subactivity))
                return createResponse(config('httpResponse.NOT_FOUND'), 'Sub Activity does not exist', ['error' => 'Sub Activity does not exist']);

            $subactivity->delete();

            return createResponse(config('httpResponse.SUCCESS'), 'Sub Activity has been deleted successfully', ['message' => 'Sub Activity has been deleted successfully']);
        } catch (\Exception $e) {
            app('log')->error("Sub Activity deletion failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not delete sub activity.', ['error' => 'Could not delete sub activity.']);
        }
    }

}

?>