<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class InformationTrigger extends Model {

    protected $guarded = ['id'];
    protected $table = 'information_trigger';
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
