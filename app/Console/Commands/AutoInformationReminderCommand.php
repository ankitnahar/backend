<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;

class AutoInformationReminderCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'information:reminder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto Infomation Reminder';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        //try {
            $today = date('Y-m-d');

            $informations = \App\Models\Backend\Information::leftjoin("information_template as infotemp", "infotemp.information_id", "information.id")
                    ->select("information.id", "information.stage_id", "information.entity_id", "information.reminder", "information.send_reminder", "infotemp.to", "infotemp.from_email", "infotemp.cc", "infotemp.bcc", "infotemp.subject", "infotemp.content")
                    ->where("information.reminder_date", $today)
                    ->where("information.send_reminder", '<', 3);

            
            if ($informations->get()->count() > 0) {
                foreach ($informations->get() as $information) {
                        $dataArray = [
                            'information_id' => $information->id,
                            'from_email' => $information->from_email,
                            'to' => $information->to,
                            'cc' => $information->cc,
                            'bcc' => $information->bcc,
                            'subject' => $information->subject,
                            'content' => $information->content,
                            'created_on' => date("Y-m-d H:i:s"),
                            'created_by' => loginUser()
                        ];

                        $data['to'] = str_replace(' ','',$information->to);
                        $data['from'] = $information->from_email;
                        $data['cc'] = str_replace(' ','',$information->cc);
                        $data['bcc'] = str_replace(' ','',$information->bcc);
                        $data['content'] = $information->content;
                        $data['subject'] = $information->subject;
                        //send mail to the client
                        cronStoreMail($data);
                        //update reminder_date
                        $reminder_days  = $information->reminder;
                        $send_reminder = $information->send_reminder + 1;
                        \App\Models\Backend\Information::where("id", $information->id)->update(
                                [
                                    'reminder_date' => date('Y-m-d', strtotime("+".$reminder_days." days", strtotime(date("Y-m-d")))),
                                    "stage_id" => 4,
                                    "send_reminder" => $send_reminder]);
                        
                        \App\Models\Backend\InformationCall::addCall($information->id, $information->entity_id);

                        \App\Models\Backend\InformationLog::addLog($information->id, 4);
                }
            }
        // } catch (Exception $ex) {
        //     $cronName = "Information Reminder Send";
        //     $message = $ex->getMessage();
        //     cronNotWorking($cronName, $message);
        // }
    }

}
