<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;
use Exception;

class RecurringStopCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recurring:stop';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'stop recurring';

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
                    ->leftJoin('billing_services as bs', function($query) {
                        $query->whereRaw("FIND_IN_SET(bs.entity_id,invoice_recurring.entity_id)");
                        $query->on("bs.service_id", "invoice_recurring.service_id");
                        $query->on("bs.is_latest", DB::raw("1"));
                    })
                    ->leftjoin("frequency as f", "f.id", "invoice_recurring.frequency_id")
                    ->leftjoin("services as s", "s.id", "invoice_recurring.service_id")
                    ->select("invoice_recurring.id as recurring_id", DB::raw("MAX(ird.invoice_date) as max_date"), "f.frequency_name", "s.service_name", "invoice_recurring.fixed_fee", "invoice_recurring.entity_id")
                    ->where("invoice_recurring.is_active", "1")
                    ->where("invoice_recurring.entity_id", "!=", "''")
                    ->groupBy("invoice_recurring.id");
                   
            if ($recurringList->get()->count() > 0) {
                  $emailTemplate = \App\Models\Backend\EmailTemplate::where("code", "RIS")->first();
                foreach ($recurringList->get() as $row) {                   
                        $lastDate = strtotime($row->max_date);
                        $lastDt = date('Y-m-d', $lastDate);
                        $lastminus7 = strtotime("-7 days ", $lastDate);
                        if ($lastminus7 == strtotime(date('Y-m-d'))) {
                            if ($emailTemplate->is_active) {
                                //$from = "noreply-bdms@befree.com.au";
                                $to = $emailTemplate->to;
                                $entity = \App\Models\Backend\Entity::whereRaw("id IN($row->entity_id)")->select(DB::raw("GROUP_CONCAT(billing_name) AS clients"))->first();
                                $ff =($row->fixed_fee == 1) ? 'Yes' :'No';
                                $content = html_entity_decode(str_replace(array('[CLIENT]', '[FF]', '[FREQ]', '[SERVICE]', '[LASTDATE]'), array($entity->clients, $ff, $row->frequency_name, $row->service_name, $lastDt), $emailTemplate->content));


                                $emailData['to'] = strtolower($to);
                                $emailData['cc'] = strtolower($emailTemplate->cc);
                                $emailData['bcc'] = strtolower($emailTemplate->bcc);
                                $emailData['subject'] = $emailTemplate->subject;
                                $emailData['content'] = $content;

                                $sendMail = cronStoreMail($emailData);
                               /* if (!$sendMail) {
                                    app('log')->channel('priorrecurring')->error("Stop Recurring failed mail send failed");
                                }*/
                            }
                        }                   
                }
            }
        } catch (Exception $e) {            
            $cronName = "Stop Recurring";
            $message = $e->getMessage();
            cronNotWorking($cronName, $message);
        }
    }

}
