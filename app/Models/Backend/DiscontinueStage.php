<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class DiscontinueStage extends Model {

    protected $guarded = ['id'];
    protected $fillable = [];
    protected $table = 'discontinue_stage';
    protected $hidden = [];
    public $timestamps = false;
}
