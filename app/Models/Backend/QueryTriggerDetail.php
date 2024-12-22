<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class QueryTriggerDetail extends Model {

    protected $guarded = [ ];

    protected $table = 'query_trigger_detail';
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
