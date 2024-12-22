<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;
use DB;

class WorksheetMaster extends Model {

    protected $guarded = [];
    protected $table = 'worksheet_master';
    protected $hidden = [];
    public $timestamps = false;

    public function createdBy() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }

    public function masterActivityId() {
        return $this->belongsTo(\App\Models\Backend\MasterActivity::class, 'master_activity_id', 'id');
    }

    public function taskId() {
        return $this->belongsTo(\App\Models\Backend\TaskActivity::class, 'task_id', 'id');
    }
    
    public function frequencyId() {
        return $this->belongsTo(\App\Models\Backend\Frequency::class, 'frequency_id', 'id');
    }
}
