<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;

class FeedbackTaskResourceCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'feedback:resourcetask';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Feedback Task create';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        //try {
        $month = date('n');
        if (($month == '1' || $month == '4' || $month == '7' || $month == '10')) {
        $date = date('Y-m-01');
        \App\Http\Controllers\Backend\Feedback\FeedbackController::getResourceClient();
        $feedbackEntityList = \App\Models\Backend\ManagementCall::get()->toArray();
        //showArray($feedbackEntityList);exit;
        $month1 = date(('M'), strtotime("-3 Month"));
            $month2 = date(('M'), strtotime("-1 Month"));
            $year = ($month == 1) ? date("Y", strtotime("-1 Year")) : date("Y");
                        $qtr = $month1 . "-" . $month2;
        if (count($feedbackEntityList) > 0) {
            foreach ($feedbackEntityList as $key => $row) {
                if($row['tam_id']!='' && $row['tam_id']!= NULL){
                $year = date("Y");
                $freqId = $row['frequency_id'];
                //$lastMonth = date("M", strtotime("-1 month", strtotime($date)));
               // $qtr= $lastMonth;
               // if($freqId > 1){
               // $startMonth = date("M", strtotime("-3 month", strtotime($date)));
               // $qtr= $startMonth."-".$lastMonth;
                //}
                $qtr=strtoupper($qtr);
                
                \App\Models\Backend\Feedback::create(["entity_id" => $row['entity_id'],
                    "contact_id" => $row['contact_id'],
                    "contact_person" => $row['contact_person'],
                    "contact_mobile" => $row['contact_mobile'],
                    "contact_office" => $row['contact_office'],
                    "contact_email" => $row['contact_email'],
                    "status_id" => 0,
                    "service_id" => $row['service_id'],
                    "category_id" => $row['category_id'],
                    "year" => $year,
                    "quarter" => $qtr,
                    "tam_id" => $row['tam_id'],
                    "tam_name" => $row['tam_name'],
                    "service_name" => $row['service_name'],
                    "service_tam" => $row['service_tam'],
                    "full_resource" => 1,
                    "related_entity_same" => $row['related_entity_same'],
                    "related_entity_diff" => $row['related_entity_diff'],
                    "created_on" => date('Y-m-d H:i:s'),
                    "created_by" => 1]);

                //Update next generation date
                
                \App\Models\Backend\FeedbackLog::addLog($row['id'], 0);
                $feedbackDate = date("Y-m-01", strtotime("+3 month", strtotime($date)));
                \App\Models\Backend\ManagementCall::where("id",$row['id'])->update(["feedback_date" => $feedbackDate,"lastcall_date" => $date]);
                }
            }
        }
        }


        /* } catch (Exception $e) {
          $cronName = "Feedback Task Creation";
          $message = $e->getMessage();
          cronNotWorking($cronName,$message);
          } */
    }

}
