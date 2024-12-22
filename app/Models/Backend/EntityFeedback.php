<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;
use DB;

class EntityFeedback extends Model {

    protected $guarded = [ ];

    protected $table = 'feedback';
    protected $hidden = [ ];
    public $timestamps = false;
    
    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
    
    public function ModifiedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }

    public static function getEntityFeedback(){
        return EntityFeedback::with('createdBy:userfullname as created_by,id','modifiedBy:userfullname as modified_by,id')
                ->leftjoin("entity as e","e.id","feedback.entity_id")
                ->leftjoin("billing_basic as b","b.entity_id","feedback.entity_id")
                ->leftjoin("billing_services as bs","bs.entity_id","feedback.entity_id")
                ->leftjoin("services as s","s.id","bs.service_id")
                ->leftJoin('feedback_user_hierarchy as ea', function($query) {
                    $query->on('ea.feedback_id', '=', 'feedback.id');
                    $query->on("ea.service_id","=","bs.service_id");
                })                
                ->leftJoin('user as ut', function($query) {
                    $query->where('ut.id',DB::raw('JSON_VALUE(ea.allocation_json,"$.9")'));
                })
                ->select(['e.id as entity_id', 'e.code', 'e.name','e.trading_name',DB::raw('GROUP_CONCAT(DISTINCT s.service_name) AS service_name_data'),
                    DB::raw('GROUP_CONCAT(DISTINCT ut.userfullname) AS tam_name'),
                    'e.discontinue_stage',"feedback.*"])
                ->where("bs.is_latest","1")
                ->whereIn("b.category_id",[1,2,3,4])
                ->whereIn("bs.service_id",[1,2,6])
                ->where("e.discontinue_stage","!=","2");
    }
}
