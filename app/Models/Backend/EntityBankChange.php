<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class EntityBankChange extends Model {

    protected $guarded = ['id'];
    protected $table = 'entity_bank_change';
    public $timestamps = false;

    public function createdBy() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }

    public function firstApproval() {
        return $this->belongsTo(\App\Models\User::class, 'first_approval', 'id');
    }

    public function secondApproval() {
        return $this->belongsTo(\App\Models\User::class, 'second_approval', 'id');
    }

    public function approvedBy() {
        return $this->belongsTo(\App\Models\User::class, 'approved_by', 'id');
    }

    public static function bankArrangeData($data) {
        $i = 0;
        foreach ($data as $d) {
            $bankDocument = EntityBankChangeDocument::where("bank_change_id", $d['id']);
            if ($bankDocument->count() > 0) {
                $bankDocument = $bankDocument->get();
                $data[$i]['document'] = $bankDocument;
            }else{
                $data[$i]['document'] = array();
            }
            $i++;
        }
        return $data;
    }

}
