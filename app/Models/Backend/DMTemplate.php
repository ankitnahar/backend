<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;
use DB;

class DMTemplate extends Model {

    //
    protected $table = 'dm_template';
    protected $guarded = ['id'];
    protected $hidden = [];
    public $timestamps = false;

    public static function getTemplateDetail($entityId, $id) {
        $template = DMTemplate::find($id);

        $signature = EmailSignature::find(9);

        $billingDetail = Entity::leftjoin("billing_basic as b", "b.entity_id", "entity.id")
                        ->select(DB::raw("SUBSTRING_INDEX(b.contact_person, ' ', 1) contact_person"), "entity.code")
                        ->where("entity.id", $entityId)->first();
        $invoiceDetail = Invoice::leftjoin("billing_basic as b", "b.entity_id", "invoice.entity_id")
                        ->select("invoice.send_date", "invoice.due_date", "paid_amount", "outstanding_amount", "invoice_no")
                        ->where("invoice.debtors_stage", 1)
                        ->whereRaw("invoice.status_id NOT IN (5,10,4)")
                        ->whereRaw("(invoice.entity_id = $entityId or b.parent_id = $entityId)")
                        ->orderby("invoice.id")->get();
        //showArray($invoiceDetail);
        //exit;
        //echo getSQL($invoiceDetail);exit;
        $invoiceAmt = $counInv = 0;
        $i = '';
        foreach ($invoiceDetail as $invoices) {
            $counInv = $counInv + 1;
            if ($invoices->outstanding_amount > 0) {
                if ($invoices->invoice_no != $i) {
                    $i = $invoices->invoice_no;
                    $invoiceAmt += $invoices->outstanding_amount;
                }
            } else {
                $invoiceAmt += $invoices->paid_amount;
            }
        }
        $invoiceCreationDate = dateFormat($invoiceDetail[0]['send_date']);
        $invoiceDueDate = dateFormat($invoiceDetail[0]['due_date']);
        $currentDate = strtotime(date('d-m-Y'));
        $nextDate = date('d-m-Y', strtotime('+3 days', $currentDate));
        //$nextDate = date('d-m-Y', $nextDate);
        $dueMonth = date_diff(date_create(date('d-m-Y')), date_create($invoiceDetail[0]['due_date']))->format("%a days");

        $countDebtors = Invoice::select("invoice_no")
                        ->where("debtors_stage", 1)
                        ->where("parent_id", "0")
                        ->where("entity_id", $entityId)
                        ->groupby("invoice_no")
                        ->get()->count();

        $content = html_entity_decode($template->template_detail);
        $content = replaceString('[CLIENTNAME]', $billingDetail->contact_person, $content);
        $content = replaceString('[INVSTR]', ($counInv > 1 ? 'invoices' : 'invoice'), $content);
        $content = replaceString('[ISSUEDATE]', $invoiceCreationDate, $content);
        $content = replaceString('[INVOICEDATE]', $invoiceDueDate, $content);
        $content = replaceString('[TILLDATE]', $nextDate, $content);
        $content = replaceString('[CLOSEDATE]', $nextDate, $content);
        $content = replaceString('[MONTH]', $dueMonth, $content);
        $content = replaceString('[AMOUNT]', number_format($invoiceAmt, 2, ".", ''), $content);
        $content = replaceString('[CLIENTCODE]', $billingDetail->code, $content);
        $content = replaceString('[DEBTORSINVCNT]', $countDebtors, $content);
        $content = replaceString('[SIGNETURE]', $signature->signature, $content);


        return $content;
    }

}
