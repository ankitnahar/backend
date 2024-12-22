<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class MasterChecklistQuestion extends Model
{
    protected $guarded = ['id'];
    protected $table = 'master_checklist_question';
    public $timestamps = false;
    
    function created_by(){
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
    
    function modified_by(){
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }
    
    public static function arrangeData(){
        return MasterChecklistQuestion::select('mc.name as checklistName', 'ma.name as activityName', 't.name as taskName', 'mcg.name as groupName', 'master_checklist_question.*')
                ->leftjoin('master_checklist as mc', 'mc.id', '=', 'master_checklist_question.master_checklist_id')
                ->leftjoin('master_activity as ma', 'ma.id', '=', 'mc.master_activity_id')
                ->leftjoin('task as t', 't.id', '=', 'mc.task_id')
                ->leftjoin('master_checklist_group as mcg', 'mcg.id', '=', 'master_checklist_question.checklist_group_id')
                ->with('created_by:id,userfullname');
    }
}
