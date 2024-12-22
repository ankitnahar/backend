<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class InformationStage extends Model {

    protected $guarded = ['id'];

    protected $table = 'information_stage';
    protected $hidden = [ ];
    public $timestamps = false;
    
}
