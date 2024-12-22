<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class PendingTimesheet extends Model {

    protected $guarded = [];
    protected $fillable = ['hr_detail_id', 'user_id', 'date'];
    protected $table = 'hr_pendingtimesheet';
    protected $hidden = [];
    public $timestamps = false;
    
    public function assignee() {
        return $this->belongsTo(\App\Models\User::class, 'user_id', 'id')->with('timesheetApprovalUser:id,userfullname,user_image,email');
    }
    
    public function hrDetailId() {
        return $this->belongsTo(\App\Models\Backend\HrDetail::class, 'hr_detail_id', 'id');
    }
}