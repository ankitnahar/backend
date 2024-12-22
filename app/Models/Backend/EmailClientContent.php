<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class EmailClientContent extends Model {

    protected $guarded = ['id'];
    protected $table = 'email_contents_client';
    protected $hidden = [];
    public $timestamps = false;    

}
