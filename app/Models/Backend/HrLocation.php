<?php
namespace App\Models\Backend;
use Illuminate\Database\Eloquent\Model;

class HrLocation extends Model
{
    protected $guarded = ['id'];
    protected $table = 'hr_location';
    protected $hidden = [];
    public $timestamps = false;   
    
    
    public function created_by()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
    
    public static function getLocation() {
        return HrLocation::where('is_active', 1)->get(['location_name', 'id'])->toArray();
    } 
    
    public static function allLocation() {
        $location = HrLocation::where('status', 1)->get(['location_name', 'id']);
        foreach($location as $row){
            $locationArray[$row->id] = $row->location_name;
        }
        return $locationArray;
    }
}
