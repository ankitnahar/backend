<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;
use Exception;

class HrReminderCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Hr:reminder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hr Reminder';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            $yesterday = date('Y-m-d');
            $emailTemplate = \App\Models\Backend\EmailTemplate::getTemplate('HRREMINDER');
            if ($emailTemplate->is_active == 1) {
                //$data['from'] = 'manish.p@befree.com.au';
                $data['to'] = $emailTemplate->to;
                $data['cc'] = $emailTemplate->cc;
                $data['bcc'] = $emailTemplate->bcc;
                $data['subject'] = $emailTemplate->subject;
                $data['content'] = $emailTemplate->content;
                storeMail('', $data);
            }
        } catch (Exception $ex) {
            $cronName = "HR Reminder Notification";
            $message = $ex->getMessage();
            cronNotWorking($cronName, $message);
        }
    }

}
