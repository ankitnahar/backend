<?php

namespace App\Http\Controllers\Backend\Invoice;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;

class UnchargeUnitController extends Controller {

    /**
     * Get invoice detail
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
        $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'entity.id';
        $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'desc';
        $pager = [];
        $billingServices = \App\Models\Backend\BillingServices::select("service_id","entity_id")
                        ->where("is_latest", "1")
                        ->where("is_active", "1")
                        ->where("is_updated", "0")
                        ->whereIn("service_id", [1, 2, 6])->orderBy("entity_id")->get()->toArray();
        $updateZero = array();
        if(!empty($billingServices)){
        foreach($billingServices as $service){
            $updateZero[$service['entity_id']][$service['service_id']] = 0;
        }
        }        
        $unchargeUnits = \App\Models\Backend\Entity::leftjoin("timesheet as t", "t.entity_id", "entity.id")
                ->leftJoin("billing_services as bs", function($join) {
                    $join->on("bs.entity_id", "entity.id");
                    $join->on("bs.service_id", db::raw("1"));
                    $join->on("bs.is_latest", db::raw("1"));
                })
                ->select(['entity.id', 'entity.code', 'entity.billing_name', 'entity.name as entity_name', 'entity.trading_name', 'entity.discontinue_stage', DB::raw("if(bs.service_id=1,bs.inc_in_ff,0) as inc_in_ff"),
                    DB::raw('SUM(t.units) total ,SUM(CASE WHEN t.service_id = 1 THEN t.units END) AS bk,SUM(CASE WHEN t.service_id = 2 THEN t.units END) AS payroll,SUM(CASE WHEN t.service_id = 6 THEN t.units END) AS tax')])
                ->whereRaw("t.billing_status IN (0,2)")
                ->whereRaw("t.service_id IN (1,2,6)")
                ->where("entity.discontinue_stage", "!=", db::raw("2"));

        //check client allocation
        $entity_ids = checkUserClientAllocation(loginUser());
        if (is_array($entity_ids)) {
            $entity_ids = implode(",", $entity_ids);
            $unchargeUnits = $unchargeUnits->whereRaw("entity.id IN ($entity_ids)");
        }

        $unchargeUnits = $unchargeUnits->groupBy("entity.id");
        //for search
        if ($request->has('search')) {
            $search = $request->get('search');
            $unchargeUnits = search($unchargeUnits, $search);
        }
        if ($request->has('entity_id')) {
            $eid = $request->input('entity_id');
            $unchargeUnits = $unchargeUnits->whereRaw("entity.id IN ($eid)");
        }
        //showArray($unchargeUnits->toSql());exit;
        // Check if all records are requested
        if ($request->has('records') && $request->get('records') == 'all') {
            $unchargeUnits = $unchargeUnits->orderBy($sortBy, $sortOrder);
            $unchargeUnit = getSQL($unchargeUnits);
            $unchargeUnits = DB::select("SELECT T.*, GROUP_CONCAT(DISTINCT u.userfullname) AS tam FROM
                                        (" . $unchargeUnit . ") T 
                                        LEFT JOIN `entity_allocation` AS `ea` ON `ea`.`entity_id` = T.`id`
                                        LEFT JOIN user AS u ON u.id = JSON_VALUE(ea.allocation_json,'$.9')
                                        GROUP BY `ea`.`entity_id`");
        } else { // Else return paginated records
            // Define pager parameters
            $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
            $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
            $skip = ($pageNumber - 1) * $recordsPerPage;
            $take = $recordsPerPage;
            //count number of total records
            $totalRecords = $unchargeUnits->get()->count();
            $unchargeUnits = $unchargeUnits->orderBy($sortBy, $sortOrder)
                    ->skip($skip)
                    ->take($take);

//showArray($bindings);exit;
            $unchargeUnit = getSQL($unchargeUnits);
            $unchargeUnits = DB::select("SELECT T.*, GROUP_CONCAT(DISTINCT u.userfullname) AS tam FROM
                                        (" . $unchargeUnit . ") T 
                                        LEFT JOIN `entity_allocation` AS `ea` ON `ea`.`entity_id` = T.`id`
                                        LEFT JOIN user AS u ON u.id=JSON_VALUE(ea.allocation_json,'$.9')
                                        GROUP BY T.id Order by T.id");


            $filteredRecords = count($unchargeUnits);

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
            $data = $unchargeUnits;
            $column = array();

            app('excel')->create('Report', function($excel) use($data,$updateZero) {
                $excel->sheet('Sheet 1', function($sheet) use($data,$updateZero) {
                    $sheet->row(1, array('Sr.No', 'Client code', 'Billing Name', 'Trading Name', 'Technical Account Manager', 'Fixed Fee', 'Uncharged units', 'Bookkeeping units', 'Payroll units', 'Tax units', 'Client Status'));
                    $sheet->cell('A1:K1', function($cell) {
                        $cell->setFontColor('#ffffff');
                        $cell->setBackground('#0c436c');
                    });
                    $i = 2;
                    $j=0;
                    foreach ($data as $clean) {
                        $j++;
                        if (isset($updateZero[$clean->id])) {
                        if (isset($updateZero[$clean->id][1])) {
                            $sheet->cell('H' . $i, function($color) {
                                $color->setFontColor('#ff0000');
                            });
                        }
                         if (isset($updateZero[$clean->id][2])) {
                            $sheet->cell('I' . $i, function($color) {
                                $color->setFontColor('#ff0000');
                            });
                        }
                         if (isset($updateZero[$clean->id][6])) {
                            $sheet->cell('J' . $i, function($color) {
                                $color->setFontColor('#ff0000');
                            });
                        }
                        }

                        $sheet->row($i, array($j,$clean->code,
                            $clean->billing_name,
                            $clean->trading_name,
                            $clean->tam,
                            $clean->inc_in_ff == '1' ? 'Yes' : 'No',
                            $clean->bk + $clean->payroll + $clean->tax,
                            $clean->bk,
                            $clean->payroll,
                            $clean->tax,
                            ($clean->discontinue_stage == 1) ? 'Discontinue Process Initiated' : 'Active'
                        ));
                        $i++;
                    }
                    $sheet->setAutoFilter();
                });
            })->export('xlsx',['Access-Control-Allow-Origin'=>'*']);

            return exportExcelsheet($column, 'UnchargeList', 'xlsx', 'A1:K1');
        }
        return createResponse(config('httpResponse.SUCCESS'), "Uncharge list.", ['data' => $unchargeUnits, 'servicesUpdated' => $billingServices], $pager);
        /* } catch (\Exception $e) {
          app('log')->error("Uncharge listing failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Uncharge", ['error' => 'Server error.']);
          } */
    }

    /**
     * Get uncharge summary
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function unchargeSummary(Request $request) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'sortOrder' => 'in:asc,desc',
                'pageNumber' => 'numeric|min:1',
                'recordsPerPage' => 'numeric|min:0',
                'entity_id' => 'required|numeric'
                    ], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            // define soring parameters
            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'timesheet.id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'desc';
            $pager = [];

            $unchargeUnits = \App\Models\Backend\Timesheet::getClientWiseTimesheetUnit();

            $unchargeUnits = $unchargeUnits->where("timesheet.entity_id", $request->input("entity_id"));
            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $alias = array("id" => "timesheet", "service_id" => "timesheet");
                $unchargeUnits = search($unchargeUnits, $search, $alias);
            }

            if ($sortBy == 'assignee' || $sortBy == 'userfullname') {
                $unchargeUnits = $unchargeUnits->leftjoin("user as u", "u.id", "timesheet.user_id");
                $sortBy = 'u.userfullname';
            }

            // Check if all records are requested
            if ($request->has('records') && $request->get('records') == 'all') {
                $unchargeUnits = $unchargeUnits->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $unchargeUnits->get()->count();

                $unchargeUnits = $unchargeUnits->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $unchargeUnits = $unchargeUnits->get();

                $filteredRecords = count($unchargeUnits);

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
                $data = $unchargeUnits->toArray();
                $column = array();

                $column[] = ['Sr.No', 'User name', 'Billing Name', 'Master Activity', 'Task', 'Subactivity', 'Start Date', 'End Date', 'Timesheet Date', 'Units', 'Notes', 'Frequency', 'No Of Value', 'Extra value', 'Bank cc name', 'Bank cc account number', 'Period Start Date', 'Period End Date', 'Billing Status', 'Client Status'];

                if (!empty($data)) {
                    $columnData = array();
                    $i = 1;
                    foreach ($data as $data) {
                        $columnData[] = $i;
                        $columnData[] = $data['assignee']['userfullname'];
                        $columnData[] = $data['billing_name'];
                        $columnData[] = $data['master_name'];
                        $columnData[] = $data['task_name'];
                        $columnData[] = $data['subactivity_full_name'];
                        $columnData[] = $data['start_date'];
                        $columnData[] = $data['end_date'];
                        $columnData[] = $data['date'];
                        $columnData[] = $data['units'];
                        $columnData[] = $data['notes'];
                        $columnData[] = $data['frequency_name'];
                        $columnData[] = $data['no_of_value'];
                        $columnData[] = $data['extra_value'];
                        $columnData[] = $data['bank_cc_name'];
                        $columnData[] = $data['bank_cc_account_no'];
                        $columnData[] = $data['period_startdate'];
                        $columnData[] = $data['period_enddate'];
                        $columnData[] = ($data['billing_status'] == 0) ? 'Not Charge' : 'Carry Forward';
                        $columnData[] = ($data['discontinue_stage'] == 1) ? 'Discontinue Process Initiated' : 'Active';
                        $column[] = $columnData;
                        $columnData = array();
                        $i++;
                    }
                }
                return exportExcelsheet($column, 'TimesheetReport', 'xlsx', 'A1:T1');
            }
            return createResponse(config('httpResponse.SUCCESS'), "TimesheetReport list.", ['data' => $unchargeUnits], $pager);
        } catch (\Exception $e) {
            app('log')->error("Invoice listing failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Invoice", ['error' => 'Server error.']);
        }
    }

}

?>
