<?php
namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class ReportSaved extends Model
{
    // Table name which we used from database
    protected $guarded = [ ];
    protected $table = 'report_saved';
    protected $hidden = [];
    public $timestamps = false;

    function created_by(){
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
    
    function shared_user(){
        return $this->hasMany(\App\Models\Backend\ReportShared::class, 'report_saved_id', 'id')->with('user_id:id,userfullname');
    }
}