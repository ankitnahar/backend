<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;
use App\Models\Backend\MasterChecklistQuestion,
    App\Models\Backend\MasterChecklistGroup;

class EntitychecklistquestionAudit extends Model {
    
    protected $guarded = ['id'];
    protected $table = 'entity_checklist_question_audit';
    protected $fillable = ['entity_id', 'changes', 'modified_by', 'modified_on'];
    public $timestamps = false;
    
    public function modified_by()
    {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }
}
