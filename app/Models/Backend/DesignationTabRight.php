<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class DesignationTabRight extends Model {

    protected $guarded = ['id'];
    protected $fillable = ['tab_id', 'designation_id', 'view', 'add_edit', 'created_on', 'created_by', 'modified_on', 'modified_by'];
    protected $table = 'designation_tab_right';
    protected $hidden = [];
    public $timestamps = false;

    //get designation wise right
    public static function checkRight($tab_id, $id) {
        return DesignationTabRight::select("id")
                        ->where("tab_id", "=", $tab_id)
                        ->where("designation_id", "=", $id)->first();
    }

    public static function store($tab_id, $id, $tabFlag) {
        $loginUser = loginUser();
        return DesignationTabRight::create([
                    'tab_id'        => $tab_id,
                    'designation_id'=> $id,
                    'view'          => $tabFlag['view'],
                    'add_edit'      => $tabFlag['add_edit'],
                    'delete'          => $tabFlag['delete'],
                    'export'      => $tabFlag['export'],
                    'download'      => $tabFlag['download'],
                    'created_on'    => date('Y-m-d H:i:s'),
                    'created_by'    => $loginUser]
        );
    }

}
