<?php

namespace App\Http\Controllers\Backend\Query;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;

class QueryControllerData extends Controller {

    /**
     * Get invoice detail
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function index(Request $request, $id) {
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
        $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'query.created_on';
        $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'desc';
        $pager = [];

        $query = \App\Models\Backend\Query::queryData();

        //check client allocation
        $entity_ids = checkUserClientAllocation(loginUser());
        if (is_array($entity_ids)) {
            $query = $query->whereRaw("e.id IN (" . implode(",", $entity_ids) . ")");
        }

        if (!empty($id)) {
            if ($id != 9) {
                $query = $query->where("query.stage_id", $id);
            }
        } else {
            $query = $query->where("query.stage_id", 1);
        }
        $query = $query->groupBy("query.id");

        //for search
        if ($request->has('search')) {
            $search = $request->get('search');
            $alias = array("entity_id" => "query", "start_period" => "query", "created_by" => "query","parent_id" => "e");
            $query = search($query, $search, $alias);
        }

        if ($request->has('technical_account_manager')) {
            $tam = $request->get('technical_account_manager');
            $query = $query->whereRaw("JSON_EXTRACT(query.team_json, '$.9') = '" . $tam . "'");
        }

        if ($request->has('team_leader')) {
            $tl = $request->get('team_leader');
            $query = $query->whereRaw("JSON_EXTRACT(query.team_json, '$.60') = '" . $tl . "'");
        }
        if ($request->has('associate_team_lead')) {
            $atl = $request->get('associate_team_lead');
            $query = $query->whereRaw("JSON_EXTRACT(query.team_json, '$.61') = '" . $atl . "'");
        }

        if ($request->has('team_member')) {
            $tm = $request->get('team_member');
            $query = $query->whereRaw("JSON_EXTRACT(query.team_json, '$.10') = '" . $tm . "'");
        }

        // for relation ship sorting
        if ($sortBy == 'created_by' || $sortBy == 'modified_by') {
            $query = $query->leftjoin("user as u", "u.id", "query.$sortBy");
            $sortBy = 'userfullname';
        }

        // Check if all records are requested
        if ($request->has('records') && $request->get('records') == 'all') {
            if ($sortBy != '') {
                $query = $query->orderBy($sortBy, $sortOrder)->get();
            } else {
                $query = $query->orderByRaw("information.id desc")->get();
            }
        } else { // Else return paginated records
            // Define pager parameters
            $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
            $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
            $skip = ($pageNumber - 1) * $recordsPerPage;
            $take = $recordsPerPage;
            //count number of total records
            $totalRecords = $query->get()->count();
            if ($sortBy != '') {
                $query = $query->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);
            } else {
                $query = $query->orderByRaw("query.id desc")
                        ->skip($skip)
                        ->take($take);
            }
            $query = $query->get();

            $filteredRecords = count($query);

            $pager = ['sortBy' => $sortByName,
                'sortOrder' => $sortOrder,
                'pageNumber' => $pageNumber,
                'recordsPerPage' => $recordsPerPage,
                'totalRecords' => $totalRecords,
                'filteredRecords' => $filteredRecords];
        }

        $query = \App\Models\Backend\query::arrangeData($query);

        if ($request->has('excel') && $request->get('excel') == 1) {


            //format data in array
            $datas = $query;
            $column = array();

            $column[] = ['Sr.No','Parent Trading Name', 'Client Name', 'Subject Line','Status', 'Total Information','Received','Internal Resolved','Partial','Pending', 'TAM', 'ATL', 'TL', 'Staff', 'Created On', 'Created By', 'Modified By', 'Modified On'];

            if (!empty($datas)) {
                $columnData = array();
                $i = 1;
                foreach ($datas as $data) {
                    $columnData[] = $i;
                    $columnData[] = $data['parent_name'];
                    $columnData[] = $data['billing_name'];
                    $columnData[] = $data['subject'];
                    $columnData[] = $data['stageId']['stage_name'];
                    $columnData[] = $data['totalInformation'];
                    $columnData[] = ($data['received_count'] > 0) ? $data['received_count'] : 0;
                    $columnData[] = ($data['resolved_count'] > 0) ? $data['resolved_count'] : 0;
                    $columnData[] = ($data['partial_count'] > 0) ? $data['partial_count'] : 0; 
                    $columnData[] = ($data['pending_count'] > 0) ? $data['pending_count'] : '0'; 
                    $columnData[] = $data['tam_name'];
                    $columnData[] = $data['atl_name'];
                    $columnData[] = $data['tl_name'];
                    $columnData[] = $data['team_member'];
                    $columnData[] = $data['created_on'];
                    $columnData[] = $data['createdBy']['created_by'];
                    $columnData[] = $data['modified_on'];
                    $columnData[] = $data['modifiedBy']['modified_by'];
                    $column[] = $columnData;
                    $columnData = array();
                    $i++;
                }
            }
            return exportExcelsheet($column, 'NewQueryList', 'xlsx', 'A1:R1');
        }

        return createResponse(config('httpResponse.SUCCESS'), "Query list.", ['data' => $query], $pager);       /* } catch (\Exception $e) {
          app('log')->error("Information listing failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Information", ['error' => 'Server error.']);
          } */
    }

}

?>