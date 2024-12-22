<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class DiscontinueComment extends Model {

    protected $guarded = ['id'];
    protected $fillable = [];
    protected $table = 'discontinue_comment';
    protected $hidden = [];
    public $timestamps = false;
    
    function createdBy(){
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
}
