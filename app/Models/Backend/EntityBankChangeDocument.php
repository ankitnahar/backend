<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class EntityBankChangeDocument extends Model {

    protected $guarded = ['id'];
    protected $table = 'entity_bank_change_document';
    public $timestamps = false;
    
    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
    
  
}
