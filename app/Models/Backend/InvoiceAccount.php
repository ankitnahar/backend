<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class InvoiceAccount extends Model {

    protected $table = 'invoice_account';
    protected $guarded = ['id'];
    protected $hidden = [];
    public $timestamps = false;   
    
    public static function invoiceAccountDetail(){
        $invoiceAccount = InvoiceAccount::join("master_activity as m","m.inv_account_id","invoice_account.id")
                ->select("m.id","invoice_account.account_no","invoice_account.account_name")
                ->where("invoice_account.is_active","1")->get();
       
        foreach($invoiceAccount as $account){
            $accountArray[$account->id] = array("account_no"=>$account->account_no,"account_name"=>$account->account_name);
        }
        return $accountArray;
    }

}
