<?php

namespace App\Http\Controllers\Backend\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Backend\HrNojob;

class DailyReportController extends Controller {
    /* Created by: Jayesh Shingrakhiya
     * Created on: April 22, 2018
     * Purpose   : Fetch no job data
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


        $teamUserRight = checkButtonRights(156, 'dailyreportteamright');
        $allUserRight  = checkButtonRights(156, 'dailyreportallright');
        $userList = $userId = array();
        if ($allUserRight == 1) {
            //$userData = \App\Models\User::where('is_active', 1)->select(app('db')->raw('id as user_id'), 'userfullname')->get()->toArray();
            $userData = \App\Models\User::select(app('db')->raw('id as user_id'), 'userfullname')->get()->toArray();
        } else if ($teamUserRight == 1) {
            $id = app('auth')->guard()->id();
            $userData = \App\Models\User::select(app('db')->raw('id as user_id'), 'userfullname')->where('first_approval_user', $id)->Orwhere('second_approval_user', $id)->Orwhere('id', $id)->get()->toArray();
            $userId[] = $id;
        } else {
            $userData[] = array('user_id'=> app('auth')->guard()->id(), 'userfullname' => app('auth')->guard()->user()->userfullname);
        }        

        foreach ($userData as $key => $value) {
            $value = (array)$value;
            $userId[] = $value['user_id'];
            $userList[] = array('id' => $value['user_id'], 'userfullname' => $value['userfullname']);
        }

        // define soring parameters
        $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'hr_detail.id';
        $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'desc';
        $pager = [];    

//            $dailyReport = \App\Models\Backend\HrDetail::select('hr_detail.*', app('db')->raw('MIN(huiot.punch_time) as first_in'), app('db')->raw('MAX(huiot.punch_time) last_out'))->with('assignee:id,userfullname', 'inout')->leftJoin('hr_user_in_out_time as huiot', 'hr_detail.id', '=', 'huiot.hr_detail_id');

        $dailyReport = \App\Models\Backend\HrDetail::select('hr_detail.*', app('db')->raw('(SELECT SUM(units) FROM timesheet WHERE hr_detail_id = `hr_detail`.`id` ) AS totalUnit'))->with('assignee:id,userfullname', 'inout')->leftjoin('timesheet AS t', 't.hr_detail_id', '=', 'hr_detail.id')->join('hr_user_in_out_time AS ht', 'ht.hr_detail_id', '=', 'hr_detail.id');
        if($allUserRight !=1){
        $dailyReport =$dailyReport->whereRaw('hr_detail.user_id IN ('.implode(",",$userId).')');
        }
        if ($sortBy == 'user_id') {
            $dailyReport = $dailyReport->leftjoin("user as u", "u.id", "hr_detail.$sortBy");
            $sortBy = 'userfullname';
        }

        $userId = '';
        if ($request->has('search')) {
            $search = $request->get('search');
            $alias = array('user_id' => 'hr_detail', 'date' => 'hr_detail');
            $dailyReport = search($dailyReport, $search, $alias);
        }

        $dailyReport = $dailyReport->groupBy('user_id', 'date');
        // Check if all records are requested
        if ($request->has('records') && $request->get('records') == 'all') {
            $dailyReport = $dailyReport->orderBy($sortBy, $sortOrder)->get();
        } else { // Else return paginated records
            // Define pager parameters
            $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
            $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
            $skip = ($pageNumber - 1) * $recordsPerPage;
            $take = $recordsPerPage;

            //count number of total records
            //$totalRecords = $dailyReport->count();
            $totalRecords = count($dailyReport->get());

            $dailyReport = $dailyReport->orderBy($sortBy, $sortOrder)
                    ->skip($skip)
                    ->take($take);

            $dailyReport = $dailyReport->get();
            $filteredRecords = count($dailyReport);

            $pager = ['sortBy' => $request->get('sortBy'),
                'sortOrder' => $sortOrder,
                'pageNumber' => $pageNumber,
                'recordsPerPage' => $recordsPerPage,
                'totalRecords' => $totalRecords,
                'filteredRecords' => $filteredRecords];
        }

        // $dailyReport = \App\Models\Backend\HrDetail::arrangeDailyReportData($dailyReport);
        if ($request->has('excel') && $request->get('excel') == 1) {
            $data = $dailyReport->toArray();
            $column = array();
            $column[] = ['Sr.No', 'Staff name', 'Date', 'First in time', 'Last out time', 'Working time', 'Break time', 'Total units'];
            if (!empty($data)) {
                $columnData = array();
                $i = 1;
                foreach ($data as $data) {
                    $columnData[] = $i;
                    $columnData[] = isset($data['assignee']['userfullname']) ? $data['assignee']['userfullname'] : '-';
                    $columnData[] = dateFormat($data['date']);
                    $columnData[] = $data['punch_in'];
                    $columnData[] = $data['punch_out'];
                    $columnData[] = $data['working_time'];
                    $columnData[] = $data['break_time'];
                    $columnData[] = $data['totalUnit'];
                    $column[] = $columnData;
                    $columnData = array();
                    $i++;
                }
            }
            return exportExcelsheet($column, 'daily report', 'xlsx', 'A1:H1');
        }

        return createResponse(config('httpResponse.SUCCESS'), "Daily report list.", ['data' => $dailyReport, 'userlist' => $userList], $pager);
//        } catch (\Exception $e) {
//            app('log')->error("No job listing failed : " . $e->getMessage());
//
//            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing no job", ['error' => 'Server error.']);
//        }
    }

    public function userList() {
        try {

            $userList = app('db')->select("CALL get_hierarchy_of_user($designationId)");

            return createResponse(config('httpResponse.SUCCESS'), "Daily report user list.", ['data' => $userList]);
        } catch (\Exception $e) {
            app('log')->error("User listing failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing user listing", ['error' => 'Server error.']);
        }
    }

}
