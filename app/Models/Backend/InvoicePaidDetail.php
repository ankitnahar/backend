<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class InvoicePaidDetail extends Model {

    protected $table = 'invoice_paid_detail';
    protected $guarded = ['id'];
    protected $hidden = [];
    public $timestamps = false;

    public function createdBy() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }  
}
