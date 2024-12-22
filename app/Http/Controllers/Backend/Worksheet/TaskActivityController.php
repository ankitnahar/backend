<?php

namespace App\Http\Controllers\Backend\Worksheet;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Backend\TaskActivity;

class TaskActivityController extends Controller
{
   /**
     * Get Task Activity detail
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function index(Request $request) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'sortOrder'     => 'in:asc,desc',
                'pageNumber'    => 'numeric|min:1',
                'recordsPerPage'=> 'numeric|min:0',
                'search' => 'json'
                    ], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            // define soring parameters
            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'task.id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $task = TaskActivity::with('createdBy:userfullname as created_by,id','modifiedBy:userfullname as modified_by,id','masterActivityId:code,name as master_name,id');
            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $task = search($task, $search);
            }
            // for relation ship sorting
            if($sortBy =='created_by' || $sortBy =='modified_by'){                
                $task =$task->leftjoin("user as u","u.id","task.$sortBy");
                $sortBy = 'userfullname';
            }
            if($sortBy == 'master_name'){
                $task =$task->leftjoin("master_activity as m","m.id","task.master_activity_id");
                $sortBy ='m.name';
            }
            
            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $task = $task->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $task->count();

                $task = $task->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);
                //$task = $task->toSql();
                $task = $task->get(['task.*']);

                $filteredRecords = count($task);

                $pager = ['sortBy'   => $sortByName,
                    'sortOrder'      => $sortOrder,
                    'pageNumber'     => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords'   => $totalRecords,
                    'filteredRecords'=> $filteredRecords];
            }   
            //For download excel if excel =1
            if ($request->has('excel') && $request->get('excel') == 1) {
                //check export right
                $user_Designation = getLoginUserHierarchy();
                if ($user_Designation->designation_id != config('constant.SUPERADMIN')) {
                    $export = checkUserRights(loginUser(), 'task', 'export');
                    if ($export == false) {
                        return createResponse(config('httpResponse.UNAUTHORIZED'), config('message.UNAUTHORIZED'), ['error' => config('message.UNAUTHORIZED')]);
                    }
                }
                //format data in array 
                $data = $task->toArray();
                $column = array();
                $column[] = ['Sr.No','Master Activity Code', 'Master Activity name','Task Name','Due Date','Adhoc Worksheet','Active', 'Created on', 'Created By','Modified on', 'Modified By'];
                if (!empty($data)) {
                    $columnData = array();
                    $i = 1;
                    foreach ($data as $data) {
                        $columnData[] = $i;
                        $columnData[] = $data['master_activity_id']['code'];
                        $columnData[] = $data['master_activity_id']['master_name'];
                        $columnData[] = $data['name'];
                        $columnData[] = $data['fixed_duedate'];
                        $columnData[] = ($data['ask_repeat_task'] == 0) ? 'No' : 'Yes';
                        $columnData[] = ($data['is_active'] == 0) ? 'No' : 'Yes';
                        $columnData[] = dateFormat($data['created_on']);
                        $columnData[] = $data['created_by']['created_by'];
                        $columnData[] = dateFormat($data['modified_on']);
                        $columnData[] = $data['modified_by']['modified_by'];
                        $column[] = $columnData;
                        $columnData = array();
                        $i++;
                    }
                }
                return exportExcelsheet($column, 'TaskList', 'xlsx', 'A1:K1');
            }
            return createResponse(config('httpResponse.SUCCESS'), "Task list.", ['data' => $task], $pager);
        } catch (\Exception $e) {
            app('log')->error("Task listing failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Task", ['error' => 'Server error.']);
        }
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
                'master_activity_id' => 'numeric',
                'name' => 'required|unique:task',
                
                'is_active' => 'in:0,1',
                    ], ['name.unique' => "Task Name has already been taken"]);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            // store bank details
            $task = TaskActivity::create([
                        'master_activity_id' => $request->input('master_activity_id'),
                        'name' => $request->input('name'),
                        'is_active' => ($request->has('is_active')) ? $request->input('is_active') : '1',
                        'ask_repeat_task' => $request->input('ask_repeat_task'),
                        'is_complete_task_pop_required' => $request->input('is_complete_task_pop_required'),
                        'exclude_assignee' => $request->input('exclude_assignee'),
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => app('auth')->guard()->id()
            ]);

            return createResponse(config('httpResponse.SUCCESS'), 'Task has been added successfully', ['data' => $task]);
       } catch (\Exception $e) {
            app('log')->error("Task creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add task', ['error' => 'Could not add task']);
        }
    }

    /**
     * update task details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // task id
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        //try {
            $validator = app('validator')->make($request->all(), [
                'master_activity_id' => 'numeric',
                'name' => 'unique:task,name,'.$id,
                'is_active' =>  'in:0,1',
                    ], ['name.unique' => "Task name has already been taken"]);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
            $task = TaskActivity::find($id);

            if (!$task)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Task does not exist', ['error' => 'The Task does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $updateData = filterFields(['master_activity_id','name', 'is_active', 'ask_repeat_task', 'is_complete_task_pop_required', 'exclude_assignee'], $request);
            $updateData['modified_on'] = date('Y-m-d H:i:s');
            $updateData['modified_by'] = app('auth')->guard()->id();
            //update the details
            $task->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Task has been updated successfully', ['message' => 'Task has been updated successfully']);
//        } catch (\Exception $e) {
//            app('log')->error("Task updation failed : " . $e->getMessage());
//            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update task details.', ['error' => 'Could not update task details.']);
//        }
    }
   /**
     * get particular task details
     *
     * @param  int  $id   //task id
     * @return Illuminate\Http\JsonResponse
     */
    public function show($id) {
        try {
            $task = TaskActivity::with('createdBy:userfullname as created_by,id','modifiedBy:userfullname as modified_by,id','masterActivityId:code,name as master_name,id')->find($id);

            if (!isset($task))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The task does not exist', ['error' => 'The task does not exist']);

            //send bank information
            return createResponse(config('httpResponse.SUCCESS'), 'Task data', ['data' => $task]);
        } catch (\Exception $e) {
            app('log')->error("Task details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get task.', ['error' => 'Could not get task.']);
        }
    }
}
?>
