<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class BillingServicesAudit extends Model
{
    protected $table = 'billing_services_audit';    
    protected $guarded = ['id'];
    protected $hidden = [];
    public $timestamps = false;
    
    public function modifiedBy(){
       return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }
}
