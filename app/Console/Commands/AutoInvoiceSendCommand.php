<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;

class AutoInvoiceSendCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoice:send';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto invoice Send';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            $today = date('Y-m-d');
            $invoices = \App\Models\Backend\Invoice::leftjoin("billing_basic as b", "b.entity_id", "invoice.entity_id")
                    ->select("invoice.invoice_no", "invoice.id", "invoice.entity_id", "b.to_email", "b.cc_email", "b.card_id", "b.surcharge", "b.address", "b.contact_person", "b.payment_id")
                    ->where("invoice.status_id", "11")
                    ->where("invoice.invoice_type", "Auto invoice")
                    ->groupBy("invoice_no");
            //showArray($invoices->get());exit;
            if ($invoices->get()->count() > 0) {
                foreach ($invoices->get() as $invoice) {
                    $pdfDetail = \App\Http\Controllers\Backend\Invoice\InvoiceSendController::
                            generatePDFData($invoice->id, $invoice->invoice_no, 11, $invoice->entity_id, 0, 1, 1);
                    // showArray($pdfDetail);exit;
                    if (!isset($pdfDetail->original['payload']['error'])) {
                        // ezidebit case
                        if ($invoice->payment_id == 1) {
                            $debtor_due_date = strtotime("+19 days", strtotime(date("Y-m-d")));
                        }
                        // credit card / net transfer case
                        else {
                            $debtor_due_date = strtotime("+15 days", strtotime(date("Y-m-d")));
                        }

                        $debtor_due_date = date('Y-m-d', $debtor_due_date);
                        $dataArray = [
                            'invoice_no' => $invoice->invoice_no,
                            'from_email' => config('constant.BILLINGID'),
                            'to' => trim($invoice->to_email),
                            'cc' => trim($invoice->cc_email),
                            'subject' => $pdfDetail['billingDetail']['subject'],
                            'body' => $pdfDetail['billingDetail']['body'],
                            'reference' => $pdfDetail['billingDetail']['reference'],
                            'attachment' => $pdfDetail['fileName'],
                            'attachment_path' => $pdfDetail['pdfPath'],
                            'amount_applied' => 0,
                            'created_on' => date("Y-m-d H:i:s"),
                            'created_by' => loginUser()
                        ];

                        $data['to'] = str_replace(' ','',$invoice->to_email);
                        $data['cc'] = str_replace(' ','',$invoice->cc_email);
                        $data['bcc'] = config('constant.BILLINGID');
                        $data['from'] = config('constant.BILLINGID');
                        $data['content'] = $pdfDetail['billingDetail']['body'];
                        $data['subject'] = $pdfDetail['billingDetail']['subject'];
                        $data['attachment'] = array($pdfDetail['pdfPath'] . $pdfDetail['fileName']);
                        //send mail to the client
                        cronStoreMail($data);
                        //update sent_date ,dm_date,due_date
                        \App\Models\Backend\Invoice::where("invoice_no", $invoice->invoice_no)->where("status_id", "11")
                                ->where("invoice_type", "Auto invoice")
                                ->update(
                                        ['send_date' => date("Y-m-d H:i:s"),
                                            'due_date' => date('Y-m-d', strtotime("+7 days", strtotime(date("Y-m-d")))),
                                            'dm_date' => $debtor_due_date,
                                            "status_id" => 3]);

                        $invoiceTemplate = \App\Models\Backend\InvoiceTemplate::create($dataArray);

                        // add log
                        $allInvoice = \App\Models\Backend\Invoice::where("invoice_no", $invoice->invoice_no)->get();
                        foreach ($allInvoice as $invoices) {
                            $invoiceLog = \App\Models\Backend\InvoiceLog::addLog($invoices->id, 3);
                        }
                    } else {
                        $cronName = "Auto Invoice Send";
                        $message = $pdfDetail->original['payload']['error'];
                        cronNotWorking($cronName, $message);
                    }
                }
            }
        } catch (Exception $ex) {
            $cronName = "Auto Invoice Send";
            $message = $ex->getMessage();
            cronNotWorking($cronName, $message);
        }
    }

}
