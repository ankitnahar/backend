<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;
use DB;

class Worksheet extends Model {

    protected $guarded = [];
    protected $table = 'worksheet';
    protected $hidden = [];
    public $timestamps = false;

    public function createdBy() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }

    public function modifiedBy() {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }

    public function masterActivityId() {
        return $this->belongsTo(MasterActivity::class, 'master_activity_id', 'id');
    }

    public function serviceId() {
        return $this->belongsTo(Services::class, 'service_id', 'id');
    }

    public function statusId() {
        return $this->belongsTo(WorksheetStatus::class, 'status_id', 'id');
    }

    public function taskId() {
        return $this->belongsTo(\App\Models\Backend\TaskActivity::class, 'task_id', 'id');
    }

    public function entityId() {
        return $this->belongsTo(\App\Models\Backend\Entity::class, 'entity_id', 'id');
    }

    public function frequencyId() {
        return $this->belongsTo(\App\Models\Backend\Frequency::class, 'frequency_id', 'id');
    }

    public function worksheetAdditionalAssignee() {
        return $this->belongsTo(\App\Models\User::class, 'worksheet_additional_assignee', 'id');
    }

    public function worksheetReviewer() {
        return $this->belongsTo(\App\Models\User::class, 'worksheet_reviewer', 'id');
    }
    
    public function worksheetPeerreviewer() {
        return $this->belongsTo(\App\Models\User::class, 'worksheet_peerreviewer', 'id');
    }
    //user wise worksheet data
    public static function getWorksheet() {
        return Worksheet::with('createdBy:userfullname as created_by,id')
                        ->with('modifiedBy:userfullname as modified_by,id')
                        ->with('masterActivityId:code,name,id')
                        ->with('taskId:name,id')
                        ->with('statusId:status_name,id')
                        ->with('worksheetAdditionalAssignee:id,userfullname')
                        ->leftjoin('worksheet_master as wm', 'wm.id', '=', 'worksheet.worksheet_master_id')
                        ->leftjoin('frequency as f', 'f.id', '=', 'worksheet.frequency_id')
                        ->leftjoin('entity as e', 'e.id', '=', 'worksheet.entity_id')
                        ->leftjoin('entity as ep', 'ep.id', '=', 'e.parent_id')
                        ->leftjoin('master_activity as ma', 'ma.id', '=', 'worksheet.master_activity_id')
                        ->leftjoin("billing_basic as bs", "bs.entity_id", "e.id")
                        ->leftjoin("entity_groupclient_belognto as egb", "egb.id", "bs.entity_grouptype_id")
                        ->leftjoin("entity_allocation as ea", function($join) {
                            $join->on('ea.entity_id', '=', 'worksheet.entity_id');
                            $join->on('ma.service_id', '=', 'ea.service_id');
                        })->leftjoin("entity_allocation_other as oea", "oea.entity_id", "worksheet.entity_id");
    }

    public static function worksheetArrangeData($worksheet, $type = null) {
        if ($type == 'reviewer'){
            $entityAllocation = entityAllocation("'9,10,60,61,68,69,70,71'");
        $entityAllocationID = entityAllocationId("'9,10,60,61,68,69,70,71'");}
        else{
            $entityAllocation = entityAllocation("'9,10,60,61,71'");
            $entityAllocationID = entityAllocationId("'9,10,60,61,71'");
        }

        $i = 0;
        foreach ($worksheet as $row) {
            $worksheet[$i]['allocation'] = (isset($entityAllocation[$row->entity_id . " - " . $row->service_id])) ? $entityAllocation[$row->entity_id . " - " . $row->service_id] : '';
            $worksheet[$i]['allocationId'] = (isset($entityAllocationID[$row->entity_id . " - " . $row->service_id])) ? $entityAllocationID[$row->entity_id . " - " . $row->service_id] : '';
            $worksheet[$i]['taskchecklist'] = self::checkTaskchecklist($row->entity_id, $row->master_activity_id, $row->task_id);
            $i++;
        }

        if($type == 'completed') {
            $designation = Designation::pluck('designation_name', 'id')->toArray();
            $user = \App\Models\User::pluck('userfullname', 'id')->toArray();
            $allocation = array();
            $i = 0;
            foreach ($worksheet as $row) {
                $userTeam = (isset($row->team_json) && $row->team_json != '')?array_filter(\GuzzleHttp\json_decode($row->team_json, true)):array();
                foreach ($userTeam as $key => $value)
                    $allocation[$designation[$key]] = $user[$value];

                $worksheet[$i]->team_json = \GuzzleHttp\json_encode($allocation);
                $i++;
            }
        }
        return $worksheet;
    }

    public static function countWorksheetStatus() {
        //get default worksheet due date period
        $default_period = default_worksheet_period();

        $worksheet = Worksheet;
        $entity_ids = checkUserClientAllocation(loginUser());
        if (is_array($entity_ids))
            $worksheet = $worksheet->whereIn("e.id", $entity_ids);
        $worksheet = $worksheet->where("worksheet.end_date", "<=", date("Y-m-d"))
                ->where("worksheet.due_date", ">=", date("Y-m-d", strtotime($default_period[0])))
                ->where("worksheet.due_date", "<=", date("Y-m-d", strtotime($default_period[1])));
        //get login user designation
        $user = getUserHierarchyDetail();
        if ($user->designation_id != 7) {
            $arrTeam = stringToArray(",", $user->team_id);
            $appendTeam = " AND (";
            foreach ($arrTeam as $team_id)
                $appendTeam .= "FIND_IN_SET (" . $team_id . ", user_team_id) OR ";

            $worksheet = $worksheet->whereRaw($appendTeam);
        }
    }

    /*
     * Created By: Jayesh Shingrakhiya
     * Created On: 17-08-2018
     * Checkout out entity allocation
     */

    public static function actualentityAllocation($user_id) {
        // Get Allocation From Entity Allocation
        $entityAllocation = \App\Models\Backend\EntityAllocation::select("entity_id", "id")->whereRaw("JSON_SEARCH(allocation_json, 'all', '$user_id') IS NOT NULL")->groupBy("entity_id")->get()->pluck("entity_id", "id")->toArray();
        return $entityAllocation;
//        showArray($entityAllocation);
//        die;
    }

    public static function checkTaskchecklist($entityId, $msterActivityId, $taskId){
        $entityChecklist = EntityChecklist::join('master_checklist AS mc', 'mc.id', '=', 'entity_checklist.master_checklist_id')->where('entity_id', $entityId)->where('is_applicable', 1)->where('mc.task_id', $taskId)->where('mc.master_activity_id', $msterActivityId)->count();
        
        if($entityChecklist != 0)
            $entityChecklist = 1;
        
        return $entityChecklist;
    }
    
    /*Pankaj
     * Review report function
     * Date - 13-09-2019
     */
    public static function getReviewReport(){
        
       return  Worksheet::leftjoin("entity as e","e.id","worksheet.entity_id")
               ->leftjoin("entity as ep","ep.id","e.parent_id")
                ->leftjoin("worksheet_task_checklist as wt","wt.worksheet_id","worksheet.id")
                ->leftjoin("worksheet_task_checklist_comment as wtc", function($join) {
                            $join->on('wtc.worksheet_id', '=', 'wt.worksheet_id');
                            $join->on('wtc.question_id', '=', 'wt.question_id');
                            $join->on('wtc.staff_type', '=', app('db')->raw("'R'"));
                })  
               ->leftjoin("worksheet_status_log as wl", function($join) {
                            $join->on('wl.worksheet_id', '=', 'worksheet.id');
                            $join->on('wl.status_id', '=', app('db')->raw('13'));
                })  
               ->where("wt.reviewer_action","!=","0")
               ->where("wt.is_draft","0")
               ->where("wt.is_revision_history","0");
        
    }
    
    public static function reportArrangeData($data) {
        $user = \App\Models\User::select('userfullname', 'id')->get()->pluck('userfullname', 'id')->toArray();

        $designationids = Designation::select("designation_name")->where("is_display_in_allocation", "1")->get();
        foreach ($designationids as $designation) {
            $arrDDOption[$designation->designation_name] = $user;
        }
        $arrDDOption['Reviewer'] = $user;
        $arrDDOption['Team Member'] = $user;
        $arrDDOption['Aditional Assignee'] = $user;
        $arrDDOption['Master Activity'] = MasterActivity::where("is_active","1")->get()->pluck('name', 'id')->toArray();
        $arrDDOption['Task'] = TaskActivity::where("is_active","1")->get()->pluck('name', 'id')->toArray();
        $arrDDOption['Report Status'] = WorksheetStatus::where("is_active","1")->get()->pluck('status_name', 'id')->toArray();
        $arrDDOption['Traning Topic'] = WorksheetTraining::where("is_active","1")->get()->pluck('traning_name', 'id')->toArray();
        $arrDDOption['Tag'] = config('constant.reviewerTag');
       // $arrDDOption['Rate'] = config('constant.UserWorksheetRatting');
        $arrDDOption['Reviewer Action'] = config('constant.reviewerchecklistAction');
        
        foreach ($data->toArray() as $key => $value) {     
             foreach ($value as $rowkey => $rowvalue) {
                $data[$key][$rowkey] = (isset($arrDDOption[$rowkey])) ? ((isset($arrDDOption[$rowkey][$rowvalue])) ? $arrDDOption[$rowkey][$rowvalue] : '') : $rowvalue;   
            }            
        }     
        
        return $data;
    }
    
    public static function reportWorksheetArrangeData($data) {
        $user = \App\Models\User::select('userfullname', 'id')->get()->pluck('userfullname', 'id')->toArray();

        $designationids = Designation::select("designation_name")->where("is_display_in_allocation", "1")->get();
        foreach ($designationids as $designation) {
            $arrDDOption[$designation->designation_name] = $user;
        }
        $arrDDOption['Reviewer'] = $user;
        $arrDDOption['Team Member'] = $user;
        $arrDDOption['Actual Team Member'] = $user;
        $arrDDOption['Aditional Assignee'] = $user;
        $arrDDOption['Master Activity'] = MasterActivity::where("is_active","1")->get()->pluck('name', 'id')->toArray();
        $arrDDOption['Task'] = TaskActivity::where("is_active","1")->get()->pluck('name', 'id')->toArray();
        $arrDDOption['Status'] = WorksheetStatus::where("is_active","1")->get()->pluck('status_name', 'id')->toArray();
        $arrDDOption['Frequency'] = Frequency::where("is_active","1")->get()->pluck('frequency_name', 'id')->toArray();
        $arrDDOption['Service'] = Services::where("is_active","1")->get()->pluck('service_name', 'id')->toArray();
        $arrDDOption['Is There Delay'] = config('constant.yesNo');
        $arrDDOption['Delay From'] = config('constant.delayFrom');
        $arrDDOption['Rating'] = config('constant.UserWorksheetRatting');
        foreach ($data->toArray() as $key => $value) {     
             foreach ($value as $rowkey => $rowvalue) {
                $data[$key][$rowkey] = (isset($arrDDOption[$rowkey])) ? ((isset($arrDDOption[$rowkey][$rowvalue])) ? $arrDDOption[$rowkey][$rowvalue] : '') : $rowvalue;   
            }            
        }     
        
        return $data;
    }
    
    public static function getworksheetReport(){
        
       return  Worksheet::leftjoin("entity as e","e.id","worksheet.entity_id")
               ->leftjoin("entity as ep","ep.id","e.parent_id")
               ->leftjoin("worksheet_status_log as wl", function($join) {
                            $join->on('wl.worksheet_id', '=', 'worksheet.id');
                            $join->on('wl.status_id', '=', app('db')->raw('4'));
                })  
               ->whereIn("worksheet.status_id",[4]);
        
    }
}
