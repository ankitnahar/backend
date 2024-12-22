<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class HrHoliday extends Model {

    protected $guarded = ['id'];
    protected $fillable = [];
    protected $table = 'hr_holiday';
    protected $hidden = [];
    public $timestamps = false;
    
    public function created_by(){
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
    
    public function modified_by(){
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }
    
    public function shiftdetails(){
        return $this->hasMany(\App\Models\Backend\HrHolidayDetail::class, 'hr_holiday_id', 'id')->with('shift_id');
    }
    
    public static function arrangeData($holiday){
        $i = 0;
        $data = array();
        foreach($holiday as $key => $value){
            $data[$value->date][] = array('description' => $value->description, 'data' => $value);
        }
        return $data;
    }
}
