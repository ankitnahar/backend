<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class DirectoryEntityFile extends Model {

    protected $guarded = ['id'];
    protected $table = 'directory_entity_file';
    protected $hidden = [ ];
    public $timestamps = false;
    
    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
    
    public function modifiedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }
}
