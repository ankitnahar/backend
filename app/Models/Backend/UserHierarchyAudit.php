<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class UserHierarchyAudit extends Model {

    protected $guarded = [];
    protected $fillable = [];
    protected $table = 'user_hierarchy_audit';
    protected $hidden = [];
    public $timestamps = false;

    public function modifiedBy() {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id')->select('id', 'userfullname');
    }

}
