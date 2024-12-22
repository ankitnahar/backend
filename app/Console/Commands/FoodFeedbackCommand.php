<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;
use Exception;

class FoodFeedbackCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'food:feedback';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Food Feedback';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        //try {
            $today = date('Y-m-d');
            $todayFood = \App\Models\Backend\FoodMenuDetail::where("date", $today);
            if ($todayFood->count() > 0) {
                $emailTemplate = \App\Models\Backend\EmailTemplate::getTemplate('FOODFEEDBACK');
                if ($emailTemplate->is_active == 1) {
                    //$data['from'] = 'norepl';
                    $data['to'] = $emailTemplate->to;
                    $data['cc'] = $emailTemplate->cc;
                    $data['bcc'] = $emailTemplate->bcc;
                    $data['subject'] = $emailTemplate->subject;
                    $data['content'] = $emailTemplate->content;
                    storeMail('', $data);
                }
            }
      /*  } catch (Exception $ex) {
            $cronName = "Food Feedback Notification";
            $message = $ex->getMessage();
            cronNotWorking($cronName, $message);
        }*/
    }

}
