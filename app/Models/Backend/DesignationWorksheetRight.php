<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class DesignationWorksheetRight extends Model {

    protected $guarded = ['id'];
    protected $table = 'designation_worksheet_status_right';
    protected $hidden = [];
    public $timestamps = false;

    //get designation wise right
    public static function checkRight($worksheet_status_id, $id) {
        return DesignationWorksheetRight::select("id")
                        ->where("worksheet_status_id", "=", $worksheet_status_id)
                        ->where("designation_id", "=", $id)->first();
    }

    public static function store($tab_id, $id, $tabFlag) {
        $loginUser = loginUser();
        return DesignationWorksheetRight::create([
                    'worksheet_status_id' => $tabFlag['id'],
                    'designation_id' => $id,
                    'right' => $tabFlag['view'],
                    'created_on' => date('Y-m-d H:i:s'),
                    'created_by' => $loginUser]
        );
    }

}
