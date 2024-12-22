<?php
namespace App\Models\Backend;
use Illuminate\Database\Eloquent\Model;

class HrExceptionshift extends Model
{
    protected $guarded = ['id'];
    protected $fillable = [];
    protected $table = 'hr_exception_shift';
    protected $hidden = [ ];
    public $timestamps = false;

    public function shift_id(){
        return $this->belongsTo(\App\Models\Backend\HrShift::class, 'shift_id', 'id');
    }
    
    public function created_by(){
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
    
    public function modified_by(){
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }
}
