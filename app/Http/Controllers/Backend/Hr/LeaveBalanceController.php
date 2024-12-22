<?php

namespace App\Http\Controllers\Backend\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LeaveBalanceController extends Controller {

    /**
     * Get Holiday detail
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
        $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'hr_leave_balance.id';
        $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'desc';
        $pager = [];

        $holiday = \App\Models\Backend\HrLeaveBalance::leftjoin("user as u", "u.id", "hr_leave_balance.user_id")
                ->select("u.user_bio_id", "u.userfullname", "hr_leave_balance.*");
        //for search
        if ($request->has('search')) {
            $search = $request->get('search');
            $holiday = search($holiday, $search);
        }
        // for relation ship sorting
        if ($sortBy == 'created_by' || $sortBy == 'modified_by') {
            $holiday = $holiday->leftjoin("user as u", "u.id", "hr_leave_balance.$sortBy");
            $sortBy = 'userfullname';
        }

        // Check if all records are requested 
        if ($request->has('records') && $request->get('records') == 'all') {
            $holiday = $holiday->orderBy($sortBy, $sortOrder)->get();
        } else { // Else return paginated records
            // Define pager parameters
            $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
            $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
            $skip = ($pageNumber - 1) * $recordsPerPage;
            $take = $recordsPerPage;
            //count number of total records
            $totalRecords = $holiday->count();

            $holiday = $holiday->orderBy($sortBy, $sortOrder)
                    ->skip($skip)
                    ->take($take);

            $holiday = $holiday->get();

            $filteredRecords = count($holiday);

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
            $data = $holiday->toArray();
            $column = array();
            $column[] = ['Sr.No', 'User Bio Id', 'User Full name','Month', 'CL','CO','LA'];
            if (!empty($data)) {
                $columnData = array();
                $i = 1;
                foreach ($data as $data) {
                    $columnData[] = $i;
                    $columnData[] = $data['user_bio_id'];
                    $columnData[] = $data['userfullname'];
                    $columnData[] = $data['month'];
                    $columnData[] = $data['cl'];
                    $columnData[] = $data['co'];
                    $columnData[] = $data['la'];
                    $column[] = $columnData;
                    $columnData = array();
                    $i++;
                }
            }
            return exportExcelsheet($column, 'LeaveBalanceList', 'xlsx', 'A1:F1');
        }
        return createResponse(config('httpResponse.SUCCESS'), "Leave Balance list.", ['data' => $holiday], $pager);
        /* } catch (\Exception $e) {
          app('log')->error("Holiday listing failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Holiday", ['error' => 'Server error.']);
          } */
    }

    public function storeleavewithcsv(Request $request) {
        // try {
        //validate request parameters
        $validator = app('validator')->make($request->all(), [
            'upload' => 'required|mimes:csv,vnd.ms-excel,txt',
        ]);

        // If validation fails then return error response
        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);
        //Get current user detail
        $loginUser = loginUser();

        $file = $request->file('upload');
        if (!empty($file)) {
            $filename = $file->getPathname();
            $row = 1;
            if (($handle = fopen($filename, "r")) !== FALSE) {
                $i = 0;
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $num = count($data);
                    $row++;

                    for ($c = 0; $c < $num; $c++) {
                        $user_id = "";
                        if($c ==0 ){
                            continue;
                        }
                        if (isset($data[0])) {
                            $userbioid = rtrim($data[0]);
                            $user = \App\Models\User::select("id")->where("user_bio_id", $userbioid)->first();
                            $user_id = $user->id;  
                        }
                        $month = "";
                        if (isset($data[1])) {
                            $month = rtrim($data[1]);
                        }
                        $cl = "";
                        if (isset($data[2])) {
                            $cl = rtrim($data[2]);
                        }
                        $co = "";
                        if (isset($data[3])) {
                            $co = rtrim($data[3]);
                        }
                        $la = "";
                        if (isset($data[4])) {
                            $la = rtrim($data[4]);
                        }
                    }
                    if ($user_id > 0) {
                        $checkholiday = \App\Models\Backend\HrLeaveBalance::where("user_id", $user_id)->where("month", $month);
                        if ($checkholiday->count() == 0) {
                            $holidayList = \App\Models\Backend\HrLeaveBalance::create([
                                        'user_id' => $user_id,
                                        'month' => $month,
                                        'cl' => $cl,
                                        'co' => $co,
                                        'la' => $la,
                                        'created_on' => date('Y-m-d H:i:s'),
                                        'created_by' => $loginUser
                            ]);
                        } else {
                            \App\Models\Backend\HrLeaveBalance::where("user_id", $user_id)->where("month", $month)->update(['cl' => $cl,
                                        'co' => $co,
                                        'la' => $la]);
                        }
                    }
                }
                fclose($handle);
            }
        }

        return createResponse(config('httpResponse.SUCCESS'), 'Leave Balance List has been added successfully.', ['message' => 'Leave Balance List has been added successfully.']);
        /* } catch (\Exception $e) {
          app('log')->error("Email List failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add Email List.', ['error' => 'Could not add Email List.']);
          } */
    }

    /**
     * Store holiday details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request) {
        //  try {
        //validate request parameters
        $validator = app('validator')->make($request->all(), [
            'user_id' => 'required',
            'leave_balance' => 'required'
                ], []);

        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

        // store holiday details
        $loginUser = loginUser();
        $holiday = \App\Models\Backend\HrLeaveBalance::create([
                    'user_id' => $request->input('user_id'),
                    'leave_balance' => $request->input('leave_balance'),
                    'created_on' => date('Y-m-d H:i:s'),
                    'created_by' => $loginUser,
                    'modified_on' => date('Y-m-d H:i:s'),
                    'modified_by' => $loginUser
        ]);

        return createResponse(config('httpResponse.SUCCESS'), 'Leave Balance has been added successfully', ['data' => $holiday]);
        /* } catch (\Exception $e) {
          app('log')->error("Holiday creation failed " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add holiday', ['error' => 'Could not add holiday']);
          } */
    }

    /**
     * update Holiday details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // holiday id
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        //  try {
        $holiday = \App\Models\Backend\HrLeaveBalance::find($id);

        if (!$holiday)
            return createResponse(config('httpResponse.NOT_FOUND'), 'Leave Balance does not exist', ['error' => 'The Leave Balance does not exist']);

        $updateData = array();
        // Filter the fields which need to be updated
        $loginUser = loginUser();
        $updateData['month'] = $request->input('month');
        $updateData['cl'] = $request->input('cl');
        $updateData['co'] = $request->input('co');
        $updateData['la'] = $request->input('la');
        $updateData['modified_on'] = date('Y-m-d H:i:s');
        $updateData['modified_by'] = $loginUser;
        //update the details
        $holiday->update($updateData);

        return createResponse(config('httpResponse.SUCCESS'), 'Leave Balance has been updated successfully', ['message' => 'Leave Balance has been updated successfully']);
        /* } catch (\Exception $e) {
          app('log')->error("Holiday updation failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update holiday details.', ['error' => 'Could not update holiday details.']);
          } */
    }

    /**
     * get particular holiday details
     *
     * @param  int  $id   //holiday id
     * @return Illuminate\Http\JsonResponse
     */
    public function show($id) {
        try {
            $holiday = \App\Models\Backend\HrLeaveBalance::with('created_by:userfullname as created_by,id', 'modified_by:userfullname as modified_by,id')->find($id);

            if (!isset($holiday))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Leave Balance does not exist', ['error' => 'The Leave Balance does not exist']);

            //send holiday information
            return createResponse(config('httpResponse.SUCCESS'), 'Leave Balance data', ['data' => $holiday]);
        } catch (\Exception $e) {
            app('log')->error("Leave Balance details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get Leave Balance.', ['error' => 'Could not get Leave Balance.']);
        }
    }

}
