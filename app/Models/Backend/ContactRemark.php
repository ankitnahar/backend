<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class ContactRemark extends Model
{
    //
    protected $table = 'contact_remark';
    protected $fillable = ['contact_id','notes','is_active','created_by','created_on'];
    protected $hidden = [];
    protected $guarded = ['id'];
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