<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class MasterChecklist extends Model
{
    protected $guarded = ['id'];
    protected $fillable = [];
    protected $table = 'master_checklist';
    public $timestamps = false;
    
    public function master_activity_id() {
        return $this->belongsTo(MasterActivity::class, 'master_activity_id', 'id');
    }
    
    public function task_id() {
        return $this->belongsTo(TaskActivity::class, 'task_id', 'id');
    }
    
    public function created_by() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
}
