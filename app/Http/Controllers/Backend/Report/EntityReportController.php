<?php

namespace App\Http\Controllers\Backend\Report;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;

class EntityReportController extends Controller {

    /**
     * Created by: Pankaj
     * Created on: 01-08-2018
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     * Reason: Display all reports listing
     */
    public function generateReport(Request $request) {
      //  try {
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

        $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'entity.id';
        //for json variable
        $fieldArray = array();
        $outputValue = explode(",", $request->get('output_field'));
        $outputValue = "'" . implode("', '", $outputValue) . "'";

        $outputField = \App\Models\Backend\Dynamicfield::whereRaw("field_name IN ($outputValue)")->get();
        $forStaticField = \App\Models\Backend\Dynamicfield::whereRaw("field_name IN ($outputValue)")->where("field_type","DD")->where("group_id","1")->get();
        foreach ($outputField as $field) {

            if ($field->group_id != 1) {
                $fieldArray[] = "JSON_UNQUOTE(JSON_EXTRACT(entity.dynamic_json, '$." . $field->group_id . "." . $field->id . "')) as `" . $field->field_title . "`";
            } else {
                if($field->field_name == 'trading_name'){
                    continue;
                }
                 if($field->field_name == 'entity_name'){
                    $field_name ="entity" . ".".'name';
                 }
                 if ($field->field_name == 'parent_id') {
                    $field_name = "ep" . "." . "trading_name";
                } if ($field->field_name != 'entity_name' && $field->field_name != 'parent_name') {
                    $field_name = "entity" . "." .$field->field_name;
                }
                $fieldArray[] = $field_name." AS `".$field->field_title."`";
            }
        }
        
        $generateReport = \App\Models\Backend\Entity::leftjoin("entity as ep","ep.id","entity.parent_id")->select(DB::raw(implode(",", $fieldArray)));
        //check client allocation
        $entity_ids = checkUserClientAllocation(app('auth')->guard()->id());
        if (is_array($entity_ids))
            $generateReport = $generateReport->whereRaw("entity.id IN(".implode(",",$entity_ids).")");
        
        $generateReport = $generateReport->where("entity.discontinue_stage","!=","2");
        if ($request->has('search')) {
            $search = $request->get('search');
            $dynamicFields = \App\Models\Backend\Dynamicfield::select(DB::raw("concat(group_id,'.',id) as fieldIds"),"field_name")->whereRaw("field_name IN ($outputValue)")->where("group_id","!=","1")->get()->pluck('fieldIds','field_name')->toArray();
            $col = $dynamicFields;
            $dynamicFieldsAlease = \App\Models\Backend\Dynamicfield::select("field_name")->get();
            foreach($dynamicFieldsAlease as $al){
               $alias[$al->field_name] = "entity"; 
            }
            
            //$alias = array("entity_name"=>"entity","trading_name"=>"entity","billing_name"=>"entity","parent_id" => "entity","");
            $generateReport = searchReport($generateReport, $search, $alias, $col, 'entity.dynamic_json','1');
        }
        //echo $generateReport = $generateReport->toSql();exit;
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
        $generateReport = \App\Models\Backend\Entity::reportArrangeData($generateReport,$forStaticField);
        if ($request->has('excel') && $request->get('excel') == 1) {
            //format data in array 
            $data = $generateReport->toArray();
            $column = $fields = array();
            $x = 'A';
            $y = '1';
            foreach ($outputField as $field) {
                $fields[] = $field->field_title;            
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
            return exportExcelsheet($column, 'EntityReport', 'xlsx', 'A1:' . $x . '1');
        }
        return createResponse(config('httpResponse.SUCCESS'), "Report output.", ['data' => $generateReport], $pager);
      /*   } catch (\Exception $e) {
          app('log')->error("Report output failed : " . $e->getMessage());

          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while Report output", ['error' => 'Server error.']);
          }*/
    }

}
