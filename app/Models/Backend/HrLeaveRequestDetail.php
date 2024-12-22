<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class HrLeaveRequestDetail extends Model {

    protected $guarded = ['id'];
    protected $table = 'hr_leave_request_detail';
    protected $hidden = [];
    public $timestamps = false;

}
