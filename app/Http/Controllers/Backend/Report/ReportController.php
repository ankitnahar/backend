<?php

namespace App\Http\Controllers\Backend\Report;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Backend\ReportSaved;

class ReportController extends Controller {

    /**
     * Created by: Jayesh Shingrakhiya
     * Created on: July 30, 2018
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     * Reason: Display all reports listing
     */
    public function index(Request $request, $id) {
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
        $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'report_saved.id';
        $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
        $pager = [];

        $reportSaved = ReportSaved::select('report_saved.*', app('db')->raw('report_saved.id as report_saved_id'))->where('tab_id', $id)->with('created_by:id,userfullname')->with('shared_user:id,report_saved_id,user_id');

        $loginUser = loginUser();
        $user = getLoginUserHierarchy();
        if ($user->designation_id != config('constant.SUPERADMIN')) {
            $reportSaved = $reportSaved->leftjoin('report_shared as rs', 'rs.report_saved_id', '=', 'report_saved.id');
            $reportSaved = $reportSaved->whereRaw('(rs.user_id = '. app('auth')->guard()->id() .' OR report_saved.created_by = '.app('auth')->guard()->id().')');
        }

        if ($request->has('search')) {
            $search = $request->get('search');
            $reportSaved = search($reportSaved, $search);
        }
        
        $reportSaved = $reportSaved->groupBy('report_saved.id');
        // Check if all records are requested 
        if ($request->has('records') && $request->get('records') == 'all') {
            $reportSaved = $reportSaved->orderBy($sortBy, $sortOrder)->get();
        } else { // Else return paginated records
            // Define pager parameters
            $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
            $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
            $skip = ($pageNumber - 1) * $recordsPerPage;
            $take = $recordsPerPage;

            //count number of total records
            $totalRecords = $reportSaved->count();

            $reportSaved = $reportSaved->orderBy($sortBy, $sortOrder)
                    ->skip($skip)
                    ->take($take);

            $reportSaved = $reportSaved->get();

            $filteredRecords = count($reportSaved);

            $pager = ['sortBy' => $sortBy,
                'sortOrder' => $sortOrder,
                'pageNumber' => $pageNumber,
                'recordsPerPage' => $recordsPerPage,
                'totalRecords' => $totalRecords,
                'filteredRecords' => $filteredRecords];
        }

        //$reportSaved = ReportSaved::arrageData($reportSaved);
        return createResponse(config('httpResponse.SUCCESS'), "Saved Report.", ['data' => $reportSaved], $pager);
//        } catch (\Exception $e) {
//            app('log')->error("Report listing failed : " . $e->getMessage());
//
//            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing saved report", ['error' => 'Server error.']);
//        }
    }

