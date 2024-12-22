<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class WorksheetStatusUserRight extends Model {

    protected $guarded = [];
    protected $table = 'worksheet_status_user_right';
    protected $hidden = [];
    public $timestamps = false;
    
    //user wise worksheet data
    public static function checkright($status_id,$id) {
    return WorksheetStatusUserRight::leftjoin("worksheet_status as ws", "ws.id", "worksheet_status_user_right.worksheet_status_id")
                                ->select("worksheet_status_user_right.id", "ws.status_name", "worksheet_status_user_right.right")
                                ->where("worksheet_status_user_right.worksheet_status_id", "=", $status_id)
                                ->where("worksheet_status_user_right.user_id", "=", $id)->first();
    } 
    

}
