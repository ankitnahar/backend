<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class WorksheetTaskChecklistComment extends Model {

    protected $guarded = [];
    protected $fillable = [];
    protected $table = 'worksheet_task_checklist_comment';
    protected $hidden = [];
    public $timestamps = false;

    public function createdBy() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }

}
