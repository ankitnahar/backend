<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class DiscontinueQuestion extends Model {

    protected $table = 'discontinue_question';
    protected $guarded = ['id'];
    protected $hidden = [];
    public $timestamps = false;
    
    public function createdBy() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
    
    public function modifiedBy() {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }
    
    public function parentId() {
        return $this->belongsTo(\App\Models\Backend\DiscontinueQuestion::class, 'parent_id', 'id');
    }
}
