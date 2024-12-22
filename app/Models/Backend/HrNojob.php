<?php
namespace App\Models\Backend;
use Illuminate\Database\Eloquent\Model;

class HrNojob extends Model
{
    protected $guarded = [ ];
    protected $fillable = ['user_id', 'date', 'start_time', 'end_time', 'is_active'];
    protected $table = 'hr_nojob';
    protected $hidden = [ ];
    public $timestamps = false;
    
    public function noJobStaff()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id', 'id');
    }
}