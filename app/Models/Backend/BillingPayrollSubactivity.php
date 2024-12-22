<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class BillingPayrollSubactivity extends Model {

    protected $table = 'billing_payroll_subactivity';
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $hidden = [];
    
    public function createdBy() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
    
    public function modifiedBy() {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }
}
