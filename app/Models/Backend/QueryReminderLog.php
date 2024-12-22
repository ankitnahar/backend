<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class QueryReminderLog extends Model {

    protected $guarded = [ ];

    protected $table = 'query_reminder_log';
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
        return $log = QueryReminderLog::create([
                        'query_id' => $id,
                        'reminder_date' => date('Y-m-d H:i:s'),
                        'to' => $to,
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => $loginUser]
            );
    }
}
