<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class MasterChecklistGroup extends Model
{
    protected $guarded = ['id'];
    protected $table = 'master_checklist_group';
    public $timestamps = false;
    
    public function parent_id() {
        return $this->belongsTo(Mastertaskactivity::class, 'parent_id', 'id');
    }
}
