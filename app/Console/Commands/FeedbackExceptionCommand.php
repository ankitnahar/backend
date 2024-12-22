<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;
use DB;

class FeedbackExceptionCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'feedback:exception';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Feedback exception';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        //try {

        $month = date('n');
        //$month = 7;
        $date = Date('d');
        if ($month == '1' || $month == '4' || $month == '7' || $month == '10') {
            $serviceDetail = \App\Models\Backend\BillingServices::leftjoin("entity as e", "e.id", "billing_services.entity_id")
                    ->leftJoin('entity_allocation as ea', function($query) {
                        $query->on('ea.entity_id', '=', 'billing_services.entity_id');
                        $query->on('ea.service_id', '=', 'billing_services.service_id');
                    })
                    ->leftjoin("services as s", "s.id", "billing_services.service_id")
                    ->leftJoin('user as ut', function($query) {
                        $query->where('ut.id', DB::raw('JSON_VALUE(ea.allocation_json,"$.9")'));
                    })
                    ->leftjoin("contact as c", "c.entity_id", "e.id")
                    ->select("billing_services.service_id", "billing_services.entity_id","e.trading_name", "ut.userfullname as tam_name", "ut.id as tam_id", "s.service_name")
                    ->where("billing_services.is_active", "1")
                    ->where("billing_services.is_updated", "1")
                    ->where("billing_services.is_latest", "1")
                    ->where("c.is_feedback_contact", "1")
                    ->where("e.discontinue_stage", "=", "0")
                    ->whereIn("billing_services.service_id", [1, 2, 6]);                    
                    
           // echo getSQL($serviceDetail);exit;
            $serviceTam = array();
            if ($serviceDetail->count() > 0) {
                foreach ($serviceDetail->get() as $service) {
                    if ($service->tam_id == null && $service->tam_id == 0) {
                        $serviceTam[$service->entity_id][] = array(
                            "trading_name" => $service->trading_name,
                            "service_name" => $service->service_name, "tam_name" => "");
                    }                    
                }
                //showArray($serviceTam);exit;
                $content = "<table><tr><td>Sr.No.</td><td>Trading Name</td><td>Service Name</td></tr>";
                $i = 1;
                foreach($serviceTam as $entity){
                    foreach($entity as $e){
                        $content .= "<tr><td>".$i."<td>".$e['trading_name']."</td><td>".$e['service_name']."</td></tr>";
                        $i++;
                    }
                }
                 $content .= "</table>";
                 $template = \App\Models\Backend\EmailTemplate::where('code',"FEEDBACKREMINDER")->where("is_active","1")->first();
                    $emailData['to'] = $template->to;
                    $emailData['cc'] = $template->cc;
                    $emailData['subject'] = "Client allocation Missing";   
                    $emailData['content'] = html_entity_decode(str_replace(array('[TABLE]'), array($content), $template->content));;              
                    
                   $sendMail =  cronStoreMail($emailData);
            }
        }

        /* } catch (Exception $e) {
          $cronName = "Feedback Task Creation";
          $message = $e->getMessage();
          cronNotWorking($cronName,$message);
          } */
    }

}
