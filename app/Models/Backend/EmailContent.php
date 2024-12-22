<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class EmailContent extends Model {

    protected $guarded = ['id'];
    protected $table = 'email_contents';
    protected $hidden = [];
    public $timestamps = false;    

}
