<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class BillingSubscriptionSoftware extends Model {

    protected $table = 'billing_subscription_plan';
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $hidden = [];

    public function createdBy() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }

    public function parentId() {
        return $this->belongsTo(BillingSubscriptionSoftware::class, 'parent_id', 'id');
    }
    
    public function modifiedBy() {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }

}
