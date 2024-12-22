<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class SubClient extends Model
{
     protected $guarded = [ ];

    protected $table = 'sub_client';
    protected $hidden = [ ];
    public $timestamps = false;
    
    public function entity_id()
    {
        return $this->belongsTo(\App\Models\Backend\Entity::class, 'entity_id', 'id');
    }
    
    public function created_by()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
    
    public function modified_by()
    {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    } 
   
   
}