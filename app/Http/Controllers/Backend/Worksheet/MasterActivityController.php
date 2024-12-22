<?php
namespace App\Http\Controllers\Backend\Worksheet;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Backend\MasterActivity;

class MasterActivityController extends Controller {

   /**
     * Get Master Activity detail
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function index(Request $request) {
        //try {
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
            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'master_activity.id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $masterActivity = MasterActivity::with('createdBy:userfullname as created_by,id','modifiedBy:userfullname as modified_by,id','serviceId:service_name,id');
            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $alias = array('created_by'=>'master_activity');
                $masterActivity = search($masterActivity, $search, $alias);
            }
            // for relation ship sorting
            if($sortBy =='created_by' || $sortBy == 'modified_by'){                
                $masterActivity =$masterActivity->leftjoin("user as u","u.id","master_activity.$sortBy");
                $sortBy = 'userfullname';
            }
            
            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $masterActivity = $masterActivity->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $masterActivity->count();

                $masterActivity = $masterActivity->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $masterActivity = $masterActivity->get(['master_activity.*']);

                $filteredRecords = count($masterActivity);

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
                    $export = checkUserRights(loginUser(), 'masteractivity', 'export');
                    if ($export == false) {
                        return createResponse(config('httpResponse.UNAUTHORIZED'), config('message.UNAUTHORIZED'), ['error' => config('message.UNAUTHORIZED')]);
                    }
                }
                $team = \App\Models\Backend\Team::select("team_name","id")->get()->pluck("team_name","id")->toArray();
                //format data in array 
                $data = $masterActivity->toArray();
                $column = array();
                $column[] = ['Sr.No','Master Activity Code', 'Master Activity name','Associated Team','In Schedule','Active', 'Created on', 'Created By','Modified on', 'Modified By'];
                if (!empty($data)) {
                    $columnData = array();
                    $i = 1;
                    foreach ($data as $data) {
                        $teamList ='';
                        if($data['user_team_id'] !=''){
                            $userTeam = explode(",",$data['user_team_id']);
                            for($t=0;$t<count($userTeam);$t++){
                                $teamName = isset($team[$userTeam[$t]]) ? $team[$userTeam[$t]] : '';
                                if($t==0){                                    
                                $teamList = $teamName;
                                }else if($teamName!=''){
                                  $teamList = $teamList.','.$teamName;  
                                }
                            }
                        }
                        $columnData[] = $i;
                        $columnData[] = $data['code'];
                        $columnData[] = $data['name'];
                        $columnData[] = $teamList;
                        $columnData[] = ($data['inschedule'] ==0) ? 'No' :'Yes';
                        $columnData[] = ($data['is_active'] ==0) ? 'No' :'Yes';
                        $columnData[] = dateFormat($data['created_on']);
                        $columnData[] = $data['created_by']['created_by'] != ''?$data['created_by']['created_by']:'-'; 
                        $columnData[] = dateFormat($data['modified_on']);
                        $columnData[] = $data['modified_by']['modified_by'] != ''?$data['modified_by']['modified_by']:'-'; 
                        $column[] = $columnData;
                        $columnData = array();
                        $i++;
                    }
                }
                return exportExcelsheet($column, 'MasterActivityList', 'xlsx', 'A1:J1');
            }
            return createResponse(config('httpResponse.SUCCESS'), "Master Activity list.", ['data' => $masterActivity], $pager);
        /*} catch (\Exception $e) {
            app('log')->error("Master Activity listing failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Master Activity", ['error' => 'Server error.']);
        }*/
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
                'name' => 'required|unique:master_activity',
                'team_id' =>'required',
                'is_active' => 'in:0,1',
                    ], ['name.unique' => "Master Activity Name has already been taken"]);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            // get last value for code generate
            $masterCode = MasterActivity::orderBy("code","desc")->first();
            $masterCodeVal = $masterCode->code +1;
            // store bank details
            $masterActivity = MasterActivity::create([
                        'code'       => $masterCodeVal,
                        'name'       => $request->get('name'),
                        'user_team_id' => $request->get('team_id'),
                        'inschedule' => $request->get('inschedule'),
                        'is_active'  => $request->get('is_active'),
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => app('auth')->guard()->id()
            ]);

            return createResponse(config('httpResponse.SUCCESS'), 'Master Activity has been added successfully', ['data' => $masterActivity]);
       } catch (\Exception $e) {
            app('log')->error("Master Activity creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add master activity', ['error' => 'Could not add master activity']);
        }
    }

    /**
     * update master activity details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // master activity id
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'name' => 'required|unique:master_activity,name,'.$id,
                'team_id' => 'required',                
                'is_active'  => 'in:0,1',
                    ], ['name.unique' => "Master Activity name has already been taken"]);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
            $masterActivity = MasterActivity::find($id);

            if (!$masterActivity)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Master Activity does not exist', ['error' => 'The Master Activity does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $loginUser = loginUser();
            $updateData = filterFields(['name', 'is_active', 'inschedule'], $request);
            $updateData['user_team_id'] = $request->get('team_id');
            $updateData['modified_on'] = date('Y-m-d H:i:s');
            $updateData['modified_by'] = $loginUser;
            //update the details
            $masterActivity->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Master Activity has been updated successfully', ['message' => 'Master Activity has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Master Activity updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update master activity details.', ['error' => 'Could not update master activity details.']);
        }
    }
   /**
     * get particular bank details
     *
     * @param  int  $id   //bank id
     * @return Illuminate\Http\JsonResponse
     */
    public function show($id) {
        try {
            $masterActivity = MasterActivity::with('createdBy:userfullname as created_by,id','modifiedBy:userfullname as modified_by,id','serviceId:service_name,id')->find($id);

            if (!isset($masterActivity))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Master Activity does not exist', ['error' => 'The Master Activity does not exist']);

            //send bank information
            return createResponse(config('httpResponse.SUCCESS'), 'Master Activity data', ['data' => $masterActivity]);
        } catch (\Exception $e) {
            app('log')->error("Master Activity details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get master activity.', ['error' => 'Could not get master activity.']);
        }
    }   
    
}
?>