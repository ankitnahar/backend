<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class DiscontinueEntity extends Model {

    protected $guarded = ['id'];
    protected $fillable = [];
    protected $table = 'discontinue_entity';
    protected $hidden = [];
    public $timestamps = false;

    function discontinueBy() {
        return $this->belongsTo(\App\Models\User::class, 'discontinue_by', 'id');
    }

    function modifiedBy() {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }

    function entityId() {
        return $this->belongsTo(\App\Models\Backend\Entity::class, 'entity_id', 'id');
    }

    function status() {
        return $this->belongsTo(\App\Models\Backend\DiscontinueStatus::class, 'status', 'id');
    }

    public static function getDiscontinueData() {
        return DiscontinueEntity::select('e.code', 'e.trading_name', 'e.parent_id', 'ep.trading_name as parent_name', 'e.contract_signed_date', 'discontinue_entity.*', app('db')->raw('GROUP_CONCAT(DISTINCT CONCAT(stage_id, "=", is_completed)) as stage'), app('db')->raw('(SELECT
     SUM(i.paid_amount)
   FROM invoice AS i
   WHERE i.entity_id = discontinue_entity.entity_id
       AND i.status_id != 5) AS totalRevenue'), app('db')->raw('(SELECT
     SUM(i.paid_amount)
   FROM invoice AS i
   WHERE i.created_on BETWEEN CASE WHEN MONTH(discontinue_entity.discontinue_on) >= 7 THEN DATE_FORMAT(CONCAT(YEAR(discontinue_entity.discontinue_on) - 1,"-07-01"), "%Y-%m-%d")ELSE DATE_FORMAT(CONCAT(YEAR(discontinue_entity.discontinue_on) - 2, "-07-01"), "%Y-%m-%d")END
       AND CASE WHEN MONTH(discontinue_entity.discontinue_on) >= 7 THEN DATE_FORMAT(CONCAT(YEAR(discontinue_entity.discontinue_on),"-06-30"), "%Y-%m-%d")ELSE DATE_FORMAT(CONCAT(YEAR(discontinue_entity.discontinue_on) - 1, "-06-30"), "%Y-%m-%d")END
       AND i.entity_id = discontinue_entity.entity_id
       AND i.status_id != 5) AS lastfyRevanue'), app('db')->raw('FORMAT(threeinvoice(discontinue_entity.entity_id, discontinue_entity.discontinue_on), 2) AS lastthreeinvoiceRevanue'), app('db')->raw('GROUP_CONCAT(DISTINCT u.userfullname) AS technical_account_manager'),app('db')->raw('GROUP_CONCAT(DISTINCT utl.userfullname) AS tl'),app('db')->raw('GROUP_CONCAT(DISTINCT uatl.userfullname) AS atl'))
                        ->leftjoin('entity AS e', 'e.id', '=', 'discontinue_entity.entity_id')
                        ->leftjoin('entity AS ep', 'ep.id', '=', 'e.parent_id')
                        ->leftjoin('entity_allocation AS ea', 'ea.entity_id', '=', 'discontinue_entity.entity_id')
                        ->leftjoin('user AS u', function ($query) {
                            $query->whereRaw('u.id = json_extract(ea.allocation_json, "$.9")');
                        })
                        ->leftjoin('user AS utl', function ($query) {
                            $query->whereRaw('utl.id = json_extract(ea.allocation_json, "$.60")');
                        })
                        ->leftjoin('user AS uatl', function ($query) {
                            $query->whereRaw('uatl.id = json_extract(ea.allocation_json, "$.61")');
//                    })->leftjoin("discontinue_entity_stage AS des", "des.discontinue_entity_id", "=", "discontinue_entity.id")
                        })->leftjoin("discontinue_entity_stage AS des", "des.discontinue_entity_id", "=", "discontinue_entity.id")
                        ->with('discontinueBy:id,userfullname', 'status:id,status', 'modifiedBy:id,userfullname');
    }

    public static function arrageData($discontinueEntity) {
        $i = 0;
        $stageName = DiscontinueStage::pluck('stage', 'id')->toArray();
        foreach ($discontinueEntity as $key => $value) {
            //DiscontinueEntityStage::where("discontinue_entity_id",$value->entity_id)->where("completed_")
            $stage = array_filter(explode(",", $value->stage));
            $pendingStage = array();
            if (isset($stage) && !empty($stage)) {
                foreach ($stage as $keyStage => $valueStagae) {
                    $is_right = 0;
                    $tempExplode = explode("=", $valueStagae);
                    $is_right = checkButtonRights(129, $tempExplode[0]);
                    if ($is_right)
                        $pendingStage[] = array('id' => $tempExplode[0], 'name' => $stageName[$tempExplode[0]], 'status' => $tempExplode[1]);
                }
            }
            $discontinueEntity[$i]->stage = $pendingStage;
            $i++;
        }
        return $discontinueEntity;
    }

    public static function entityStage() {
        $discontinueEntityStage = DiscontinueEntityStage::all();
        return $discontinueEntityStage;
    }

}
