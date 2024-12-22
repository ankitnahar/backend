<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class DocuAuth extends Model
{
    protected $guarded = ['id'];
    protected $table = 'docu_auth';
    public $timestamps = false;

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
    
   
    
}
