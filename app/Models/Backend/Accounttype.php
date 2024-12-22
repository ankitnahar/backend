<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class Accounttype extends Model
{
    protected $guarded = ['id'];
    protected $fillable = [];

    protected $table = 'bank_type';
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
    
    public static function getAccountType() {
        return $account = Accounttype::where('is_active', 1)->get()->pluck('type_name','id')->toArray();        
    }
}
