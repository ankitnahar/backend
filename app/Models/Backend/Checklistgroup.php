<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class Checklistgroup extends Model
{

    protected $guarded = ['id','is_active'];
    protected $fillable = [];
    protected $table = 'master_checklist_group';
    public $timestamps = false;
 
    function master_checklist_id(){
        return $this->belongsTo(MasterChecklist::class, 'master_checklist_id', 'id');
    }
    
    function subactivity_id(){
        return $this->belongsTo(SubActivity::class, 'subactivity_id', 'id');
    }
    
    function task_id(){
        return $this->belongsTo(TaskActivity::class, 'subactivity_id', 'id');
    }
    
    function created_by(){
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
    
    function modified_by(){
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }
}
