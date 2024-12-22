<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;
use DB;

class Query extends Model {

    protected $guarded = [ ];

    protected $table = 'query';
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
    
    public function additionalATl()
    {
        return $this->belongsTo(\App\Models\User::class, 'additional_atl', 'id');
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

    public static function queryData() {
        return Query::with('createdBy:id,userfullname as created_by', 'modifiedBy:id,userfullname as modified_by')
                ->with('additionalATl:id,userfullname')
                ->with('additionalTl:id,userfullname')
                ->with('additionalTm:id,userfullname')
                ->with('stageId:id,status_name as stage_name')
                ->leftjoin("entity as e", "e.id", "query.entity_id")
                ->leftjoin("entity as ep", "ep.id", "e.parent_id")
                ->leftjoin("query_stage as ins", "ins.id", "query.stage_id")
                ->leftjoin("query_detail as indetail", "indetail.query_id", "query.id")
                ->leftjoin("frequency", "frequency.id", "query.frequency_id")
                ->leftJoin('user as ut', function($query) {
                    $query->where('ut.id', DB::raw('JSON_VALUE(query.team_json,"$.9")'));
                })
                ->leftJoin('user as u', function($query) {
                    $query->where('u.id', DB::raw('JSON_VALUE(query.team_json,"$.10")'));
                })
                ->leftJoin('user as um', function($query) {
                    $query->where('um.id', DB::raw('JSON_VALUE(query.team_json,"$.60")'));
                })
                ->leftJoin('user as ul', function($query) {
                    $query->where('ul.id', DB::raw('JSON_VALUE(query.team_json,"$.61")'));
                })
                ->select("query.id", "query.entity_id","e.discontinue_stage","query.reminder","query.snooze","e.billing_name", "e.trading_name","ep.trading_name as parent_name","ut.userfullname as tam_name","um.userfullname as tl_name",
                        "ul.userfullname as atl_name","e.parent_id", "u.userfullname as team_member", "query.subject", "query.start_period", "query.end_period", "query.stage_id", "frequency.frequency_name",
                        "query.additional_tl", "query.additional_tm","query.created_on", "query.created_by",DB::raw('COUNT(indetail.id) AS totalInformation'));
    }

    public static function contactMailData() {
        return Query::leftjoin("entity as e", "e.id", "query.entity_id")
                ->select("query.id","query.entity_id","query.subject", "e.trading_name", "query.start_period", "query.end_period")
               ->where("e.discontinue_stage","!=","2");
    }
    
    public static function arrangeData($data) {      
         $i = 0;
        foreach ($data as $row) {
            $addQuery = QueryAdditionalInfo::where("is_deleted","0")->where("query_id",$row->id)->count();
            $data[$i]['totalInformation'] = $row->totalInformation + $addQuery;
            $data[$i]['partial_count'] = QueryDetail::where("status_id","2")->where("query_id",$row->id)->count();
            $data[$i]['received_count'] = QueryDetail::whereIn("status_id",[3,5])->where("query_id",$row->id)->count();
            $onlyReceive = QueryDetail::whereIn("status_id",[3])->where("query_id",$row->id)->count();
            $data[$i]['resolved_count'] = QueryDetail::whereIn("status_id",[5])->where("query_id",$row->id)->count();
            $pending = $data[$i]['totalInformation'] - ($data[$i]['partial_count'] + $onlyReceive + $data[$i]['resolved_count']);
            $data[$i]['pending_count'] = $pending > 0 ? $pending : 0;
                    
            
             $i++;
        }
        return $data;
    }
}
