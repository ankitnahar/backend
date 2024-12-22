<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class TaskActivity extends Model {

    protected $guarded = [];
    protected $table = 'task';
    protected $hidden = [];
    public $timestamps = false;

    public function createdBy() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }

    public function modifiedBy() {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }

    public function masterActivityId() {
        return $this->belongsTo(MasterActivity::class, 'master_activity_id', 'id');
    }

}
