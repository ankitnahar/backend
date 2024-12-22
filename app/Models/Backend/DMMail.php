<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class DMMail extends Model {

    //
    protected $table = 'dm_mail';
    protected $guarded = ['id'];
    protected $hidden = [];
    public $timestamps = false;

    public function createdBy() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }

    public static function getMailList($entityId) {
        return DMMail::leftjoin("entity as e", "e.id", "dm_mail.entity_id")
                        ->select("e.code", "e.name", "dm_mail.*");
    }

}
