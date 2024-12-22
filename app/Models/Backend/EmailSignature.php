<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class EmailSignature extends Model {

    protected $guarded = ['id'];
    protected $table = 'email_signature';
    protected $hidden = [ ];
    public $timestamps = false;
    
    public function userId()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id', 'id');
    }
    
    public function bkUserId()
    {
        return $this->belongsTo(\App\Models\User::class, 'bk_user_id', 'id');
    }
    
    public function designationId(){
        return $this->belongsTo(\App\Models\Backend\Designation::class, 'designation_id', 'id');
    }
    
    public function createdBy(){
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
    
    public function modifiedBy(){
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }
}
