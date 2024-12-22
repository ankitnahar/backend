<?php

namespace App\Http\Controllers\Backend\Report;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;

class EntityAllocationReportController extends Controller {

    /**
     * Created by: Pankaj
     * Created on: 01-08-2018
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     * Reason: Display all reports listing
     */
    public function generateReport(Request $request) {
        //try {
        //validate request parameters
        $validator = app('validator')->make($request->all(), [
            'sortOrder' => 'in:asc,desc',
            'pageNumber' => 'numeric|min:1',
            'recordsPerPage' => 'numeric|min:0',
            'search' => 'json',
            'output_field' => 'required'
                ], []);

        if ($validator->fails()) // Return error message if validation fails
            return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

        // define soring parameters
        $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'id';
        $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
        $pager = [];


        //for entity allocation report

        $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'entity_allocation.id';
        //for json variable
        $fieldArray = array();
        $outputField = explode(",", $request->get('output_field'));
        $fieldNotChange = array('code', 'trading_name', 'entity_name', 'billing_name','service_id','parent_id');
        foreach ($outputField as $field) {
            if (!in_array($field, $fieldNotChange)) {
                $designationName = \App\Models\Backend\Designation::select("designation_name")->find($field);
                $fieldArray[] = "JSON_UNQUOTE(JSON_EXTRACT(allocation_json, '$." . $field . "')) as `" . $designationName->designation_name . "`";
            } else {            
                 $fieldTitle = \App\Models\Backend\ReportField::
                                where("field_name",$field)
                                ->where("tab_id","32")
                                ->first();
                    
                if($field == 'entity_name'){
                    $field = "e".".".'name';
                }
                if ($field == 'parent_id') {
                    $field = "ep" . "." . "trading_name";
                }
                 if ($field == 'code' || $field == 'billing_name' || $field == 'trading_name') {
                    $field = "e" . "." .$field;
                }
                if($field == 'service_id'){
                    $field ='entity_allocation.`service_id`';
                }
                 $fieldArray[] = $field." AS `".$fieldTitle['field_title']."`";        
            }
        }

        $generateReport = \App\Models\Backend\EntityAllocation::getAllocationReportData()->select(DB::raw(implode(",", $fieldArray)));
        //check client allocation
        $entity_ids = checkUserClientAllocation(app('auth')->guard()->id());
        if (is_array($entity_ids))
            $generateReport = $generateReport->whereRaw("e.id IN(".implode(",",$entity_ids).")");

        if ($request->has('search')) {
            $search = $request->get('search');
            $designationids = \App\Models\Backend\Designation::where("is_display_in_allocation", "1")->get()->pluck('id','id')->toArray();
            $col = $designationids;
            $alias = array("entity_name"=>"e","trading_name"=>"e","billing_name"=>"e","service_id"=>"entity_allocation","parent_id" => "e");
            $generateReport = searchReport($generateReport, $search, $alias, $col, 'allocation_json');
        }
       // echo getSQL($generateReport);exit;
        // Check if all records are requested 
        if ($request->has('records') && $request->get('records') == 'all') {
            $generateReport = $generateReport->orderBy($sortBy, $sortOrder)->get();
        } else { // Else return paginated records
            // Define pager parameters
            $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
            $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
            $skip = ($pageNumber - 1) * $recordsPerPage;
            $take = $recordsPerPage;

            //count number of total records
            $totalRecords = $generateReport->count();

            $generateReport = $generateReport->orderBy($sortBy, $sortOrder)
                    ->skip($skip)
                    ->take($take);

            $generateReport = $generateReport->get();

            $filteredRecords = count($generateReport);

            $pager = ['sortBy' => $sortBy,
                'sortOrder' => $sortOrder,
                'pageNumber' => $pageNumber,
                'recordsPerPage' => $recordsPerPage,
                'totalRecords' => $totalRecords,
                'filteredRecords' => $filteredRecords];
        }
        $generateReport = \App\Models\Backend\EntityAllocation::reportArrangeData($generateReport);
        if ($request->has('excel') && $request->get('excel') == 1) {
            //format data in array 
            $data = $generateReport->toArray();
            $column = $fields = array();
            $x = 'A';
            $y = '1';
            $fieldNotChange = array('code', 'trading_name', 'entity_name', 'billing_name','service_id','parent_id');
            foreach ($outputField as $field) {
                if (!in_array($field, $fieldNotChange)) {
                    $designationName = \App\Models\Backend\Designation::select("designation_name")->find($field);
                    $fields[] = $designationName->designation_name;
                } else {
                    $fieldTitle = \App\Models\Backend\ReportField::
                            where("field_name", $field)
                            ->where("tab_id", "32")
                            ->first();
                    $fields[] = $fieldTitle->field_title;
                }
                $x++;
            }
            $column[] = $fields;
            if (!empty($data)) {
                $columnData = array();
                $i = 1;
                foreach ($data as $data) {
                    $column[] = $data;
                }
            }
            return exportExcelsheet($column, 'EntityAllocationReport', 'xlsx', 'A1:' . $x . '1');
        }
        return createResponse(config('httpResponse.SUCCESS'), "Report output.", ['data' => $generateReport], $pager);
        /* } catch (\Exception $e) {
          app('log')->error("Report output failed : " . $e->getMessage());

          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while Report output", ['error' => 'Server error.']);
          } */
    }

}
