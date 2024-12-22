<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;

class CheckRecurringCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recurring:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'check recurring';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            $recurringDate = date("Y-m-d");
            $recurringList = \App\Models\Backend\InvoiceRecurring::
                    leftjoin("invoice_recurring_detail as ird", "ird.recurring_id", "invoice_recurring.id")
                    ->where("invoice_recurring.is_active", "1")
                    ->where("invoice_recurring.entity_id", "!=", "")
                    ->where("ird.invoice_date", $recurringDate);

            if ($recurringList->count() > 0) {
                $invoice = \App\Models\Backend\Invoice::whereIn("invoice_type", ['Auto Invoice', 'Recurred'])
                        ->whereRaw("DATE(invoice.created_on) = '" . $recurringDate . "'");
                if ($invoice->count() == 0) {

                    $emailData['to'] = 'bdmsdeveloper@befree.com.au,billing@befree.com.au';
                    $emailData['subject'] = 'Urgent :: Invoice not Recurred Today';
                    $emailData['content'] = 'Urgent :: Invoice not Recurred Today';

                    $sendMail = cronStoreMail($emailData);
                    if (!$sendMail) {
                        app('log')->channel('checkrecurring')->error("Check Recurring mail send failed : " . $e->getMessage());
                    }
                } else {
                    $InvoiceNotRecurre = array();
                    foreach ($recurringList->get() as $recurring) {
                        $entityIds = explode(',', $recurring['entity_id']);
                        foreach ($entityIds as $entityId) {
                            $entity = \App\Models\Backend\Entity::where("id", $entityId)
                                    ->where("discontinue_stage", "!=", "2")
                                    ->select("billing_name", "id")
                                    ->first();
                            if(isset($entity->billing_name)){
                            $invoiceEntity = \App\Models\Backend\Invoice::whereIn("invoice_type", ['Auto Invoice', 'Recurred'])
                                    ->where("entity_id", $entityId)
                                    ->whereRaw("DATE(invoice.created_on) = '" . $recurringDate . "'");
                            if ($invoiceEntity->count() == 0) {
                                $InvoiceNotRecurre[] = $entity->billing_name;
                            }
                            }else{
                                continue;
                            }
                        }
                    }
                    if (!empty($InvoiceNotRecurre)) {
                        $content = 'Below is the list of the client whose invoices have not recurred on ' . date('d-m-Y') . '<br/><br/>';
                        $content .= implode("<br/>", $InvoiceNotRecurre);
                        $content .= '<br/><br/>Thank You <br/> Befree Data Management System';

                        $emailData['to'] = 'bdmsdeveloper@befree.com.au,billing@befree.com.au';
                        $emailData['subject'] = 'Urgent :: Invoice not Recurred for the below client list';
                        $emailData['content'] = $content;

                        $sendMail = cronStoreMail($emailData);
                        if (!$sendMail) {
                            $cronName = "Check Recurring";
                            $message = $e->getMessage();
                            $this->cronNotWorking($cronName, $message);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $cronName = "Check Recurring";
            $message = $e->getMessage();
            cronNotWorking($cronName, $message);
        }
    }

}
