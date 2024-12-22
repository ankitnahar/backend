<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class BillingServicesSubactivityAudit extends Model {

    protected $table = 'billing_subactivity_audit';
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $hidden = [];  
    
    public function modifiedBy(){
       return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }

}
