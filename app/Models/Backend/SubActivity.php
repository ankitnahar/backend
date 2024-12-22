<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class SubActivity extends Model
{
     protected $guarded = [ ];

    protected $table = 'subactivity';
    protected $hidden = [ ];
    public $timestamps = false;
    
    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
    
    public function modifiedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }
    
    public function masterId()
    {
        return $this->belongsTo(MasterActivity::class, 'master_id', 'id');
    }
    
    public function taskId()
    {
        return $this->belongsTo(TaskActivity::class, 'task_id', 'id');
    }
   
}