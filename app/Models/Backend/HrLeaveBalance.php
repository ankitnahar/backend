<?php
namespace App\Models\Backend;
use Illuminate\Database\Eloquent\Model;

class HrLeaveBalance extends Model
{
    protected $guarded = ['id'];
    protected $table = 'hr_leave_balance';
    protected $hidden = [];
    public $timestamps = false;   
    
    
    public function created_by()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
}
