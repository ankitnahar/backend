<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class HrDetailHistory extends Model {

    protected $guarded = ['id'];
    protected $fillable = [];
    protected $table = 'hr_detail_history';
    protected $hidden = [];
    public $timestamps = false;

}
