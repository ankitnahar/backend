<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class Department extends Model {

    protected $guarded = ['id'];
    protected $table = 'department';
    protected $hidden = [];
    public $timestamps = false; 
    
    public static function getDepartment() {
        return Department::where('is_active', 1)->get('department_name', 'id')->toArray();
    }
    
    public static function allDepartment() {
        $department = Department::where('is_active', 1)->get(['department_name', 'id']);
        foreach($department as $row){
            $departmentArray[$row->id] = $row->department_name;
        }
        return $departmentArray;
    }
}
