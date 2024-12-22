<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class HrLeaveRequest extends Model {

    protected $guarded = ['id'];
    protected $table = 'hr_leave_request';
    protected $hidden = [];
    public $timestamps = false;

    public function created_by() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
     public function firstApproval() {
        return $this->belongsTo(\App\Models\User::class, 'first_approval', 'id');
    }
    public function secondApproval() {
        return $this->belongsTo(\App\Models\User::class, 'second_approval', 'id');
    }
}
