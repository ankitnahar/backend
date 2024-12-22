<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class BouncedEmail extends Model {

    protected $guarded = [ ];

    protected $table = 'bounced_email';
    protected $guarded = ['id'];
    public $timestamps = false;
    
}
