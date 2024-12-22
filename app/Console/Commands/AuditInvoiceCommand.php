<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;
use DB;

class AuditInvoiceCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoice:audit';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit Invoice create';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        //try {
        
         /*$emailList = DB::table('user_from16')->get();
              $i = 0;
              foreach($emailList as $row){
              $insertArray[] = array('to_email' => trim($row->email),
             'from_name'=>'Befree',
             'from_email'=> 'salary@befree.com.au',
             'bcc_email' => 'devyani.a@superrecords.com.au',
             'subject' => 'FORM-16 | FY 2023-24 | SMSFA ASSURANCE SERVICES INDIA LLP',
             'content' => '<p style="font-family:sans-serif;">
	Hi ,</p>
<p style="font-family:sans-serif;font-size: 13px;">
Please find attached FORM-16 for FY 2023-24 of SMSFA ASSURANCE SERVICES INDIA LLP.</p>
<p style="font-family:sans-serif;font-size: 13px;">
	Regards,<br/>
 salary@befree.com.au</p>',
             'attachment' => '[{"path":"'.$row->path1.'","filename":"'.$row->file1.'"},{"path":"'.$row->path2.'",
"filename":"'.$row->file2.'"}]',
             'status' => 2);
              }
              DB::table('email_contents')->insert($insertArray);
              */
                      $auditInvoice = \App\Models\Backend\BillingServices::
                    leftjoin("entity as e", "e.id", "billing_services.entity_id")
                    ->select("billing_services.*")
                    ->where("billing_services.service_id", "4")
                    ->where("billing_services.is_latest", "1")
                    ->where("billing_services.is_active", "1")
                    ->where("billing_services.audit_fee_inc", "0")
                    ->where("e.discontinue_stage", "!=", "2");


            if ($auditInvoice->count() > 0) {
                foreach ($auditInvoice->get() as $row) {
                    \App\Models\Backend\Invoice::create([
                        'parent_id' => '0',
                        'entity_id' => $row->entity_id,
                        'billing_id' => $row->id,
                        'service_id' => '4',
                        'invoice_type' => 'Audit',
                        'status_id' => '2',
                        'gross_amount' => $row->audit_fee,
                        'net_amount' => $row->audit_fee,
                        'created_by' => 1,
                        'created_on' => date('Y-m-d')
                    ]);
                }
            }            /* ini_set('max_execution_time', 0);
              ini_set('display_errors', 1);
              ini_set('memory_limit','5000000000M');
              $emailList = DB::table('email')->orderBy("id","desc")->skip("0")
              ->take("25000")->get();
              $i = 0;
              foreach($emailList as $row){
              $insertArray[] = array('gmail_message_id' => $row->gmail_message_id,
              'entity_id' => $row->entity_id,
              'label_id' => $row->label_id,
              'to' => $row->to,
              'from' => $row->from,
              'subject' => $row->subject,
              'email_json' => $row->email_json,
              'created_on' => $row->created_on,
              'has_attachment' => $row->has_attachment);
              if($i == 10){
              DB::table('email')::insert($insertArray);
              $i = 0;
              }
              $i++;
              } */
       /* } catch (Exception $e) {
            $cronName = "Audit Invoice";
            $message = $e->getMessage();
            cronNotWorking($cronName, $message);
        }*/
    
    }
}
