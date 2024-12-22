<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class InvoiceMasterUnitCalc extends Model {

    protected $table = 'invoice_master_unit_calc';
    protected $guarded = ['id'];
    protected $hidden = [];
    public $timestamps = false;

    public function createdBy() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }

    public function modifiedBy() {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }

    public static function invoiceUnitCalc($invoiceId) {
        $invoiceMasterCalc = InvoiceMasterUnitCalc::leftJoin("master_activity as m","m.id","invoice_master_unit_calc.master_id")
                ->select("invoice_master_unit_calc.*","m.name as master_name")
                ->where("invoice_id", $invoiceId)->get();        
        if (empty($invoiceMasterCalc)) {
             return 0;          
        }else{
            $masterArray =array();
            foreach ($invoiceMasterCalc as $master) {
                $masterArray[$master->master_id] = $master;
            }
            return $masterArray;
        }
       
    }

}
