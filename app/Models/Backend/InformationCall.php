<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class InformationCall extends Model {

    protected $guarded = ['id'];
    protected $table = 'information_call';
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
        return $call = InformationCall::create([
                        'information_id' => $id,
                        'entity_id' => $entity_id,
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => $loginUser]
            );
    }

    public static function callListData($id) {
        return InformationCall::with('createdBy:id,userfullname as created_by')
                ->leftjoin("entity as e", "e.id", "information_call.entity_id")
                ->select("information_call.id", "information_call.entity_id","e.billing_name", "e.trading_name", "information_call.information_id", "information_call.created_by", "information_call.created_on")
                ->where("information_call.information_id",$id);
    }
}
