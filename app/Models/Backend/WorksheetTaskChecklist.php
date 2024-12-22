<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class WorksheetTaskChecklist extends Model {

    protected $guarded = [];
    protected $fillable = [];
    protected $table = 'worksheet_task_checklist';
    protected $hidden = [];
    public $timestamps = false;
    
    function teamMemberComment(){
        return $this->hasMany(\App\Models\Backend\WorksheetTaskChecklistComment::class, 'worksheet_task_checklist_id', 'id')->where('staff_type', 'P');
    }
    
    function reviewerComment(){
        return $this->hasMany(\App\Models\Backend\WorksheetTaskChecklistComment::class, 'worksheet_task_checklist_id', 'id')->where('staff_type', 'R');
    }
    
    function technicalHeadComment(){
        return $this->hasMany(\App\Models\Backend\WorksheetTaskChecklistComment::class, 'worksheet_task_checklist_id', 'id')->where('staff_type', 'T');
    }
}
