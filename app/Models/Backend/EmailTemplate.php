<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model {

    protected $guarded = ['id'];
    protected $table = 'email_template';
    protected $hidden = [];
    public $timestamps = false;

    public function createdBy() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }

    public function modifiedBy() {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }
    
    public static function getTemplate($code){
        return $template = EmailTemplate::where('code',$code)->where('is_active', 1)->first();
    }
}