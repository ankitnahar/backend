<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class InvoiceUserHierarchy extends Model {

    protected $table = 'invoice_user_hierarchy';
    protected $guarded = ['id'];
    protected $hidden = [];
    public $timestamps = false;  
}
