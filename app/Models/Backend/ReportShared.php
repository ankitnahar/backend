<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class ReportShared extends Model
{
    protected $guarded = [ ];
    protected $table = 'report_shared';
    protected $hidden = [];
    public $timestamps = false;

    function user_id(){
        return $this->belongsTo(\App\Models\User::class, 'user_id', 'id');
    }
}