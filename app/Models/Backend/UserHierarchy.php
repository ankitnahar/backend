<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;
use DB;

class UserHierarchy extends Model {

    protected $guarded = [];
    protected $table = 'user_hierarchy';
    protected $hidden = [];
    public $timestamps = false;
    
    //for save history
    public static function boot() {
        parent::boot();
        self::updating(function($user_hierarchy) {
         $col_name = [           
            'team_id' => 'Team',
            'other_right' => 'Other Service Right',
            'department_id' => 'Department',
            'designation_id' => 'Designation',
            'parent_user_id' => 'Parent User'
        ];
        
        $changesArray = \App\Http\Controllers\Backend\User\UserController::saveHistory($user_hierarchy, $col_name);
        $updatedBy = loginUser();
        
        DB::table("user_hierarchy_audit")->insert([
            'user_id' => $user_hierarchy->user_id,
            'changes' => json_encode($changesArray),
            'modified_on' => date('Y-m-d H:i:s'),
            'modified_by' => $updatedBy
        ]);
        });
    } 
    
    
    public static function getUserHierarchy($user_id) {
        return UserHierarchy::where('user_id', $user_id)->pluck('designation_id', 'user_id')->toArray();
    }
    
     public function createdBy(){
       return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
    
}
