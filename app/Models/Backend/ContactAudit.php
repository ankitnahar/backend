<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;
use DB;

class ContactAudit extends Model {

    //
    protected $table = 'contact_audit';
    protected $guarded = ['id'];
    protected $hidden = [];
    public $timestamps = false;  

    public function modifiedBy() {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }
}
