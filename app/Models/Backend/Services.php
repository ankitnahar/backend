<?php
namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class Services extends Model
{
    // Table name which we used from database
    protected $table = 'services';
    protected $fillable = ['service_name', 'is_active'];
    protected $hidden = [];
    public $timestamps = false;
    
     public static function getServices() {
        return Services::where('is_active', 1)->get()->pluck('service_name', 'id')->toArray();
    }
    
    public static function AllServices() {
        $service =  Services::where('is_active', 1)->get(['service_name', 'id']);
        foreach($service as $row){
            $serviceArray[$row->id] = $row->service_name;
        }
        return $serviceArray;
    }
    
    public function createdBy() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }

    public function modifiedBy() {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }
}