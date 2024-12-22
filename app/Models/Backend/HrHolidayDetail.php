<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class HrHolidayDetail extends Model {

    protected $guarded = ['id'];
    protected $fillable = [];
    protected $table = 'hr_holiday_detail';
    protected $hidden = [];
    public $timestamps = false;
    
    public function shift_id(){
        return $this->belongsTo(\App\Models\Backend\HrShift::class, 'shift_id', 'id')->select('id', 'shift_name');
    }
}
