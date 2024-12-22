<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class QueryCall extends Model {

    protected $guarded = [ ];

    protected $table = 'query_call';
    protected $hidden = [ ];
    public $timestamps = false;
    
    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
    
    public static function addCall($id, $entity_id, $loginUser = NULL){
        if($loginUser ==NULL){
            $loginUser = loginUser();
        }else{
            $loginUser = $loginUser; 
        }
        return $call = QueryCall::create([
                        'query_id' => $id,
                        'entity_id' => $entity_id,
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => $loginUser]
            );
    }

    public static function callListData($id) {
        return QueryCall::with('createdBy:id,userfullname as created_by')
                ->leftjoin("entity as e", "e.id", "query_call.entity_id")
                ->select("query_call.id", "query_call.entity_id","e.billing_name", "e.trading_name", "query_call.query_id", "query_call.created_by", "query_call.created_on")
                ->where("query_call.query_id",$id);
    }
}
