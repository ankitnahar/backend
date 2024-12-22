<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class DiscontinueStatus extends Model {

    protected $guarded = ['id'];
    protected $fillable = [];
    protected $table = 'discontinue_status';
    protected $hidden = [];
    public $timestamps = false;
}
