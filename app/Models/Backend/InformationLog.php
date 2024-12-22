<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class InformationLog extends Model {

    protected $guarded = ['id'];
    protected $table = 'information_log';
    protected $hidden = [ ];
    public $timestamps = false;
    
    public function modifiedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }
    
    public function statusId() {
        return $this->belongsTo(InformationStage::class, 'status_id', 'id');
    }
    
    public static function addLog($id,$status_id,$loginUser=NULL){
        if($loginUser ==NULL){
        $loginUser = loginUser();
        }else{
           $loginUser = $loginUser; 
        }
        return $log = InformationLog::create([
                        'information_id' => $id,
                        'status_id' => $status_id,
                        'modified_on' => date('Y-m-d H:i:s'),
                        'modified_by' => $loginUser]
            );
    }
}