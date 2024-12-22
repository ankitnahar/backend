<?php
namespace App\Models\Backend;
use Illuminate\Database\Eloquent\Model;

class HrLeaveBal extends Model
{
    protected $guarded = ['id'];
    protected $table = 'hr_leave_bal';
    protected $hidden = [];
    public $timestamps = false;   
    
    
    public function created_by()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
     public function shift_id() {
        return $this->belongsTo(\App\Models\Backend\HrShift::class, 'shift_id', 'id');
    }
    
    public function assignee() {
        return $this->belongsTo(\App\Models\User::class, 'user_id', 'id')->with('firstApproval:id,userfullname,email,user_image')->with('secondApproval:id,userfullname,email,user_image');
    }

}
