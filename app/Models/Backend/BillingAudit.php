<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class BillingAudit extends Model {

    protected $guarded = ['id'];
    protected $table = 'billing_basic_audit';
    protected $hidden = [];
    public $timestamps = false;

    public function modifiedBy() {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }

}
