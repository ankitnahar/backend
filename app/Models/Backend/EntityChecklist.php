<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;
use App\Models\Backend\MasterChecklist,
    App\Models\Backend\BillingServices,
    App\Models\Backend\UserHierarchy;
use DB;
class EntityChecklist extends Model {

    protected $guarded = ['id'];
    protected $table = 'entity_checklist';
    public $timestamps = false;

    public static function entityChecklist($entity_id) {
        $team = UserHierarchy::select('team_id', 'designation_id')->where('user_id', app('auth')->guard()->id())->first();
        $isInternalEntity = Billing::where('entity_id', $entity_id)->where('entity_grouptype_id', 17)->get()->count();
        if ($team->designation_id != config('constant.SUPERADMIN')) {
            $agreeTeam = explode(",", $team->team_id);
            $agreeService = $agreeTeam;
        } else {
            $agreeService = BillingServices::select('service_id')->where('is_latest', 1)->where('entity_id', $entity_id)->get()->pluck('service_id', 'service_id')->toArray();
        }
        // Checkout entity agreed service agreed or not
        //if (!empty($agreeService)) {
        $masterChecklist = MasterChecklist::select('master_activity.id as master_activity_id', 'master_activity.name as master_activity_name', 'master_activity.service_id', 'master_checklist.master_activity_id', 'master_checklist.name', 'master_checklist.task_id', 'task.name as task_name', 'ec.*', app('db')->raw("IF(`ec`.`is_applicable` != '', `ec`.`is_applicable`, 0) as is_applicable"), 'master_checklist.id AS master_checklist_id')
                ->leftJoin('master_activity', 'master_activity.id', '=', 'master_activity_id')
                ->leftJoin('task', 'task.id', '=', 'master_checklist.task_id')
                ->leftjoin('entity_checklist as ec', function($join) use($entity_id) {
                    $join->on('ec.master_checklist_id', '=', 'master_checklist.id');
                    $join->on('ec.entity_id', '=', app('db')->raw($entity_id));
                })
                ->where('master_checklist.is_active', 1);

        if ($isInternalEntity == 0) {
            $masterChecklist = $masterChecklist->whereIn('master_activity.service_id', $agreeService);
        } else {
            $masterChecklist = $masterChecklist->where('master_activity.service_id', "0");
            if ($team->designation_id != config('constant.SUPERADMIN')) {
                for ($t = 0; $t < count($agreeTeam); $t++) {
                    $masterChecklist = $masterChecklist->whereRaw("FIND_IN_SET($agreeTeam[$t],master_activity.user_team_id)");
                }
            }
        }


        return $masterChecklist;
//        } else {
//            return $agreeService;
//        }
    }

    public static function getReportData() {
        return EntityChecklist::leftjoin("entity as e", "e.id", "entity_checklist.entity_id")
                        ->leftjoin("entity as ep", "ep.id", "e.parent_id")
                        ->leftjoin("master_checklist as m", "m.id", "entity_checklist.master_checklist_id")
                        ->leftJoin('entity_allocation as ea', function($query) {
                            $query->on('ea.entity_id', '=', 'entity_checklist.entity_id');
                            $query->on('ea.service_id', '=', DB::raw("1"));
                        })
                        ->leftJoin('user as ut', function($query) {
                            $query->where('ut.id', DB::raw('JSON_VALUE(ea.allocation_json,"$.9")'));
                        })
                        ->leftJoin('user as u', function($query) {
                            $query->where('u.id', DB::raw('JSON_VALUE(ea.allocation_json,"$.60")'));
                        })
                        ->leftJoin('user as utl', function($query) {
                            $query->where('utl.id', DB::raw('JSON_VALUE(ea.allocation_json,"$.61")'));
                        })
                        ->select("e.code", "e.trading_name", "e.billing_name", "ep.trading_name as parent_trading_name",
                                "ut.userfullname as tam_name", "u.userfullname as tl_name","utl.userfullname as atl_name", "e.discontinue_stage", "m.name", "entity_checklist.is_applicable")
                        ->where('m.is_active', "1")
                        ->where('entity_checklist.is_applicable', "1")
                        ->where("e.discontinue_stage", "!=", "2");
    }

    public function master_checklist_id() {
        return $this->belongsTo(\App\Models\Backend\MasterChecklist::class, 'master_checklist_id', 'id')->with('master_activity_id:id,name')->with('task_id:id,name');
    }

}
