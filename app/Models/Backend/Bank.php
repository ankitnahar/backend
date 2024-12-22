<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class Bank extends Model {

    protected $guarded = ['id'];
    protected $table = 'banks';
    public $timestamps = false;
    
    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
    
    public function modifiedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }

     public static function getBank() {
        return $bank = Bank::get()->pluck('bank_name', 'id')->toArray();
    }
}
