<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class InformationReminderLog extends Model {

    protected $guarded = ['id'];
    protected $table = 'information_reminder_log';
    protected $hidden = [ ];
    public $timestamps = false;
    
    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
    
    public static function addLog($id,$to,$loginUser=NULL){
        if($loginUser ==NULL){
        $loginUser = loginUser();
        }else{
           $loginUser = $loginUser; 
        }
        return $log = InformationReminderLog::create([
                        'information_id' => $id,
                        'reminder_date' => date('Y-m-d H:i:s'),
                        'to' => $to,
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => $loginUser]
            );
    }
}
