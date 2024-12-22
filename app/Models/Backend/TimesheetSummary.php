<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;
use DB;

class TimesheetSummary extends Model {

    protected $guarded = [];
    protected $table = 'timesheet_summary';
    protected $hidden = [];
    public $timestamps = false;

    
    public function userId() {
        return $this->belongsTo(\App\Models\User::class, 'user_id', 'id');
    }
    
    public function divisionHead() {
        return $this->belongsTo(\App\Models\User::class, 'division_head', 'id');
    }
    
    public function technicalAccountManager() {
        return $this->belongsTo(\App\Models\User::class, 'technical_account_manager', 'id');
    }
}
