<?php

namespace App\Http\Controllers\Backend\Report;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;

class RsheetSummaryReportController extends Controller {

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
            'search' => 'json'
                ], []);

        if ($validator->fails()) // Return error message if validation fails
            return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

        // define soring parameters
        $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'id';
        $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
        $pager = [];
        $userDes = getLoginUserHierarchy();

        $worksheetDetail = \App\Models\Backend\Worksheet::leftjoin('worksheet_status_log AS wsl', 'wsl.worksheet_id', '=', 'worksheet.id')
                ->leftjoin("user as u", "u.id", DB::raw('JSON_VALUE(worksheet.team_json,"$.9")'))
                ->select('worksheet.id', 'worksheet.knockback_count', 'worksheet.user_rating', 'worksheet.neglience_count', 'u.is_active', 'worksheet.reportsent_count', DB::raw("JSON_UNQUOTE(JSON_EXTRACT(worksheet.team_json, '$.9')) as tam_id,JSON_UNQUOTE(JSON_EXTRACT(worksheet.team_json, '$.60')) as tl_id"), "u.userfullname as tam_name", "worksheet.worksheet_actual_teammember", 'worksheet.worksheet_additional_assignee', 'wsl.created_by')
                ->whereIn('wsl.status_id',[13,15]);
        if ($userDes->designation_id != config('constant.SUPERADMIN')) {
            $worksheetDetail = $worksheetDetail->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(worksheet.team_json,'$.$userDes->designation_id')) = $userDes->user_id");
        }

        if ($request->has('search')) {
            $search = \GuzzleHttp\json_decode($request->get('search'), true);            
            if ($search['compare']['equal']['service_id'] == 1) {
                $worksheetDetail = $worksheetDetail->where("worksheet.master_activity_id", "5")
                        ->where("worksheet.task_id", "5");
            }
            if (isset($search['compare']['greaterthanequal']['created_on']) && $search['compare']['greaterthanequal']['created_on'] != '') {
                $fromDate = $search['compare']['greaterthanequal']['created_on'];
                $toDate = $search['compare']['lessthanequal']['created_on'];
                $wh = "(u.is_active =1 OR u.user_lastlogin >='" . $fromDate . "')";

                $worksheetDetail = $worksheetDetail->whereRaw($wh);
            }

            $search = $request->get('search');
            $alias = array('created_on' => 'wsl', 'created_by' => 'wsl', 'service_id' => 'worksheet');
            $worksheetDetail = search($worksheetDetail, $search, $alias);
        }
        $worksheetDetail = $worksheetDetail->groupBy('wsl.worksheet_id')->orderBy('worksheet.id', 'desc')->get();
        
        $counterArray = $userID = $tamId = $tlId = array();
        $i = 0;
        $j = 0;
        $user = \App\Models\User::select('userfullname', 'id')->get()->pluck('userfullname', 'id')->toArray();
        foreach ($worksheetDetail as $value) {
             if($value->reportsent_count == 0){
                 continue;
             }
            $userFullName = "";
            $worksheetUserId = 0;
            if ($value->worksheet_actual_teammember > 0) {
                $userFullName = $user[$value->worksheet_actual_teammember];
                $worksheetUserId = $value->worksheet_actual_teammember;
            } else if ($value->worksheet_additional_assignee > 0 ) {
                $userFullName = $user[$value->worksheet_additional_assignee];
                $worksheetUserId = $value->worksheet_additional_assignee;
            }
            if ($userFullName == "") {
                continue;
            }

            if (in_array($worksheetUserId, $userID)) {
                $useDetail[$worksheetUserId]['knockback'] = $useDetail[$worksheetUserId]['knockback'] + $value->knockback_count;
                $useDetail[$worksheetUserId]['negligence'] = $useDetail[$worksheetUserId]['negligence'] + $value->neglience_count;
                $useDetail[$worksheetUserId]['reportsent'] = $useDetail[$worksheetUserId]['reportsent'] + $value->reportsent_count;
                
                $useDetail[$worksheetUserId]['rating'] = $useDetail[$worksheetUserId]['rating'] + $value->user_rating;
                
            } else {
                $useDetail[$worksheetUserId]['knockback'] = $value->knockback_count;
                $useDetail[$worksheetUserId]['negligence'] = $value->neglience_count;
                $useDetail[$worksheetUserId]['reportsent'] = $value->reportsent_count;
               
                $useDetail[$worksheetUserId]['rating'] = $value->user_rating;
                
                $useDetail[$worksheetUserId]['user_name'] = $userFullName;
                $useDetail[$worksheetUserId]['is_active'] = $value->is_active;
                $useDetail[$worksheetUserId]['tam_id'] = $value->tam_id;
                $useDetail[$worksheetUserId]['tl_id'] = $value->tl_id;
                $userID[] = $worksheetUserId;

                if (!in_array($value->tam_id, $tamId) && $value->tam_id != 0 && $value->tam_id != null) {
                    $teamArray[$i]['tam_id'] = $value->tam_id;
                    $teamArray[$i]['tam_name'] = $value->tam_name;
                    $tamId[] = $value->tam_id;
                    $tlId[$value->tam_id] = array();
                    $i++;
                    $j = 0;
                }
                if (!in_array($value->tl_id, $tlId[$value->tam_id]) && $value->tl_id != 0 && $value->tl_id != null) {
                    $tlId[$value->tam_id][] = $value->tl_id;
                    $j = array_keys($tlId[$value->tam_id], $value->tl_id, true);
                    //showArray($j);exit;
                    //$tlArray[$value->tam_id][$value->tl_id]['tl_id'] = $value->tl_id;
                    //$tlArray[$value->tam_id][$value->tl_id]['tl_name'] = isset($user[$value->tl_id]) ? $user[$value->tl_id]:'' ;
                    $tlArray[$value->tam_id][$j[0]]['tl_id'] = $value->tl_id;
                    $tlArray[$value->tam_id][$j[0]]['tl_name'] = isset($user[$value->tl_id]) ? $user[$value->tl_id] : '';

                    $j++;
                }
            }
        }
        //showArray($tlId);
        //showArray($tlArray);
        foreach ($useDetail as $key1 => $value) {
            $rating = number_format($value['reportsent'] > '0' ? ($value['rating'] / $value['reportsent']) : '0', 2);
            $percentage = number_format($value['reportsent'] != '' ? ($value['knockback'] * 100) / $value['reportsent'] : '0', 2);
            $value['actual_rating'] = $value['rating'];
            $value['rating'] = $rating;
            $value['percentage'] = $percentage;
            $tlteamArray[$value['tam_id']][$value['tl_id']][] = $value;
        }
        
        

        foreach ($tlteamArray as $key2 => $tlvalue) {
            $t = 0;
            foreach ($tlvalue as $k => $value2) {
                $tlArray[$key2][$t]['team'] = isset($tlteamArray[$key2][$k]) ? $tlteamArray[$key2][$k] : array();
                $t++;
            }
        }
        //showArray($tlArray);
        foreach ($teamArray as $key => $row) {
            $teamArray[$key]['teamDetail'] = isset($tlArray[$row['tam_id']]) ? $tlArray[$row['tam_id']] : array();
        }
        //showArray($tlArray);
         //showArray($teamArray);
         //exit;
        if ($request->has('excel') && $request->get('excel') == 1) {
            //format data in array 
            $data1 = $teamArray;
            $column = array();
            $column[] = ['Tam Name','TL', 'Processing Staff', 'User Type', 'Reports Sent', 'Knockback', '% of Knockback', 'Negligence', 'Rate'];
            if (!empty($data1)) {
                $columnData = array();
                
                foreach ($data1 as $data) {
                    $i = 0;
                    $columnData[] = $data['tam_name'];
                    if ($i == 0) {
                            $column[] = $columnData;
                            $columnData = array();
                            $i++;
                        }
                    $kTotal = $rTotal = $nTotal = $pTotal = $k = $l = 0;
                    foreach ($data['teamDetail'] as $row) {                       
                        $k = 0;
                        $columnData[] = '';
                        $columnData[] = isset($row['tl_name']) ? $row['tl_name'] : '';
                         if ($k == 0) {
                            $column[] = $columnData;
                            $columnData = array();
                            $k++;
                        }
                        
                        foreach ($row['team'] as $row1) {
                        if ($l == 0) {
                            $column[] = $columnData;
                            $columnData = array();
                            $l++;
                        }
                        $kTotal = $kTotal + $row1['knockback'];
                        $rTotal = $rTotal + $row1['reportsent'];
                        $nTotal = $nTotal + $row1['negligence'];
                        $pTotal = $pTotal + $row1['percentage'];
                        $columnData[] = '';
                        $columnData[] = '';
                        $columnData[] = $row1['user_name'];
                        $columnData[] = ($row1['is_active']) ? 'Active' : 'Inactive';
                        $columnData[] = ($row1['reportsent'] > 0) ? $row1['reportsent'] : '0';
                        $columnData[] = ($row1['knockback'] > 0) ? $row1['knockback'] : '0';
                        $columnData[] = $row1['percentage'];
                        $columnData[] = ($row1['negligence'] > 0) ? $row1['negligence'] : '0';
                        $columnData[] = ($row1['rating'] > 0) ? $row1['rating'] : '0';

                        $column[] = $columnData;
                        $columnData = array();
                        }
                    }
                    $columnData[] = $data['tam_name'] . 'Total';
                    $columnData[] = '';
                    $columnData[] = ''; 
                    $columnData[] = ''; 
                    $columnData[] = ($rTotal > 0) ? $rTotal : '0';
                    $columnData[] = ($kTotal > 0) ? $kTotal : '0';
                    $columnData[] = ($pTotal > 0) ? $pTotal : '0.00';
                    $columnData[] = ($nTotal > 0) ? $nTotal : '0';
                    $columnData[] = '';
                    $column[] = $columnData;
                    $columnData = array();
                }
            }
            return exportExcelsheet($column, 'RsheetSummaryReport', 'xlsx', 'A1:I1');
        }
        return createResponse(config('httpResponse.SUCCESS'), "Rsheet Report", ['data' => $teamArray]);
        /* } catch (\Exception $e) {
          app('log')->error("Rsheet Report listing failed : " . $e->getMessage());

          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing resheet report", ['error' => 'Server error.']);
          } */
    }

}
