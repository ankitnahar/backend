<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class WorksheetTraining extends Model
{
    protected $table = 'worksheet_traning';
    protected $fillable = ['id', 'traning_name', 'is_active', 'created_by', 'created_on', 'modified_by', 'modified_on'];
    protected $hidden = [];
    public $timestamps = false;
    
    public function created_by(){
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
    
     public function modified_by(){
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }
}
