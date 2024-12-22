<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;
use DB;

class Invoice extends Model {

    protected $table = 'invoice';
    protected $guarded = ['id'];
    protected $hidden = [];
    public $timestamps = false;

    public function createdBy() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }

    public function modifiedBy() {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }

    public static function invoiceData() {
        return Invoice::with('createdBy:id,userfullname as created_by')
                        ->leftjoin("entity as e", "e.id", "invoice.entity_id")
                        ->leftjoin("entity as ep", "ep.id", "e.parent_id")
                        ->leftjoin("services as s", "s.id", "invoice.service_id")
                        ->leftjoin("invoice_status as ins", "ins.id", "invoice.status_id")
                        ->leftjoin("invoice_paid_detail as ip", "ip.invoice_no", "invoice.invoice_no")
                        ->leftjoin("billing_basic as b", "b.entity_id", "invoice.entity_id")
                        ->leftJoin('entity_allocation as ea', function($query) {
                            $query->on('ea.entity_id', '=', 'invoice.entity_id');
                            $query->on('ea.service_id', '=', 'invoice.service_id');
                        })
                        ->leftJoin('user as ut', function($query) {
                            $query->where('ut.id', DB::raw('JSON_VALUE(ea.allocation_json,"$.9")'));
                        })
                        ->leftJoin('user as u', function($query) {
                            $query->where('u.id', DB::raw('JSON_VALUE(ea.allocation_json,"$.60")'));
                        })
                        ->leftJoin('user as utl', function($query) {
                            $query->where('utl.id', DB::raw('JSON_VALUE(ea.allocation_json,"$.61")'));
                        })
                        ->select("e.billing_name", "e.trading_name","ep.trading_name as parent_name", "ins.name as status","invoice.service_id","s.service_name", "ut.userfullname as tam_name", "u.userfullname as tl_name","utl.userfullname as atl_name", "e.discontinue_stage", "ip.allocate_credit", "invoice.id", "invoice.entity_id", "invoice.invoice_no", "invoice.invoice_type", "invoice.debtors_stage", "invoice.status_id", "invoice.from_period", "invoice.to_period", "invoice.is_fixed_fees", "invoice.paid_amount", "invoice.created_on", "invoice.payment_date", "invoice.parent_id", "invoice.outstanding_amount", "invoice.adjusted", "invoice.dismiss_reason", "invoice.created_by", "invoice.send_date", "b.debtor_followup", "invoice.is_merge as merge_invoice");
                       // ->where("e.discontinue_stage", "!=", "2");
    }

    public static function getInvoiceNo($entityId, $clientCodeLength) {
        return Invoice::leftjoin("entity as e", "e.id", "invoice.entity_id")
                        ->select(DB::raw("MAX(CAST(SUBSTRING(invoice.invoice_no, $clientCodeLength, LENGTH(invoice.invoice_no)) AS SIGNED INTEGER)) 
                invoice_no, e.code"))
                        ->where("invoice.parent_id", "0")
                        ->where("invoice.invoice_type", "!=", "Imported")
                        ->where("invoice.entity_id", $entityId)->first();
    }

    public static function getImportCSV($invoiceNo) {
        $str = explode(",", $invoiceNo);
        $str = "'" . implode("','", $str) . "'";

        return Invoice::leftjoin("entity as e", "e.id", "invoice.entity_id")
                        ->leftjoin("billing_basic as b", "b.entity_id", "invoice.entity_id")
                        ->leftjoin("invoice_desc as id", "id.invoice_no", "invoice.invoice_no")
                        ->leftjoin("invoice_account as ia", "ia.id", "id.inv_account_id")
                        ->leftjoin("invoice_template as it", "it.invoice_no", "invoice.invoice_no")
                        ->select("invoice.id as invoice_id", "invoice.invoice_no", "id.*", "e.billing_name as name","it.reference", "b.state_id", "ia.account_no")
                        ->where("id.description", "!=", "''")
                        ->where("invoice.status_id", "3")
                        ->whereRaw(DB::raw("invoice.invoice_no IN ($str)"))->orderby("invoice.invoice_no", "invoice.service_id", "id.hide", "id.sort_order");
    }

    public static function getInvoiceReportData() {
        return Invoice::leftjoin("entity as e", "e.id", "invoice.entity_id")
                ->leftjoin("entity as ep", "ep.id", "e.parent_id")
                        ->leftJoin('invoice_user_hierarchy as iu', "iu.invoice_id", "invoice.id")
                        ->leftjoin("permanent_info as p", "p.entity_id", "invoice.entity_id");
    }

    public static function reportArrangeData($data) {
        $user = \App\Models\User::select('userfullname', 'id')->get()->pluck('userfullname', 'id')->toArray();

        $designationids = Designation::select("designation_name")->where("is_display_in_allocation", "1")->get();
        foreach ($designationids as $designation) {
            $arrDDOption[$designation->designation_name] = $user;
        }
        $arrDDOption['sales_person_id'] = $user;
        $arrDDOption['Invoice type'] = config('constant.invoiceType');
        $arrDDOption['Client is on FF'] = config('constant.yesNo');
        $arrDDOption['Service'] = Services::where('is_active', '=', '1')->get()->pluck('service_name', 'id')->toArray();
        $arrDDOption['Invoice status'] = InvoiceStatus::where('is_active', '=', '1')->get()->pluck('name', 'id')->toArray();
        
        foreach ($data->toArray() as $key => $value) {
            foreach ($value as $rowkey => $rowvalue) {
                $data[$key][$rowkey] = (isset($arrDDOption[$rowkey])) ? ((isset($arrDDOption[$rowkey][$rowvalue])) ? $arrDDOption[$rowkey][$rowvalue] : '') : $rowvalue;
            }
        }

        return $data;
    }

    public static function getMonthlyInvoiceData() {
        return Invoice::leftjoin("entity as e", "e.id", "invoice.entity_id")
                        ->leftjoin("entity as ep", "ep.id", "e.parent_id")
                        ->leftjoin("billing_services as b", "b.id", "invoice.billing_id")
                        ->select("e.code", "e.name","ep.trading_name as parent_name", "invoice.invoice_no", "b.fixed_fee", "invoice.billing_id", "invoice.created_on", 
                                DB::raw("(SELECT fixed_fee FROM billing_bk_rph WHERE service_id = 8 and billing_id = b.id  ORDER BY id DESC LIMIT 0,1) as AR,
                                (SELECT fixed_fee FROM billing_bk_rph WHERE service_id = 9 and billing_id = b.id ORDER BY id DESC LIMIT 0,1 ) as AP,
                                (SELECT fixed_fee FROM billing_bk_rph WHERE service_id = 10 and billing_id = b.id ORDER BY id DESC LIMIT 0,1) as DM,
                                (SELECT fixed_fee FROM billing_bk_rph WHERE service_id = 11 and billing_id = b.id ORDER BY id DESC LIMIT 0,1) as Payroll"))
                        ->where("b.service_id", "1")
                        ->where("invoice.invoice_type", "Auto Invoice")
                        ->whereIn("invoice.status_id", [3, 4, 9, 11]);
    }

    public static function getclientWiseInvoiceData() {
        return Invoice::leftjoin("entity as e", "e.id", "invoice.entity_id")
                        ->leftjoin("entity as ep","ep.id","e.parent_id")
                        ->leftJoin('invoice_user_hierarchy as iu', "iu.invoice_id", "invoice.id")
                        ->where("invoice.parent_id", "0")
                        ->whereIn("invoice.status_id", [3, 4, 9, 10]);
    }

    public static function getDebtorsList() {
        return Invoice::leftjoin("entity as e", "e.id", "invoice.entity_id")
                        ->leftjoin("entity as ep", "ep.id", "e.parent_id")
                        ->leftjoin("billing_basic as b", "b.entity_id", "invoice.entity_id")
                        ->leftjoin("services as s", "s.id", "invoice.service_id")
                        ->leftjoin("billing_services as bs", "bs.id", "invoice.billing_id")
                        ->leftJoin('entity_allocation as ea', function($query) {
                            $query->on('ea.entity_id', '=', 'invoice.entity_id');
                            $query->on('ea.service_id', '=', 'invoice.service_id');
                        })
                         ->leftJoin('user as ut', function($query) {
                            $query->where('ut.id', DB::raw('JSON_VALUE(ea.allocation_json,"$.9")'));
                        })
                        ->select("invoice.id", "invoice.entity_id", "e.code", "e.name","b.is_related","e.parent_id as entity_parent_id", "e.billing_name","ep.trading_name as parent_name","ut.userfullname as tam_name", "e.discontinue_stage", "e.related_entity", "e.related_entity_id", "invoice.invoice_no", "invoice.to_period", "invoice.from_period", "invoice.paid_amount", "b.debtor_followup", "invoice.created_by", DB::raw("IF(invoice.outstanding_amount > 0 ,invoice.outstanding_amount,invoice.paid_amount) as outstanding_amount"), "invoice.send_date", "invoice.due_date", "invoice.is_fixed_fees", "b.payment_id", "b.to_email", "b.cc_email", "s.service_name", "invoice.payment_date")
                        ->where("invoice.parent_id", "0")
                        ->where("invoice.status_id", "9")
                        ->where("invoice.debtors_stage", "1");                        
                       // ->where("e.discontinue_stage", "!=", "2");
    }

    public static function sumAmount() {
        $invoices = Invoice::leftjoin("entity as e", "e.id", "invoice.entity_id")
                        ->leftjoin("billing_basic as b", "b.entity_id", "invoice.entity_id")
                        ->select(DB::raw("invoice.invoice_no,invoice.service_id,SUM(invoice.paid_amount) as paid_amount, SUM(invoice.outstanding_amount) as outstanding_amount"))
                        ->where("invoice.status_id", "9")        
                        ->where("invoice.debtors_stage", "1")
                        ->where("e.discontinue_stage", "!=", "2")  
                        ->groupBy("invoice.service_id")
                        ->groupBy("invoice.invoice_no")->get();
        foreach ($invoices as $row) {
            $invoiceAmt[$row->invoice_no] = array("paid_amount" => $row->paid_amount,
                "outstanding_amount" => number_format(($row->paid_amount - $row->outstanding_amount),2,'.', ''));
        }
        return $invoiceAmt;
    }

    public static function updateDebtors() {
        $entitylist = Invoice::where("debtors_stage","1")->where("status_id","9")->select(DB::raw("DISTINCT entity_id as entity_id"))->get();
        $entityIds = array();
        foreach($entitylist as $entity){
            $entityIds[] = $entity->entity_id;
        }
        $entityId ='';
        if(!empty($entityIds)){
          $entityId =  implode(",",$entityIds);
        }
        return $debtors = Invoice::leftjoin("invoice_template as it", "it.invoice_no", "invoice.invoice_no")
        ->where("invoice.status_id", "9")
        ->where("invoice.paid_amount", ">",'0.00')
        ->where("invoice.debtors_stage", "0")
        ->whereRaw("(invoice.dm_date <= '".date('Y-m-d')."' OR invoice.entity_id IN ($entityId))")
        ->groupBy("invoice.invoice_no");
    }

}
