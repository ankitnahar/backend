<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class HrUserInOuttimeAmendment extends Model {

    protected $guarded = ['id'];
    protected $fillable = [];
    protected $table = 'hr_user_in_out_time_amendment';
    protected $hidden = [];
    public $timestamps = false;

    public function user_id() {
        return $this->belongsTo(\App\Models\User::class, 'user_id', 'id');
    }

    public function created_by() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }

    public function approved_by() {
        return $this->belongsTo(\App\Models\User::class, 'approved_by', 'id');
    }

    public static function arrangeData($data) {
        $i = 0;
        foreach ($data as $key => $value) {
            $data[$i]->modified_reason_for_rejection = $value->reason_for_rejection;
            $data[$i]->is_reason_limit_exceed = 0;
            if ($value->reason_for_rejection != '' && strlen($value->reason_for_rejection) > 50) {
                $data[$i]->is_reason_limit_exceed = 1;
                $data[$i]->modified_reason_for_rejection = substr($value->reason_for_rejection, 0, 35) . '.....';
            }
            $i++;
        }

        return $data;
    }

}
