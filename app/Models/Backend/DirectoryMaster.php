<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class DirectoryMaster extends Model {

    protected $guarded = ['id'];
    protected $table = 'directory_master';
    protected $hidden = [];
    public $timestamps = false;

    public function createdBy() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }

    public function modifiedBy() {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }

    public function children() {
        return $this->hasMany(DirectoryMaster::class, 'parent_id', 'id');
    }

    public function parent()
    {
    return $this->belongsTo(DirectoryMaster::class, 'parent_id');

    }
    

}
