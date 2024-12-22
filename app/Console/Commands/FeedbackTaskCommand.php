<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;

class FeedbackTaskCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'feedback:task';

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
       // $month = 7;
       // $date = 1;
      //  $date = Date('d');
        if (($month == '1' || $month == '4' || $month == '7' || $month == '10')) {
            //$category = '';
            //if ($month == 4 || $month == 10) {
                $category = ['1','2','3','4'];
            //}
            $month1 = date(('M'), strtotime("-3 Month"));
            $month2 = date(('M'), strtotime("-1 Month"));
            $month3 = date(('M'), strtotime("-6 Month"));
            $feedbackList = \App\Models\Backend\Entity::leftjoin("billing_basic as b", "b.entity_id", "entity.id")
                    ->leftjoin("contact as c", "c.entity_id", "entity.id")
                    ->leftjoin("wr3", "wr3.entity_id", "entity.id")
                    ->select("entity.id", "entity.trading_name", "b.full_time_resource", "b.category_id", "c.id as contact_id", "c.feedback_email", "c.mobile_no", "c.office_no", "c.feedback_email", "c.contact_person")
                    ->where("entity.is_parent", "1")
                    ->whereRaw("(wr3.is_archived =1 OR DATE(entity.created_on) <='2020-07-01')")
                    ->where("c.is_feedback_contact", "1")
                    ->where("b.full_time_resource","=","0")
                    ->where("entity.discontinue_stage", "0");
            if ($category != '') {
                $feedbackList = $feedbackList->whereIn("category_id", $category);
            }

            $feedbackList = $feedbackList->groupBy("entity.id")
                    ->get();
            //echo getSQL($feedbackList);
            //exit;
            $feedbackEntityList = array();
            foreach ($feedbackList as $feedback) {
                $feedbackEntity = array();
                $reason = \App\Models\Backend\BillingServices::checkTAMonService($feedback->id);

                $relatedEntity = \App\Models\Backend\Entity::leftjoin("contact as c", "c.entity_id", "entity.id")
                                ->leftjoin("wr3", "wr3.entity_id", "entity.id")
                                ->leftjoin("billing_basic as b", "b.entity_id", "entity.id")
                                ->select("entity.id", "entity.trading_name", "b.full_time_resource", "b.category_id", "c.id as contact_id", "c.feedback_email", "c.mobile_no", "c.office_no", "c.feedback_email", "c.contact_person")
                                ->where("entity.parent_id", $feedback->id)
                                ->where("wr3.is_archived", "1")
                                ->where("c.is_feedback_contact", "1")
                                ->where("entity.discontinue_stage", "0")->groupBy("entity.id");

                if ($reason == '') {
                    $feedbackExceptionList[] = array("entity_id" => $feedback->id);
                    if ($relatedEntity->count() == 0) {
                        continue;
                    }
                } else {
                    if ($feedback->feedback_email == null || $feedback->feedback_email != '') {

                        $feedbackEntity[$feedback->feedback_email] = array("entity_id" => $feedback->id,
                            "category_id" => $feedback->category_id,
                            "full_resource" => ($feedback->full_time_resource == 0) ? 0 : 1,
                            "contact_id" => $feedback->contact_id,
                            "contact_person" => $feedback->contact_person,
                            "email" => $feedback->email,
                            "mobile_no" => $feedback->mobile_no,
                            "office_no" => $feedback->office_no,
                            "tam_id" => (!empty($reason['tam_id'])) ? implode(",", $reason['tam_id']) : '',
                            "service_id" => (!empty($reason['service_id'])) ? implode(",", $reason['service_id']) : '',
                            "tam_name" => (!empty($reason['tam_name'])) ? implode(",", $reason['tam_name']) : '',
                            "service_name" => (!empty($reason['service_name'])) ? implode(",", $reason['service_name']) : '',
                            "service_tam" => (!empty($reason['service_name'])) ? \GuzzleHttp\json_encode($reason['service_tam']) : '');
                    }
                }
                $relatedEntityWithParent = array();
                $relEntityDiff = array();


                if ($relatedEntity->count() > 0) {
                    $relatedEntity = $relatedEntity->get();
                    foreach ($relatedEntity as $related) {
                        if ($feedback->feedback_email == $related->feedback_email) {
                            $relatedEntityWithParent[] = array("id" => $related->id, "name" => $related->trading_name);
                        } else if ($related->feedback_email != '' || $related->feedback_email != null) {
                            $reasonRelated = \App\Models\Backend\BillingServices::checkTAMonService($related->id);
                            $relEntityDiff[] = array("id" => $related->id, "name" => $related->trading_name);
                            if ($reasonRelated == '') {
                                continue;
                            }
                            $relatedEntityWithoutParent[$related->feedback_email][] = array("id" => $related->id, "name" => $related->trading_name);
                            $feedbackEntity[$related->feedback_email] = array("entity_id" => $related->id,
                                "category_id" => $related->category_id,
                                "full_resource" => ($related->full_time_resource == 0) ? 0 : 1,
                                "contact_id" => $related->contact_id,
                                "contact_person" => $related->contact_person,
                                "email" => $related->feedback_email,
                                "mobile_no" => $related->mobile_no,
                                "office_no" => $related->office_no,
                                "tam_id" => (!empty($reasonRelated['tam_id'])) ? implode(",", $reasonRelated['tam_id']) : '',
                                "service_id" => (!empty($reasonRelated['service_id'])) ? implode(",", $reasonRelated['service_id']) : '',
                                "tam_name" => (!empty($reasonRelated['tam_name'])) ? implode(",", $reasonRelated['tam_name']) : '',
                                "service_name" => (!empty($reasonRelated['service_name'])) ? implode(",", $reasonRelated['service_name']) : '',
                                "service_tam" => (!empty($reasonRelated['service_name'])) ? \GuzzleHttp\json_encode($reasonRelated['service_tam']) : '',
                                "related_entity_same" => (!empty($relatedEntityWithoutParent[$related->feedback_email])) ? json_encode($relatedEntityWithoutParent[$related->feedback_email]) : '',
                                "related_entity_without_same" => '');
                        }
                    }
                }

                $feedbackEntity[$feedback->feedback_email]['related_entity_same'] = (!empty($relatedEntityWithParent)) ? json_encode($relatedEntityWithParent) : '';
                $feedbackEntity[$feedback->feedback_email]['related_entity_without_same'] = (!empty($relEntityDiff)) ? json_encode($relEntityDiff) : '';
                //showArray($relatedEntityWithParent);exit;


                if ($feedback->id > 0) {
                    $feedbackEntityList[$feedback->id] = $feedbackEntity;
                }
            }
            //showArray($feedbackEntityList);exit;
            if(count($feedbackEntityList) > 0){
            foreach ($feedbackEntityList as $key => $value) {
                // showArray($value);exit;
                foreach ($value as $row) {
                    $year = ($month == 1) ? date("Y", strtotime("-1 Year")) : date("Y");
                    /*if ($row['category_id'] == 3 || $row['category_id'] == 4) {
                        $qtr = $month3. "-" . $month2;
                    } else {*/
                        $qtr = $month1 . "-" . $month2;
                    //}
                    $qtr=strtoupper($qtr);
                    \App\Models\Backend\Feedback::create(["entity_id" => $row['entity_id'],
                        "contact_id" => $row['contact_id'],
                        "contact_person" => $row['contact_person'],
                        "contact_mobile" => $row['mobile_no'],
                        "contact_office" => $row['office_no'],
                        "contact_email" => $row['email'],
                        "status_id" => 0,
                        "service_id" => $row['service_id'],
                        "category_id" => $row['category_id'],
                        "year" => $year,
                        "quarter" => $qtr,
                        "tam_id" => $row['tam_id'],
                        "tam_name" => $row['tam_name'],
                        "service_name" => $row['service_name'],
                        "service_tam" => $row['service_tam'],
                        "full_resource" => $row['full_resource'],
                        "related_entity_same" => $row['related_entity_same'],
                        "related_entity_diff" => $row['related_entity_without_same'],
                        "created_on" => date('Y-m-d H:i:s'),
                        "created_by" => 1]);
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
