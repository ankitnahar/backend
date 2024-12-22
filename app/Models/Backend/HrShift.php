<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class HrShift extends Model {

    protected $guarded = ['id'];
    protected $fillable = ['shift_name', 'from_time', 'to_time', 'grace_period', 'late_period', 'late_allowed_count', 'break_time', 'description', 'is_active', 'sort_order', 'created_by', 'created_on'];
    protected $table = 'hr_shift_master';
    protected $hidden = [];
    public $timestamps = false;

    public function created_by()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
    
    public function modified_by()
    {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }
    
    public static function getShift() {
        return HrShift::where('is_active', 1)->get(['shift_name', 'id'])->toArray();
    }
    
    public static function AllShift() {
        return $shift =  HrShift::where('is_active', 1)->get()->pluck('shift_name', 'id')->toArray();   
        
    } 
    
}
