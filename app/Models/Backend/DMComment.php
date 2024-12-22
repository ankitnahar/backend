<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class DMComment extends Model {

    //
    protected $table = 'dm_comments';
    protected $guarded = ['id'];
    protected $hidden = [];
    public $timestamps = false;

    public function createdBy() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }

}
