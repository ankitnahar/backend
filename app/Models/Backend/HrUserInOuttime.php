<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class HrUserInOuttime extends Model {

    protected $guarded = ['id'];
    protected $fillable = [];
    protected $table = 'hr_user_in_out_time';
    protected $hidden = [];
    public $timestamps = false;

    public function user_id()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id', 'id');
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: May 04, 2018
     * Purpose   : Arrange data after fetch from table
     */

    public static function arrangeData($data) {
        $userList = \App\Models\User::getUser();
        $shift = \App\Models\Backend\Shift::getShift();
        $i = 0;
        foreach ($data->toArray() as $key => $value) {
            $shiftName = array();
            $shiftIds = explode(",", $value['shift_ids']);
            foreach ($shiftIds as $keyId => $valueId) {
                $shiftName[] = $shift[$valueId];
            }
            $data[$i]->holiday_year = 'January - ' . $value['holiday_year'] . ' To December - ' . $value['holiday_year'];
            $data[$i]->shift_name = implode(",", $shiftName);
            $data[$i]->created_by_name = isset($userList[$value['created_by']]) ? $userList[$value['created_by']] : '-';
            $data[$i]->created_on = dateFormat($value['created_on']);
            $data[$i]->modified_by_name = isset($userList[$value['modified_by']]) ? $userList[$value['modified_by']] : '-';
            $data[$i]->modified_on = dateFormat($value['modified_on']);
            $i++;
        }
        return $data;
    }
}
