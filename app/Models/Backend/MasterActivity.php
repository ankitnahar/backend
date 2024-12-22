<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class MasterActivity extends Model {

    protected $guarded = ['id'];
    protected $table = 'master_activity';
    protected $hidden = [];
    public $timestamps = false;

    public function createdBy() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }

    public function modifiedBy() {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }

    public function serviceId() {
        return $this->belongsTo(Services::class, 'service_id', 'id');
    }
    
    public static function masterActivity(){
        $data = $task = array();
        $masterActivity = \App\Models\Backend\MasterActivity::get()->pluck('name', 'id')->toArray();
        $taskActivity = \App\Models\Backend\TaskActivity::select('id', 'master_activity_id', 'name')->get()->toArray();
        foreach($taskActivity as $key => $value)
            $task[$value['master_activity_id']][] = $value;
        
        $data['masterActivity'] = $masterActivity;
        $data['task'] = $task;
        
        return $data;
    }
    
    public static function getMasterIdServiceWise($serviceId){
        return MasterActivity::where("service_id", $serviceId)->get();
    }
}
