<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class InvoiceTemplate extends Model {

    protected $table = 'invoice_template';
    protected $guarded = ['id'];
    protected $hidden = [];
    public $timestamps = false;

    public function createdBy() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }

    public function modifiedBy() {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }

    public static function getTemplateDetail($invoiceNo){
        return InvoiceTemplate::leftjoin("invoice as i","i.invoice_no","invoice_template.invoice_no")
                ->leftjoin("entity as e","e.id","i.entity_id")
                ->select("i.send_date","e.name","e.billing_name","invoice_template.*")
                ->where("invoice_template.invoice_no",$invoiceNo);
    }
}
