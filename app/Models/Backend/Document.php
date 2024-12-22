<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class Document extends Model {

    protected $guarded = ['id'];
    protected $table = 'document';
    public $timestamps = false;

    function created_by() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }

}
