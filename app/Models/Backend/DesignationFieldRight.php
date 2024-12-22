<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class DesignationFieldRight extends Model {

    protected $guarded = ['id'];
    protected $fillable = ['designation_id','field_id','view','add_edit','delete','created_on','created_by','modified_on','modified_by'];
    protected $table = 'designation_field_right';
    protected $hidden = [];
    public $timestamps = false;

    

    public static function store($field_id, $id, $tabFlag) {
        $loginUser = loginUser();
        return DesignationFieldRight::create([
                    'field_id' => $field_id,
                    'designation_id' => $id,
                    'view' => $tabFlag['view'],
                    'add_edit' => $tabFlag['add_edit'],
                    'created_on' => date('Y-m-d H:i:s'),
                    'created_by' => $loginUser]
        );
    }
}
