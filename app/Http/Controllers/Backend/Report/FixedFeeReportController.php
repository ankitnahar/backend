<?php

namespace App\Http\Controllers\Backend\Report;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;

class FixedFeeReportController extends Controller {

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
        $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'ff_proposal.id';
        $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
        $pager = [];

        //for field who need alias
        $alias = array('inc_in_ff' => "bs", 'fixed_total_unit' => "bs", 'fixed_total_amount' => "bs","parent_id" => "e",
            'fixed_fee' => "bs", 'frequency_id' => "bs", "ff_start_date" => "bs", 'code' => "e", 'name' => "e", 'trading_name' => "e", 'abn_number' => "e",
            'entity_id' => "ff_proposal", 'month' => "ff_proposal", 'year' => "ff_proposal", 'created_on' => "ff_proposal", 'service_id' => "ff_proposal");
        $fieldArray = array();
        $outputField = explode(",", $request->get('output_field'));
        $designationField = array(9, 10);
        foreach ($outputField as $field) {
            if (in_array($field, $designationField)) {
                $designationName = \App\Models\Backend\Designation::select("designation_name")->find($field);
                $fieldArray[] = "JSON_EXTRACT(ea.allocation_json, '$." . $field . "') as `" . $designationName->designation_name . "`";
            } else if ($field == 'software_id') {
                $fieldTitle = \App\Models\Backend\ReportField::
                        where("field_name", $field)
                        ->where("tab_id", "145")
                        ->first();
                $fieldArray[] = "JSON_EXTRACT(e.dynamic_json, '$.2.28') as `" . $fieldTitle['field_title'] . "`";
            } else {
                $fieldTitle = \App\Models\Backend\ReportField::
                        where("field_name", $field)
                        ->where("tab_id", "145")
                        ->first();
                if ($field == 'entity_name') {
                     $field =  "e".".".'name';
                }
                if ($field == 'parent_id') {
                    $field = "ep" . "." . "trading_name";
                }
                if ($field == 'code' || $field == 'billing_name' || $field == 'trading_name') {
                    $field = "e" . "." .$field;
                }
                if (isset($alias[$field])) {
                    if ($fieldTitle['field_type'] == 'CL') {
                        $field = 'DATE_FORMAT(' . $alias[$field] . '.' . $field . ',"%d-%m-%Y")';
                    } else {
                        $field = $alias[$field] . "." . $field;
                    }
                } else {
                    if ($fieldTitle['field_type'] == 'CL') {
                        $field = 'DATE_FORMAT(' . $field . ',"%d-%m-%Y")';
                    } else {
                        $field = $field;
                    }
                }                
                $fieldArray[] = $field . " AS `" . $fieldTitle['field_title'] . "`";
            }
        }

        $selectField = implode(",", $fieldArray);
        $generateReport = \App\Models\Backend\FFProposal::getFFData()->select(DB::raw($selectField));

        //check client allocation
        $entity_ids = checkUserClientAllocation(loginUser());
        if (is_array($entity_ids))
            $generateReport = $generateReport->whereRaw("e.id IN(".implode(",",$entity_ids).")");

        if ($request->has('search')) {
            $search = $request->get('search');
            $alias = array('inc_in_ff' => "bs", 'fixed_total_unit' => "bs", 'fixed_total_amount' => "bs", 'entity_grouptype_id' => "b",
                'fixed_fee' => "bs", 'frequency_id' => "bs", "ff_start_date" => "bs", 'code' => "e", 'name' => "e", 'trading_name' => "e", 'abn_number' => "e",
                'entity_id' => "ff_proposal", 'month' => "ff_proposal", 'year' => "ff_proposal", 'created_on' => "ff_proposal", 'service_id' => "ff_proposal");

            $designationids = \App\Models\Backend\Designation::where("is_display_in_allocation", "1")->get()->pluck('id', 'id')->toArray();
            $col = $designationids;
            $generateReport = searchReport($generateReport, $search, $alias, $col, 'allocation_json');
        }
        // echo $generateReport =$generateReport->toSql();exit;
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

        $generateReport = \App\Models\Backend\FFProposal::reportArrangeData($generateReport);

        if ($request->has('excel') && $request->get('excel') == 1) {
            //format data in array 
            $data = $generateReport->toArray();
            $column = $fields = array();
            $x = 'A';
            $y = '1';
            foreach ($outputField as $field) {
                if (in_array($field, $designationField)) {
                    $designationName = \App\Models\Backend\Designation::select("designation_name")->find($field);
                    $fields[] = $designationName->designation_name;
                    $x++;
                } else {
                    $fieldTitle = \App\Models\Backend\ReportField::
                            where("field_name", $field)
                            ->where("tab_id", "145")
                            ->first();
                    $fields[] = $fieldTitle['field_title'];
                    $x++;
                }
            }
            $column[] = $fields;
            if (!empty($data)) {
                $columnData = array();
                $i = 1;
                foreach ($data as $data) {
                    $column[] = $data;
                }
            }

            return exportExcelsheet($column, 'FixedFeeReport', 'xlsx', 'A1:' . $x . '1');
        }
        return createResponse(config('httpResponse.SUCCESS'), "Report output.", ['data' => $generateReport], $pager);
        /* } catch (\Exception $e) {
          app('log')->error("Report output failed : " . $e->getMessage());

          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while Report output", ['error' => 'Server error.']);
          } */
    }

}