    /**
     * Created by: Jayesh Shingrakhiya
     * Created on: July 30, 2018
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     * Reason: Add all reports
     */
    public function store(Request $request, $id) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'tab_id' => 'required|numeric',
                'name' => 'required|unique:report_saved,name',
                'filter_condition_value' => 'json',
                'output' => 'required',
                'orderby' => 'json'], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            $report = ReportSaved::create([
                        'tab_id' => $request->get('tab_id'),
                        'name' => $request->get('name'),
                        'filter_condition_value' => $request->get('filter_condition_value'),
                        'output' => $request->get('output'),
                        'filter_output_field' => $request->get('filter_output_field'),
                        'groupby' => $request->get('groupby'),
                        'orderby' => $request->get('orderby'),
                        'created_by' => app('auth')->guard()->id(),
                        'created_on' => date('Y-m-d H:i:s'),
            ]);

            return createResponse(config('httpResponse.SUCCESS'), 'Report has been added successfully', ['data' => $report]);
        } catch (\Exception $e) {
            app('log')->error("Report creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add report', ['error' => 'Could not add report']);
        }
    }

    /**
     * get particular contact details
     *
     * @param  int  $id   //contact id
     * @return Illuminate\Http\JsonResponse
     */
    public function show($id) {
        try {
            $loadReport = ReportSaved::find($id);

            if (!isset($loadReport))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The report does not exist', ['error' => 'The report does not exist']);

            //send contact information
            return createResponse(config('httpResponse.SUCCESS'), 'Report data', ['data' => $loadReport]);
        } catch (\Exception $e) {
            app('log')->error("Report details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get report.', ['error' => 'Could not get report.']);
        }
    }

    /**
     * Created by: Jayesh Shingrakhiya
     * Created on: July 30, 2018
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     * Reason: Add all reports
     */
    public function update(Request $request, $id) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'tab_id' => 'required|numeric',
                'name' => 'required',
                'filter_condition_value' => 'json',
                'output' => 'required'], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            $saveReport = ReportSaved::find($id);

            if (!$saveReport)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The report does not exist', ['error' => 'The report does not exist']);

            $updateData = filterFields(['tab_id', 'name', 'filter_condition_value', 'output', 'filter_output_field', 'orderby', 'groupby'], $request);
            $saveReport->modified_by = app('auth')->guard()->id();
            $saveReport->modified_on = date('Y-m-d H:i:s');
            //update the details
            $saveReport->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Report has been updated successfully', ['message' => 'Report has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Report creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not save report', ['error' => 'Could not save report']);
        }
    }

    /**
     * Created by: Jayesh Shingrakhiya
     * Created on: July 30, 2018
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     * Reason: get all shared usser list
     */
    public function viewShared(Request $request, $id) {
        try {
            $viewShared = \App\Models\Backend\ReportShared::select('id', 'user_id')->where('report_saved_id', $id)->select('user_id', 'id', 'report_saved_id')->get()->toArray();
            $userList = \App\Models\User::select('id', 'userfullname', 'is_active')->where('is_active', 1)->get()->toArray();

            $shareUser['alreadyShared'] = $viewShared;
            $shareUser['userList'] = $userList;

            //send software  information
            return createResponse(config('httpResponse.SUCCESS'), 'Entity software  data', ['data' => $shareUser]);
        } catch (\Exception $e) {
            app('log')->error("Entity software  details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get software .', ['error' => 'Could not get software .']);
        }
    }

    /**
     * Created by: Jayesh Shingrakhiya
     * Created on: July 31, 2018
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     * Reason: shared report with user
     */
    public function reportShare(Request $request, $id) {
        try {
            $reportShared = new \App\Models\Backend\ReportShared;
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'user_id' => 'required|json',], ['user_id.required' => 'The user field is required.', 'user_id.json' => 'The user must be a valid JSON string.']);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            $existinUser = $reportShared->select('user_id', 'id')->where('report_saved_id', $id)->get()->pluck('user_id', 'id')->toArray();
            $newUser = \GuzzleHttp\json_decode($request->get('user_id'));

            $sharedReportwithNewuser = array_diff($newUser, $existinUser);
            $records = array();
            foreach ($sharedReportwithNewuser as $key => $value) {
                $data['report_saved_id'] = $id;
                $data['user_id'] = $value;
                $data['created_by'] = app('auth')->guard()->id();
                $data['created_on'] = date('Y-m-d H:i:s');
                $records[] = $data;
            }

            $reportShared->insert($records);
            $reportShared->whereNotIn('user_id', $newUser)->where('report_saved_id', $id)->delete();

            return createResponse(config('httpResponse.SUCCESS'), 'Report has been shared successfully', ['message' => 'Report has been shared successfully']);
        } catch (\Exception $e) {
            app('log')->error("Report creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add report', ['error' => 'Could not add report']);
        }
    }

    /**
     * delete report from database
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // report id
     * @return Illuminate\Http\JsonResponse
     */
    public function destroy($id) {
        try {
            $saveReport = ReportSaved::find($id);
            // Check whether report exists or not
            if (!isset($saveReport))
                return createResponse(config('httpResponse.NOT_FOUND'), 'Report does not exist', ['error' => 'Report does not exist']);

            \App\Models\Backend\ReportShared::where('report_saved_id', $id)->delete();
            $saveReport->delete();
            return createResponse(config('httpResponse.SUCCESS'), 'Saved report has been deleted successfully', ['message' => 'Saved report has been deleted successfully']);
        } catch (\Exception $e) {
            app('log')->error("Report deletion failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not delete saved report.', ['error' => 'Could not delete saved report.']);
        }
    }

    /**
     * created By Pankaj
     * created on 01-08-2018
     * fetch field from database
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // id
     * @return Illuminate\Http\JsonResponse
     */
    public function fetchFields(Request $request, $id) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'type' => 'required|in:filter,output'], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            //for client report
            if ($id == '33') {
                $reportField = \App\Models\Backend\Dynamicfield::with('groupId:group_name,id')->where("is_active", "1")->where("disable", "0");
            } else {// for Other Reports
                $reportField = \App\Models\Backend\ReportField::where('tab_id', $id)->where("is_active","1");
            }
            if ($request->get('type') == 'filter')
                $reportField = $reportField->where("is_filter_field", "1");

            $reportField = $reportField->orderBy("sort_order")->get();

            if (!isset($reportField))
                return createResponse(config('httpResponse.NOT_FOUND'), 'Report Field does not exist', ['error' => 'Report Field does not exist']);

            return createResponse(config('httpResponse.SUCCESS'), "Report Fields .", ['data' => $reportField]);
        } catch (\Exception $e) {
            app('log')->error("Report Field listing failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while fetch report fields", ['error' => 'Server error.']);
        }
    }

}
