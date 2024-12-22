<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class DirectoryServiceCreation extends Model {

    protected $guarded = ['id'];
    protected $table = 'directory_service_creation';
    protected $hidden = [ ];
    public $timestamps = false;
    
    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
}
