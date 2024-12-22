<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class InvoiceDescription extends Model {

    protected $table = 'invoice_desc';
    protected $guarded = ['id'];
    protected $hidden = [];
    public $timestamps = false;

    public function createdBy() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }

    public function modifiedBy() {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }

    public static function getDescription($invoiceNo, $stateName, $categoryOptionId) {
        $desc = InvoiceDescription::leftjoin("invoice as i", "i.id", "invoice_desc.invoice_id")
                        ->leftjoin("invoice_account as ia", "ia.id", "invoice_desc.inv_account_id")
                        ->select("invoice_desc.description", "invoice_desc.amount", "ia.account_no")
                        ->where("invoice_desc.description", "!=", "''")
                        ->where("invoice_desc.invoice_no", $invoiceNo)
                        ->where("invoice_desc.hide", "!=", "2")
                        ->orderBy("i.service_id","ASC")
                        ->orderBy("invoice_desc.hide", "ASC")
                        ->orderBy("invoice_desc.sort_order", "ASC")->get();
        
        $i = 0;
        $dsArray = array();
        foreach ($desc as $ds) {
            if($ds->description !=''){
            $account = $ds->amount == '0.00' ? '0' : $ds->account_no;
            $quantity = $ds->amount == '0.00' ? '0' : '1';
            $dsArray[$i]['Description'] = $ds->description;
            $dsArray[$i]['Quantity'] = $quantity;
            $dsArray[$i]['UnitAmount'] = $ds->amount;
            if ($ds->amount != '' && $ds->amount != '0' && $ds->amount != '0.00') {
                $dsArray[$i]['TrackingCategory'] = array("Name" => "Job",
                    "Option" => $stateName,
                    "TrackingCategoryID" => config("constant.JOBTRACKINGCATEGORY"),
                    "TrackingOptionID" => $categoryOptionId);
            }
            //if($account > 0){
            //$dsArray[$i]['TaxType'] = 'OUTPUT';
            $dsArray[$i]['AccountCode'] = $account;
            //}
            $i++;
            }
        }
        $cardSurchargeDesc = InvoiceDescription::leftjoin("invoice as i", "i.id", "invoice_desc.invoice_id")
                        ->leftjoin("invoice_account as ia", "ia.id", "invoice_desc.inv_account_id")
                        ->select("invoice_desc.description", "invoice_desc.amount", "ia.account_no")
                        ->where("invoice_desc.description", "!=", "''")
                        ->where("invoice_desc.invoice_no", $invoiceNo)
                        ->where("invoice_desc.hide", "=", "2")
                        ->orderby("i.service_id", "invoice_desc.hide", "invoice_desc.sort_order", "ASC")->get();


        foreach ($cardSurchargeDesc as $ds) {
            if($ds->description !=''){
            $account = $ds->amount == '0.00' ? '0' : $ds->account_no;
            $quantity = $ds->amount == '0.00' ? '0' : '1';
            $dsArray[$i]['Description'] = $ds->description;
            $dsArray[$i]['Quantity'] = $quantity;
            $dsArray[$i]['UnitAmount'] = $ds->amount;
            if ($ds->amount != '' && $ds->amount != '0' && $ds->amount != '0.00') {
                $dsArray[$i]['TrackingCategory'] = array("Name" => "Job",
                    "Option" => $stateName,
                    "TrackingCategoryID" => config("constant.JOBTRACKINGCATEGORY"),
                    "TrackingOptionID" => $categoryOptionId);
            }
            //if($account > 0){
            //$dsArray[$i]['TaxType'] = 'OUTPUT';
            $dsArray[$i]['AccountCode'] = $account;
            //}
            $i++;
            }
        }
        // showArray($dsArray);exit;
        return $dsArray;
    }

}
