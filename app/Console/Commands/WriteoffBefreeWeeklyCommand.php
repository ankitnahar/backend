<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;
class WriteoffBefreeWeeklyCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'writeoff:befreeweekly';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Writeoff Befree Weekly Reminder';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            $startDt = date('Y-m-d 00:00:00', strtotime('-8 days'));
            $endDt = date('Y-m-d 24:59:59');
            
            $writeoffWeekly = \App\Models\Backend\WriteoffBefree::leftjoin("user as u", "u.id", "writeoff_befree.user_id")
            ->select("writeoff_befree.*", "u.userfullname", "u.email")
            ->where("u.is_active", "1")
            ->whereBetween("writeoff_befree.created_on", [$startDt, $endDt])
            ->whereRaw("writeoff_befree.staff_reason IS NULL");
            // showArray($nonCharge);
            // showArray($totalUnits);exit;
            $userArray = array();
            $TAM = $DVI = array();
            $emailTemplate = \App\Models\Backend\EmailTemplate::where("code", "WEEKLYREMINDER")->first();
            $sDate = date('d-m-Y', strtotime($startDt));
            $eDate = date('d-m-Y', strtotime($endDt));
            if($writeoffWeekly->count() > 0){
            foreach ($writeoffWeekly->get() as $row) {
                    if ($row->technical_account_manager!='')
                        $TAM[$row->technical_account_manager][] = $row->userfullname;
                    // send mail to user

                    if ($emailTemplate->is_active) {
                        $to = $row->email;
                        $cc = $emailTemplate->cc;
                        $subject = html_entity_decode(str_replace(array('[SUBJECT]'), array('for befree writeoff not updated'), $emailTemplate->subject));
                        $content = html_entity_decode(str_replace(array('[USERNAME]', '[LINE]'), array($row->userfullname, 'Update your befree writeoff reason .'), $emailTemplate->content));


                        $emailData['to'] = strtolower($to);
                        $emailData['cc'] = strtolower($cc);
                        $emailData['bcc'] = strtolower($emailTemplate->bcc);
                        $emailData['subject'] = $subject;
                        $emailData['content'] = $content;

                        $sendMail = cronStoreMail($emailData);
                        if (!$sendMail) {
                            app('log')->channel('befreeweekly')->error("Befree Writeoff user mail send failed");
                        }
                    }
                }
            
            }


             //send mail to tam 
            foreach ($TAM as $key => $value) {

                $user = \App\Models\User::select("userfullname", "email")->where("id", $key)->first();

                $to = $user->email;
                $cc = $emailTemplate->cc;
                $subject =$subject = html_entity_decode(str_replace(array('[SUBJECT]'), array('for befree writeoff not updated by your team'), $emailTemplate->subject));
                $table = '<div class="table_template">
            <table style="font-size: 13px; width: 100%; text-align: left;  border-spacing: 0;   padding: 15px 0 15px 0;padding-bottom: 20px">';
                foreach ($value as $val) {
                    $table .= '<tr><td>' . $val . '</td></tr>';
                }
                $table .= '</table>';
                $content = html_entity_decode(str_replace(array('[USERNAME]','[LINE]', '[USERLIST]'), array($user->userfullname,'User not updated befree writeoff reason from last week .', $table), $emailTemp->content));


                $emailData['to'] = strtolower($to);
                $emailData['cc'] = strtolower($cc);
                $emailData['bcc'] = strtolower($emailTemplate->bcc);
                $emailData['subject'] = $subject;
                $emailData['content'] = $content;

                $sendMail = cronStoreMail($emailData);
                if (!$sendMail) {
                    app('log')->channel('befreewriteoff')->error("Befree Writeoff user mail send failed");
                }
            }
        } catch (Exception $e) {
            $cronName = "Write Off Befree Weekly";
            $message = $e->getMessage();
            cronNotWorking($cronName, $message);
        }
    }

}
