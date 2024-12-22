<?php
namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;
use DB;

class Information extends Model {

    protected $guarded = ['id'];

    protected $table = 'information';
    protected $hidden = [ ];
    public $timestamps = false;
    
    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }

    public function additionalTl()
    {
        return $this->belongsTo(\App\Models\User::class, 'additional_tl', 'id');
    }

    public function additionalTm()
    {
        return $this->belongsTo(\App\Models\User::class, 'additional_tm', 'id');
    }
    
    public function modifiedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }

    public function stageId()
    {
        return $this->belongsTo(\App\Models\Backend\InformationStage::class, 'stage_id', 'id');
    }

    public static function informationData() {
        return Information::with('createdBy:id,userfullname as created_by', 'modifiedBy:id,userfullname as modified_by')
                ->with('additionalTl:id,userfullname')
                ->with('additionalTm:id,userfullname')
                ->with('stageId:id,status_name as stage_name')
                ->leftjoin("entity as e", "e.id", "information.entity_id")
                ->leftjoin("entity as ep", "ep.id", "e.parent_id")
                ->leftjoin("information_stage as ins", "ins.id", "information.stage_id")
                ->leftjoin("information_detail as indetail", "indetail.information_id", "information.id")
                ->leftjoin("frequency", "frequency.id", "information.frequency_id")
                ->leftJoin('user as ut', function($query) {
                    $query->where('ut.id', DB::raw('JSON_VALUE(information.team_json,"$.9")'));
                })
                ->leftJoin('user as u', function($query) {
                    $query->where('u.id', DB::raw('JSON_VALUE(information.team_json,"$.10")'));
                })
                ->leftJoin('user as um', function($query) {
                    $query->where('um.id', DB::raw('JSON_VALUE(information.team_json,"$.60")'));
                })
                 ->leftJoin('user as uat', function($query) {
                    $query->where('uat.id', DB::raw('JSON_VALUE(information.team_json,"$.61")'));
                })
                ->select("information.*", "e.billing_name",'e.parent_id',"e.discontinue_stage", "e.trading_name","ep.trading_name as parent_name","ut.userfullname as tam_name","um.userfullname as tl_name",
                        "uat.userfullname as atl_name", "u.userfullname as team_member", "frequency.frequency_name",DB::raw('COUNT(indetail.id) AS totalInformation'));
    }
    
    public static function arrangeData($data) {      
         $i = 0;
        foreach ($data as $row) {
             $addInfo = InformationAdditionalInfo::where("is_deleted","0")->where("information_id",$row->id)->count();
            $data[$i]['totalInformation'] = $row->totalInformation +$addInfo;
            $data[$i]['partial_count'] = InformationDetail::where("status_id","2")->where("information_id",$row->id)->count();
            $data[$i]['received_count'] = InformationDetail::whereIn("status_id",[3,5])->where("information_id",$row->id)->count();
            $data[$i]['resolved_count'] = InformationDetail::whereIn("status_id",[5])->where("information_id",$row->id)->count();
            $data[$i]['pending_count'] = $data[$i]['totalInformation'] - ($data[$i]['partial_count'] + $data[$i]['received_count'] + $data[$i]['resolved_count']);
            $i++;
        }
        return $data;
    }

    public static function contactMailData() {
        return Information::leftjoin("entity as e", "e.id", "information.entity_id")
                ->select("information.id","information.entity_id","information.subject", "e.trading_name", "information.start_period", "information.end_period")
               ->where("e.discontinue_stage","!=","2");
    }
}
